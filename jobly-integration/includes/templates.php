<?php
/**
 * Public rendering: template loader (theme overrides), job query, list renderer.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Locate a template: yourtheme/jobly/{name} (child, then parent), else the plugin's templates/.
 *
 * @param string $name Template file, e.g. 'archive-jobs.php' or 'parts/job-card.php'.
 * @return string Absolute path.
 */
function jobly_integration_locate_template( $name ) {
	$dir     = trim( (string) apply_filters( 'jobly_integration_template_path', 'jobly' ), '/' );
	$located = locate_template( $dir . '/' . $name );
	if ( ! $located ) {
		$located = plugin_dir_path( JOBLY_INTEGRATION_FILE ) . 'templates/' . $name;
	}
	return (string) apply_filters( 'jobly_integration_template', $located, $name );
}

/**
 * Include a template; its variables are in $args.
 *
 * @param string $name Template file.
 * @param array  $args Data for the template.
 */
function jobly_integration_get_template( $name, array $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is read by the included template.
	$file = jobly_integration_locate_template( $name );
	if ( is_readable( $file ) ) {
		include $file;
	}
}

/**
 * Template output as a string.
 *
 * @param string $name Template file.
 * @param array  $args Data for the template.
 * @return string
 */
function jobly_integration_render_template( $name, array $args = array() ) {
	ob_start();
	jobly_integration_get_template( $name, $args );
	return (string) ob_get_clean();
}

/**
 * Public stylesheet; printed only where a list, job page or block is rendered.
 */
function jobly_integration_enqueue_frontend() {
	wp_enqueue_style( 'jobly-integration', plugins_url( 'assets/frontend.css', JOBLY_INTEGRATION_FILE ), array(), JOBLY_INTEGRATION_VERSION );
}

/**
 * Filters from the URL (plain GET, works without JavaScript).
 *
 * @return array{search: string, location: string, type: string, remote: bool, page: int}
 */
function jobly_integration_request_filters() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public read-only filters.
	return array(
		'search'   => isset( $_GET['iskanje'] ) ? sanitize_text_field( wp_unslash( $_GET['iskanje'] ) ) : '',
		'location' => isset( $_GET['kraj'] ) ? sanitize_text_field( wp_unslash( $_GET['kraj'] ) ) : '',
		'type'     => isset( $_GET['vrsta'] ) ? sanitize_key( wp_unslash( $_GET['vrsta'] ) ) : '',
		'remote'   => ! empty( $_GET['daljava'] ),
		'page'     => isset( $_GET['stran'] ) ? max( 1, absint( $_GET['stran'] ) ) : 1,
	);
	// phpcs:enable
}

/**
 * Job works remotely (only when the API says so).
 *
 * @param array $job Job from the API.
 * @return bool
 */
function jobly_integration_job_is_remote( array $job ) {
	return 'remote' === ( $job['arrangement'] ?? '' );
}

/**
 * Open jobs, filtered, newest first (highlighted first), paginated.
 *
 * @param array $args search, location, type, remote, per_page, page.
 * @return array{jobs: array[], total: int, pages: int, page: int, all: array[]}
 */
function jobly_integration_query_jobs( array $args = array() ) {
	$args = (array) apply_filters(
		'jobly_integration_jobs_query_args',
		wp_parse_args(
			$args,
			array(
				'search'   => '',
				'location' => '',
				'type'     => '',
				'remote'   => false,
				'per_page' => 10,
				'page'     => 1,
			)
		)
	);

	$all  = jobly_integration_open_jobs();
	$jobs = array_values(
		array_filter(
			$all,
			static function ( $job ) use ( $args ) {
				if ( '' !== $args['location'] && ( $job['location'] ?? '' ) !== $args['location'] ) {
					return false;
				}
				if ( '' !== $args['type'] && ( $job['employmentType'] ?? '' ) !== $args['type'] ) {
					return false;
				}
				if ( $args['remote'] && ! jobly_integration_job_is_remote( $job ) ) {
					return false;
				}
				return '' === $args['search'] || false !== mb_stripos( ( $job['title'] ?? '' ) . ' ' . ( $job['location'] ?? '' ), $args['search'] );
			}
		)
	);
	usort(
		$jobs,
		static function ( $a, $b ) {
			$fa = ! empty( $a['featured'] ) ? 1 : 0;
			$fb = ! empty( $b['featured'] ) ? 1 : 0;
			return $fb <=> $fa ? $fb <=> $fa : strcmp( (string) ( $b['publishedAt'] ?? '' ), (string) ( $a['publishedAt'] ?? '' ) );
		}
	);

	$total    = count( $jobs );
	$per_page = max( 1, (int) $args['per_page'] );
	$pages    = max( 1, (int) ceil( $total / $per_page ) );
	$page     = max( 1, min( $pages, (int) $args['page'] ) );

	return (array) apply_filters(
		'jobly_integration_jobs',
		array(
			'jobs'  => array_slice( $jobs, ( $page - 1 ) * $per_page, $per_page ),
			'total' => $total,
			'pages' => $pages,
			'page'  => $page,
			'all'   => $all,
		),
		$args
	);
}

/**
 * Distinct values for the filter selects.
 *
 * @param array[] $jobs Open jobs.
 * @return array{locations: string[], types: array<string,string>, remote: bool}
 */
function jobly_integration_facets( array $jobs ) {
	$types     = jobly_integration_employment_types();
	$locations = array();
	$found     = array();
	$remote    = false;
	foreach ( $jobs as $job ) {
		if ( ! empty( $job['location'] ) ) {
			$locations[ (string) $job['location'] ] = (string) $job['location'];
		}
		$type = (string) ( $job['employmentType'] ?? '' );
		if ( isset( $types[ $type ] ) ) {
			$found[ $type ] = $types[ $type ];
		}
		$remote = $remote || jobly_integration_job_is_remote( $job );
	}
	natcasesort( $locations );
	return array(
		'locations' => array_values( $locations ),
		'types'     => $found,
		'remote'    => $remote,
	);
}

/**
 * Plural-aware "N open positions" (Slovenian has four forms).
 *
 * @param int $n Count.
 * @return string
 */
function jobly_integration_count_label( $n ) {
	$mod = $n % 100;
	if ( 1 === $mod ) {
		/* translators: %d: number of open positions (singular form). */
		$fmt = __( '%d odprto mesto', 'jobly-integration' );
	} elseif ( 2 === $mod ) {
		/* translators: %d: number of open positions (dual form). */
		$fmt = __( '%d odprti mesti', 'jobly-integration' );
	} elseif ( 3 === $mod || 4 === $mod ) {
		/* translators: %d: number of open positions (3-4). */
		$fmt = __( '%d odprta mesta', 'jobly-integration' );
	} else {
		/* translators: %d: number of open positions (5 or more). */
		$fmt = __( '%d odprtih mest', 'jobly-integration' );
	}
	return sprintf( $fmt, $n );
}

/**
 * "danes", "včeraj", "pred 3 dnevi", else the date.
 *
 * @param string|null $iso Timestamp.
 * @return string
 */
function jobly_integration_relative_date( $iso ) {
	$ts = $iso ? strtotime( (string) $iso ) : false;
	if ( ! $ts ) {
		return '';
	}
	$days = (int) floor( ( time() - $ts ) / DAY_IN_SECONDS );
	if ( $days < 1 ) {
		return __( 'danes', 'jobly-integration' );
	}
	if ( 1 === $days ) {
		return __( 'včeraj', 'jobly-integration' );
	}
	if ( 2 === $days ) {
		return __( 'pred 2 dnevoma', 'jobly-integration' );
	}
	if ( $days < 31 ) {
		/* translators: %d: number of days (3 or more). */
		return sprintf( __( 'pred %d dnevi', 'jobly-integration' ), $days );
	}
	return wp_date( get_option( 'date_format' ), $ts );
}

/**
 * Meta items of a job: icon => text, only real fields.
 *
 * @param array $job Job from the API.
 * @return array<int,array{icon: string, text: string, class: string}>
 */
function jobly_integration_job_meta( array $job ) {
	$types = jobly_integration_employment_types();
	$arr   = jobly_integration_work_arrangements();
	$items = array();
	if ( ! empty( $job['location'] ) ) {
		$items[] = array(
			'icon'  => 'map-pin',
			'text'  => (string) $job['location'],
			'class' => '',
		);
	}
	if ( isset( $types[ $job['employmentType'] ?? '' ] ) ) {
		$items[] = array(
			'icon'  => 'clock',
			'text'  => $types[ $job['employmentType'] ],
			'class' => '',
		);
	}
	if ( ! empty( $job['arrangement'] ) && isset( $arr[ $job['arrangement'] ] ) && 'on_site' !== $job['arrangement'] ) {
		$items[] = array(
			'icon'  => 'home',
			'text'  => $arr[ $job['arrangement'] ],
			'class' => jobly_integration_job_is_remote( $job ) ? 'jobly-meta__tag' : '',
		);
	}
	return $items;
}

/**
 * Salary text from real fields only, or ''.
 *
 * @param array $job Job from the API.
 * @return string
 */
function jobly_integration_salary_text( array $job ) {
	if ( ! isset( $job['salaryMin'] ) || ! isset( $job['salaryMax'] ) ) {
		return '';
	}
	return number_format_i18n( (float) $job['salaryMin'] ) . ' – ' . number_format_i18n( (float) $job['salaryMax'] ) . ' €';
}

/**
 * One job card (filterable).
 *
 * @param array $job  Job from the API.
 * @param array $args List arguments.
 * @return string Escaped HTML.
 */
function jobly_integration_render_job_card( array $job, array $args = array() ) {
	$html = jobly_integration_render_template(
		'parts/job-card.php',
		array(
			'job'  => $job,
			'list' => $args,
		)
	);
	return (string) apply_filters( 'jobly_integration_job_card', $html, $job, $args );
}

/**
 * The job list (cards, optional search/filters and pagination). Used by the
 * careers page, [jobly_jobs] and the "seznam delovnih mest" block.
 *
 * @param array $args number (0 = per-page setting), filters, layout, paginate, action, count.
 * @return string Escaped HTML.
 */
function jobly_integration_render_list( array $args = array() ) {
	$s              = jobly_integration_settings();
	$args           = wp_parse_args(
		$args,
		array(
			'number'   => 0,
			'filters'  => false,
			'layout'   => $s['layout'],
			'paginate' => false,
			'action'   => '',
			'count'    => true,
		)
	);
	$args['layout'] = 'grid' === $args['layout'] ? 'grid' : 'list';
	$args['action'] = '' !== $args['action'] ? $args['action'] : jobly_integration_careers_url();

	if ( jobly_integration_is_unavailable() ) {
		jobly_integration_enqueue_frontend();
		return jobly_integration_render_template( 'parts/unavailable.php' ); // No further calls while Jobly is down.
	}

	$req   = ( $args['filters'] || $args['paginate'] ) ? jobly_integration_request_filters() : array();
	$query = jobly_integration_query_jobs(
		array(
			'search'   => $args['filters'] ? ( $req['search'] ?? '' ) : '',
			'location' => $args['filters'] ? ( $req['location'] ?? '' ) : '',
			'type'     => $args['filters'] ? ( $req['type'] ?? '' ) : '',
			'remote'   => $args['filters'] ? ! empty( $req['remote'] ) : false,
			'per_page' => (int) $args['number'] > 0 ? (int) $args['number'] : (int) $s['per_page'],
			'page'     => $args['paginate'] ? ( $req['page'] ?? 1 ) : 1,
		)
	);

	jobly_integration_enqueue_frontend();
	// Brackets encoded: a shortcode or block written into a job title can never run, wherever this HTML ends up.
	return jobly_integration_neutralise(
		jobly_integration_render_template(
			'archive-jobs.php',
			array(
				'query'   => $query,
				'list'    => $args,
				'request' => $req,
				'facets'  => jobly_integration_facets( $query['all'] ),
				'demo'    => jobly_integration_is_demo(),
			)
		)
	);
}

/**
 * Text from Jobly as safe HTML: narrow allowlist (see jobly_integration_clean_html), plain text gets paragraphs.
 *
 * @param string $text Description from the API.
 * @return string Escaped HTML.
 */
function jobly_integration_rich_text( $text ) {
	$text = jobly_integration_clean_html( (string) $text );
	return wp_strip_all_tags( $text ) !== $text ? $text : wpautop( esc_html( $text ) );
}
