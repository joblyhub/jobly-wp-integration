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
	if ( 0 === $table->total_all ) {
		jobly_integration_empty_state( 'applications', __( 'Še ni prijav', 'jobly-integration' ), __( 'Ko kandidat odda prijavo prek vašega spletnega mesta ali Jobly, se prikaže tukaj. Podatki se berejo sproti in se v WordPressu ne shranjujejo.', 'jobly-integration' ) );
		jobly_integration_footer();
		return;
	}
	echo '<div class="jobly-panel jobly-panel--table"><form method="get"><input type="hidden" name="page" value="jobly-applications">';
	$table->search_box( __( 'Išči prijave', 'jobly-integration' ), 'jobly-app' );
	$table->display();
	echo '</form></div>';
	jobly_integration_footer();
}

/**
 * One application, with the fields the API returns.
 *
 * @param string $id Application public id.
 */
function jobly_integration_application_show( $id ) {
	jobly_integration_header( __( 'Prijava', 'jobly-integration' ) );
	echo '<p class="jobly-back"><a href="' . esc_url( jobly_integration_admin_url( 'jobly-applications' ) ) . '">';
	jobly_integration_icon( 'arrow-left', '', 16 );
	echo ' ' . esc_html__( 'Vse prijave', 'jobly-integration' ) . '</a></p>';
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
	$name = (string) ( $app['applicant']['name'] ?? '' );
	echo '<div class="jobly-detail"><div class="jobly-detail__main"><section class="jobly-panel"><header class="jobly-panel__head"><div class="jobly-person"><span class="jobly-avatar jobly-avatar--lg">' . esc_html( jobly_integration_initials( $name ) ) . '</span><h2>' . esc_html( $name ) . '</h2></div>' . wp_kses_post( jobly_integration_stage_badge( (string) ( $app['stage'] ?? '' ) ) ) . '</header><dl class="jobly-dl">';
	$rows = array(
		__( 'E-naslov', 'jobly-integration' )      => $app['applicant']['email'] ?? '',
		__( 'Delovno mesto', 'jobly-integration' ) => $app['job']['title'] ?? '',
		__( 'Prijavljen', 'jobly-integration' )    => jobly_integration_format_date( $app['appliedAt'] ?? null ),
	);
	// Any other field the API returns (e.g. answers to custom questions, once Jobly has them).
	$known = array( 'id', 'stage', 'coverLetter', 'appliedAt', 'job', 'applicant' );
	foreach ( $app as $key => $value ) {
		if ( ! in_array( $key, $known, true ) ) {
			$rows[ (string) $key ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
		}
	}
	foreach ( $rows as $label => $value ) {
		echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
	}
	echo '</dl><h3>' . esc_html__( 'Motivacijsko pismo', 'jobly-integration' ) . '</h3><div class="jobly-letter">' . nl2br( esc_html( (string) ( $app['coverLetter'] ?? '' ) ) ) . '</div></section></div><aside class="jobly-detail__side">';
	if ( '' !== $job_slug ) {
		$url = jobly_integration_admin_url(
			'jobly-jobs',
			array(
				'action' => 'show',
				'job'    => $job_slug,
			)
		);
		echo '<section class="jobly-panel"><header class="jobly-panel__head"><h2>' . esc_html__( 'Delovno mesto', 'jobly-integration' ) . '</h2></header><p>' . esc_html( (string) ( $app['job']['title'] ?? '' ) ) . '</p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Odpri delovno mesto', 'jobly-integration' ) . '</a></section>';
	}
	echo '</aside></div>';
	echo '<p class="description">' . esc_html__( 'Postopek z kandidatom (stanje, razgovori, ponudba) vodiš na Jobly. Podatki se berejo sproti in se v WordPressu ne shranjujejo.', 'jobly-integration' ) . '</p>';
	jobly_integration_footer();
}
