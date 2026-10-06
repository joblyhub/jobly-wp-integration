<?php
/**
 * Server-side client for the Jobly REST API (/api/v1).
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_CACHE    = 'jobly_integration_jobs';
const JOBLY_INTEGRATION_DOWN     = 'jobly_integration_down';
const JOBLY_INTEGRATION_KEY_HOST = 'jobly_integration_key_host';

/**
 * Local development override: the wp-config.php constant JOBLY_API_BASE is defined.
 *
 * @return bool
 */
function jobly_integration_dev_override() {
	return defined( 'JOBLY_API_BASE' ) && JOBLY_API_BASE;
}

/**
 * Schemes accepted for Jobly addresses: https, and http only on local/development sites.
 *
 * @return string[]
 */
function jobly_integration_schemes() {
	return in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ? array( 'https', 'http' ) : array( 'https' );
}

/**
 * Browser-facing Jobly address: always https://jobly.si unless the JOBLY_API_BASE constant is
 * defined (local testing), then the saved "Naslov Jobly" (https, or http on local sites).
 *
 * @param string $saved Saved setting.
 * @return string
 */
function jobly_integration_safe_base( $saved ) {
	$base = jobly_integration_dev_override() ? esc_url_raw( trim( $saved ), jobly_integration_schemes() ) : '';
	return '' !== $base ? untrailingslashit( $base ) : 'https://jobly.si';
}

/**
 * Base URL for server-side API calls: https://jobly.si, or the JOBLY_API_BASE constant from
 * wp-config.php (local testing). Never editable in wp-admin. '' = refuse to call.
 *
 * @return string
 */
function jobly_integration_api_base() {
	if ( ! jobly_integration_dev_override() ) {
		return 'https://jobly.si';
	}
	return untrailingslashit( esc_url_raw( (string) JOBLY_API_BASE, jobly_integration_schemes() ) );
}

/**
 * Remember which host the stored credentials belong to; when the API host changed
 * (constant edited, old install), drop the key, token and everything cached.
 *
 * @param string $host Current API host.
 * @return bool True when the credentials were dropped.
 */
function jobly_integration_bind_credentials( $host ) {
	$bound = (string) get_option( JOBLY_INTEGRATION_KEY_HOST, '' );
	if ( $bound === $host ) {
		return false;
	}
	update_option( JOBLY_INTEGRATION_KEY_HOST, $host, false );
	if ( '' === $bound ) {
		return false; // First use: adopt the current host.
	}
	jobly_integration_update_settings(
		array(
			'api_key'       => '',
			'app_token'     => '',
			'connected'     => 0,
			'company_api'   => 0,
			'company_logo'  => '',
			'company_url'   => '',
			'careers_embed' => '',
		)
	);
	jobly_integration_flush_cache();
	return true;
}

/**
 * One API call. Public pages never wait long and stop calling while Jobly is down.
 *
 * @param string     $method GET or POST.
 * @param string     $path   Path under the API base, e.g. /api/v1/jobs.
 * @param array|null $body   JSON body for POST.
 * @param string     $key    Override API key (used to verify a key before saving).
 * @return array{code: int, data: array, error: string} code 0 = no connection.
 */
function jobly_integration_api_request( $method, $path, $body = null, $key = '' ) {
	$none = array(
		'code'  => 0,
		'data'  => array(),
		'error' => 'network',
	);
	$base = jobly_integration_api_base();
	$host = (string) wp_parse_url( $base, PHP_URL_HOST );
	if ( '' === $host ) {
		return $none;
	}
	// Circuit breaker: after a failure public pages skip the call until the 60 s negative cache expires.
	if ( ! is_admin() && get_transient( JOBLY_INTEGRATION_DOWN ) ) {
		return $none;
	}
	// The stored credentials belong to one host: when it changed they are dropped and never sent (also not when passed in explicitly).
	$stored = jobly_integration_settings()['api_key'];
	if ( jobly_integration_bind_credentials( $host ) && ( '' === $key || $key === $stored ) ) {
		return $none;
	}

	$key       = '' !== $key ? $key : jobly_integration_settings()['api_key'];
	$args      = array(
		'method'      => $method,
		'timeout'     => is_admin() ? 10 : 5,
		'redirection' => 0,
		'headers'     => array(
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

	// The safe variant refuses private/loopback hosts; only the wp-config.php constant (local testing) lifts that.
	$response = jobly_integration_dev_override() ? wp_remote_request( $base . $path, $args ) : wp_safe_remote_request( $base . $path, $args );
	if ( is_wp_error( $response ) ) {
		set_transient( JOBLY_INTEGRATION_DOWN, 1, MINUTE_IN_SECONDS );
		return $none;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 429 === $code || $code >= 500 ) {
		set_transient( JOBLY_INTEGRATION_DOWN, 1, MINUTE_IN_SECONDS );
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return array(
		'code'  => $code,
		'data'  => is_array( $data ) ? $data : array(),
		'error' => '',
	);
}

/**
 * Plain text from the API: scalars only, tags and control characters removed.
 *
 * @param mixed $value Value from the API.
 * @return string
 */
function jobly_integration_text( $value ) {
	return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
}

/**
 * An address from the API, kept only when it is https (http on local sites) and on the Jobly
 * host (or its www. variant); everything else is dropped so the plugin builds its own.
 *
 * @param mixed  $url  Address from the API.
 * @param string $base Jobly address to compare with ('' = the one in use).
 * @return string URL or ''.
 */
function jobly_integration_trusted_url( $url, $base = '' ) {
	$url = is_string( $url ) ? esc_url_raw( trim( $url ), jobly_integration_schemes() ) : '';
	if ( ! preg_match( '#^https?://#i', $url ) ) { // Not scheme-relative (//host) either.
		return '';
	}
	$bare = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( '' !== $base ? $base : jobly_integration_settings()['base_url'], PHP_URL_HOST ) ) );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	return ( '' !== $bare && in_array( $host, array( $bare, 'www.' . $bare ), true ) ) ? $url : '';
}

/**
 * Remove HTML comments, also ones reassembled from nested fragments.
 *
 * @param string $html Markup.
 * @return string
 */
function jobly_integration_strip_comments( $html ) {
	do {
		$before = $html;
		$html   = (string) preg_replace( '/<!--.*?(?:-->|$)/s', '', $html );
	} while ( $html !== $before );
	return $html;
}

/**
 * Text from Jobly as HTML: comments out, then a narrow allowlist (no forms, styles, classes,
 * iframes); links get rel="nofollow noopener". Idempotent.
 *
 * @param string $html Description from the API.
 * @return string
 */
function jobly_integration_clean_html( $html ) {
	$allowed = array(
		'p'      => array(),
		'br'     => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'h3'     => array(),
		'h4'     => array(),
		'a'      => array( 'href' => true ),
	);
	$html    = wp_kses( jobly_integration_strip_comments( (string) $html ), $allowed, array( 'http', 'https', 'mailto' ) );
	$html    = jobly_integration_strip_comments( $html );
	$html    = (string) preg_replace( '/<a\s+href="(?!https?:|mailto:)[^"]*"\s*>/i', '<a>', $html ); // kses leaves relative links; no scheme left = no link.
	return (string) preg_replace( '/<a\s/i', '<a rel="nofollow noopener" target="_blank" ', $html );
}

/**
 * Encode [ and ] so no shortcode or block syntax in text from the API can ever run.
 * Applied to the final HTML of job pages and lists; <script>/<style> bodies are left alone.
 *
 * @param string $html Rendered markup.
 * @return string
 */
function jobly_integration_neutralise( $html ) {
	$parts = preg_split( '#(<(script|style)\b.*?</\2>)#is', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$out   = '';
	foreach ( $parts as $i => $part ) {
		if ( 2 === $i % 3 ) {
			continue; // The captured tag name.
		}
		$out .= 1 === $i % 3 ? $part : str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $part );
	}
	return $out;
}

/**
 * The one door every job from the API passes through (list rows and detail): text is plain,
 * slugs are slugs, numbers are numbers, addresses are on the Jobly host, descriptions are
 * narrow HTML. Unknown keys survive only as scalars. Not an array = dropped.
 *
 * @param mixed $row Row from the API.
 * @return array|null
 */
function jobly_integration_clean_job( $row ) {
	if ( ! is_array( $row ) ) {
		return null;
	}
	$row = jobly_integration_public_urls( $row );
	$out = array();
	foreach ( $row as $k => $v ) {
		$k = (string) $k;
		if ( 'slug' === $k ) {
			$out[ $k ] = sanitize_title( is_scalar( $v ) ? (string) $v : '' );
		} elseif ( 'description' === $k ) {
			$out[ $k ] = is_string( $v ) ? jobly_integration_clean_html( $v ) : '';
		} elseif ( in_array( $k, array( 'url', 'embedUrl' ), true ) ) {
			$out[ $k ] = jobly_integration_trusted_url( $v );
		} elseif ( in_array( $k, array( 'responsibilities', 'requirements', 'benefits' ), true ) ) {
			$out[ $k ] = is_array( $v ) ? array_values( array_filter( array_map( 'jobly_integration_text', $v ), 'strlen' ) ) : array();
		} elseif ( in_array( $k, array( 'salaryMin', 'salaryMax' ), true ) ) {
			$out[ $k ] = is_numeric( $v ) ? $v + 0 : null;
		} elseif ( in_array( $k, array( 'applicationsCount', 'hiredCount', 'jobViews' ), true ) ) {
			$out[ $k ] = absint( is_scalar( $v ) ? $v : 0 );
		} elseif ( 'featured' === $k ) {
			$out[ $k ] = ! empty( $v );
		} elseif ( is_int( $v ) || is_float( $v ) || is_bool( $v ) || null === $v ) {
			$out[ $k ] = $v;
		} elseif ( is_string( $v ) ) {
			$out[ $k ] = sanitize_text_field( $v );
		}
	}
	return $out;
}

/**
 * Company profile through the same door.
 *
 * @param mixed $row Row from the API.
 * @return array
 */
function jobly_integration_clean_company( $row ) {
	if ( ! is_array( $row ) ) {
		return array();
	}
	$row = jobly_integration_public_urls( $row );
	$out = array(
		'name'          => jobly_integration_text( $row['name'] ?? '' ),
		'slug'          => sanitize_title( is_scalar( $row['slug'] ?? null ) ? (string) $row['slug'] : '' ),
		'website'       => is_string( $row['website'] ?? null ) ? esc_url_raw( $row['website'], array( 'https', 'http' ) ) : '',
		'openJobsCount' => absint( is_scalar( $row['openJobsCount'] ?? null ) ? $row['openJobsCount'] : 0 ),
	);
	foreach ( array( 'logoUrl', 'profileUrl', 'careersEmbedUrl' ) as $k ) {
		$out[ $k ] = jobly_integration_trusted_url( $row[ $k ] ?? '' );
	}
	return $out;
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
 * @return array{code: int, items: array[]} code 200 on success. Rows that are not arrays are dropped.
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
		$items = array_merge( $items, array_values( array_filter( (array) ( $res['data']['data'] ?? array() ), 'is_array' ) ) );
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

	// Cleaned on every read, so a cache written by an older version (or anyone) never skips the door.
	$res = $fresh ? false : get_transient( JOBLY_INTEGRATION_CACHE );
	if ( ! is_array( $res ) || ! isset( $res['code'], $res['items'] ) ) {
		$res = jobly_integration_fetch_all( '/api/v1/jobs' );
		if ( 200 === $res['code'] ) {
			set_transient( JOBLY_INTEGRATION_CACHE, $res, 5 * MINUTE_IN_SECONDS );
		}
	}
	$items = array_values( array_filter( array_map( 'jobly_integration_clean_job', (array) $res['items'] ), 'jobly_integration_job_has_slug' ) );
	return array(
		'code'  => (int) $res['code'],
		'items' => $items,
	);
}

/**
 * A cleaned job row can be linked (it has a slug).
 *
 * @param array|null $job Cleaned row.
 * @return bool
 */
function jobly_integration_job_has_slug( $job ) {
	return is_array( $job ) && '' !== ( $job['slug'] ?? '' );
}

/**
 * Jobly cannot be reached or is failing (not "no jobs", not "no key").
 *
 * @return bool
 */
function jobly_integration_is_unavailable() {
	if ( jobly_integration_is_demo() ) {
		return false;
	}
	$code = jobly_integration_all_jobs()['code'];
	return 0 === $code || 429 === $code || $code >= 500;
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
	delete_transient( JOBLY_INTEGRATION_DOWN );
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
			return jobly_integration_clean_company( $cache );
		}
	}
	$res     = jobly_integration_api_request( 'GET', '/api/v1/company', null, $key );
	$company = ( 200 === $res['code'] && is_array( $res['data']['data'] ?? null ) ) ? jobly_integration_clean_company( $res['data']['data'] ) : array();
	if ( '' === $key && ( 200 === $res['code'] || 404 === $res['code'] ) ) {
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
		$detail = ( 200 === $res['code'] && is_array( $res['data']['data'] ?? null ) ) ? $res['data']['data'] : array();
		if ( 200 === $res['code'] || 404 === $res['code'] ) {
			set_transient( $ckey, $detail, 5 * MINUTE_IN_SECONDS );
		}
	}
	// Only the content keys of the detail are merged: it can never change a row's identity or status.
	$detail = (array) jobly_integration_clean_job( $detail );
	return array_merge( $row, array_intersect_key( $detail, array_flip( array( 'description', 'responsibilities', 'requirements', 'benefits', 'embedUrl', 'url' ) ) ) );
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
	$list = array();
	if ( 200 === $res['code'] && is_array( $res['data']['data'] ?? null ) ) {
		foreach ( $res['data']['data'] as $cat ) {
			if ( is_array( $cat ) && ! empty( $cat['slug'] ) ) {
				$list[] = array(
					'slug'  => sanitize_title( jobly_integration_text( $cat['slug'] ) ),
					'name'  => jobly_integration_text( $cat['name'] ?? '' ),
					'group' => jobly_integration_text( $cat['group'] ?? '' ),
				);
			}
		}
	}
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
	$query  = http_build_query(
		array(
			'from' => $from,
			'to'   => $to,
		)
	);
	$res    = jobly_integration_api_request( 'GET', '/api/v1/stats?' . $query );
	$result = array(
		'code' => $res['code'],
		'data' => is_array( $res['data']['data'] ?? null ) ? $res['data']['data'] : array(),
	);
	if ( 200 === $res['code'] || 404 === $res['code'] ) {
		set_transient( $ckey, $result, 5 * MINUTE_IN_SECONDS );
	}
	return $result;
}
