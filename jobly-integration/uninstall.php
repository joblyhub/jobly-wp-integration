<?php
/**
 * Remove everything the plugin stored: options (settings, API key and App-Token, landing, SEO),
 * every transient (cache, form state, circuit breaker) and per-user screen options, on every
 * site of a network.
 *
 * @package jobly-integration
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Clean one site (the current blog).
 */
function jobly_integration_uninstall_site() {
	global $wpdb;

	// Everything the plugin keeps in wp_options starts with jobly_integration; transients with _transient_[timeout_]jobly_integration_.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall only: a prefix delete has no API and the cache is flushed right after.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'jobly_integration' ) . '%',
			$wpdb->esc_like( '_transient_jobly_integration_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_jobly_integration_' ) . '%'
		)
	);
	// Screen options: saved as user meta with and without the blog prefix.
	foreach ( array( $wpdb->get_blog_prefix() . 'jobly_integration_per_page', 'jobly_integration_per_page' ) as $meta_key ) {
		delete_metadata( 'user', 0, $meta_key, '', true );
	}
	// phpcs:enable
	wp_cache_flush();
	flush_rewrite_rules();
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $jobly_integration_site_id ) {
		switch_to_blog( (int) $jobly_integration_site_id );
		jobly_integration_uninstall_site();
		restore_current_blog();
	}
} else {
	jobly_integration_uninstall_site();
}
