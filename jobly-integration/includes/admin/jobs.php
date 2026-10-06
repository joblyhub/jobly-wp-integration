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
 * Detail of one job, two columns: details + applications | links, embed, form.
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

	$job    = jobly_integration_job_detail( $job );
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
					<div class="jobly-letter"><?php echo jobly_integration_rich_text( $job['description'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered with wp_kses_post(). ?></div>
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

			<section class="jobly-panel">
				<header class="jobly-panel__head"><h2><?php esc_html_e( 'Prijave', 'jobly-integration' ); ?></h2></header>
				<?php
				$apps = jobly_integration_all_applications();
				if ( 200 !== $apps['code'] ) {
					jobly_integration_api_error_notice( $apps['code'] );
				} else {
					$mine = array_filter(
						$apps['items'],
						static function ( $a ) use ( $job ) {
							return ( $a['job']['id'] ?? '' ) === $job['id'];
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
				?>
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

			<section class="jobly-panel">
				<header class="jobly-panel__head"><h2><?php esc_html_e( 'Vgradnja', 'jobly-integration' ); ?></h2></header>
				<?php
				jobly_integration_copy_field( __( 'Kratka koda', 'jobly-integration' ), '[jobly job="' . $slug . '"]' );
				jobly_integration_copy_field( __( 'Vgradna koda (iframe)', 'jobly-integration' ), jobly_integration_embed_html( $slug, '', array( 'url' => (string) ( $job['embedUrl'] ?? '' ) ) ), true );
				?>
				<p class="description"><?php esc_html_e( 'V urejevalniku blokov pa uporabite blok »Jobly – prijavni obrazec«.', 'jobly-integration' ); ?></p>
			</section>

			<section class="jobly-panel">
				<header class="jobly-panel__head"><h2><?php esc_html_e( 'Obrazec', 'jobly-integration' ); ?></h2></header>
				<ul class="jobly-checks">
					<?php
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
					?>
				</ul>
				<p class="description"><?php esc_html_e( 'Vprašanja po meri: kmalu, urejanje na Jobly.', 'jobly-integration' ); ?></p>
			</section>
		</aside>
	</div>
	<?php
	jobly_integration_footer();
}
