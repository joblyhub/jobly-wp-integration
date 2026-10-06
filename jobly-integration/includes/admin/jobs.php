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
	$refresh = wp_nonce_url( admin_url( 'admin-post.php?action=jobly_integration_refresh&back=jobly-jobs' ), 'jobly_integration_refresh' );
	echo '<p><a class="button" href="' . esc_url( $refresh ) . '">' . esc_html__( 'Osveži', 'jobly-integration' ) . '</a> <span class="description">' . esc_html__( 'Seznam se predpomni za 5 minut.', 'jobly-integration' ) . '</span></p>';
	$table->views();
	echo '<form method="get"><input type="hidden" name="page" value="jobly-jobs">';
	$table->search_box( __( 'Išči delovna mesta', 'jobly-integration' ), 'jobly-job' );
	$table->display();
	echo '</form>';
	jobly_integration_footer();
}

/**
 * Detail of one job: everything GET /api/v1/jobs returns, its applications
 * (the API has no per-job filter: filtered here), embed code, links.
 *
 * @param string $slug Job slug.
 */
function jobly_integration_job_show( $slug ) {
	jobly_integration_header( __( 'Delovno mesto', 'jobly-integration' ) );
	echo '<p><a href="' . esc_url( jobly_integration_admin_url( 'jobly-jobs' ) ) . '">← ' . esc_html__( 'Vsa delovna mesta', 'jobly-integration' ) . '</a></p>';
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

	$types  = jobly_integration_employment_types();
	$fields = array(
		__( 'Naziv', 'jobly-integration' )               => $job['title'] ?? '',
		__( 'Slug', 'jobly-integration' )                => $job['slug'] ?? '',
		__( 'Stanje', 'jobly-integration' )              => jobly_integration_status_label( (string) ( $job['status'] ?? '' ) ),
		__( 'Kraj', 'jobly-integration' )                => trim( ( $job['location'] ?? '' ) . ' ' . ( $job['country'] ?? '' ) ),
		__( 'Vrsta zaposlitve', 'jobly-integration' )    => $types[ $job['employmentType'] ?? '' ] ?? ( $job['employmentType'] ?? '' ),
		__( 'Plačni razpon (EUR)', 'jobly-integration' ) => isset( $job['salaryMin'] ) ? $job['salaryMin'] . ' – ' . $job['salaryMax'] : '',
		__( 'Objavljeno', 'jobly-integration' )          => jobly_integration_format_date( $job['publishedAt'] ?? null ),
		__( 'Prijav', 'jobly-integration' )              => $job['applicationsCount'] ?? 0,
		__( 'Zaposlenih', 'jobly-integration' )          => $job['hiredCount'] ?? 0,
		__( 'Izpostavljen', 'jobly-integration' )        => ! empty( $job['featured'] ) ? __( 'Da', 'jobly-integration' ) : __( 'Ne', 'jobly-integration' ),
	);
	echo '<h2>' . esc_html( (string) $job['title'] ) . '</h2><table class="widefat striped" style="max-width:700px"><tbody>';
	foreach ( $fields as $label => $value ) {
		echo '<tr><th scope="row" style="width:200px">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Opis, naloge in zahteve API pri branju ne vrne; urejaš jih na Jobly.', 'jobly-integration' ) . '</p>';

	$site_url = jobly_integration_job_url( $slug );
	$jobly    = untrailingslashit( jobly_integration_settings()['base_url'] ) . '/jobs/' . rawurlencode( $slug );
	$short    = '[jobly job="' . $slug . '"]';
	$iframe   = jobly_integration_embed_html( $slug );
	echo '<h3>' . esc_html__( 'Povezave', 'jobly-integration' ) . '</h3><p>';
	if ( 'active' === ( $job['status'] ?? '' ) ) {
		echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $site_url ) . '">' . esc_html__( 'Poglej na strani', 'jobly-integration' ) . '</a> ';
	}
	if ( ! jobly_integration_is_demo() ) {
		echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $jobly ) . '">' . esc_html__( 'Na Jobly', 'jobly-integration' ) . '</a> ';
		echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( jobly_integration_edit_url( $slug ) ) . '">' . esc_html__( 'Uredi na Jobly', 'jobly-integration' ) . '</a>';
	}
	echo '</p><h3>' . esc_html__( 'Vgradnja', 'jobly-integration' ) . '</h3>';
	echo '<p><code class="jobly-shortcode">' . esc_html( $short ) . '</code> <button type="button" class="jobly-copy" data-jobly-copy="' . esc_attr( $short ) . '">' . esc_html__( 'Kopiraj', 'jobly-integration' ) . '</button></p>';
	echo '<p><textarea readonly rows="3" class="large-text code" style="max-width:700px">' . esc_textarea( $iframe ) . '</textarea></p>';

	// What the application form asks today. The form itself lives on Jobly.
	echo '<h3>' . esc_html__( 'Obrazec', 'jobly-integration' ) . '</h3><ul style="list-style:disc;margin-left:1.5em">';
	foreach ( array(
		__( 'Ime in priimek', 'jobly-integration' ),
		__( 'E-naslov', 'jobly-integration' ),
		__( 'Telefon (neobvezno)', 'jobly-integration' ),
		__( 'Nekaj vrstic o sebi', 'jobly-integration' ),
		__( 'Privolitev za obdelavo podatkov', 'jobly-integration' ),
	) as $question ) {
		echo '<li>' . esc_html( $question ) . '</li>';
	}
	echo '</ul>';
	if ( ! empty( $job['requirements'] ) && is_array( $job['requirements'] ) ) {
		echo '<p><strong>' . esc_html__( 'Zahteve oglasa', 'jobly-integration' ) . '</strong></p><ul style="list-style:disc;margin-left:1.5em">';
		foreach ( $job['requirements'] as $req ) {
			echo '<li>' . esc_html( (string) $req ) . '</li>';
		}
		echo '</ul>';
	}
	echo '<p class="description">' . esc_html__( 'Vprašanja po meri: kmalu, urejanje na Jobly.', 'jobly-integration' ) . '</p>';

	// Applications of this job (client-side filter by job id).
	echo '<h3>' . esc_html__( 'Prijave', 'jobly-integration' ) . '</h3>';
	$apps = jobly_integration_all_applications();
	if ( 200 !== $apps['code'] ) {
		jobly_integration_api_error_notice( $apps['code'] );
		jobly_integration_footer();
		return;
	}
	$mine = array_filter(
		$apps['items'],
		static function ( $a ) use ( $job ) {
			return ( $a['job']['id'] ?? '' ) === $job['id'];
		}
	);
	if ( ! $mine ) {
		echo '<p>' . esc_html__( 'Za to mesto še ni prijav.', 'jobly-integration' ) . '</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:700px"><tbody>';
		foreach ( $mine as $a ) {
			$url = jobly_integration_admin_url(
				'jobly-applications',
				array(
					'action'      => 'show',
					'application' => $a['id'],
				)
			);
			echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( (string) ( $a['applicant']['name'] ?? '' ) ) . '</a></td><td>' . esc_html( jobly_integration_stage_label( (string) $a['stage'] ) ) . '</td><td>' . esc_html( jobly_integration_format_date( $a['appliedAt'] ?? null ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	jobly_integration_footer();
}
