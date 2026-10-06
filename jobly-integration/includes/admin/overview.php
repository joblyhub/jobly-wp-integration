<?php
/**
 * Pregled: connection status, counts, quick links.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Screen callback.
 */
function jobly_integration_page_overview() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	jobly_integration_header( 'Jobly.si HRM' );
	echo '<p>' . esc_html__( 'Delovna mesta in prijave podjetja iz Jobly.si v WordPressu. Prijave, privolitve in zaščita pred neželeno pošto ostanejo na Jobly.', 'jobly-integration' ) . '</p>';

	$s = jobly_integration_settings();
	if ( $s['demo'] ) {
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Demo način: vidiš izmišljene podatke podjetja Primer d.o.o. in Jobly se ne kliče. Za prave podatke povežite podjetje v Nastavitvah.', 'jobly-integration' ) . '</p></div>';
	}
	if ( ! jobly_integration_require_connection() ) {
		jobly_integration_footer();
		return;
	}

	$jobs = jobly_integration_all_jobs();
	$apps = jobly_integration_all_applications();
	if ( 200 !== $jobs['code'] ) {
		jobly_integration_api_error_notice( $jobs['code'] );
		jobly_integration_footer();
		return;
	}

	$open = count( jobly_integration_open_jobs() );
	$new  = 0;
	foreach ( $apps['items'] as $app ) {
		if ( strtotime( (string) ( $app['appliedAt'] ?? '' ) ) >= time() - 7 * DAY_IN_SECONDS ) {
			++$new;
		}
	}
	?>
	<div class="jobly-cards">
		<div class="jobly-card"><strong><?php echo esc_html( (string) $open ); ?></strong><?php esc_html_e( 'odprta mesta', 'jobly-integration' ); ?></div>
		<div class="jobly-card"><strong><?php echo 200 === $apps['code'] ? esc_html( (string) $new ) : '—'; ?></strong><?php esc_html_e( 'novih prijav (7 dni)', 'jobly-integration' ); ?></div>
	</div>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( jobly_integration_admin_url( 'jobly-job-new' ) ); ?>"><?php esc_html_e( 'Dodaj delovno mesto', 'jobly-integration' ); ?></a>
		<a class="button" href="<?php echo esc_url( jobly_integration_admin_url( 'jobly-jobs' ) ); ?>"><?php esc_html_e( 'Delovna mesta', 'jobly-integration' ); ?></a>
		<a class="button" href="<?php echo esc_url( jobly_integration_admin_url( 'jobly-applications' ) ); ?>"><?php esc_html_e( 'Prijave', 'jobly-integration' ); ?></a>
		<a class="button" href="<?php echo esc_url( jobly_integration_careers_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Karierna stran', 'jobly-integration' ); ?></a>
	</p>
	<?php
	jobly_integration_footer();
}
