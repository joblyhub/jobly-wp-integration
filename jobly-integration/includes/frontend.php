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
			'url'    => '',
		)
	);
	$base = untrailingslashit( $s['base_url'] );
	$url  = '' !== $job
		? $base . '/embed/jobs/' . rawurlencode( $job )
		: $base . '/embed/companies/' . rawurlencode( $company );
	// Prefer the embed address Jobly gives us, but only when it is on the Jobly host; else the one built here.
	$given = jobly_integration_trusted_url( $opts['url'] );
	if ( '' !== $given ) {
		$url = $given;
	}

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
		'<iframe class="jobly-embed" src="%s" width="100%%" height="%d" style="border:0;width:100%%" loading="lazy" sandbox="allow-forms allow-scripts allow-same-origin allow-popups" referrerpolicy="strict-origin-when-cross-origin" title="%s"></iframe>',
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

	if ( '' === $job && $company === $settings['company'] && $settings['careers_embed'] ) {
		$atts['url'] = $settings['careers_embed'];
	}
	return jobly_integration_embed_html( $job, $company, $atts );
}
add_shortcode( 'jobly', 'jobly_integration_shortcode' );

/**
 * A real WordPress page chosen as the careers page (setup wizard / Prikaz), or 0.
 *
 * @return int
 */
function jobly_integration_careers_page_id() {
	$id = (int) jobly_integration_settings()['careers_page'];
	return ( $id > 0 && 'publish' === get_post_status( $id ) ) ? $id : 0;
}

/**
 * Path the job pages live under: the chosen page's path, else the base setting.
 *
 * @return string
 */
function jobly_integration_base_path() {
	$id = jobly_integration_careers_page_id();
	if ( $id ) {
		$uri = get_page_uri( $id );
		if ( $uri ) {
			return $uri;
		}
	}
	return jobly_integration_settings()['base_path'];
}

/**
 * Public URL of the careers index.
 *
 * @return string
 */
function jobly_integration_careers_url() {
	$id = jobly_integration_careers_page_id();
	return $id ? (string) get_permalink( $id ) : home_url( '/' . jobly_integration_settings()['base_path'] . '/' );
}

/**
 * Public URL of one job's subpage.
 *
 * @param string $slug Job slug.
 * @return string
 */
function jobly_integration_job_url( $slug ) {
	return home_url( '/' . jobly_integration_base_path() . '/' . rawurlencode( $slug ) . '/' );
}

/**
 * [jobly_jobs]                         seznam odprtih mest (strani, če ni število)
 * [jobly_jobs number="6"]              prvih 6, brez strani
 * [jobly_jobs filters="1" layout="grid"] z iskanjem in filtri, kartice v mreži
 *
 * @param array|string $atts Shortcode attributes.
 * @return string Escaped HTML.
 */
function jobly_integration_shortcode_jobs( $atts ) {
	$atts   = shortcode_atts(
		array(
			'number'  => '0',
			'filters' => '0',
			'layout'  => '',
		),
		$atts,
		'jobly_jobs'
	);
	$number = absint( $atts['number'] );
	$args   = array(
		'number'   => $number,
		'filters'  => in_array( strtolower( (string) $atts['filters'] ), array( '1', 'true', 'yes', 'da' ), true ),
		'paginate' => 0 === $number,
	);
	if ( in_array( $atts['layout'], array( 'list', 'grid' ), true ) ) {
		$args['layout'] = $atts['layout'];
	}
	// On the chosen careers page the filters post back to the page itself.
	$id = jobly_integration_careers_page_id();
	if ( $id && is_page( $id ) ) {
		$args['action'] = (string) get_permalink( $id );
	}
	return jobly_integration_render_list( $args );
}
add_shortcode( 'jobly_jobs', 'jobly_integration_shortcode_jobs' );

/**
 * Rewrite rules for {base}/ and {base}/{slug}/.
 */
function jobly_integration_register_rewrites() {
	$base = preg_quote( jobly_integration_base_path(), '#' );
	if ( ! jobly_integration_careers_page_id() ) {
		add_rewrite_rule( '^' . $base . '/?$', 'index.php?jobly_index=1', 'top' );
	}
	add_rewrite_rule( '^' . $base . '/([^/]+)/?$', 'index.php?jobly_job=$matches[1]', 'top' );
	add_rewrite_rule( '^jobly-sitemap\\.xml$', 'index.php?jobly_sitemap=1', 'top' );
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
	$vars[] = 'jobly_sitemap';
	return $vars;
}
add_filter( 'query_vars', 'jobly_integration_query_vars' );

/**
 * Our query vars only count when the request matched one of our rewrite rules, so
 * /?jobly_job=x or /any-page/?jobly_index=1 cannot turn another URL into a careers page.
 *
 * @param WP $wp The request.
 */
function jobly_integration_guard_query_vars( $wp ) {
	$rule = (string) $wp->matched_rule;
	if ( 0 === strpos( $rule, '^' . preg_quote( jobly_integration_base_path(), '#' ) ) || '^jobly-sitemap\\.xml$' === $rule ) {
		return;
	}
	unset( $wp->query_vars['jobly_index'], $wp->query_vars['jobly_job'], $wp->query_vars['jobly_sitemap'] );
}
add_action( 'parse_request', 'jobly_integration_guard_query_vars' );

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
				$cache[ $slug ] = jobly_integration_job_detail( $job );
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
	$is_index    = (bool) $query->get( 'jobly_index' );
	$is_job      = '' !== (string) $query->get( 'jobly_job' );
	$unavailable = false;
	if ( ! $is_index && ! $is_job ) {
		return $posts;
	}

	if ( $is_job ) {
		$job = jobly_integration_current_job();
		if ( ! $job && jobly_integration_is_unavailable() ) {
			$unavailable = true;
		} elseif ( ! $job ) {
			$closed = jobly_integration_closed_job();
			if ( $closed ) {
				$action = jobly_integration_seo_settings()['closed_action'];
				if ( 'redirect' === $action ) {
					wp_safe_redirect( jobly_integration_careers_url(), 301 );
					exit;
				}
				if ( 'noindex' === $action ) {
					$job = $closed;
				}
			}
		}
		if ( ! $job && ! $unavailable ) {
			if ( ! empty( $closed ) ) {
				$query->set_404();
				status_header( 410 );
				nocache_headers();
				return array();
			}
			$query->set_404();
			status_header( 404 );
			nocache_headers();
			return array();
		}
		jobly_integration_enqueue_frontend();
		if ( $unavailable ) {
			$title   = __( 'Oglasi trenutno niso na voljo', 'jobly-integration' );
			$content = jobly_integration_render_template( 'parts/unavailable.php' );
		} else {
			$title   = (string) $job['title'];
			$content = jobly_integration_neutralise(
				jobly_integration_render_template(
					'single-job.php',
					array(
						'job'  => $job,
						'demo' => jobly_integration_is_demo(),
					)
				)
			);
		}
	} else {
		$title   = jobly_integration_index_title();
		$content = jobly_integration_render_landing();
	}

	// The HTML never enters post_content: the_content (blocks, shortcodes) would run it. A token is swapped back in last.
	$post = new WP_Post(
		(object) array(
			'ID'             => 0,
			'post_title'     => jobly_integration_neutralise( esc_html( $title ) ),
			'post_name'      => $is_job && ! $unavailable ? (string) $job['slug'] : jobly_integration_base_path(),
			'post_content'   => JOBLY_INTEGRATION_VIRTUAL_TOKEN,
			'post_excerpt'   => '',
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
	jobly_integration_virtual_html( $content, $unavailable || ( $is_index && jobly_integration_is_unavailable() ) );
	return array( $post );
}
add_filter( 'the_posts', 'jobly_integration_virtual_page', 10, 2 );

const JOBLY_INTEGRATION_VIRTUAL_TOKEN = '<!--jobly-integration-virtual-->';

/**
 * The virtual page being served: its HTML (read by the content filter) and whether Jobly was down.
 *
 * @param string|null $html        HTML to keep, or null to read.
 * @param bool        $unavailable Jobly could not be reached.
 * @return array{html: string, down: bool}
 */
function jobly_integration_virtual_html( $html = null, $unavailable = false ) {
	static $page = array(
		'html' => '',
		'down' => false,
	);
	if ( null !== $html ) {
		$page = array(
			'html' => $html,
			'down' => $unavailable,
		);
	}
	return $page;
}

/**
 * 503 + Retry-After while Jobly is down (core's 404 handling runs before this and would otherwise reset the status).
 */
function jobly_integration_virtual_status() {
	if ( jobly_integration_virtual_html()['down'] ) {
		status_header( 503 );
		header( 'Retry-After: 60' );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'jobly_integration_virtual_status', 1 );

/**
 * Last filter on the_content: put the page back for the virtual post only. Because it runs after
 * do_blocks and do_shortcode, nothing in our HTML (API data included) is ever parsed as a block or shortcode.
 *
 * @param string $content Post content.
 * @return string
 */
function jobly_integration_virtual_content( $content ) {
	return false === strpos( $content, JOBLY_INTEGRATION_VIRTUAL_TOKEN ) ? $content : str_replace( JOBLY_INTEGRATION_VIRTUAL_TOKEN, jobly_integration_virtual_html()['html'], $content );
}
add_filter( 'the_content', 'jobly_integration_virtual_content', PHP_INT_MAX );

/**
 * The request is one of our virtual pages (list or job).
 *
 * @return bool
 */
function jobly_integration_is_virtual() {
	return (bool) get_query_var( 'jobly_index' ) || '' !== (string) get_query_var( 'jobly_job' );
}

/**
 * Keep wpautop from mangling the markup of the virtual pages.
 */
function jobly_integration_no_autop() {
	if ( jobly_integration_is_virtual() ) {
		remove_filter( 'the_content', 'wpautop' );
	}
}
add_action( 'wp', 'jobly_integration_no_autop' );

/**
 * A job of this company that is no longer open (closed, draft…), for the slug in the URL.
 *
 * @return array|null
 */
function jobly_integration_closed_job() {
	$slug = sanitize_title( (string) get_query_var( 'jobly_job' ) );
	foreach ( jobly_integration_all_jobs()['items'] as $job ) {
		if ( ( $job['slug'] ?? '' ) === $slug && 'active' !== ( $job['status'] ?? '' ) ) {
			return jobly_integration_job_detail( $job );
		}
	}
	return null;
}
