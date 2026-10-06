<?php
/**
 * Remove everything the plugin stored.
 *
 * @package jobly-integration
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'jobly_integration' );
delete_option( 'jobly_integration_flush' );
delete_option( 'jobly_integration_landing' );
delete_transient( 'jobly_integration_jobs' );

foreach ( get_users( array( 'fields' => 'ID' ) ) as $jobly_integration_user_id ) {
	delete_transient( 'jobly_integration_form_' . $jobly_integration_user_id );
	delete_user_option( $jobly_integration_user_id, 'jobly_integration_per_page' );
}
flush_rewrite_rules();
