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
	$url   = jobly_integration_job_url( (string) ( $job['slug'] ?? '' ) );
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
		'hiringOrganization' => array(
			'@type'  => 'Organization',
			'name'   => $org,
			'sameAs' => home_url( '/' ),
		),
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
 * Print the JSON-LD on a job page (never in demo mode, those pages are noindex).
 */
function jobly_integration_print_schema() {
	if ( ! jobly_integration_settings()['schema'] || jobly_integration_is_demo() || '' === (string) get_query_var( 'jobly_job' ) ) {
		return;
	}
	$job = jobly_integration_current_job();
	if ( ! $job ) {
		return;
	}
	$schema = jobly_integration_job_schema( $job );
	if ( $schema ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON, tags and ampersands hex-encoded.
	}
}
add_action( 'wp_head', 'jobly_integration_print_schema', 5 );

/**
 * Add the jobs to the WordPress core sitemap (wp-sitemap.xml).
 *
 * @param WP_Sitemaps $sitemaps Core sitemap registry.
 */
function jobly_integration_register_sitemap( $sitemaps ) {
	if ( ! jobly_integration_settings()['sitemap'] || jobly_integration_is_demo() ) {
		return;
	}
	require_once __DIR__ . '/class-jobly-integration-sitemap-provider.php';
	$sitemaps->registry->add_provider( 'jobly', new Jobly_Integration_Sitemap_Provider() );
}
add_action( 'wp_sitemaps_init', 'jobly_integration_register_sitemap' );
