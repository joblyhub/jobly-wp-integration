<?php
/**
 * Public side: [jobly] embed, [jobly_jobs] list, /kariera/ and /kariera/{slug}/.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build the iframe for the Jobly embed.
 *
 * @param string $job     Job slug ('' = company page).
 * @param string $company Company slug (when $job is '').
 * @param array  $opts    show, filter, accent, height, title.
 * @return string Escaped HTML.
 */
function jobly_integration_embed_html( $job = '', $company = '', array $opts = array() ) {
	$s    = jobly_integration_settings();
	$opts = wp_parse_args(
		$opts,
		array(
			'show'   => '',
			'filter' => '',
			'accent' => $s['accent'],
			'height' => $s['height'],
			'title'  => __( 'Prijava na delo', 'jobly-integration' ),
		)
	);
	$base = untrailingslashit( esc_url_raw( $s['base_url'] ) );
	$url  = '' !== $job
		? $base . '/embed/jobs/' . rawurlencode( $job )
		: $base . '/embed/companies/' . rawurlencode( $company );

	$query = array();
	if ( in_array( $opts['show'], array( 'basic', 'full' ), true ) ) {
		$query['show'] = $opts['show'];
	}
	$filter = preg_replace( '/[^a-z,]/', '', strtolower( (string) $opts['filter'] ) );
	if ( '' !== $filter ) {
		$query['filter'] = $filter;
	}
	$accent = sanitize_hex_color( (string) $opts['accent'] );
	if ( $accent && 7 === strlen( $accent ) ) {
		$query['accent'] = $accent;
	}
	if ( $query ) {
		$url = add_query_arg( array_map( 'rawurlencode', $query ), $url );
	}

	return sprintf(
		'<iframe class="jobly-embed" src="%s" width="100%%" height="%d" style="border:0;width:100%%" loading="lazy" title="%s"></iframe>',
		esc_url( $url ),
		max( 300, min( 3000, absint( $opts['height'] ) ) ),
		esc_attr( $opts['title'] )
	);
}

/**
 * [jobly]                          celotna karierna stran podjetja iz nastavitev
 * [jobly company="podjetje"]       karierna stran drugega podjetja
 * [jobly job="slug-oglasa"]        obrazec za en oglas
 * [jobly show="full"]              z vsebino oglasa (privzeto: basic)
 * [jobly filter="title,benefits"]  natančno izbrani deli
 * [jobly accent="#2563eb" height="900"]
 * [jobly demo="1"]                 izmišljeni primer, brez klica na Jobly
 *
 * All rendering, validation, consent and anti-spam stay on Jobly; the plugin
 * only builds the URL, so a WordPress site never handles applicant data.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function jobly_integration_shortcode( $atts ) {
	$settings = jobly_integration_settings();
	$atts     = shortcode_atts(
		array(
			'company' => $settings['company'],
			'job'     => '',
			'show'    => '',
			'filter'  => '',
			'accent'  => $settings['accent'],
			'height'  => $settings['height'],
			'title'   => __( 'Prijava na delo', 'jobly-integration' ),
			'demo'    => (string) $settings['demo'],
		),
		$atts,
		'jobly'
	);

	$job     = sanitize_title( $atts['job'] );
	$company = sanitize_title( $atts['company'] );

	// Demo: invented data only, nothing from Jobly.
	if ( in_array( strtolower( (string) $atts['demo'] ), array( '1', 'true', 'yes', 'da' ), true ) ) {
		return jobly_integration_demo( (string) sanitize_hex_color( $atts['accent'] ), $job );
	}

	if ( '' === $job && '' === $company ) {
		return current_user_can( 'manage_options' )
			? '<p><em>' . esc_html__( 'Jobly: v nastavitvah vpiši podjetje ali v kratki kodi podaj job="…".', 'jobly-integration' ) . '</em></p>'
			: '';
	}

	return jobly_integration_embed_html( $job, $company, $atts );
}
add_shortcode( 'jobly', 'jobly_integration_shortcode' );

/**
 * Public URL of the careers index.
 *
 * @return string
 */
function jobly_integration_careers_url() {
	return home_url( '/' . jobly_integration_settings()['base_path'] . '/' );
}

/**
 * Public URL of one job's subpage.
 *
 * @param string $slug Job slug.
 * @return string
 */
function jobly_integration_job_url( $slug ) {
	return home_url( '/' . jobly_integration_settings()['base_path'] . '/' . rawurlencode( $slug ) . '/' );
}

/**
 * Server-rendered list of open jobs. Shared by [jobly_jobs] and /kariera/.
 *
 * @return string Escaped HTML.
 */
function jobly_integration_render_jobs_list() {
	$jobs  = jobly_integration_open_jobs();
	$types = jobly_integration_employment_types();
	$css   = '.jobly-jobs{list-style:none;margin:0;padding:0;display:grid;gap:.75rem}'
		. '.jobly-jobs li{border:1px solid #e2e8f0;border-radius:10px;padding:.9rem 1.1rem}'
		. '.jobly-jobs a{font-weight:600;text-decoration:none}'
		. '.jobly-jobs__meta{display:block;color:#64748b;font-size:.875rem;margin-top:.15rem}'
		. '.jobly-jobs__demo{font-size:.75rem;color:#92400e;background:#fef3c7;border-radius:999px;padding:.1rem .6rem;display:inline-block;margin-bottom:.6rem}';

	ob_start();
	echo '<div class="jobly-jobs-wrap"><style>' . esc_html( $css ) . '</style>';
	if ( jobly_integration_is_demo() ) {
		echo '<span class="jobly-jobs__demo">' . esc_html__( 'Demo · izmišljeni podatki', 'jobly-integration' ) . '</span>';
	}
	if ( ! $jobs ) {
		echo '<p>' . esc_html__( 'Trenutno ni odprtih delovnih mest.', 'jobly-integration' ) . '</p></div>';
		return (string) ob_get_clean();
	}
	echo '<ul class="jobly-jobs">';
	foreach ( $jobs as $job ) {
		$meta = array_filter(
			array(
				$job['location'] ?? '',
				$types[ $job['employmentType'] ?? '' ] ?? '',
				jobly_integration_format_date( $job['publishedAt'] ?? null ),
			),
			static function ( $v ) {
				return '' !== $v && '—' !== $v;
			}
		);
		echo '<li><a href="' . esc_url( jobly_integration_job_url( (string) $job['slug'] ) ) . '">' . esc_html( (string) $job['title'] ) . '</a><span class="jobly-jobs__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span></li>';
	}
	echo '</ul></div>';
	return (string) ob_get_clean();
}
add_shortcode( 'jobly_jobs', 'jobly_integration_render_jobs_list' );

/**
 * Rewrite rules for {base}/ and {base}/{slug}/.
 */
function jobly_integration_register_rewrites() {
	$base = preg_quote( jobly_integration_settings()['base_path'], '#' );
	add_rewrite_rule( '^' . $base . '/?$', 'index.php?jobly_index=1', 'top' );
	add_rewrite_rule( '^' . $base . '/([^/]+)/?$', 'index.php?jobly_job=$matches[1]', 'top' );
}
add_action( 'init', 'jobly_integration_register_rewrites' );

/**
 * Flush once after the base path changed (flag set by Nastavitve).
 */
function jobly_integration_maybe_flush() {
	if ( get_option( 'jobly_integration_flush' ) ) {
		delete_option( 'jobly_integration_flush' );
		flush_rewrite_rules();
	}
}
add_action( 'init', 'jobly_integration_maybe_flush', 99 );

/**
 * Register our query vars.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function jobly_integration_query_vars( $vars ) {
	$vars[] = 'jobly_index';
	$vars[] = 'jobly_job';
	return $vars;
}
add_filter( 'query_vars', 'jobly_integration_query_vars' );

/**
 * The job (row from the API) of the current virtual page, or null.
 *
 * @return array|null
 */
function jobly_integration_current_job() {
	static $cache = array();
	$slug         = sanitize_title( (string) get_query_var( 'jobly_job' ) );
	if ( '' === $slug ) {
		return null;
	}
	if ( ! array_key_exists( $slug, $cache ) ) {
		$cache[ $slug ] = null;
		foreach ( jobly_integration_open_jobs() as $job ) {
			if ( ( $job['slug'] ?? '' ) === $slug ) {
				$cache[ $slug ] = $job;
			}
		}
	}
	return $cache[ $slug ];
}

/**
 * Turn the main query into a virtual page (theme template, our content).
 *
 * @param WP_Post[]|null $posts Posts found.
 * @param WP_Query       $query The query.
 * @return WP_Post[]|null
 */
function jobly_integration_virtual_page( $posts, $query ) {
	if ( ! $query->is_main_query() ) {
		return $posts;
	}
	$is_index = (bool) $query->get( 'jobly_index' );
	$is_job   = '' !== (string) $query->get( 'jobly_job' );
	if ( ! $is_index && ! $is_job ) {
		return $posts;
	}

	if ( $is_job ) {
		$job = jobly_integration_current_job();
		if ( ! $job ) {
			$query->set_404();
			status_header( 404 );
			nocache_headers();
			return array();
		}
		$title   = (string) $job['title'];
		$content = jobly_integration_is_demo()
			? jobly_integration_demo( jobly_integration_settings()['accent'], (string) $job['slug'] )
			: jobly_integration_embed_html( (string) $job['slug'], '', array( 'show' => 'full' ) );
	} else {
		$title   = __( 'Kariera', 'jobly-integration' );
		$content = jobly_integration_render_jobs_list();
	}

	$post = new WP_Post(
		(object) array(
			'ID'             => 0,
			'post_title'     => $title,
			'post_name'      => $is_job ? (string) $job['slug'] : jobly_integration_settings()['base_path'],
			'post_content'   => $content,
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_date'      => current_time( 'mysql' ),
			'post_date_gmt'  => current_time( 'mysql', true ),
			'filter'         => 'raw',
		)
	);

	$query->is_page     = true;
	$query->is_singular = true;
	$query->is_home     = false;
	$query->is_archive  = false;
	$query->is_404      = false;
	status_header( 200 );
	return array( $post );
}
add_filter( 'the_posts', 'jobly_integration_virtual_page', 10, 2 );

/**
 * Keep wpautop from mangling the iframe/list markup of the virtual page.
 */
function jobly_integration_is_virtual() {
	return (bool) get_query_var( 'jobly_index' ) || '' !== (string) get_query_var( 'jobly_job' );
}

/**
 * <title> of the virtual pages.
 *
 * @param string $title Default title.
 * @return string
 */
function jobly_integration_document_title( $title ) {
	if ( ! jobly_integration_is_virtual() ) {
		return $title;
	}
	$job  = jobly_integration_current_job();
	$name = $job ? (string) $job['title'] : __( 'Kariera', 'jobly-integration' );
	return $name . ' – ' . get_bloginfo( 'name' );
}
add_filter( 'pre_get_document_title', 'jobly_integration_document_title' );

/**
 * Canonical link: the job's page on Jobly (real data only), else our own URL.
 */
function jobly_integration_canonical() {
	if ( ! jobly_integration_is_virtual() ) {
		return;
	}
	remove_action( 'wp_head', 'rel_canonical' );
	$job = jobly_integration_current_job();
	if ( $job && ! jobly_integration_is_demo() ) {
		$url = untrailingslashit( jobly_integration_settings()['base_url'] ) . '/jobs/' . rawurlencode( (string) $job['slug'] );
	} else {
		$url = $job ? jobly_integration_job_url( (string) $job['slug'] ) : jobly_integration_careers_url();
	}
	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	if ( jobly_integration_is_demo() ) {
		echo '<meta name="robots" content="noindex">' . "\n";
	}
}
add_action( 'wp_head', 'jobly_integration_canonical', 1 );
