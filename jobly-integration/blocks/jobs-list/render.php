<?php
/**
 * Server-side render of the "Jobly – seznam delovnih mest" block.
 *
 * @var array $attributes Block attributes.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

$jobly_integration_html = jobly_integration_render_list(
	array(
		'number'  => max( 1, absint( $attributes['number'] ?? 6 ) ),
		'filters' => ! empty( $attributes['filters'] ),
		'layout'  => (string) ( $attributes['layout'] ?? 'list' ),
	)
);
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'jobly-block' ) ) ); ?>><?php echo $jobly_integration_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the templates. ?></div>
