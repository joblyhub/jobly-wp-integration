<?php
/**
 * Sticky summary of a job page.
 *
 * Override in your theme: yourtheme/jobly/parts/job-sidebar.php
 *
 * @var array $args Template data of single-job.php.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

$jobly_integration_job    = $args['job'];
$jobly_integration_types  = jobly_integration_employment_types();
$jobly_integration_arr    = jobly_integration_work_arrangements();
$jobly_integration_salary = jobly_integration_salary_text( $jobly_integration_job );
$jobly_integration_rows   = array_filter(
	array(
		__( 'Podjetje', 'jobly-integration' )         => jobly_integration_company_name(),
		__( 'Kraj', 'jobly-integration' )             => (string) ( $jobly_integration_job['location'] ?? '' ),
		__( 'Vrsta zaposlitve', 'jobly-integration' ) => $jobly_integration_types[ $jobly_integration_job['employmentType'] ?? '' ] ?? '',
		__( 'Način dela', 'jobly-integration' )       => $jobly_integration_arr[ $jobly_integration_job['arrangement'] ?? '' ] ?? '',
		__( 'Plačni razpon', 'jobly-integration' )    => $jobly_integration_salary,
		__( 'Objavljeno', 'jobly-integration' )       => jobly_integration_format_date( $jobly_integration_job['publishedAt'] ?? null ),
	),
	static function ( $v ) {
		return '' !== $v && '—' !== $v;
	}
);
?>
<div class="jobly-summary">
	<h2 class="jobly-summary__title"><?php esc_html_e( 'Povzetek', 'jobly-integration' ); ?></h2>
	<dl class="jobly-summary__list">
		<?php foreach ( $jobly_integration_rows as $jobly_integration_label => $jobly_integration_value ) : ?>
			<div><dt><?php echo esc_html( $jobly_integration_label ); ?></dt><dd><?php echo esc_html( $jobly_integration_value ); ?></dd></div>
		<?php endforeach; ?>
	</dl>
	<a class="jobly-btn jobly-btn--block" href="#jobly-apply"><?php esc_html_e( 'Prijavi se', 'jobly-integration' ); ?> <?php jobly_integration_icon( 'arrow-right', '', 16 ); ?></a>
</div>
