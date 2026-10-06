<?php
/**
 * One job: breadcrumb, chips, content + sticky summary, the Jobly embed form.
 *
 * Override in your theme: yourtheme/jobly/single-job.php
 *
 * @var array $args job, demo.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

$jobly_integration_job  = $args['job'];
$jobly_integration_meta = jobly_integration_job_meta( $jobly_integration_job );
$jobly_integration_when = jobly_integration_relative_date( $jobly_integration_job['publishedAt'] ?? null );
$jobly_integration_slug = (string) $jobly_integration_job['slug'];

/**
 * Fires before the job page content.
 *
 * @param array $job Job from the API.
 */
do_action( 'jobly_integration_before_single_job', $jobly_integration_job );
?>
<div class="jobly-careers jobly-job" style="--jobly-accent:<?php echo esc_attr( jobly_integration_accent() ); ?>">
	<nav class="jobly-breadcrumb" aria-label="<?php esc_attr_e( 'Drobtinice', 'jobly-integration' ); ?>">
		<a href="<?php echo esc_url( jobly_integration_careers_url() ); ?>"><?php jobly_integration_icon( 'arrow-left', '', 16 ); ?> <?php esc_html_e( 'Vsa delovna mesta', 'jobly-integration' ); ?></a>
	</nav>

	<ul class="jobly-meta jobly-meta--chips">
		<li><?php jobly_integration_icon( 'building', '', 16 ); ?><span><?php echo esc_html( jobly_integration_company_name() ); ?></span></li>
		<?php foreach ( $jobly_integration_meta as $jobly_integration_item ) : ?>
			<li class="<?php echo esc_attr( $jobly_integration_item['class'] ); ?>"><?php jobly_integration_icon( $jobly_integration_item['icon'], '', 16 ); ?><span><?php echo esc_html( $jobly_integration_item['text'] ); ?></span></li>
		<?php endforeach; ?>
		<?php if ( '' !== $jobly_integration_when ) : ?>
			<li><?php jobly_integration_icon( 'calendar', '', 16 ); ?><span><?php echo esc_html( $jobly_integration_when ); ?></span></li>
		<?php endif; ?>
	</ul>

	<div class="jobly-job__grid">
		<div class="jobly-job__main">
			<?php if ( ! empty( $jobly_integration_job['description'] ) && is_string( $jobly_integration_job['description'] ) ) : ?>
				<div class="jobly-job__text"><?php echo jobly_integration_rich_text( $jobly_integration_job['description'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered with wp_kses_post(). ?></div>
			<?php endif; ?>
			<?php
			foreach ( array(
				'responsibilities' => __( 'Naloge', 'jobly-integration' ),
				'requirements'     => __( 'Zahteve', 'jobly-integration' ),
				'benefits'         => __( 'Ugodnosti', 'jobly-integration' ),
			) as $jobly_integration_key => $jobly_integration_label ) :
				if ( empty( $jobly_integration_job[ $jobly_integration_key ] ) || ! is_array( $jobly_integration_job[ $jobly_integration_key ] ) ) {
					continue;
				}
				?>
				<section class="jobly-job__list">
					<h2><?php echo esc_html( $jobly_integration_label ); ?></h2>
					<ul>
						<?php foreach ( $jobly_integration_job[ $jobly_integration_key ] as $jobly_integration_li ) : ?>
							<li><?php echo esc_html( is_scalar( $jobly_integration_li ) ? (string) $jobly_integration_li : '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
			<section id="jobly-apply" class="jobly-job__apply" aria-label="<?php esc_attr_e( 'Prijava na delo', 'jobly-integration' ); ?>">
				<?php
				if ( $args['demo'] ) {
					echo jobly_integration_demo( jobly_integration_accent(), $jobly_integration_slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in jobly_integration_demo().
				} else {
					// With the job text shown above only the form is embedded; otherwise the embed brings the ad body too.
					$jobly_integration_has_text = ! empty( $jobly_integration_job['description'] );
					$jobly_integration_embed    = jobly_integration_embed_html(
						$jobly_integration_slug,
						'',
						array(
							'url'    => (string) ( $jobly_integration_job['embedUrl'] ?? '' ),
							'filter' => $jobly_integration_has_text ? '' : 'description,responsibilities,requirements,benefits',
							'height' => $jobly_integration_has_text ? (int) jobly_integration_settings()['height'] : max( 1100, (int) jobly_integration_settings()['height'] ),
						)
					);
					echo $jobly_integration_embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in jobly_integration_embed_html().
				}
				?>
			</section>
		</div>
		<aside class="jobly-job__side" aria-label="<?php esc_attr_e( 'Povzetek oglasa', 'jobly-integration' ); ?>">
			<?php jobly_integration_get_template( 'parts/job-sidebar.php', $args ); ?>
		</aside>
	</div>
</div>
<?php
/**
 * Fires after the job page content.
 *
 * @param array $job Job from the API.
 */
do_action( 'jobly_integration_after_single_job', $jobly_integration_job );
