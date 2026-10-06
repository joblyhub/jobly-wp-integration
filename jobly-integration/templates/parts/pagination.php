<?php
/**
 * Pagination of the job list.
 *
 * Override in your theme: yourtheme/jobly/parts/pagination.php
 *
 * @var array $args Template data of archive-jobs.php.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

$jobly_integration_page  = (int) $args['query']['page'];
$jobly_integration_pages = (int) $args['query']['pages'];
$jobly_integration_req   = $args['request'];
$jobly_integration_base  = array_filter(
	array(
		'iskanje' => $jobly_integration_req['search'] ?? '',
		'kraj'    => $jobly_integration_req['location'] ?? '',
		'vrsta'   => $jobly_integration_req['type'] ?? '',
		'daljava' => ! empty( $jobly_integration_req['remote'] ) ? '1' : '',
	),
	'strlen'
);
$jobly_integration_url   = static function ( $n ) use ( $jobly_integration_base, $args ) {
	return add_query_arg( $n > 1 ? array_merge( $jobly_integration_base, array( 'stran' => $n ) ) : $jobly_integration_base, $args['list']['action'] );
};
?>
<nav class="jobly-pagination" aria-label="<?php esc_attr_e( 'Strani seznama', 'jobly-integration' ); ?>">
	<?php if ( $jobly_integration_page > 1 ) : ?>
		<a class="jobly-pagination__link" rel="prev" href="<?php echo esc_url( $jobly_integration_url( $jobly_integration_page - 1 ) ); ?>"><?php jobly_integration_icon( 'arrow-left', '', 16 ); ?> <?php esc_html_e( 'Prejšnja', 'jobly-integration' ); ?></a>
	<?php endif; ?>
	<?php for ( $jobly_integration_i = 1; $jobly_integration_i <= $jobly_integration_pages; $jobly_integration_i++ ) : ?>
		<?php if ( $jobly_integration_i === $jobly_integration_page ) : ?>
			<span class="jobly-pagination__link is-current" aria-current="page"><?php echo esc_html( (string) $jobly_integration_i ); ?></span>
		<?php else : ?>
			<a class="jobly-pagination__link" href="<?php echo esc_url( $jobly_integration_url( $jobly_integration_i ) ); ?>"><?php echo esc_html( (string) $jobly_integration_i ); ?></a>
		<?php endif; ?>
	<?php endfor; ?>
	<?php if ( $jobly_integration_page < $jobly_integration_pages ) : ?>
		<a class="jobly-pagination__link" rel="next" href="<?php echo esc_url( $jobly_integration_url( $jobly_integration_page + 1 ) ); ?>"><?php esc_html_e( 'Naslednja', 'jobly-integration' ); ?> <?php jobly_integration_icon( 'arrow-right', '', 16 ); ?></a>
	<?php endif; ?>
</nav>
