<?php
/**
 * SEO for the careers pages: titles, descriptions, canonical, robots, Open Graph, Twitter,
 * structured data graph, hreflang; integrations with Yoast SEO, Rank Math, SEOPress and AIOSEO.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_SEO_OPTION      = 'jobly_integration_seo';
const JOBLY_INTEGRATION_SEO_JOBS_OPTION = 'jobly_integration_seo_jobs';

/**
 * SEO settings merged over the defaults.
 *
 * @return array
 */
function jobly_integration_seo_settings() {
	$saved = get_option( JOBLY_INTEGRATION_SEO_OPTION, array() );
	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'title_landing'    => '%company% – Kariera %sep% %site_name%',
			'desc_landing'     => 'Odprta delovna mesta pri podjetju %company%. Poglejte oglase in oddajte prijavo.',
			'title_job'        => '%job_title% – %company% %sep% %site_name%',
			'desc_job'         => '',
			'og'               => 1,
			'twitter'          => 1,
			'og_image'         => 0,
			'og_image_landing' => 0,
			'canonical'        => 'site',
			'closed_action'    => 'gone',
			'noindex_filtered' => 1,
			'noindex_all'      => 0,
			'breadcrumb'       => 1,
			'organization'     => 1,
			'itemlist'         => 1,
		)
	);
}

/**
 * Per-job overrides keyed by job slug: title, description, noindex.
 *
 * @return array<string,array{title: string, description: string, noindex: int}>
 */
function jobly_integration_seo_job_overrides() {
	$saved = get_option( JOBLY_INTEGRATION_SEO_JOBS_OPTION, array() );
	return is_array( $saved ) ? $saved : array();
}

/**
 * Which SEO plugin is active: yoast, rankmath, seopress, aioseo or ''.
 *
 * @return string
 */
function jobly_integration_seo_plugin() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'yoast';
	}
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		return 'rankmath';
	}
	if ( defined( 'SEOPRESS_VERSION' ) ) {
		return 'seopress';
	}
	if ( defined( 'AIOSEO_VERSION' ) ) {
		return 'aioseo';
	}
	return '';
}

/**
 * Template variables.
 *
 * @param array|null $job Job or null (landing).
 * @return array<string,string>
 */
function jobly_integration_seo_vars( $job ) {
	$types = jobly_integration_employment_types();
	return array(
		'%job_title%'       => $job ? (string) $job['title'] : '',
		'%company%'         => jobly_integration_company_name(),
		'%location%'        => $job ? (string) ( $job['location'] ?? '' ) : '',
		'%employment_type%' => $job ? (string) ( $types[ $job['employmentType'] ?? '' ] ?? '' ) : '',
		'%site_name%'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'%sep%'             => '–',
	);
}

/**
 * Fill a template; empty variables leave no stray separators.
 *
 * @param string $tpl  Template.
 * @param array  $vars Variables.
 * @return string
 */
function jobly_integration_seo_fill( $tpl, array $vars ) {
	$out = strtr( $tpl, $vars );
	$out = preg_replace( '/\s+/', ' ', $out );
	$out = preg_replace( '/\.\.(?!\.)/', '.', (string) $out );
	$out = preg_replace( '/(\s[–-]\s)+(?=\s[–-]\s)/u', '', (string) $out );
	return trim( (string) $out, " \t–-" );
}

/**
 * First 155 characters of the job description, cleaned.
 *
 * @param string $text Description.
 * @return string
 */
function jobly_integration_seo_excerpt( $text ) {
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
	if ( mb_strlen( $text ) <= 155 ) {
		return $text;
	}
	return rtrim( mb_substr( $text, 0, 154 ), ' ,.;:-' ) . '…';
}

/**
 * Canonical URL of a job: this site (default) or its page on Jobly.
 *
 * @param array $job Job.
 * @return string
 */
function jobly_integration_job_canonical( array $job ) {
	if ( 'jobly' === jobly_integration_seo_settings()['canonical'] && ! jobly_integration_is_demo() ) {
		return ! empty( $job['url'] ) ? (string) $job['url'] : untrailingslashit( jobly_integration_settings()['base_url'] ) . '/jobs/' . rawurlencode( (string) $job['slug'] );
	}
	return jobly_integration_job_url( (string) $job['slug'] );
}

/**
 * Image for Open Graph: chosen image, company logo, site icon.
 *
 * @param bool $landing Landing page (own image first).
 * @return string URL or ''.
 */
function jobly_integration_seo_image( $landing ) {
	$seo = jobly_integration_seo_settings();
	$ids = $landing ? array( $seo['og_image_landing'], $seo['og_image'] ) : array( $seo['og_image'] );
	foreach ( $ids as $id ) {
		$url = $id ? wp_get_attachment_image_url( (int) $id, 'large' ) : false;
		if ( $url ) {
			return (string) $url;
		}
	}
	$logo = jobly_integration_is_demo() ? '' : (string) jobly_integration_settings()['company_logo'];
	if ( '' !== $logo ) {
		return $logo;
	}
	return (string) get_site_icon_url( 512 );
}

/**
 * Everything the head needs for the current careers page, or null elsewhere.
 *
 * @return array|null type, title, description, canonical, robots (array), image, job, closed.
 */
function jobly_integration_seo_context() {
	static $ctx = false;
	if ( false !== $ctx ) {
		return $ctx;
	}
	$ctx = null;
	if ( ! jobly_integration_is_virtual() ) {
		return $ctx;
	}
	$seo = jobly_integration_seo_settings();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only: detect filtered list URLs.
	$filtered = isset( $_GET['iskanje'] ) || isset( $_GET['kraj'] ) || isset( $_GET['vrsta'] ) || isset( $_GET['daljava'] ) || isset( $_GET['stran'] );
	// phpcs:enable
	$robots = array();
	$job    = null;
	if ( '' !== (string) get_query_var( 'jobly_job' ) ) {
		$job = jobly_integration_current_job();
		if ( ! $job ) {
			$job = jobly_integration_closed_job();
		}
		if ( ! $job ) {
			return $ctx;
		}
		$over  = jobly_integration_seo_job_overrides()[ (string) $job['slug'] ] ?? array();
		$vars  = jobly_integration_seo_vars( $job );
		$title = ! empty( $over['title'] ) ? jobly_integration_seo_fill( $over['title'], $vars ) : jobly_integration_seo_fill( $seo['title_job'], $vars );
		$desc  = '';
		if ( ! empty( $over['description'] ) ) {
			$desc = jobly_integration_seo_fill( $over['description'], $vars );
		} elseif ( '' !== trim( $seo['desc_job'] ) ) {
			$desc = jobly_integration_seo_fill( $seo['desc_job'], $vars );
		} elseif ( ! empty( $job['description'] ) ) {
			$desc = jobly_integration_seo_excerpt( (string) $job['description'] );
		} else {
			$desc = jobly_integration_seo_fill( '%job_title% pri %company%. %location% %sep% %employment_type%', $vars );
		}
		if ( ! empty( $over['noindex'] ) || 'active' !== ( $job['status'] ?? 'active' ) ) {
			$robots['noindex'] = true;
		}
		$canonical = jobly_integration_job_canonical( $job );
		$type      = 'job';
	} else {
		$vars      = jobly_integration_seo_vars( null );
		$title     = jobly_integration_seo_fill( $seo['title_landing'], $vars );
		$desc      = jobly_integration_seo_fill( $seo['desc_landing'], $vars );
		$canonical = jobly_integration_careers_url();
		$type      = 'landing';
		if ( $filtered && $seo['noindex_filtered'] ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
	}
	if ( $seo['noindex_all'] || jobly_integration_is_demo() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	$ctx = array(
		'type'        => $type,
		'title'       => $title,
		'description' => $desc,
		'canonical'   => $canonical,
		'robots'      => $robots,
		'image'       => jobly_integration_seo_image( 'landing' === $type ),
		'job'         => $job,
	);
	return $ctx;
}

/**
 * Structured data pieces for the current page (no @context).
 *
 * @param array $ctx      Context.
 * @param bool  $external An SEO plugin builds the rest of the graph (skip Organization/Breadcrumb).
 * @return array[]
 */
function jobly_integration_seo_graph( array $ctx, $external ) {
	$seo    = jobly_integration_seo_settings();
	$s      = jobly_integration_settings();
	$pieces = array();
	if ( 'job' === $ctx['type'] ) {
		if ( $s['schema'] && ! jobly_integration_is_demo() && 'active' === ( $ctx['job']['status'] ?? 'active' ) ) {
			$piece = jobly_integration_job_schema( $ctx['job'] );
			unset( $piece['@context'] );
			if ( $piece ) {
				$pieces[] = $piece;
			}
		}
		if ( $seo['breadcrumb'] && ! $external ) {
			$pieces[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
						'item'     => home_url( '/' ),
					),
					array(
						'@type'    => 'ListItem',
						'position' => 2,
						'name'     => __( 'Kariera', 'jobly-integration' ),
						'item'     => jobly_integration_careers_url(),
					),
					array(
						'@type'    => 'ListItem',
						'position' => 3,
						'name'     => (string) $ctx['job']['title'],
					),
				),
			);
		}
	} else {
		$company = jobly_integration_company();
		if ( $seo['organization'] && ! $external && ! jobly_integration_is_demo() ) {
			$org = array(
				'@type' => 'Organization',
				'name'  => jobly_integration_company_name(),
				'url'   => ! empty( $company['website'] ) ? (string) $company['website'] : home_url( '/' ),
			);
			if ( $s['company_logo'] ) {
				$org['logo'] = $s['company_logo'];
			}
			if ( ! empty( $company['profileUrl'] ) ) {
				$org['sameAs'] = array( (string) $company['profileUrl'] );
			}
			$pieces[] = $org;
		}
		if ( $seo['itemlist'] && ! jobly_integration_is_demo() ) {
			$items = array();
			foreach ( array_values( jobly_integration_open_jobs() ) as $i => $job ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'url'      => jobly_integration_job_url( (string) $job['slug'] ),
					'name'     => (string) $job['title'],
				);
			}
			if ( $items ) {
				$pieces[] = array(
					'@type'           => 'ItemList',
					'itemListElement' => $items,
				);
			}
		}
	}
	return $pieces;
}

/**
 * Document title (no SEO plugin).
 *
 * @param string $title Default.
 * @return string
 */
function jobly_integration_seo_document_title( $title ) {
	$ctx = jobly_integration_seo_context();
	return $ctx ? $ctx['title'] : $title;
}

/**
 * Robots via the core wp_robots filter (no SEO plugin).
 *
 * @param array $robots Directives.
 * @return array
 */
function jobly_integration_seo_wp_robots( $robots ) {
	$ctx = jobly_integration_seo_context();
	if ( $ctx && $ctx['robots'] ) {
		unset( $robots['index'], $robots['follow'] );
		$robots = array_merge( $robots, $ctx['robots'] );
	}
	return $robots;
}

/**
 * Head output (no SEO plugin): description, canonical, Open Graph, Twitter, hreflang, JSON-LD.
 */
function jobly_integration_seo_head() {
	$ctx = jobly_integration_seo_context();
	if ( ! $ctx ) {
		return;
	}
	$seo = jobly_integration_seo_settings();
	remove_action( 'wp_head', 'rel_canonical' );
	echo '<meta name="description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( 'landing' === $ctx['type'] ? $ctx['canonical'] : $ctx['canonical'] ) . '">' . "\n";
	if ( $seo['og'] ) {
		$og = array(
			'og:locale'      => get_locale(),
			'og:type'        => 'job' === $ctx['type'] ? 'article' : 'website',
			'og:title'       => $ctx['title'],
			'og:description' => $ctx['description'],
			'og:url'         => $ctx['canonical'],
			'og:site_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		if ( '' !== $ctx['image'] ) {
			$og['og:image'] = $ctx['image'];
		}
		foreach ( $og as $prop => $content ) {
			echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $content ) . '">' . "\n";
		}
	}
	if ( $seo['twitter'] ) {
		echo '<meta name="twitter:card" content="' . ( '' !== $ctx['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $ctx['title'] ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
		if ( '' !== $ctx['image'] ) {
			echo '<meta name="twitter:image" content="' . esc_url( $ctx['image'] ) . '">' . "\n";
		}
	}
	jobly_integration_seo_hreflang( $ctx );
	jobly_integration_seo_print_graph( $ctx, false );
}

/**
 * Print the JSON-LD graph.
 *
 * @param array $ctx      Context.
 * @param bool  $external SEO plugin owns the rest of the graph.
 */
function jobly_integration_seo_print_graph( array $ctx, $external ) {
	$pieces = jobly_integration_seo_graph( $ctx, $external );
	if ( ! $pieces ) {
		return;
	}
	$data = array(
		'@context' => 'https://schema.org',
		'@graph'   => $pieces,
	);
	if ( 1 === count( $pieces ) ) {
		$data = array_merge( array( '@context' => 'https://schema.org' ), $pieces[0] );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON, tags and ampersands hex-encoded.
}

/**
 * Print hreflang, only on multilingual sites (Polylang, WPML): the page's own language plus x-default.
 *
 * @param array $ctx Context.
 */
function jobly_integration_seo_hreflang( array $ctx ) {
	if ( ! function_exists( 'pll_current_language' ) && ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return;
	}
	$lang = function_exists( 'pll_current_language' ) ? (string) pll_current_language() : substr( get_locale(), 0, 2 );
	if ( '' === $lang ) {
		return;
	}
	echo '<link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $ctx['canonical'] ) . '">' . "\n";
	echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $ctx['canonical'] ) . '">' . "\n";
}

/**
 * Wire it up: our own tags without an SEO plugin, their filters with one.
 */
function jobly_integration_seo_boot() {
	$plugin = jobly_integration_seo_plugin();
	if ( '' === $plugin ) {
		add_filter( 'pre_get_document_title', 'jobly_integration_seo_document_title', 20 );
		add_filter( 'wp_robots', 'jobly_integration_seo_wp_robots', 20 );
		add_action( 'wp_head', 'jobly_integration_seo_head', 1 );
		return;
	}
	// Robots through core still works next to every SEO plugin that honours wp_robots; they get their own filters below.
	$get  = static function ( $key ) {
		$ctx = jobly_integration_seo_context();
		return $ctx ? $ctx[ $key ] : null;
	};
	$when = static function ( $fallback, $key ) use ( $get ) {
		$v = $get( $key );
		return null === $v ? $fallback : $v;
	};
	if ( 'yoast' === $plugin ) {
		add_filter(
			'wpseo_title',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'title' );
			}
		);
		add_filter(
			'wpseo_metadesc',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'description' );
			}
		);
		add_filter(
			'wpseo_canonical',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'canonical' );
			}
		);
		add_filter(
			'wpseo_robots_array',
			static function ( $robots ) use ( $get ) {
				$r = $get( 'robots' );
				if ( $r ) {
					$robots['index']  = ! empty( $r['noindex'] ) ? 'noindex' : 'index';
					$robots['follow'] = ! empty( $r['nofollow'] ) ? 'nofollow' : 'follow';
				}
				return $robots;
			}
		);
		foreach ( array(
			'wpseo_opengraph_title'     => 'title',
			'wpseo_opengraph_desc'      => 'description',
			'wpseo_opengraph_url'       => 'canonical',
			'wpseo_twitter_title'       => 'title',
			'wpseo_twitter_description' => 'description',
		) as $hook => $key ) {
			add_filter(
				$hook,
				static function ( $v ) use ( $when, $key ) {
					return $when( $v, $key );
				}
			);
		}
		add_filter(
			'wpseo_schema_graph',
			static function ( $graph ) {
				$ctx = jobly_integration_seo_context();
				return $ctx ? array_merge( (array) $graph, jobly_integration_seo_graph( $ctx, true ) ) : $graph;
			}
		);
	} elseif ( 'rankmath' === $plugin ) {
		add_filter(
			'rank_math/frontend/title',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'title' );
			}
		);
		add_filter(
			'rank_math/frontend/description',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'description' );
			}
		);
		add_filter(
			'rank_math/frontend/canonical',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'canonical' );
			}
		);
		add_filter(
			'rank_math/frontend/robots',
			static function ( $robots ) use ( $get ) {
				$r = $get( 'robots' );
				if ( $r ) {
					$robots['index']  = ! empty( $r['noindex'] ) ? 'noindex' : 'index';
					$robots['follow'] = ! empty( $r['nofollow'] ) ? 'nofollow' : 'follow';
				}
				return $robots;
			}
		);
		foreach ( array(
			'rank_math/opengraph/facebook/og_title'       => 'title',
			'rank_math/opengraph/facebook/og_description' => 'description',
			'rank_math/opengraph/facebook/og_url'         => 'canonical',
			'rank_math/opengraph/twitter/twitter_title'   => 'title',
			'rank_math/opengraph/twitter/twitter_description' => 'description',
		) as $hook => $key ) {
			add_filter(
				$hook,
				static function ( $v ) use ( $when, $key ) {
					return $when( $v, $key );
				}
			);
		}
		add_filter(
			'rank_math/json_ld',
			static function ( $data ) {
				$ctx = jobly_integration_seo_context();
				if ( $ctx ) {
					foreach ( jobly_integration_seo_graph( $ctx, true ) as $i => $piece ) {
						$data[ 'jobly_' . $i ] = $piece;
					}
				}
				return $data;
			}
		);
	} elseif ( 'seopress' === $plugin ) {
		add_filter(
			'seopress_titles_title',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'title' );
			}
		);
		add_filter(
			'seopress_titles_desc',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'description' );
			}
		);
		add_filter(
			'seopress_titles_canonical',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'canonical' );
			}
		);
		add_filter(
			'seopress_social_og_title',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'title' );
			}
		);
		add_filter(
			'seopress_social_og_desc',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'description' );
			}
		);
		add_filter(
			'seopress_social_og_url',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'canonical' );
			}
		);
	} else { // AIOSEO.
		add_filter(
			'aioseo_title',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'title' );
			}
		);
		add_filter(
			'aioseo_description',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'description' );
			}
		);
		add_filter(
			'aioseo_canonical_url',
			static function ( $v ) use ( $when ) {
				return $when( $v, 'canonical' );
			}
		);
	}
	// SEO plugins that honour wp_robots (and the two without a dedicated filter) get robots from core.
	add_filter( 'wp_robots', 'jobly_integration_seo_wp_robots', 20 );
	// SEOPress / AIOSEO do not know our structured data: print it ourselves.
	if ( in_array( $plugin, array( 'seopress', 'aioseo' ), true ) ) {
		add_action(
			'wp_head',
			static function () {
				$ctx = jobly_integration_seo_context();
				if ( $ctx ) {
					jobly_integration_seo_print_graph( $ctx, true );
					jobly_integration_seo_hreflang( $ctx );
				}
			},
			5
		);
	}
}
add_action( 'wp', 'jobly_integration_seo_boot', 1 );
