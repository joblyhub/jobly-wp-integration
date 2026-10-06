<?php
/**
 * Server-side render of the "Jobly – prijavni obrazec" block.
 *
 * @var array $attributes Block attributes.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

jobly_integration_enqueue_frontend();
$jobly_integration_slug = sanitize_title( (string) ( $attributes['job'] ?? '' ) );
$jobly_integration_opts = array();
if ( ! empty( $attributes['height'] ) ) {
	$jobly_integration_opts['height'] = absint( $attributes['height'] );
}
if ( ! empty( $attributes['showAd'] ) ) {
	$jobly_integration_opts['show'] = 'full';
}

if ( jobly_integration_is_demo() ) {
	$jobly_integration_out = jobly_integration_demo( jobly_integration_accent(), $jobly_integration_slug );
} elseif ( '' !== $jobly_integration_slug ) {
	$jobly_integration_out = jobly_integration_embed_html( $jobly_integration_slug, '', $jobly_integration_opts );
} elseif ( '' !== jobly_integration_settings()['company'] ) {
	$jobly_integration_out = jobly_integration_embed_html( '', jobly_integration_settings()['company'], $jobly_integration_opts );
} else {
	$jobly_integration_out = current_user_can( 'manage_options' )
		? '<p class="jobly-notice">' . esc_html__( 'Jobly: izberi delovno mesto ali v nastavitvah vpiši podjetje.', 'jobly-integration' ) . '</p>'
		: '';
}
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'jobly-block jobly-careers' ) ) ); ?>><?php echo $jobly_integration_out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by the embed/demo helpers. ?></div>
