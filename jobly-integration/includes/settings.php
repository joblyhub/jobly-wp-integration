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
 * @return array{base_url: string, company: string, accent: string, height: int, demo: int, api_key: string, connected: int, base_path: string}
 */
function jobly_integration_settings() {
	$saved = get_option( JOBLY_INTEGRATION_OPTION, array() );

	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'base_url'  => 'https://jobly.si',
			'company'   => '',
			'accent'    => '',
			'height'    => 720,
			'demo'      => 1,
			'api_key'   => '',
			'connected' => 0,
			'base_path' => 'kariera',
		)
	);
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
