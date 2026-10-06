<?php
/**
 * Saved settings.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saved settings merged over defaults.
 *
 * @return array<string,mixed> Keys: base_url, company, company_name, accent, layout, per_page, height, demo, api_key, connected, base_path, careers_page, careers_set, schema, sitemap, wizard_done.
 */
function jobly_integration_settings() {
	$saved = get_option( JOBLY_INTEGRATION_OPTION, array() );

	$settings             = wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'base_url'      => 'https://jobly.si',
			'company'       => '',
			'accent'        => '',
			'height'        => 720,
			'demo'          => 1,
			'api_key'       => '',
			'connected'     => 0,
			'base_path'     => 'kariera',
			'company_name'  => '',
			'layout'        => 'list',
			'per_page'      => 10,
			'careers_page'  => 0,
			'careers_set'   => 0,
			'schema'        => 1,
			'sitemap'       => 1,
			'wizard_done'   => 0,
			'company_api'   => 0,
			'company_logo'  => '',
			'company_url'   => '',
			'careers_embed' => '',
			'app_token'     => '',
		)
	);
	$settings['base_url'] = jobly_integration_safe_base( (string) $settings['base_url'] );
	foreach ( array( 'company_logo', 'company_url', 'careers_embed' ) as $key ) {
		$settings[ $key ] = jobly_integration_trusted_url( $settings[ $key ], $settings['base_url'] ); // Also for values stored by older versions.
	}
	return $settings;
}

/**
 * Merge changes into the stored option (autoload off, it holds the API key).
 *
 * @param array $changes Keys to overwrite.
 */
function jobly_integration_update_settings( array $changes ) {
	$new = array_merge( jobly_integration_settings(), $changes );
	if ( false === get_option( JOBLY_INTEGRATION_OPTION ) ) {
		add_option( JOBLY_INTEGRATION_OPTION, $new, '', false );
	} else {
		update_option( JOBLY_INTEGRATION_OPTION, $new, false );
	}
}

/**
 * Demo mode is on: nothing is read from, or sent to, Jobly.
 *
 * @return bool
 */
function jobly_integration_is_demo() {
	return (bool) jobly_integration_settings()['demo'];
}

/**
 * Last four characters of the stored key, for display ("jbl_…abcd").
 *
 * @return string
 */
function jobly_integration_masked_key() {
	$key = jobly_integration_settings()['api_key'];
	return '' === $key ? '' : 'jbl_…' . substr( $key, -4 );
}

/**
 * Accent colour in use: the setting or the Jobly blue.
 *
 * @return string Hex colour.
 */
function jobly_integration_accent() {
	$accent = sanitize_hex_color( (string) jobly_integration_settings()['accent'] );
	return $accent ? $accent : '#2563eb';
}

/**
 * Name shown as hiring organisation: the company from Jobly, else the saved name, else the site name.
 *
 * @return string
 */
function jobly_integration_company_name() {
	if ( jobly_integration_is_demo() ) {
		return 'Primer d.o.o.';
	}
	$s = jobly_integration_settings();
	if ( $s['company_api'] && '' !== trim( (string) $s['company_name'] ) ) {
		return trim( (string) $s['company_name'] );
	}
	$name = trim( (string) $s['company_name'] );
	return '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

/**
 * Copy the company profile from Jobly into the settings (name, slug, logo, links).
 *
 * @param array $company Company from GET /api/v1/company.
 * @return array Settings changes.
 */
function jobly_integration_company_changes( array $company ) {
	if ( empty( $company['slug'] ) ) {
		return array( 'company_api' => 0 );
	}
	return array(
		'company_api'   => 1,
		'company'       => sanitize_title( (string) $company['slug'] ),
		'company_name'  => jobly_integration_text( $company['name'] ?? '' ),
		'company_logo'  => jobly_integration_trusted_url( $company['logoUrl'] ?? '' ),
		'company_url'   => jobly_integration_trusted_url( $company['profileUrl'] ?? '' ),
		'careers_embed' => jobly_integration_trusted_url( $company['careersEmbedUrl'] ?? '' ),
	);
}

/**
 * Link where a Jobly user creates an API key (Jobly redirects to their company's Integracije).
 *
 * @return string
 */
function jobly_integration_key_url() {
	return untrailingslashit( jobly_integration_settings()['base_url'] ) . '/employer/integrations';
}

/**
 * App token of the integration (sent as the App-Token header): the JOBLY_APP_TOKEN constant wins over the saved setting.
 *
 * @return string
 */
function jobly_integration_app_token() {
	if ( defined( 'JOBLY_APP_TOKEN' ) && JOBLY_APP_TOKEN ) {
		return (string) JOBLY_APP_TOKEN;
	}
	return (string) jobly_integration_settings()['app_token'];
}

/**
 * Last four characters of the app token for display ("jba_…abcd").
 *
 * @return string
 */
function jobly_integration_masked_app_token() {
	if ( defined( 'JOBLY_APP_TOKEN' ) && JOBLY_APP_TOKEN ) {
		return ''; // The wp-config.php constant is network-level: never shown, not even masked, to site admins.
	}
	$token = jobly_integration_app_token();
	return '' === $token ? '' : 'jba_…' . substr( $token, -4 );
}
