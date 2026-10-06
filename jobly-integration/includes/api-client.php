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
	$key       = '' !== $key ? $key : jobly_integration_settings()['api_key'];
	$args      = array(
		'method'  => $method,
		'timeout' => 10,
		'headers' => array(
			'Authorization' => 'Bearer ' . $key,
			'Accept'        => 'application/json',
		),
	);
	$app_token = jobly_integration_app_token();
	if ( '' !== $app_token ) {
		$args['headers']['App-Token'] = $app_token;
	}
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
	if ( 401 === $res['code'] ) {
		// Jobly says which credential it rejects.
		$msg = (string) ( $res['data']['message'] ?? '' );
		if ( false !== stripos( $msg, 'App-Token' ) ) {
			return false !== stripos( $msg, 'Manjka' ) ? 'app_token_missing' : 'app_token_invalid';
		}
		return 'unauthorized';
	}
	return 'error';
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
 * Forget the cached job list, company profile and categories.
 */
function jobly_integration_flush_cache() {
	delete_transient( JOBLY_INTEGRATION_CACHE );
	delete_transient( 'jobly_integration_company' );
	delete_transient( 'jobly_integration_categories' );
}

/**
 * Make URLs returned by the API browser-usable. With JOBLY_API_BASE defined (local testing,
 * server-to-server address) its origin is swapped for the browser-facing "Naslov Jobly";
 * in production URLs are used as returned.
 *
 * @param array $data Company or job from the API.
 * @return array
 */
function jobly_integration_public_urls( array $data ) {
	if ( ! defined( 'JOBLY_API_BASE' ) || ! JOBLY_API_BASE ) {
		return $data;
	}
	$from = untrailingslashit( (string) JOBLY_API_BASE );
	$to   = untrailingslashit( (string) jobly_integration_settings()['base_url'] );
	foreach ( array( 'logoUrl', 'profileUrl', 'careersEmbedUrl', 'url', 'embedUrl' ) as $key ) {
		if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) && 0 === strpos( $data[ $key ], $from ) ) {
			$data[ $key ] = $to . substr( $data[ $key ], strlen( $from ) );
		}
	}
	return $data;
}

/**
 * Company profile behind the API key (GET /api/v1/company), cached for an hour.
 * Demo mode returns an invented company. Empty array on older Jobly (404) or no connection.
 *
 * @param string $key Override key (verify step); skips the cache.
 * @return array{name?: string, slug?: string, logoUrl?: string, website?: string, profileUrl?: string, careersEmbedUrl?: string, openJobsCount?: int}
 */
function jobly_integration_company( $key = '' ) {
	if ( jobly_integration_is_demo() && '' === $key ) {
		return jobly_integration_demo_company();
	}
	if ( '' === $key ) {
		if ( '' === jobly_integration_settings()['api_key'] ) {
			return array();
		}
		$cache = get_transient( 'jobly_integration_company' );
		if ( is_array( $cache ) ) {
			return $cache;
		}
	}
	$res     = jobly_integration_api_request( 'GET', '/api/v1/company', null, $key );
	$company = ( 200 === $res['code'] && is_array( $res['data']['data'] ?? null ) ) ? jobly_integration_public_urls( $res['data']['data'] ) : array();
	if ( 200 === $res['code'] || 404 === $res['code'] ) {
		set_transient( 'jobly_integration_company', $company, HOUR_IN_SECONDS );
	}
	return $company;
}

/**
 * One job with its content (GET /api/v1/jobs/{slug}), merged over the list row.
 * Falls back to the list row when the endpoint is missing (older Jobly).
 *
 * @param array $row Job row from the list.
 * @return array
 */
function jobly_integration_job_detail( array $row ) {
	$slug = (string) ( $row['slug'] ?? '' );
	if ( '' === $slug ) {
		return $row;
	}
	if ( jobly_integration_is_demo() ) {
		return array_merge( $row, jobly_integration_demo_detail( $slug ) );
	}
	$ckey   = 'jobly_integration_job_' . md5( $slug );
	$detail = get_transient( $ckey );
	if ( ! is_array( $detail ) ) {
		$res    = jobly_integration_api_request( 'GET', '/api/v1/jobs/' . rawurlencode( $slug ) );
		$detail = ( 200 === $res['code'] && is_array( $res['data']['data'] ?? null ) ) ? jobly_integration_public_urls( $res['data']['data'] ) : array();
		if ( 200 === $res['code'] || 404 === $res['code'] ) {
			set_transient( $ckey, $detail, 5 * MINUTE_IN_SECONDS );
		}
	}
	return array_merge( $row, $detail );
}

/**
 * Job categories (GET /api/v1/categories), cached for a day. Empty on older Jobly.
 *
 * @return array<int,array{slug: string, name: string, group?: string}>
 */
function jobly_integration_categories() {
	if ( jobly_integration_is_demo() || '' === jobly_integration_settings()['api_key'] ) {
		return array();
	}
	$cache = get_transient( 'jobly_integration_categories' );
	if ( is_array( $cache ) ) {
		return $cache;
	}
	$res  = jobly_integration_api_request( 'GET', '/api/v1/categories' );
	$list = ( 200 === $res['code'] && is_array( $res['data']['data'] ?? null ) ) ? array_values( $res['data']['data'] ) : array();
	if ( 200 === $res['code'] || 404 === $res['code'] ) {
		set_transient( 'jobly_integration_categories', $list, DAY_IN_SECONDS );
	}
	return $list;
}

/**
 * Status value -> Slovenian label.
 *
 * @param string $status API status.
 * @return string
 */
function jobly_integration_status_label( $status ) {
	$labels = array(
		'active'            => __( 'Odprto', 'jobly-integration' ),
		'draft'             => __( 'Osnutek', 'jobly-integration' ),
		'private'           => __( 'Zasebno', 'jobly-integration' ),
		'ready_for_publish' => __( 'Pripravljen', 'jobly-integration' ),
		'closed'            => __( 'Zaprto', 'jobly-integration' ),
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

/**
 * Aggregate statistics for a period (GET /api/v1/stats), cached for 5 minutes. No personal data.
 * Demo mode returns an invented, believable curve.
 *
 * @param string $from Start date, Y-m-d.
 * @param string $to   End date, Y-m-d.
 * @return array{code: int, data: array} code 404 = older Jobly without statistics.
 */
function jobly_integration_stats( $from, $to ) {
	if ( jobly_integration_is_demo() ) {
		return array(
			'code' => 200,
			'data' => jobly_integration_demo_stats( $from, $to ),
		);
	}
	if ( '' === jobly_integration_settings()['api_key'] ) {
		return array(
			'code' => 401,
			'data' => array(),
		);
	}
	$ckey  = 'jobly_integration_stats_' . md5( $from . $to );
	$cache = get_transient( $ckey );
	if ( is_array( $cache ) ) {
		return $cache;
	}
	$res    = jobly_integration_api_request( 'GET', '/api/v1/stats?' . http_build_query( array( 'from' => $from, 'to' => $to ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing -- compact query.
	$result = array(
		'code' => $res['code'],
		'data' => is_array( $res['data']['data'] ?? null ) ? $res['data']['data'] : array(),
	);
	if ( 200 === $res['code'] || 404 === $res['code'] ) {
		set_transient( $ckey, $result, 5 * MINUTE_IN_SECONDS );
	}
	return $result;
}
