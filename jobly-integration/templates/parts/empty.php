<?php
/**
 * Empty state of the job list.
 *
 * Override in your theme: yourtheme/jobly/parts/empty.php
 *
 * @var array $args Template data of archive-jobs.php plus 'filtered' (bool).
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="jobly-empty">
	<svg class="jobly-empty__art" xmlns="http://www.w3.org/2000/svg" width="120" height="96" viewBox="0 0 120 96" fill="none" aria-hidden="true" focusable="false"><rect x="14" y="30" width="92" height="56" rx="8" fill="currentColor" opacity=".06"/><rect x="14" y="30" width="92" height="56" rx="8" stroke="currentColor" stroke-width="2" opacity=".35"/><path d="M44 30v-6a6 6 0 0 1 6-6h20a6 6 0 0 1 6 6v6" stroke="currentColor" stroke-width="2" opacity=".35"/><path d="M14 54h92" stroke="currentColor" stroke-width="2" opacity=".35"/><circle cx="60" cy="54" r="7" fill="var(--jobly-accent)"/><path d="M92 14l2 5l5 2l-5 2l-2 5l-2-5l-5-2l5-2z" fill="var(--jobly-accent)" opacity=".7"/></svg>
	<?php if ( ! empty( $args['filtered'] ) ) : ?>
		<h3><?php esc_html_e( 'Ni zadetkov', 'jobly-integration' ); ?></h3>
		<p><?php esc_html_e( 'Poskusite z drugimi filtri ali iskalnim nizom.', 'jobly-integration' ); ?></p>
		<p><a class="jobly-btn jobly-btn--ghost" href="<?php echo esc_url( $args['list']['action'] ); ?>"><?php esc_html_e( 'Počisti filtre', 'jobly-integration' ); ?></a></p>
	<?php else : ?>
		<h3><?php esc_html_e( 'Trenutno ni odprtih delovnih mest', 'jobly-integration' ); ?></h3>
		<p><?php esc_html_e( 'Ko bo objavljeno novo delovno mesto, se bo prikazalo tukaj. Preverite znova kmalu.', 'jobly-integration' ); ?></p>
	<?php endif; ?>
</div>
