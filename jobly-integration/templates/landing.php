<?php
/**
 * Careers landing: custom HTML slots and the enabled sections in the chosen order.
 *
 * Override in your theme: yourtheme/jobly/landing.php
 *
 * @var array $args landing, order, demo.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

$jobly_integration_landing = $args['landing'];
?>
<div class="jobly-careers jobly-landing" style="--jobly-accent:<?php echo esc_attr( jobly_integration_accent() ); ?>">
	<?php jobly_integration_landing_slot( 'html_top' ); ?>
	<?php foreach ( $args['order'] as $jobly_integration_section ) : ?>
		<?php
		if ( empty( $jobly_integration_landing['enabled'][ $jobly_integration_section ] ) ) {
			continue;
		}
		?>
		<?php if ( 'hero' === $jobly_integration_section ) : ?>
			<section class="jobly-hero">
				<div class="jobly-hero__text">
					<?php if ( '' !== trim( (string) $jobly_integration_landing['hero_subtitle'] ) ) : ?>
						<p class="jobly-hero__lead"><?php echo esc_html( $jobly_integration_landing['hero_subtitle'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $jobly_integration_landing['hero_cta'] ) && ! empty( $jobly_integration_landing['enabled']['jobs'] ) ) : ?>
						<p><a class="jobly-btn" href="#jobly-jobs"><?php echo esc_html( $jobly_integration_landing['hero_cta'] ); ?> <?php jobly_integration_icon( 'arrow-right', '', 16 ); ?></a></p>
					<?php endif; ?>
				</div>
				<?php if ( $jobly_integration_landing['hero_image'] ) : ?>
					<div class="jobly-hero__media"><?php echo wp_get_attachment_image( (int) $jobly_integration_landing['hero_image'], 'large', false, array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes attachment markup. ?></div>
				<?php endif; ?>
			</section>
		<?php elseif ( 'intro' === $jobly_integration_section && '' !== trim( (string) $jobly_integration_landing['intro_html'] ) ) : ?>
			<section class="jobly-intro"><?php echo wp_kses_post( do_shortcode( wpautop( $jobly_integration_landing['intro_html'] ) ) ); ?></section>
		<?php elseif ( 'benefits' === $jobly_integration_section && $jobly_integration_landing['benefits'] ) : ?>
			<section class="jobly-benefits">
				<?php if ( '' !== trim( (string) $jobly_integration_landing['benefits_title'] ) ) : ?>
					<h2><?php echo esc_html( $jobly_integration_landing['benefits_title'] ); ?></h2>
				<?php endif; ?>
				<ul class="jobly-benefits__grid">
					<?php foreach ( $jobly_integration_landing['benefits'] as $jobly_integration_item ) : ?>
						<li>
							<span class="jobly-benefits__icon"><?php jobly_integration_icon( (string) $jobly_integration_item['icon'], '', 22 ); ?></span>
							<h3><?php echo esc_html( (string) $jobly_integration_item['title'] ); ?></h3>
							<?php if ( '' !== (string) $jobly_integration_item['text'] ) : ?>
								<p><?php echo esc_html( (string) $jobly_integration_item['text'] ); ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php elseif ( 'jobs' === $jobly_integration_section ) : ?>
			<section id="jobly-jobs" class="jobly-landing__jobs">
				<?php
				echo jobly_integration_render_list( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin templates escape every value; brackets are encoded in render_list().
					array(
						'filters'  => (bool) $jobly_integration_landing['filters'],
						'paginate' => true,
					)
				);
				?>
			</section>
		<?php endif; ?>
	<?php endforeach; ?>
	<?php jobly_integration_landing_slot( 'html_bottom' ); ?>
</div>
