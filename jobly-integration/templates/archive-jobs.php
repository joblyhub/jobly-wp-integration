<?php
/**
 * Job list: count, search + filters, cards, pagination.
 *
 * Override in your theme: yourtheme/jobly/archive-jobs.php
 *
 * @var array $args query, list, request, facets, demo.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

$jobly_integration_query   = $args['query'];
$jobly_integration_list    = $args['list'];
$jobly_integration_request = $args['request'];
$jobly_integration_facets  = $args['facets'];
$jobly_integration_active  = ! empty( $jobly_integration_request['search'] ) || ! empty( $jobly_integration_request['location'] ) || ! empty( $jobly_integration_request['type'] ) || ! empty( $jobly_integration_request['remote'] );
$jobly_integration_has_any = count( $jobly_integration_query['all'] ) > 0;

/**
 * Fires before the job list.
 *
 * @param array $args Template data.
 */
do_action( 'jobly_integration_before_jobs_list', $args );
?>
<div class="jobly-careers jobly-careers--<?php echo esc_attr( $jobly_integration_list['layout'] ); ?>" style="--jobly-accent:<?php echo esc_attr( jobly_integration_accent() ); ?>">
	<?php if ( $args['demo'] ) : ?>
		<p class="jobly-demo-pill"><?php esc_html_e( 'Demo · izmišljeni podatki', 'jobly-integration' ); ?></p>
	<?php endif; ?>

	<?php if ( $jobly_integration_list['count'] && $jobly_integration_has_any ) : ?>
		<p class="jobly-count" role="status"><?php echo esc_html( jobly_integration_count_label( $jobly_integration_query['total'] ) ); ?><?php echo $jobly_integration_active ? ' · ' . esc_html__( 'filtrirano', 'jobly-integration' ) : ''; ?></p>
	<?php endif; ?>

	<?php if ( $jobly_integration_list['filters'] && $jobly_integration_has_any ) : ?>
		<?php jobly_integration_get_template( 'parts/filters.php', $args ); ?>
	<?php endif; ?>

	<?php if ( $jobly_integration_query['jobs'] ) : ?>
		<ul class="jobly-jobs jobly-jobs--<?php echo esc_attr( $jobly_integration_list['layout'] ); ?>">
			<?php foreach ( $jobly_integration_query['jobs'] as $jobly_integration_job ) : ?>
				<li class="jobly-jobs__item"><?php echo jobly_integration_render_job_card( $jobly_integration_job, $jobly_integration_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the card template. ?></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $jobly_integration_list['paginate'] && $jobly_integration_query['pages'] > 1 ) : ?>
			<?php jobly_integration_get_template( 'parts/pagination.php', $args ); ?>
		<?php elseif ( ! $jobly_integration_list['paginate'] && $jobly_integration_query['total'] > count( $jobly_integration_query['jobs'] ) ) : ?>
			<p class="jobly-more"><a class="jobly-btn jobly-btn--ghost" href="<?php echo esc_url( jobly_integration_careers_url() ); ?>"><?php esc_html_e( 'Vsa delovna mesta', 'jobly-integration' ); ?> <?php jobly_integration_icon( 'arrow-right', '', 16 ); ?></a></p>
		<?php endif; ?>
	<?php else : ?>
		<?php jobly_integration_get_template( 'parts/empty.php', array_merge( $args, array( 'filtered' => $jobly_integration_has_any ) ) ); ?>
	<?php endif; ?>
</div>
<?php
/**
 * Fires after the job list.
 *
 * @param array $args Template data.
 */
do_action( 'jobly_integration_after_jobs_list', $args );
