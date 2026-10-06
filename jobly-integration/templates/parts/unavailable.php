<?php
/**
 * Shown instead of the job list or a job page while Jobly cannot be reached.
 *
 * Override in your theme: yourtheme/jobly/parts/unavailable.php
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="jobly-careers jobly-unavailable" style="--jobly-accent:<?php echo esc_attr( jobly_integration_accent() ); ?>">
	<div class="jobly-empty">
		<h3><?php esc_html_e( 'Oglasi trenutno niso na voljo', 'jobly-integration' ); ?></h3>
		<p><?php esc_html_e( 'Poskusite znova čez nekaj minut.', 'jobly-integration' ); ?></p>
	</div>
</div>
