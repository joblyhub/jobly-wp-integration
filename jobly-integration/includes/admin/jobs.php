<?php
/**
 * Delovna mesta: index (list table) and show.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Screen callback.
 */
function jobly_integration_page_jobs() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
	if ( isset( $_GET['action'], $_GET['job'] ) && 'show' === $_GET['action'] ) {
		jobly_integration_job_show( sanitize_title( wp_unslash( $_GET['job'] ) ) );
		return;
	}
	// phpcs:enable
	jobly_integration_job_index();
}

/**
 * Index of jobs.
 */
function jobly_integration_job_index() {
	require_once __DIR__ . '/class-jobs-table.php';

	jobly_integration_header( __( 'Delovna mesta', 'jobly-integration' ), jobly_integration_admin_url( 'jobly-job-new' ), __( 'Dodaj novo', 'jobly-integration' ) );
	if ( ! jobly_integration_require_connection() ) {
		jobly_integration_footer();
		return;
	}
	$table = new Jobly_Integration_Jobs_Table();
	$table->prepare_items();
	if ( 200 !== $table->api_code ) {
		jobly_integration_api_error_notice( $table->api_code );
		jobly_integration_footer();
		return;
	}
	if ( 0 === $table->total_all ) {
		jobly_integration_empty_state( 'jobs', __( 'Še ni delovnih mest', 'jobly-integration' ), __( 'Objavite prvi oglas in prikazal se bo na karierni strani vašega spletnega mesta.', 'jobly-integration' ), jobly_integration_admin_url( 'jobly-job-new' ), __( 'Dodaj delovno mesto', 'jobly-integration' ) );
		jobly_integration_footer();
		return;
	}
	$refresh = wp_nonce_url( admin_url( 'admin-post.php?action=jobly_integration_refresh&back=jobly-jobs' ), 'jobly_integration_refresh' );
	echo '<div class="jobly-toolbar"><a class="button" href="' . esc_url( $refresh ) . '">';
	jobly_integration_icon( 'refresh', '', 16 );
	echo ' ' . esc_html__( 'Osveži', 'jobly-integration' ) . '</a> <span class="description">' . esc_html__( 'Seznam se predpomni za 5 minut.', 'jobly-integration' ) . '</span></div>';
	echo '<div class="jobly-panel jobly-panel--table">';
	$table->views();
	echo '<form method="get"><input type="hidden" name="page" value="jobly-jobs">';
	$table->search_box( __( 'Išči delovna mesta', 'jobly-integration' ), 'jobly-job' );
	$table->display();
	echo '</form></div>';
	jobly_integration_footer();
}

/**
 * Tabs of the job screen: id => label.
 *
 * @return array<string,string>
 */
function jobly_integration_job_tabs() {
	return array(
		'overview'     => __( 'Pregled', 'jobly-integration' ),
		'applications' => __( 'Prijave', 'jobly-integration' ),
		'embed'        => __( 'Vgradnja', 'jobly-integration' ),
		'form'         => __( 'Obrazec', 'jobly-integration' ),
		'seo'          => __( 'SEO', 'jobly-integration' ),
	);
}

/**
 * Detail of one job in tabs: Pregled | Prijave | Vgradnja | Obrazec | SEO.
 *
 * @param string $slug Job slug.
 */
function jobly_integration_job_show( $slug ) {
	jobly_integration_header( __( 'Delovno mesto', 'jobly-integration' ) );
	echo '<p class="jobly-back"><a href="' . esc_url( jobly_integration_admin_url( 'jobly-jobs' ) ) . '">';
	jobly_integration_icon( 'arrow-left', '', 16 );
	echo ' ' . esc_html__( 'Vsa delovna mesta', 'jobly-integration' ) . '</a></p>';
	if ( ! jobly_integration_require_connection() ) {
		jobly_integration_footer();
		return;
	}
	$res = jobly_integration_all_jobs();
	$job = null;
	foreach ( $res['items'] as $row ) {
		if ( ( $row['slug'] ?? '' ) === $slug ) {
			$job = $row;
		}
	}
	if ( 200 !== $res['code'] || ! $job ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Zapisa ni mogoče najti.', 'jobly-integration' ) . '</p></div>';
		jobly_integration_footer();
		return;
	}

	$job  = jobly_integration_job_detail( $job );
	$tabs = jobly_integration_job_tabs();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab routing only, whitelisted below.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	$tab = isset( $tabs[ $tab ] ) ? $tab : 'overview';

	echo '<p class="jobly-job-context"><strong>' . esc_html( (string) $job['title'] ) . '</strong> ' . wp_kses_post( jobly_integration_status_badge( (string) ( $job['status'] ?? '' ) ) ) . '</p>';
	echo '<nav class="jobly-subtabs" aria-label="' . esc_attr__( 'Delovno mesto', 'jobly-integration' ) . '">';
	foreach ( $tabs as $id => $label ) {
		$url = jobly_integration_admin_url(
			'jobly-jobs',
			array(
				'action' => 'show',
				'job'    => $slug,
				'tab'    => $id,
			)
		);
		printf( '<a href="%s" class="jobly-subtabs__item%s">%s</a>', esc_url( $url ), $id === $tab ? ' is-active' : '', esc_html( $label ) );
	}
	echo '</nav>';

	if ( 'overview' === $tab ) {
		jobly_integration_job_tab_overview( $job, $slug );
	} elseif ( 'applications' === $tab ) {
		jobly_integration_job_tab_applications( $job );
	} elseif ( 'embed' === $tab ) {
		jobly_integration_job_tab_embed( $job, $slug );
	} elseif ( 'form' === $tab ) {
		jobly_integration_job_tab_form();
	} else {
		jobly_integration_seo_job_box( $job );
	}
	jobly_integration_footer();
}

/**
 * Pregled: details and description | links.
 *
 * @param array  $job  Job with detail.
 * @param string $slug Job slug.
 */
function jobly_integration_job_tab_overview( array $job, $slug ) {
	$types  = jobly_integration_employment_types();
	$fields = array(
		__( 'Naziv', 'jobly-integration' )               => $job['title'] ?? '',
		__( 'Slug', 'jobly-integration' )                => $job['slug'] ?? '',
		__( 'Kraj', 'jobly-integration' )                => trim( ( $job['location'] ?? '' ) . ' ' . ( $job['country'] ?? '' ) ),
		__( 'Vrsta zaposlitve', 'jobly-integration' )    => $types[ $job['employmentType'] ?? '' ] ?? ( $job['employmentType'] ?? '' ),
		__( 'Plačni razpon (EUR)', 'jobly-integration' ) => isset( $job['salaryMin'] ) ? $job['salaryMin'] . ' – ' . ( $job['salaryMax'] ?? '' ) : '',
		__( 'Objavljeno', 'jobly-integration' )          => jobly_integration_format_date( $job['publishedAt'] ?? null ),
		__( 'Prijav', 'jobly-integration' )              => $job['applicationsCount'] ?? 0,
		__( 'Zaposlenih', 'jobly-integration' )          => $job['hiredCount'] ?? 0,
		__( 'Izpostavljen', 'jobly-integration' )        => ! empty( $job['featured'] ) ? __( 'Da', 'jobly-integration' ) : __( 'Ne', 'jobly-integration' ),
	);
	$active = 'active' === ( $job['status'] ?? '' );
	$site   = jobly_integration_job_url( $slug );
	$jobly  = ! empty( $job['url'] ) ? (string) $job['url'] : untrailingslashit( jobly_integration_settings()['base_url'] ) . '/jobs/' . rawurlencode( $slug );
	?>
	<div class="jobly-detail">
		<div class="jobly-detail__main">
			<section class="jobly-panel">
				<header class="jobly-panel__head">
					<h2><?php echo esc_html( (string) $job['title'] ); ?></h2>
					<?php echo wp_kses_post( jobly_integration_status_badge( (string) ( $job['status'] ?? '' ) ) ); ?>
				</header>
				<dl class="jobly-dl">
					<?php foreach ( $fields as $label => $value ) : ?>
						<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo '' === (string) $value ? '—' : esc_html( (string) $value ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
				<?php if ( ! empty( $job['description'] ) && is_string( $job['description'] ) ) : ?>
					<h3><?php esc_html_e( 'Opis', 'jobly-integration' ); ?></h3>
					<div class="jobly-letter"><?php echo jobly_integration_rich_text( $job['description'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jobly_integration_rich_text() returns narrow-allowlist wp_kses output. ?></div>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Opis, naloge in zahteve ta različica Jobly API-ja ne vrne; urejaš jih na Jobly.', 'jobly-integration' ); ?></p>
				<?php endif; ?>
				<?php
				foreach ( array(
					'responsibilities' => __( 'Naloge', 'jobly-integration' ),
					'requirements'     => __( 'Zahteve', 'jobly-integration' ),
					'benefits'         => __( 'Ugodnosti', 'jobly-integration' ),
				) as $list_key => $list_label ) :
					if ( empty( $job[ $list_key ] ) || ! is_array( $job[ $list_key ] ) ) {
						continue;
					}
					?>
					<h3><?php echo esc_html( $list_label ); ?></h3>
					<ul class="jobly-bullets">
						<?php foreach ( $job[ $list_key ] as $item ) : ?>
							<li><?php echo esc_html( is_scalar( $item ) ? (string) $item : '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			</section>
		</div>

		<aside class="jobly-detail__side">
			<section class="jobly-panel">
				<header class="jobly-panel__head"><h2><?php esc_html_e( 'Povezave', 'jobly-integration' ); ?></h2></header>
				<ul class="jobly-links">
					<?php if ( $active ) : ?>
						<li><a href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener"><?php jobly_integration_icon( 'world', '', 18 ); ?> <?php esc_html_e( 'Poglej na strani', 'jobly-integration' ); ?></a></li>
					<?php endif; ?>
					<?php if ( ! jobly_integration_is_demo() ) : ?>
						<li><a href="<?php echo esc_url( $jobly ); ?>" target="_blank" rel="noopener"><?php jobly_integration_icon( 'external', '', 18 ); ?> <?php esc_html_e( 'Na Jobly', 'jobly-integration' ); ?></a></li>
						<li><a href="<?php echo esc_url( jobly_integration_edit_url( $slug ) ); ?>" target="_blank" rel="noopener"><?php jobly_integration_icon( 'link', '', 18 ); ?> <?php esc_html_e( 'Uredi na Jobly', 'jobly-integration' ); ?></a></li>
					<?php endif; ?>
				</ul>
			</section>
		</aside>
	</div>
	<?php
}

/**
 * Prijave tab.
 *
 * @param array $job Job.
 */
function jobly_integration_job_tab_applications( array $job ) {
	echo '<section class="jobly-panel"><header class="jobly-panel__head"><h2>' . esc_html__( 'Prijave', 'jobly-integration' ) . '</h2></header>';
	$apps = jobly_integration_all_applications();
	if ( 200 !== $apps['code'] ) {
		jobly_integration_api_error_notice( $apps['code'] );
	} else {
		$mine = array_filter(
			$apps['items'],
			static function ( $a ) use ( $job ) {
				return ( $a['job']['id'] ?? '' ) === ( $job['id'] ?? null );
			}
		);
		if ( ! $mine ) {
			jobly_integration_empty_state( 'applications', __( 'Za to mesto še ni prijav', 'jobly-integration' ), __( 'Ko kandidat odda prijavo, se prikaže tukaj.', 'jobly-integration' ) );
		} else {
			echo '<table class="widefat striped jobly-table"><thead><tr><th>' . esc_html__( 'Kandidat', 'jobly-integration' ) . '</th><th>' . esc_html__( 'Stanje', 'jobly-integration' ) . '</th><th>' . esc_html__( 'Datum', 'jobly-integration' ) . '</th></tr></thead><tbody>';
			foreach ( $mine as $a ) {
				$url = jobly_integration_admin_url(
					'jobly-applications',
					array(
						'action'      => 'show',
						'application' => $a['id'],
					)
				);
				echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( (string) ( $a['applicant']['name'] ?? '' ) ) . '</a></td><td>' . wp_kses_post( jobly_integration_stage_badge( (string) ( $a['stage'] ?? '' ) ) ) . '</td><td>' . esc_html( jobly_integration_format_date( $a['appliedAt'] ?? null ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}
	echo '</section>';
}

/**
 * Vgradnja tab.
 *
 * @param array  $job  Job.
 * @param string $slug Job slug.
 */
function jobly_integration_job_tab_embed( array $job, $slug ) {
	echo '<section class="jobly-panel"><header class="jobly-panel__head"><h2>' . esc_html__( 'Vgradnja', 'jobly-integration' ) . '</h2></header>';
	jobly_integration_copy_field( __( 'Kratka koda', 'jobly-integration' ), '[jobly job="' . $slug . '"]' );
	jobly_integration_copy_field( __( 'Vgradna koda (iframe)', 'jobly-integration' ), jobly_integration_embed_html( $slug, '', array( 'url' => (string) ( $job['embedUrl'] ?? '' ) ) ), true );
	echo '<p class="description">' . esc_html__( 'V urejevalniku blokov pa uporabite blok »Jobly – prijavni obrazec«.', 'jobly-integration' ) . '</p></section>';
}

/**
 * Obrazec tab: what the application form asks.
 */
function jobly_integration_job_tab_form() {
	echo '<section class="jobly-panel"><header class="jobly-panel__head"><h2>' . esc_html__( 'Obrazec', 'jobly-integration' ) . '</h2></header><ul class="jobly-checks">';
	foreach ( array(
		__( 'Ime in priimek', 'jobly-integration' ),
		__( 'E-naslov', 'jobly-integration' ),
		__( 'Telefon (neobvezno)', 'jobly-integration' ),
		__( 'Nekaj vrstic o sebi', 'jobly-integration' ),
		__( 'Privolitev za obdelavo podatkov', 'jobly-integration' ),
	) as $question ) {
		echo '<li>';
		jobly_integration_icon( 'check', '', 16 );
		echo esc_html( $question ) . '</li>';
	}
	echo '</ul><p class="description">' . esc_html__( 'Vprašanja po meri: kmalu, urejanje na Jobly.', 'jobly-integration' ) . '</p></section>';
}
