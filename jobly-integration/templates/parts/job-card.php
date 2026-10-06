<?php
/**
 * One job card.
 *
 * Override in your theme: yourtheme/jobly/parts/job-card.php
 *
 * @var array $args job, list.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

$jobly_integration_job  = $args['job'];
$jobly_integration_meta = jobly_integration_job_meta( $jobly_integration_job );
$jobly_integration_when = jobly_integration_relative_date( $jobly_integration_job['publishedAt'] ?? null );
?>
<article class="jobly-card<?php echo ! empty( $jobly_integration_job['featured'] ) ? ' jobly-card--featured' : ''; ?>">
	<div class="jobly-card__body">
		<p class="jobly-card__company"><?php echo esc_html( jobly_integration_company_name() ); ?><?php echo ! empty( $jobly_integration_job['featured'] ) ? ' <span class="jobly-badge">' . esc_html__( 'Izpostavljeno', 'jobly-integration' ) . '</span>' : ''; ?></p>
		<h3 class="jobly-card__title"><a href="<?php echo esc_url( jobly_integration_job_url( (string) $jobly_integration_job['slug'] ) ); ?>"><?php echo esc_html( (string) $jobly_integration_job['title'] ); ?></a></h3>
		<?php if ( $jobly_integration_meta ) : ?>
			<ul class="jobly-meta">
				<?php foreach ( $jobly_integration_meta as $jobly_integration_item ) : ?>
					<li class="<?php echo esc_attr( $jobly_integration_item['class'] ); ?>"><?php jobly_integration_icon( $jobly_integration_item['icon'], '', 16 ); ?><span><?php echo esc_html( $jobly_integration_item['text'] ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<div class="jobly-card__foot">
		<?php if ( '' !== $jobly_integration_when && ! empty( $jobly_integration_job['publishedAt'] ) ) : ?>
			<time datetime="<?php echo esc_attr( gmdate( 'c', (int) strtotime( (string) $jobly_integration_job['publishedAt'] ) ) ); ?>"><?php echo esc_html( $jobly_integration_when ); ?></time>
		<?php endif; ?>
		<span class="jobly-card__cta" aria-hidden="true"><?php esc_html_e( 'Poglej oglas', 'jobly-integration' ); ?> <?php jobly_integration_icon( 'arrow-right', '', 16 ); ?></span>
	</div>
</article>
