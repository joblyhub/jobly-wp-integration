<?php
/**
 * First-run setup wizard: 1 Poveži podjetje, 2 Karierna stran, 3 Videz, done.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redirect once after activation (skippable, never on bulk activation).
 */
function jobly_integration_wizard_redirect() {
	if ( ! get_transient( 'jobly_integration_wizard' ) ) {
		return;
	}
	delete_transient( 'jobly_integration_wizard' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core flag set on bulk activation.
	if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	jobly_integration_redirect( 'jobly-setup' );
}
add_action( 'admin_init', 'jobly_integration_wizard_redirect' );

/**
 * Wizard screen: full-width, without the WordPress side menu.
 *
 * @param string $classes Body classes.
 * @return string
 */
function jobly_integration_wizard_body_class( $classes ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	if ( isset( $_GET['page'] ) && 'jobly-setup' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
		$classes .= ' jobly-wizard-screen';
	}
	return $classes;
}
add_filter( 'admin_body_class', 'jobly_integration_wizard_body_class' );

/**
 * Create the careers page with the list shortcode.
 *
 * @param string $title Page title.
 * @return int Page ID or 0.
 */
function jobly_integration_create_careers_page( $title ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => '' !== $title ? $title : __( 'Kariera', 'jobly-integration' ),
			'post_content' => "<!-- wp:shortcode -->\n[jobly_jobs filters=\"1\"]\n<!-- /wp:shortcode -->",
		),
		true
	);
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/**
 * Save one wizard step and move on.
 */
function jobly_integration_handle_wizard() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_wizard' );

	$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;
	$next = array(
		'step'      => $step + 1,
		'jobly_msg' => '',
	);

	if ( 1 === $step ) {
		if ( isset( $_POST['use_demo'] ) ) {
			jobly_integration_update_settings( array( 'demo' => 1 ) );
		} else {
			$base = isset( $_POST['base_url'] ) ? esc_url_raw( trim( sanitize_text_field( wp_unslash( $_POST['base_url'] ) ) ), array( 'https', 'http' ) ) : '';
			$key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
			$tok  = jobly_integration_posted_app_token();
			if ( $tok ) {
				jobly_integration_update_settings( $tok );
			}
			if ( '' === $key && jobly_integration_settings()['connected'] ) {
				$code = 'verified'; // Already connected, nothing to change.
			} else {
				$code = '' === $key ? 'no_key' : jobly_integration_connect(
					$key,
					array(
						'base_url'     => '' !== $base ? untrailingslashit( $base ) : jobly_integration_settings()['base_url'],
						'company'      => isset( $_POST['company'] ) ? sanitize_title( wp_unslash( $_POST['company'] ) ) : '',
						'company_name' => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '',
					)
				);
			}
			if ( 'verified' !== $code ) {
				$next = array(
					'step'      => 1,
					'jobly_msg' => $code,
				);
			}
		}
	} elseif ( 2 === $step ) {
		$old  = jobly_integration_base_path();
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'auto';
		$set  = array( 'careers_set' => 1 );
		if ( 'create' === $mode ) {
			$set['careers_page'] = jobly_integration_create_careers_page( isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : '' );
		} elseif ( 'existing' === $mode ) {
			$id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
			if ( $id && 'page' === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
				$set['careers_page'] = $id;
				$content             = (string) get_post_field( 'post_content', $id );
				if ( false === strpos( $content, 'jobly_jobs' ) && false === strpos( $content, 'jobly/jobs-list' ) ) {
					wp_update_post(
						array(
							'ID'           => $id,
							'post_content' => $content . "\n\n<!-- wp:shortcode -->\n[jobly_jobs filters=\"1\"]\n<!-- /wp:shortcode -->",
						)
					);
				}
			}
		} else {
			$base                = isset( $_POST['base_path'] ) ? sanitize_title( wp_unslash( $_POST['base_path'] ) ) : '';
			$set['base_path']    = '' !== $base ? $base : 'kariera';
			$set['careers_page'] = 0;
		}
		jobly_integration_update_settings( $set );
		if ( jobly_integration_base_path() !== $old || 'auto' !== $mode ) {
			update_option( 'jobly_integration_flush', 1, false );
		}
	} elseif ( 3 === $step ) {
		jobly_integration_update_settings( array_merge( jobly_integration_posted_display(), array( 'wizard_done' => 1 ) ) );
	}

	jobly_integration_redirect(
		'jobly-setup',
		array_filter( $next, 'strlen' )
	);
}
add_action( 'admin_post_jobly_integration_wizard', 'jobly_integration_handle_wizard' );

/**
 * "Preskoči nastavitev": mark the wizard done and go to the overview.
 */
function jobly_integration_handle_wizard_skip() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_wizard_skip' );
	jobly_integration_update_settings( array( 'wizard_done' => 1 ) );
	jobly_integration_redirect( 'jobly-integration', array( 'jobly_msg' => 'wizard_skip' ) );
}
add_action( 'admin_post_jobly_integration_wizard_skip', 'jobly_integration_handle_wizard_skip' );

/**
 * Step indicator.
 *
 * @param int $current Current step (4 = done).
 */
function jobly_integration_wizard_steps( $current ) {
	$steps = array(
		1 => __( 'Poveži podjetje', 'jobly-integration' ),
		2 => __( 'Karierna stran', 'jobly-integration' ),
		3 => __( 'Videz', 'jobly-integration' ),
	);
	echo '<ol class="jobly-steps" aria-label="' . esc_attr__( 'Koraki nastavitve', 'jobly-integration' ) . '">';
	foreach ( $steps as $n => $label ) {
		$state = $n < $current ? ' is-done' : ( $n === $current ? ' is-current' : '' );
		echo '<li class="jobly-steps__item' . esc_attr( $state ) . '"' . ( $n === $current ? ' aria-current="step"' : '' ) . '><span class="jobly-steps__dot">';
		if ( $n < $current ) {
			jobly_integration_icon( 'check', '', 16 );
		} else {
			echo esc_html( (string) $n );
		}
		echo '</span><span class="jobly-steps__label">' . esc_html( $label ) . '</span></li>';
	}
	echo '</ol>';
}

/**
 * Screen callback.
 */
function jobly_integration_page_wizard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- step routing only.
	$step = isset( $_GET['step'] ) ? max( 1, min( 4, absint( $_GET['step'] ) ) ) : 1;
	$s    = jobly_integration_settings();
	$skip = wp_nonce_url( admin_url( 'admin-post.php?action=jobly_integration_wizard_skip' ), 'jobly_integration_wizard_skip' );

	echo '<div class="wrap jobly-admin jobly-wizard"><div class="jobly-wizard__brand">';
	jobly_integration_logo( 44 );
	echo '<span class="jobly-hdr__name">Jobly<span>.si</span> HRM</span></div>';
	jobly_integration_wizard_steps( $step );
	echo '<hr class="wp-header-end">';
	jobly_integration_notices();
	echo '<div class="jobly-wizard__card">';

	if ( 1 === $step ) {
		?>
		<h1><?php esc_html_e( 'Poveži svoje podjetje', 'jobly-integration' ); ?></h1>
		<p class="jobly-lead"><?php esc_html_e( 'Ključ ustvarite v Jobly → Integracije → API žetoni. Ključ preverimo in shranimo; nikoli se ne prikaže. Podjetje se prebere iz ključa.', 'jobly-integration' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( jobly_integration_key_url() ); ?>" target="_blank" rel="noopener"><?php jobly_integration_icon( 'external', '', 16 ); ?> <?php esc_html_e( 'Pridobi ključ', 'jobly-integration' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="jobly_integration_wizard"><input type="hidden" name="step" value="1">
			<?php wp_nonce_field( 'jobly_integration_wizard' ); ?>
			<div class="jobly-field">
				<label for="jw-key"><?php esc_html_e( 'API ključ', 'jobly-integration' ); ?></label>
				<input id="jw-key" type="password" name="api_key" autocomplete="off" placeholder="<?php echo esc_attr( $s['api_key'] ? jobly_integration_masked_key() : 'jbl_…' ); ?>">
			</div>
			<div class="jobly-field">
				<label for="jw-apptoken"><?php esc_html_e( 'App-Token', 'jobly-integration' ); ?></label>
				<?php jobly_integration_app_token_field( 'jw-apptoken' ); ?>
			</div>
			<input type="hidden" name="base_url" value="<?php echo esc_attr( $s['base_url'] ); ?>">
			<div class="jobly-wizard__actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Poveži in nadaljuj', 'jobly-integration' ); ?></button>
				<button type="submit" name="use_demo" value="1" class="button button-hero"><?php esc_html_e( 'Nadaljuj z demo podatki', 'jobly-integration' ); ?></button>
			</div>
		</form>
		<?php
	} elseif ( 2 === $step ) {
		$pages = get_pages( array( 'post_status' => 'publish' ) );
		?>
		<h1><?php esc_html_e( 'Kje naj bo karierna stran?', 'jobly-integration' ); ?></h1>
		<p class="jobly-lead"><?php esc_html_e( 'Seznam odprtih mest in podstrani oglasov dobijo svoj naslov na vašem spletnem mestu.', 'jobly-integration' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="jobly_integration_wizard"><input type="hidden" name="step" value="2">
			<?php wp_nonce_field( 'jobly_integration_wizard' ); ?>
			<label class="jobly-option">
				<input type="radio" name="mode" value="auto" checked>
				<span class="jobly-option__body"><strong><?php esc_html_e( 'Samodejna stran (priporočeno)', 'jobly-integration' ); ?></strong>
				<span><?php esc_html_e( 'Plugin sam pripravi seznam na naslovu:', 'jobly-integration' ); ?></span>
				<span class="jobly-inline"><code><?php echo esc_html( home_url( '/' ) ); ?></code><input name="base_path" value="<?php echo esc_attr( $s['base_path'] ); ?>" aria-label="<?php esc_attr_e( 'Osnova povezav', 'jobly-integration' ); ?>"><code>/</code></span></span>
			</label>
			<label class="jobly-option">
				<input type="radio" name="mode" value="create">
				<span class="jobly-option__body"><strong><?php esc_html_e( 'Ustvari WordPress stran', 'jobly-integration' ); ?></strong>
				<span><?php esc_html_e( 'Nova stran s seznamom, ki jo lahko urejaš kot vsako drugo.', 'jobly-integration' ); ?></span>
				<input name="page_title" value="<?php esc_attr_e( 'Kariera', 'jobly-integration' ); ?>" aria-label="<?php esc_attr_e( 'Naslov strani', 'jobly-integration' ); ?>"></span>
			</label>
			<?php if ( $pages ) : ?>
			<label class="jobly-option">
				<input type="radio" name="mode" value="existing">
				<span class="jobly-option__body"><strong><?php esc_html_e( 'Uporabi obstoječo stran', 'jobly-integration' ); ?></strong>
				<span><?php esc_html_e( 'Če stran še nima seznama, se na konec doda kratka koda [jobly_jobs].', 'jobly-integration' ); ?></span>
				<select name="page_id" aria-label="<?php esc_attr_e( 'Stran', 'jobly-integration' ); ?>">
					<?php foreach ( $pages as $jobly_page ) : ?>
						<option value="<?php echo esc_attr( (string) $jobly_page->ID ); ?>"><?php echo esc_html( get_the_title( $jobly_page ) ); ?></option>
					<?php endforeach; ?>
				</select></span>
			</label>
			<?php endif; ?>
			<div class="jobly-wizard__actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Nadaljuj', 'jobly-integration' ); ?></button>
				<a class="button button-hero" href="<?php echo esc_url( jobly_integration_admin_url( 'jobly-setup', array( 'step' => 1 ) ) ); ?>"><?php esc_html_e( 'Nazaj', 'jobly-integration' ); ?></a>
			</div>
		</form>
		<?php
	} elseif ( 3 === $step ) {
		?>
		<h1><?php esc_html_e( 'Videz', 'jobly-integration' ); ?></h1>
		<p class="jobly-lead"><?php esc_html_e( 'Plugin podeduje pisavo in barve vaše teme. Izberite poudarek in videz seznama.', 'jobly-integration' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="jobly_integration_wizard"><input type="hidden" name="step" value="3">
			<?php wp_nonce_field( 'jobly_integration_wizard' ); ?>
			<div class="jobly-field">
				<label for="jw-accent"><?php esc_html_e( 'Barva poudarka', 'jobly-integration' ); ?></label>
				<input id="jw-accent" type="color" name="accent" value="<?php echo esc_attr( jobly_integration_accent() ); ?>" data-jobly-accent>
			</div>
			<div class="jobly-field"><span class="jobly-label"><?php esc_html_e( 'Videz seznama', 'jobly-integration' ); ?></span><?php jobly_integration_layout_picker( (string) $s['layout'] ); ?></div>
			<div class="jobly-preview" style="--jobly-accent:<?php echo esc_attr( jobly_integration_accent() ); ?>" aria-hidden="true">
				<span class="jobly-preview__co"><?php echo esc_html( jobly_integration_company_name() ); ?></span>
				<strong><?php esc_html_e( 'Skladiščnik (m/ž)', 'jobly-integration' ); ?></strong>
				<span class="jobly-preview__meta">Ljubljana · <?php esc_html_e( 'Polni delovni čas', 'jobly-integration' ); ?></span>
				<span class="jobly-preview__cta"><?php esc_html_e( 'Poglej oglas', 'jobly-integration' ); ?> →</span>
			</div>
			<div class="jobly-wizard__actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Dokončaj', 'jobly-integration' ); ?></button>
				<a class="button button-hero" href="<?php echo esc_url( jobly_integration_admin_url( 'jobly-setup', array( 'step' => 2 ) ) ); ?>"><?php esc_html_e( 'Nazaj', 'jobly-integration' ); ?></a>
			</div>
		</form>
		<?php
	} else {
		$cards = array(
			array( 'plus', __( 'Dodaj prvo delovno mesto', 'jobly-integration' ), __( 'Objavi oglas neposredno iz WordPressa.', 'jobly-integration' ), jobly_integration_admin_url( 'jobly-job-new' ) ),
			array( 'world', __( 'Poglej karierno stran', 'jobly-integration' ), __( 'Tako jo vidijo kandidati.', 'jobly-integration' ), jobly_integration_careers_url() ),
			array( 'code', __( 'Vstavi blok ali kratko kodo', 'jobly-integration' ), __( 'Blok »Jobly – seznam delovnih mest« ali [jobly_jobs].', 'jobly-integration' ), admin_url( 'post-new.php?post_type=page' ) ),
			array( 'trending', __( 'Odpri pregled', 'jobly-integration' ), __( 'Kontrolni seznam in zadnje prijave.', 'jobly-integration' ), jobly_integration_admin_url( 'jobly-integration' ) ),
		);
		?>
		<div class="jobly-done">
			<span class="jobly-done__mark"><?php jobly_integration_icon( 'check', '', 34 ); ?></span>
			<h1><?php esc_html_e( 'Vse je pripravljeno', 'jobly-integration' ); ?></h1>
			<p class="jobly-lead"><?php esc_html_e( 'Jobly.si HRM je nastavljen. Kaj bi naredili najprej?', 'jobly-integration' ); ?></p>
		</div>
		<div class="jobly-next">
			<?php foreach ( $cards as $card ) : ?>
				<a class="jobly-next__card" href="<?php echo esc_url( $card[3] ); ?>">
					<?php jobly_integration_icon( $card[0], '', 24 ); ?>
					<strong><?php echo esc_html( $card[1] ); ?></strong>
					<span><?php echo esc_html( $card[2] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}
	echo '</div>';
	if ( $step < 4 ) {
		echo '<p class="jobly-wizard__skip"><a href="' . esc_url( $skip ) . '">' . esc_html__( 'Preskoči nastavitev', 'jobly-integration' ) . '</a></p>';
	}
	echo '</div>';
}
