<?php
/**
 * Nastavitve: tabs Povezava | Prikaz | Demo.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Save connection fields and, when a key is given, verify it first.
 * Shared by the Nastavitve form and the setup wizard.
 *
 * @param string $key     Raw API key from the form ('' = keep the stored one).
 * @param array  $changes Other connection settings to save.
 * @return string Notice code.
 */
function jobly_integration_connect( $key, array $changes ) {
	$code = 'saved';
	$key  = preg_replace( '/[^A-Za-z0-9_]/', '', $key );
	if ( '' !== $key ) {
		if ( 0 !== strpos( $key, 'jbl_' ) ) {
			$code = 'bad_key';
		} else {
			$result = jobly_integration_verify_key( $key );
			if ( 'ok' === $result ) {
				$changes              = array_merge( $changes, jobly_integration_company_changes( jobly_integration_company( $key ) ) );
				$changes['api_key']   = $key;
				$changes['connected'] = 1;
				$changes['demo']      = 0; // Connected: demo off (still toggleable).
				update_option( JOBLY_INTEGRATION_KEY_HOST, (string) wp_parse_url( jobly_integration_api_base(), PHP_URL_HOST ), false ); // The key belongs to this host.
				$code = 'verified';
			} else {
				$code = $result;
			}
		}
	}
	jobly_integration_update_settings( $changes );
	jobly_integration_flush_cache();
	return $code;
}

/**
 * App token from the posted form: empty keeps the stored one, the checkbox removes it.
 *
 * @return array
 */
function jobly_integration_posted_app_token() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
	if ( ! empty( $_POST['remove_app_token'] ) ) {
		return array( 'app_token' => '' );
	}
	$token = isset( $_POST['app_token'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', sanitize_text_field( wp_unslash( $_POST['app_token'] ) ) ) : '';
	// phpcs:enable
	return '' !== $token ? array( 'app_token' => $token ) : array();
}

/**
 * App token field (settings and wizard); never echoes the value.
 *
 * @param string $id Input id.
 */
function jobly_integration_app_token_field( $id ) {
	if ( defined( 'JOBLY_APP_TOKEN' ) && JOBLY_APP_TOKEN ) {
		echo '<p class="description">' . esc_html__( 'App-Token: nastavljeno v wp-config.php (konstanta JOBLY_APP_TOKEN). Vrednost se nikoli ne prikaže.', 'jobly-integration' ) . '</p>';
		return;
	}
	$set = '' !== jobly_integration_settings()['app_token'];
	echo '<input id="' . esc_attr( $id ) . '" class="regular-text" type="password" name="app_token" autocomplete="off" placeholder="' . esc_attr( $set ? jobly_integration_masked_app_token() : 'jba_…' ) . '">';
	if ( $set ) {
		echo '<p><label><input type="checkbox" name="remove_app_token" value="1"> ' . esc_html__( 'Odstrani shranjeni App-Token', 'jobly-integration' ) . '</label></p>';
	}
}

/**
 * Settings of the "Prikaz" tab from the posted form (shared with the wizard).
 *
 * @return array
 */
function jobly_integration_posted_display() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
	$changes = array();
	if ( isset( $_POST['accent'] ) ) {
		$changes['accent'] = (string) sanitize_hex_color( wp_unslash( $_POST['accent'] ) );
	}
	if ( isset( $_POST['layout'] ) ) {
		$changes['layout'] = 'grid' === sanitize_key( wp_unslash( $_POST['layout'] ) ) ? 'grid' : 'list';
	}
	if ( isset( $_POST['per_page'] ) ) {
		$changes['per_page'] = max( 3, min( 50, absint( $_POST['per_page'] ) ) );
	}
	if ( isset( $_POST['height'] ) ) {
		$changes['height'] = max( 300, min( 3000, absint( $_POST['height'] ) ) );
	}
	// phpcs:enable
	return $changes;
}

/**
 * Save one settings tab. Fields of other tabs are left untouched.
 */
function jobly_integration_handle_save_settings() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_save' );

	$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'connection';
	$code = 'saved';

	if ( 'display' === $tab ) {
		$old                    = jobly_integration_base_path();
		$changes                = jobly_integration_posted_display();
		$base                   = isset( $_POST['base_path'] ) ? sanitize_title( wp_unslash( $_POST['base_path'] ) ) : '';
		$changes['base_path']   = '' !== $base ? $base : 'kariera';
		$changes['careers_set'] = 1;
		jobly_integration_update_settings( $changes );
		if ( jobly_integration_base_path() !== $old ) {
			update_option( 'jobly_integration_flush', 1, false );
		}
	} elseif ( 'google' === $tab ) {
		jobly_integration_update_settings(
			array(
				'schema'       => empty( $_POST['schema'] ) ? 0 : 1,
				'sitemap'      => empty( $_POST['sitemap'] ) ? 0 : 1,
				'company_name' => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '',
			)
		);
	} elseif ( 'demo' === $tab ) {
		jobly_integration_update_settings( array( 'demo' => empty( $_POST['demo'] ) ? 0 : 1 ) );
	} else {
		$changes = array(); // The Jobly address is not editable here (wp-config.php constant only).
		if ( isset( $_POST['company'] ) && ! jobly_integration_settings()['company_api'] ) {
			$changes['company'] = sanitize_title( wp_unslash( $_POST['company'] ) );
		}
		$changes = array_merge( $changes, jobly_integration_posted_app_token() );
		if ( ! empty( $_POST['remove_key'] ) ) {
			$changes['api_key']     = '';
			$changes['connected']   = 0;
			$changes['company_api'] = 0;
			$code                   = 'key_removed';
		}
		$key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$code = jobly_integration_connect( $key, $changes );
		if ( ! empty( $_POST['remove_key'] ) && 'saved' === $code ) {
			$code = 'key_removed';
		}
	}

	jobly_integration_redirect(
		'jobly-settings',
		array(
			'tab'       => $tab,
			'jobly_msg' => $code,
		)
	);
}
add_action( 'admin_post_jobly_integration_save', 'jobly_integration_handle_save_settings' );

/**
 * "Preveri povezavo": check the stored key.
 */
function jobly_integration_handle_verify() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_verify' );

	$key = jobly_integration_settings()['api_key'];
	if ( '' === $key ) {
		$code = 'no_key';
	} else {
		$code    = jobly_integration_verify_key( $key );
		$changes = array( 'connected' => 'ok' === $code ? 1 : 0 );
		if ( 'ok' === $code ) {
			$changes = array_merge( $changes, jobly_integration_company_changes( jobly_integration_company( $key ) ) );
		}
		jobly_integration_update_settings( $changes );
	}
	jobly_integration_redirect(
		'jobly-settings',
		array(
			'tab'       => 'connection',
			'jobly_msg' => $code,
		)
	);
}
add_action( 'admin_post_jobly_integration_verify', 'jobly_integration_handle_verify' );

/**
 * "Osveži": drop the cached job list.
 */
function jobly_integration_handle_refresh() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_refresh' );
	jobly_integration_flush_cache();
	$back = isset( $_REQUEST['back'] ) ? sanitize_key( wp_unslash( $_REQUEST['back'] ) ) : 'jobly-jobs';
	jobly_integration_redirect(
		in_array( $back, array( 'jobly-jobs', 'jobly-integration' ), true ) ? $back : 'jobly-jobs',
		array( 'jobly_msg' => 'refreshed' )
	);
}
add_action( 'admin_post_jobly_integration_refresh', 'jobly_integration_handle_refresh' );

/**
 * Open the form of one tab.
 *
 * @param string $tab Tab id.
 */
function jobly_integration_form_open( $tab ) {
	echo '<form class="jobly-panel jobly-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="jobly_integration_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
	wp_nonce_field( 'jobly_integration_save' );
}

/**
 * Layout choice (list / grid) as two radio cards.
 *
 * @param string $current Current layout.
 */
function jobly_integration_layout_picker( $current ) {
	$choices = array(
		'list' => array( __( 'Seznam', 'jobly-integration' ), 'layout-list' ),
		'grid' => array( __( 'Mreža', 'jobly-integration' ), 'layout-grid' ),
	);
	echo '<div class="jobly-choices">';
	foreach ( $choices as $value => $choice ) {
		echo '<label class="jobly-choice"><input type="radio" name="layout" value="' . esc_attr( $value ) . '"' . checked( $current, $value, false ) . '>';
		jobly_integration_icon( $choice[1], '', 22 );
		echo '<span>' . esc_html( $choice[0] ) . '</span></label>';
	}
	echo '</div>';
}

/**
 * Screen callback.
 */
function jobly_integration_page_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab routing only.
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection';
	$tabs = array(
		'connection' => array( __( 'Povezava', 'jobly-integration' ), 'plug' ),
		'display'    => array( __( 'Prikaz', 'jobly-integration' ), 'palette' ),
		'landing'    => array( __( 'Karierna stran', 'jobly-integration' ), 'layout-list' ),
		'seo'        => array( __( 'SEO', 'jobly-integration' ), 'search' ),
		'google'     => array( __( 'Google for Jobs', 'jobly-integration' ), 'world' ),
		'demo'       => array( __( 'Demo', 'jobly-integration' ), 'sparkles' ),
	);
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'connection';
	$s    = jobly_integration_settings();

	jobly_integration_header( __( 'Nastavitve', 'jobly-integration' ) );
	echo '<nav class="jobly-subtabs" aria-label="' . esc_attr__( 'Nastavitve', 'jobly-integration' ) . '">';
	foreach ( $tabs as $id => $item ) {
		printf( '<a href="%s" class="jobly-subtabs__item%s">%s</a>', esc_url( jobly_integration_admin_url( 'jobly-settings', array( 'tab' => $id ) ) ), $id === $tab ? ' is-active' : '', esc_html( $item[0] ) );
	}
	echo '</nav>';

	if ( 'connection' === $tab ) {
		jobly_integration_form_open( 'connection' );
		?>
		<h2><?php esc_html_e( 'Povezava z Jobly', 'jobly-integration' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="jobly-key"><?php esc_html_e( 'API ključ', 'jobly-integration' ); ?></label></th>
				<td>
					<input id="jobly-key" class="regular-text" type="password" name="api_key" autocomplete="off" placeholder="<?php echo esc_attr( $s['api_key'] ? jobly_integration_masked_key() : 'jbl_…' ); ?>">
					<p class="description"><?php esc_html_e( 'Ključ ustvarite v Jobly → Integracije → API žetoni.', 'jobly-integration' ); ?> <a class="button button-small" href="<?php echo esc_url( jobly_integration_key_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Pridobi ključ', 'jobly-integration' ); ?></a> <?php esc_html_e( 'Shranjen ključ se nikoli ne prikaže; za zamenjavo vpiši novega.', 'jobly-integration' ); ?></p>
					<?php if ( $s['api_key'] ) : ?>
						<label><input type="checkbox" name="remove_key" value="1"> <?php esc_html_e( 'Odstrani shranjeni ključ', 'jobly-integration' ); ?></label>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-apptoken"><?php esc_html_e( 'App-Token', 'jobly-integration' ); ?></label></th>
				<td>
					<?php jobly_integration_app_token_field( 'jobly-apptoken' ); ?>
					<p class="description"><?php esc_html_e( 'Žeton aplikacije (jba_…), ki ga izda Jobly. Skupaj s ključem podjetja ga Jobly zahteva pri vsaki zahtevi. Nikoli se ne prikaže.', 'jobly-integration' ); ?></p>
				</td>
			</tr>
			<?php if ( $s['company_api'] ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Podjetje', 'jobly-integration' ); ?></th>
				<td><?php jobly_integration_company_badge( 36 ); ?> <p class="description"><?php esc_html_e( 'Podjetje se prebere iz API ključa.', 'jobly-integration' ); ?></p></td>
			</tr>
			<?php elseif ( $s['api_key'] ) : ?>
			<tr>
				<th scope="row"><label for="jobly-company"><?php esc_html_e( 'Podjetje (slug)', 'jobly-integration' ); ?></label></th>
				<td>
					<input id="jobly-company" class="regular-text" name="company" value="<?php echo esc_attr( $s['company'] ); ?>" placeholder="moje-podjetje">
					<p class="description"><?php esc_html_e( 'Ta različica Jobly ne vrača podjetja prek API-ja, zato ga vpiši ročno (zadnji del naslova profila na jobly.si/companies/…).', 'jobly-integration' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><span id="jobly-base-label"><?php esc_html_e( 'Naslov Jobly', 'jobly-integration' ); ?></span></th>
				<td>
					<code id="jobly-base"><?php echo esc_html( $s['base_url'] ); ?></code>
					<p class="description"><?php esc_html_e( 'Naslov je določen in ga ni mogoče spremeniti v skrbniškem delu. Za lokalno testiranje ga lahko skrbnik strežnika nastavi s konstanto JOBLY_API_BASE v wp-config.php.', 'jobly-integration' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		submit_button( __( 'Shrani in preveri', 'jobly-integration' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<form class="jobly-inline-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="jobly_integration_verify">';
		wp_nonce_field( 'jobly_integration_verify' );
		submit_button( __( 'Preveri povezavo', 'jobly-integration' ), 'secondary', 'submit', false, $s['api_key'] ? array() : array( 'disabled' => 'disabled' ) );
		echo '</form>';
	} elseif ( 'display' === $tab ) {
		jobly_integration_form_open( 'display' );
		?>
		<h2><?php esc_html_e( 'Karierna stran in videz', 'jobly-integration' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="jobly-path"><?php esc_html_e( 'Osnova povezav', 'jobly-integration' ); ?></label></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="jobly-path" name="base_path" value="<?php echo esc_attr( $s['base_path'] ); ?>" class="small-text"><code>/</code>
					<p class="description"><?php esc_html_e( 'Seznam odprtih mest in podstrani oglasov so pod tem naslovom.', 'jobly-integration' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-accent"><?php esc_html_e( 'Barva poudarka', 'jobly-integration' ); ?></label></th>
				<td><input id="jobly-accent" type="color" name="accent" value="<?php echo esc_attr( jobly_integration_accent() ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Videz seznama', 'jobly-integration' ); ?></th>
				<td><?php jobly_integration_layout_picker( (string) $s['layout'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-pp"><?php esc_html_e( 'Oglasov na stran', 'jobly-integration' ); ?></label></th>
				<td><input id="jobly-pp" type="number" min="3" max="50" name="per_page" value="<?php echo esc_attr( (string) $s['per_page'] ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-height"><?php esc_html_e( 'Višina okvirja (px)', 'jobly-integration' ); ?></label></th>
				<td><input id="jobly-height" type="number" min="300" max="3000" name="height" value="<?php echo esc_attr( (string) $s['height'] ); ?>" class="small-text"></td>
			</tr>
		</table>
		<?php
		submit_button( __( 'Shrani spremembe', 'jobly-integration' ), 'primary', 'submit', false );
		echo '</form>';
	} elseif ( 'landing' === $tab ) {
		jobly_integration_landing_tab();
	} elseif ( 'seo' === $tab ) {
		jobly_integration_seo_tab();
	} elseif ( 'google' === $tab ) {
		jobly_integration_form_open( 'google' );
		$sitemap = function_exists( 'wp_sitemaps_get_server' ) ? wp_sitemaps_get_server()->index->get_index_url() : '';
		?>
		<h2><?php esc_html_e( 'Google for Jobs', 'jobly-integration' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Na vsaki podstrani oglasa se doda strukturirani zapis JobPosting. Vsebuje samo podatke, ki jih vrne Jobly (naziv, datum objave, vrsta zaposlitve, kraj, plača, če je navedena).', 'jobly-integration' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Strukturirani zapis', 'jobly-integration' ); ?></th>
				<td><label><input type="checkbox" name="schema" value="1" <?php checked( 1, (int) $s['schema'] ); ?>> <?php esc_html_e( 'Dodaj JobPosting (JSON-LD) na strani oglasov', 'jobly-integration' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Zemljevid strani', 'jobly-integration' ); ?></th>
				<td>
					<label><input type="checkbox" name="sitemap" value="1" <?php checked( 1, (int) $s['sitemap'] ); ?>> <?php esc_html_e( 'Dodaj oglase v WordPress zemljevid strani', 'jobly-integration' ); ?></label>
					<?php if ( $sitemap ) : ?>
						<p class="description"><a href="<?php echo esc_url( $sitemap ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $sitemap ); ?></a></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-cname"><?php esc_html_e( 'Ime podjetja', 'jobly-integration' ); ?></label></th>
				<td>
					<input id="jobly-cname" class="regular-text" name="company_name" value="<?php echo esc_attr( $s['company_name'] ); ?>" placeholder="<?php echo esc_attr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?>">
					<p class="description"><?php esc_html_e( 'Prikazano na karticah oglasov in v zapisu (hiringOrganization). Prazno = ime spletnega mesta.', 'jobly-integration' ); ?></p>
				</td>
			</tr>
		</table>
		<?php if ( $s['demo'] ) : ?>
			<p class="description"><?php esc_html_e( 'V demo načinu se zapis in zemljevid ne izpišeta (strani so noindex).', 'jobly-integration' ); ?></p>
		<?php endif; ?>
		<?php
		submit_button( __( 'Shrani spremembe', 'jobly-integration' ), 'primary', 'submit', false );
		echo '</form>';
	} else {
		jobly_integration_form_open( 'demo' );
		?>
		<h2><?php esc_html_e( 'Demo podatki', 'jobly-integration' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Demo način', 'jobly-integration' ); ?></th>
				<td>
					<label><input type="checkbox" name="demo" value="1" <?php checked( 1, (int) $s['demo'] ); ?>> <?php esc_html_e( 'Prikaži izmišljen primer (Primer d.o.o.) namesto podatkov z Jobly', 'jobly-integration' ); ?></label>
					<p class="description"><?php esc_html_e( 'Za gradnjo in predstavitev strani; vtičnik v tem načinu ne pokliče Jobly. Izklopi se samodejno, ko shraniš veljaven ključ.', 'jobly-integration' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		submit_button( __( 'Shrani spremembe', 'jobly-integration' ), 'primary', 'submit', false );
		echo '</form>';
		echo '<p><a href="' . esc_url( jobly_integration_admin_url( 'jobly-setup' ) ) . '">' . esc_html__( 'Zaženi čarovnika za nastavitev znova', 'jobly-integration' ) . '</a></p>';
	}
	jobly_integration_footer();
}
