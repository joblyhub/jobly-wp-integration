<?php
/**
 * Gutenberg blocks (server-rendered, no build step).
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the editor script and both blocks.
 */
function jobly_integration_register_blocks() {
	wp_register_style( 'jobly-integration', plugins_url( 'assets/frontend.css', JOBLY_INTEGRATION_FILE ), array(), JOBLY_INTEGRATION_VERSION );
	wp_register_script(
		'jobly-integration-blocks',
		plugins_url( 'assets/blocks.js', JOBLY_INTEGRATION_FILE ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		JOBLY_INTEGRATION_VERSION,
		true
	);
	wp_set_script_translations( 'jobly-integration-blocks', 'jobly-integration' );

	register_block_type( __DIR__ . '/../blocks/jobs-list' );
	register_block_type( __DIR__ . '/../blocks/apply-form' );
}
add_action( 'init', 'jobly_integration_register_blocks' );

/**
 * Job choices for the apply-form block (slug + title only, no applicant data).
 */
function jobly_integration_block_editor_data() {
	$options = array(
		array(
			'value' => '',
			'label' => __( 'Karierna stran podjetja (vsa mesta)', 'jobly-integration' ),
		),
	);
	foreach ( jobly_integration_open_jobs() as $job ) {
		$options[] = array(
			'value' => (string) $job['slug'],
			'label' => (string) $job['title'],
		);
	}
	wp_add_inline_script(
		'jobly-integration-blocks',
		'window.joblyIntegration = ' . wp_json_encode(
			array(
				'jobs' => $options,
				'demo' => jobly_integration_is_demo(),
			)
		) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'jobly_integration_block_editor_data' );
