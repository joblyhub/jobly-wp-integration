<?php
/**
 * Prijave: index and show. Applicant data is read live, never cached or logged.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Screen callback.
 */
function jobly_integration_page_applications() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	if ( isset( $_GET['action'], $_GET['application'] ) && 'show' === $_GET['action'] ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		jobly_integration_application_show( sanitize_text_field( wp_unslash( $_GET['application'] ) ) );
		return;
	}

	require_once __DIR__ . '/class-applications-table.php';
	jobly_integration_header( __( 'Prijave', 'jobly-integration' ) );
	if ( ! jobly_integration_require_connection() ) {
		jobly_integration_footer();
		return;
	}
	$table = new Jobly_Integration_Applications_Table();
	$table->prepare_items();
	if ( 200 !== $table->api_code ) {
		jobly_integration_api_error_notice( $table->api_code );
		jobly_integration_footer();
		return;
	}
	echo '<form method="get"><input type="hidden" name="page" value="jobly-applications">';
	$table->search_box( __( 'Išči prijave', 'jobly-integration' ), 'jobly-app' );
	$table->display();
	echo '</form>';
	jobly_integration_footer();
}

/**
 * One application, with the fields the API returns.
 *
 * @param string $id Application public id.
 */
function jobly_integration_application_show( $id ) {
	jobly_integration_header( __( 'Prijava', 'jobly-integration' ) );
	echo '<p><a href="' . esc_url( jobly_integration_admin_url( 'jobly-applications' ) ) . '">← ' . esc_html__( 'Vse prijave', 'jobly-integration' ) . '</a></p>';
	if ( ! jobly_integration_require_connection() ) {
		jobly_integration_footer();
		return;
	}
	$res = jobly_integration_all_applications();
	$app = null;
	foreach ( $res['items'] as $row ) {
		if ( ( $row['id'] ?? '' ) === $id ) {
			$app = $row;
		}
	}
	if ( 200 !== $res['code'] || ! $app ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Zapisa ni mogoče najti.', 'jobly-integration' ) . '</p></div>';
		jobly_integration_footer();
		return;
	}
	$job_slug = '';
	foreach ( jobly_integration_all_jobs()['items'] as $job ) {
		if ( ( $job['id'] ?? '' ) === ( $app['job']['id'] ?? null ) ) {
			$job_slug = $job['slug'];
		}
	}
	echo '<h2>' . esc_html( (string) ( $app['applicant']['name'] ?? '' ) ) . '</h2><table class="widefat striped" style="max-width:700px"><tbody>';
	$rows = array(
		__( 'E-naslov', 'jobly-integration' )      => $app['applicant']['email'] ?? '',
		__( 'Delovno mesto', 'jobly-integration' ) => $app['job']['title'] ?? '',
		__( 'Stanje', 'jobly-integration' )        => jobly_integration_stage_label( (string) ( $app['stage'] ?? '' ) ),
		__( 'Prijavljen', 'jobly-integration' )    => jobly_integration_format_date( $app['appliedAt'] ?? null ),
	);
	foreach ( $rows as $label => $value ) {
		echo '<tr><th scope="row" style="width:200px">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
	}
	// Any other field the API returns (e.g. answers to custom questions, once Jobly has them).
	$known = array( 'id', 'stage', 'coverLetter', 'appliedAt', 'job', 'applicant' );
	foreach ( $app as $key => $value ) {
		if ( in_array( $key, $known, true ) ) {
			continue;
		}
		$text = is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
		echo '<tr><th scope="row">' . esc_html( (string) $key ) . '</th><td>' . esc_html( (string) $text ) . '</td></tr>';
	}
	echo '<tr><th scope="row">' . esc_html__( 'Motivacijsko pismo', 'jobly-integration' ) . '</th><td>' . nl2br( esc_html( (string) ( $app['coverLetter'] ?? '' ) ) ) . '</td></tr></tbody></table>';
	if ( '' !== $job_slug ) {
		$url = jobly_integration_admin_url(
			'jobly-jobs',
			array(
				'action' => 'show',
				'job'    => $job_slug,
			)
		);
		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Odpri delovno mesto', 'jobly-integration' ) . '</a></p>';
	}
	echo '<p class="description">' . esc_html__( 'Postopek z kandidatom (stanje, razgovori, ponudba) vodiš na Jobly. Podatki se berejo sproti in se v WordPressu ne shranjujejo.', 'jobly-integration' ) . '</p>';
	jobly_integration_footer();
}
