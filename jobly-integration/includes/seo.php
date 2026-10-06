<?php
/**
 * Google for Jobs: JobPosting JSON-LD and the jobs sitemap.
 * Built only from fields the Jobly API really returns.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Employment type value -> schema.org enumeration.
 *
 * @return array<string,string>
 */
function jobly_integration_schema_employment_map() {
	return array(
		'full_time'  => 'FULL_TIME',
		'part_time'  => 'PART_TIME',
		'contract'   => 'CONTRACTOR',
		'internship' => 'INTERN',
		'student'    => 'OTHER',
	);
}

/**
 * JobPosting structured data for a job, or array() when the integration is off.
 *
 * @param array $job Job from the API.
 * @return array
 */
function jobly_integration_job_schema( array $job ) {
	$types = jobly_integration_schema_employment_map();
	$title = (string) ( $job['title'] ?? '' );
	$url   = jobly_integration_job_canonical( $job );
	$org   = jobly_integration_company_name();

	$schema = array(
		'@context'           => 'https://schema.org',
		'@type'              => 'JobPosting',
		'title'              => $title,
		'url'                => $url,
		'identifier'         => array(
			'@type' => 'PropertyValue',
			'name'  => $org,
			'value' => (string) ( $job['id'] ?? '' ),
		),
		'hiringOrganization' => jobly_integration_schema_organization( $org ),
		'directApply'        => true,
	);

	// Google requires a description. The API list has none, so use the real
	// description when it appears, else a plain statement of the fields we have.
	if ( ! empty( $job['description'] ) && is_string( $job['description'] ) ) {
		$schema['description'] = jobly_integration_rich_text( $job['description'] );
	} else {
		$bits                  = array_map(
			static function ( $item ) {
				return $item['text'];
			},
			jobly_integration_job_meta( $job )
		);
		$schema['description'] = '<p>' . esc_html( trim( $title . ' · ' . $org . ( $bits ? ' · ' . implode( ' · ', $bits ) : '' ) ) ) . '</p>';
	}

	if ( ! empty( $job['publishedAt'] ) && strtotime( (string) $job['publishedAt'] ) ) {
		$schema['datePosted'] = gmdate( 'c', (int) strtotime( (string) $job['publishedAt'] ) );
	}
	foreach ( array( 'validThrough', 'expiresAt', 'closesAt' ) as $key ) {
		if ( ! empty( $job[ $key ] ) && strtotime( (string) $job[ $key ] ) ) {
			$schema['validThrough'] = gmdate( 'c', (int) strtotime( (string) $job[ $key ] ) );
			break;
		}
	}
	if ( isset( $types[ $job['employmentType'] ?? '' ] ) ) {
		$schema['employmentType'] = $types[ $job['employmentType'] ];
	}

	$country = ! empty( $job['country'] ) ? (string) $job['country'] : '';
	if ( ! empty( $job['location'] ) ) {
		$address = array(
			'@type'           => 'PostalAddress',
			'addressLocality' => (string) $job['location'],
		);
		if ( '' !== $country ) {
			$address['addressCountry'] = $country;
		}
		$schema['jobLocation'] = array(
			'@type'   => 'Place',
			'address' => $address,
		);
	}
	if ( jobly_integration_job_is_remote( $job ) ) {
		$schema['jobLocationType'] = 'TELECOMMUTE';
		if ( '' !== $country ) {
			$schema['applicantLocationRequirements'] = array(
				'@type' => 'Country',
				'name'  => $country,
			);
		}
	}

	if ( isset( $job['salaryMin'], $job['salaryMax'] ) ) {
		$schema['baseSalary'] = array(
			'@type'    => 'MonetaryAmount',
			'currency' => 'EUR',
			'value'    => array(
				'@type'    => 'QuantitativeValue',
				'minValue' => (float) $job['salaryMin'],
				'maxValue' => (float) $job['salaryMax'],
				'unitText' => 'MONTH',
			),
		);
	}

	return (array) apply_filters( 'jobly_integration_job_schema', $schema, $job );
}

/**
 * Add the jobs to the WordPress core sitemap (wp-sitemap.xml).
 *
 * @param WP_Sitemaps $sitemaps Core sitemap registry.
 */
function jobly_integration_register_sitemap( $sitemaps ) {
	if ( ! jobly_integration_settings()['sitemap'] || jobly_integration_is_demo() || '' !== jobly_integration_seo_plugin() ) {
		return;
	}
	require_once __DIR__ . '/class-jobly-integration-sitemap-provider.php';
	$sitemaps->registry->add_provider( 'jobly', new Jobly_Integration_Sitemap_Provider() );
}
add_action( 'wp_sitemaps_init', 'jobly_integration_register_sitemap' );

/**
 * Build hiringOrganization from the company profile: name, sameAs (website, profile), logo.
 *
 * @param string $name Company name.
 * @return array
 */
function jobly_integration_schema_organization( $name ) {
	$org     = array(
		'@type' => 'Organization',
		'name'  => $name,
	);
	$company = jobly_integration_company();
	$same    = array_values( array_filter( array( (string) ( $company['website'] ?? '' ), (string) ( $company['profileUrl'] ?? '' ) ) ) );
	if ( $same ) {
		$org['sameAs'] = 1 === count( $same ) ? $same[0] : $same;
	} elseif ( ! jobly_integration_is_demo() ) {
		$org['sameAs'] = home_url( '/' );
	}
	$logo = jobly_integration_is_demo() ? '' : (string) jobly_integration_settings()['company_logo'];
	if ( '' !== $logo ) {
		$org['logo'] = $logo;
	}
	return $org;
}

/**
 * Our own jobs sitemap, for sites where an SEO plugin owns the sitemaps (core provider is off then).
 */
function jobly_integration_sitemap_xml() {
	$urls = array( jobly_integration_careers_url() => '' );
	foreach ( jobly_integration_open_jobs() as $job ) {
		$urls[ jobly_integration_job_url( (string) $job['slug'] ) ] = ! empty( $job['publishedAt'] ) && strtotime( (string) $job['publishedAt'] ) ? gmdate( 'c', (int) strtotime( (string) $job['publishedAt'] ) ) : '';
	}
	$xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
	foreach ( $urls as $loc => $last ) {
		$xml .= '<url><loc>' . esc_url( $loc ) . '</loc>' . ( '' !== $last ? '<lastmod>' . esc_html( $last ) . '</lastmod>' : '' ) . '</url>';
	}
	return $xml . '</urlset>';
}

/**
 * Serve /jobly-sitemap.xml.
 */
function jobly_integration_serve_sitemap() {
	if ( ! get_query_var( 'jobly_sitemap' ) ) {
		return;
	}
	$seo = jobly_integration_settings();
	if ( ! $seo['sitemap'] || jobly_integration_is_demo() ) {
		status_header( 404 );
		return;
	}
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo jobly_integration_sitemap_xml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built.
	exit;
}
add_action( 'template_redirect', 'jobly_integration_serve_sitemap', 0 );

/**
 * URL of our jobs sitemap.
 *
 * @return string
 */
function jobly_integration_sitemap_url() {
	return home_url( '/jobly-sitemap.xml' );
}

/**
 * Hand the jobs sitemap to the SEO plugin in use: Yoast and Rank Math list it in their index,
 * the others get a Sitemap line in robots.txt.
 */
function jobly_integration_sitemap_for_plugins() {
	$plugin = jobly_integration_seo_plugin();
	if ( '' === $plugin || ! jobly_integration_settings()['sitemap'] || jobly_integration_is_demo() ) {
		return;
	}
	$entry = static function ( $xml ) {
		return $xml . '<sitemap><loc>' . esc_url( jobly_integration_sitemap_url() ) . '</loc></sitemap>';
	};
	if ( 'yoast' === $plugin ) {
		add_filter( 'wpseo_sitemap_index', $entry );
	} elseif ( 'rankmath' === $plugin ) {
		add_filter( 'rank_math/sitemap/index', $entry );
	} else {
		add_filter(
			'robots_txt',
			static function ( $output ) {
				return $output . "\nSitemap: " . jobly_integration_sitemap_url() . "\n";
			}
		);
	}
}
add_action( 'init', 'jobly_integration_sitemap_for_plugins', 20 );
