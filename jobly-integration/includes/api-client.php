<?php
/**
 * Server-side client for the Jobly REST API (/api/v1).
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_CACHE = 'jobly_integration_jobs';

/**
 * Base URL for server-side API calls. JOBLY_API_BASE (wp-config.php) wins, for
 * local testing; the browser-facing embed base stays the "Naslov Jobly" setting.
 *
 * @return string
 */
function jobly_integration_api_base() {
	$base = defined( 'JOBLY_API_BASE' ) && JOBLY_API_BASE ? JOBLY_API_BASE : jobly_integration_settings()['base_url'];
	return untrailingslashit( esc_url_raw( (string) $base ) );
}

/**
 * One API call.
 *
 * @param string     $method GET or POST.
 * @param string     $path   Path under the API base, e.g. /api/v1/jobs.
 * @param array|null $body   JSON body for POST.
 * @param string     $key    Override API key (used to verify a key before saving).
 * @return array{code: int, data: array, error: string} code 0 = no connection.
 */
function jobly_integration_api_request( $method, $path, $body = null, $key = '' ) {
	$key  = '' !== $key ? $key : jobly_integration_settings()['api_key'];
	$args = array(
		'method'  => $method,
		'timeout' => 10,
		'headers' => array(
			'Authorization' => 'Bearer ' . $key,
			'Accept'        => 'application/json',
		),
	);
	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
	}

	$response = wp_remote_request( jobly_integration_api_base() . $path, $args );
	if ( is_wp_error( $response ) ) {
		return array(
			'code'  => 0,
			'data'  => array(),
			'error' => 'network',
		);
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return array(
		'code'  => (int) wp_remote_retrieve_response_code( $response ),
		'data'  => is_array( $data ) ? $data : array(),
		'error' => '',
	);
}

/**
 * Check a key against the API.
 *
 * @param string $key API token.
 * @return string 'ok', 'unauthorized', 'network' or 'error'.
 */
function jobly_integration_verify_key( $key ) {
	$res = jobly_integration_api_request( 'GET', '/api/v1/jobs', null, $key );
	if ( 0 === $res['code'] ) {
		return 'network';
	}
	if ( 200 === $res['code'] ) {
		return 'ok';
	}
	return 401 === $res['code'] ? 'unauthorized' : 'error';
}

/**
 * Follow the API's pagination (max 5 pages of 25).
 *
 * @param string $path Path under the API base.
 * @return array{code: int, items: array[]} code 200 on success.
 */
function jobly_integration_fetch_all( $path ) {
	$items = array();
	for ( $page = 1; $page <= 5; $page++ ) {
		$res = jobly_integration_api_request( 'GET', $path . '?page=' . $page );
		if ( 200 !== $res['code'] ) {
			return array(
				'code'  => $res['code'],
				'items' => array(),
			);
		}
		$items = array_merge( $items, array_values( (array) ( $res['data']['data'] ?? array() ) ) );
		if ( empty( $res['data']['links']['next'] ) ) {
			break;
		}
	}
	return array(
		'code'  => 200,
		'items' => $items,
	);
}

/**
 * All of the company's jobs in every status, cached for 5 minutes.
 * Demo mode returns the invented jobs and makes no call.
 *
 * @param bool $fresh Skip the cache.
 * @return array{code: int, items: array[]}
 */
function jobly_integration_all_jobs( $fresh = false ) {
	if ( jobly_integration_is_demo() ) {
		return array(
			'code'  => 200,
			'items' => jobly_integration_demo_jobs(),
		);
	}
	if ( '' === jobly_integration_settings()['api_key'] ) {
		return array(
			'code'  => 401,
			'items' => array(),
		);
	}

	$cache = $fresh ? false : get_transient( JOBLY_INTEGRATION_CACHE );
	if ( is_array( $cache ) ) {
		return $cache;
	}
	$res = jobly_integration_fetch_all( '/api/v1/jobs' );
	if ( 200 === $res['code'] ) {
		set_transient( JOBLY_INTEGRATION_CACHE, $res, 5 * MINUTE_IN_SECONDS );
	}
	return $res;
}

/**
 * Open jobs only (status "active"), for the public pages.
 *
 * @return array[]
 */
function jobly_integration_open_jobs() {
	$res = jobly_integration_all_jobs();
	return array_values(
		array_filter(
			$res['items'],
			static function ( $job ) {
				return 'active' === ( $job['status'] ?? '' );
			}
		)
	);
}

/**
 * Applications to the company's jobs. Applicant data: never cached, never logged.
 * Demo mode returns invented applicants and makes no call.
 *
 * @return array{code: int, items: array[]}
 */
function jobly_integration_all_applications() {
	if ( jobly_integration_is_demo() ) {
		return array(
			'code'  => 200,
			'items' => jobly_integration_demo_applications(),
		);
	}
	if ( '' === jobly_integration_settings()['api_key'] ) {
		return array(
			'code'  => 401,
			'items' => array(),
		);
	}
	return jobly_integration_fetch_all( '/api/v1/applications' );
}

/**
 * Forget the cached job list.
 */
function jobly_integration_flush_cache() {
	delete_transient( JOBLY_INTEGRATION_CACHE );
}

/**
 * Status value -> Slovenian label.
 *
 * @param string $status API status.
 * @return string
 */
function jobly_integration_status_label( $status ) {
	$labels = array(
		'active'            => __( 'Objavljen', 'jobly-integration' ),
		'draft'             => __( 'Osnutek', 'jobly-integration' ),
		'private'           => __( 'Zasebno', 'jobly-integration' ),
		'ready_for_publish' => __( 'Pripravljen', 'jobly-integration' ),
		'closed'            => __( 'Zaprt', 'jobly-integration' ),
	);
	return $labels[ $status ] ?? $status;
}

/**
 * Employment type value -> label (the values the API accepts).
 *
 * @return array<string,string>
 */
function jobly_integration_employment_types() {
	return array(
		'full_time'  => __( 'Polni delovni čas', 'jobly-integration' ),
		'part_time'  => __( 'Krajši delovni čas', 'jobly-integration' ),
		'contract'   => __( 'Pogodbeno delo', 'jobly-integration' ),
		'internship' => __( 'Pripravništvo', 'jobly-integration' ),
		'student'    => __( 'Študentsko delo', 'jobly-integration' ),
	);
}

/**
 * Work arrangement value -> label (the values the API accepts).
 *
 * @return array<string,string>
 */
function jobly_integration_work_arrangements() {
	return array(
		'on_site' => __( 'Na lokaciji', 'jobly-integration' ),
		'hybrid'  => __( 'Hibridno', 'jobly-integration' ),
		'remote'  => __( 'Na daljavo', 'jobly-integration' ),
	);
}

/**
 * Application stage value -> label.
 *
 * @param string $stage API stage.
 * @return string
 */
function jobly_integration_stage_label( $stage ) {
	$labels = array(
		'new'             => __( 'Nova', 'jobly-integration' ),
		'reviewed'        => __( 'Pregledana', 'jobly-integration' ),
		'intro_round'     => __( 'Uvodni razgovor', 'jobly-integration' ),
		'technical_round' => __( 'Tehnični krog', 'jobly-integration' ),
		'final_round'     => __( 'Zaključni krog', 'jobly-integration' ),
		'decision'        => __( 'Odločitev', 'jobly-integration' ),
		'offer'           => __( 'Ponudba', 'jobly-integration' ),
		'hired'           => __( 'Zaposlen', 'jobly-integration' ),
		'cancelled'       => __( 'Preklicana', 'jobly-integration' ),
		'rejected'        => __( 'Zavrnjena', 'jobly-integration' ),
		'withdrawn'       => __( 'Umaknjena', 'jobly-integration' ),
	);
	return $labels[ $stage ] ?? $stage;
}

/**
 * Localised date from an ISO 8601 string.
 *
 * @param string|null $iso Timestamp.
 * @return string
 */
function jobly_integration_format_date( $iso ) {
	$ts = $iso ? strtotime( $iso ) : false;
	return $ts ? wp_date( get_option( 'date_format' ), $ts ) : '—';
}
