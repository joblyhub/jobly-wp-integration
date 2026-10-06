<?php
/**
 * Search and filters (GET form, works without JavaScript).
 *
 * Override in your theme: yourtheme/jobly/parts/filters.php
 *
 * @var array $args Template data of archive-jobs.php.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

$jobly_integration_req    = $args['request'];
$jobly_integration_facets = $args['facets'];
$jobly_integration_uid    = 'jobly-f-' . wp_unique_id();
$jobly_integration_active = ! empty( $jobly_integration_req['search'] ) || ! empty( $jobly_integration_req['location'] ) || ! empty( $jobly_integration_req['type'] ) || ! empty( $jobly_integration_req['remote'] );
?>
<form class="jobly-filters" method="get" action="<?php echo esc_url( $args['list']['action'] ); ?>" role="search" aria-label="<?php esc_attr_e( 'Iskanje delovnih mest', 'jobly-integration' ); ?>">
	<div class="jobly-filters__search">
		<label class="jobly-sr" for="<?php echo esc_attr( $jobly_integration_uid ); ?>-q"><?php esc_html_e( 'Išči po nazivu ali kraju', 'jobly-integration' ); ?></label>
		<?php jobly_integration_icon( 'search', 'jobly-filters__icon', 18 ); ?>
		<input id="<?php echo esc_attr( $jobly_integration_uid ); ?>-q" type="search" name="iskanje" value="<?php echo esc_attr( $jobly_integration_req['search'] ); ?>" placeholder="<?php esc_attr_e( 'Išči po nazivu ali kraju', 'jobly-integration' ); ?>">
	</div>
	<?php if ( $jobly_integration_facets['locations'] ) : ?>
		<div class="jobly-filters__field">
			<label for="<?php echo esc_attr( $jobly_integration_uid ); ?>-l"><?php esc_html_e( 'Kraj', 'jobly-integration' ); ?></label>
			<select id="<?php echo esc_attr( $jobly_integration_uid ); ?>-l" name="kraj">
				<option value=""><?php esc_html_e( 'Vsi kraji', 'jobly-integration' ); ?></option>
				<?php foreach ( $jobly_integration_facets['locations'] as $jobly_integration_loc ) : ?>
					<option value="<?php echo esc_attr( $jobly_integration_loc ); ?>" <?php selected( $jobly_integration_req['location'], $jobly_integration_loc ); ?>><?php echo esc_html( $jobly_integration_loc ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>
	<?php if ( $jobly_integration_facets['types'] ) : ?>
		<div class="jobly-filters__field">
			<label for="<?php echo esc_attr( $jobly_integration_uid ); ?>-t"><?php esc_html_e( 'Vrsta zaposlitve', 'jobly-integration' ); ?></label>
			<select id="<?php echo esc_attr( $jobly_integration_uid ); ?>-t" name="vrsta">
				<option value=""><?php esc_html_e( 'Vse vrste', 'jobly-integration' ); ?></option>
				<?php foreach ( $jobly_integration_facets['types'] as $jobly_integration_key => $jobly_integration_label ) : ?>
					<option value="<?php echo esc_attr( $jobly_integration_key ); ?>" <?php selected( $jobly_integration_req['type'], $jobly_integration_key ); ?>><?php echo esc_html( $jobly_integration_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>
	<?php if ( $jobly_integration_facets['remote'] ) : ?>
		<label class="jobly-filters__check"><input type="checkbox" name="daljava" value="1" <?php checked( ! empty( $jobly_integration_req['remote'] ) ); ?>> <span><?php esc_html_e( 'Delo na daljavo', 'jobly-integration' ); ?></span></label>
	<?php endif; ?>
	<div class="jobly-filters__actions">
		<button type="submit" class="jobly-btn"><?php esc_html_e( 'Išči', 'jobly-integration' ); ?></button>
		<?php if ( $jobly_integration_active ) : ?>
			<a class="jobly-btn jobly-btn--ghost" href="<?php echo esc_url( $args['list']['action'] ); ?>"><?php esc_html_e( 'Počisti', 'jobly-integration' ); ?></a>
		<?php endif; ?>
	</div>
</form>
