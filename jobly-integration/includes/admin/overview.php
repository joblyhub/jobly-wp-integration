<?php
/**
 * Pregled: stat cards, setup checklist, latest applications and jobs.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Setup checklist items: [done, label, hint, url, cta].
 *
 * @param int $open_jobs Number of open jobs.
 * @return array[]
 */
function jobly_integration_checklist( $open_jobs ) {
	$s         = jobly_integration_settings();
	$connected = ! $s['demo'] && $s['connected'] && '' !== $s['api_key'];
	return array(
		array(
			'done'  => $connected,
			'label' => __( 'Poveži podjetje', 'jobly-integration' ),
			'hint'  => __( 'API ključ iz Jobly, preverjen in shranjen.', 'jobly-integration' ),
			'url'   => jobly_integration_admin_url( 'jobly-settings' ),
			'cta'   => __( 'Poveži', 'jobly-integration' ),
		),
		array(
			'done'  => (bool) $s['careers_set'],
			'label' => __( 'Nastavi karierno stran', 'jobly-integration' ),
			'hint'  => __( 'Naslov seznama in videz.', 'jobly-integration' ),
			'url'   => jobly_integration_admin_url( 'jobly-settings', array( 'tab' => 'display' ) ),
			'cta'   => __( 'Nastavi', 'jobly-integration' ),
		),
		array(
			'done'  => $connected && $open_jobs > 0,
			'label' => __( 'Objavi prvi oglas', 'jobly-integration' ),
			'hint'  => __( 'Vsaj eno odprto delovno mesto.', 'jobly-integration' ),
			'url'   => jobly_integration_admin_url( 'jobly-job-new' ),
			'cta'   => __( 'Dodaj oglas', 'jobly-integration' ),
		),
		array(
			'done'  => $connected && $s['schema'] && $open_jobs > 0,
			'label' => __( 'Google for Jobs', 'jobly-integration' ),
			'hint'  => __( 'Strukturirani zapis na straneh oglasov.', 'jobly-integration' ),
			'url'   => jobly_integration_admin_url( 'jobly-settings', array( 'tab' => 'google' ) ),
			'cta'   => __( 'Odpri', 'jobly-integration' ),
		),
	);
}

/**
 * Screen callback.
 */
function jobly_integration_page_overview() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	jobly_integration_header( __( 'Pregled', 'jobly-integration' ), jobly_integration_admin_url( 'jobly-job-new' ), __( 'Dodaj delovno mesto', 'jobly-integration' ) );

	$s = jobly_integration_settings();
	if ( $s['demo'] ) {
		echo '<div class="jobly-banner">';
		jobly_integration_icon( 'sparkles', '', 22 );
		echo '<div><strong>' . esc_html__( 'Demo način', 'jobly-integration' ) . '</strong><span>' . esc_html__( 'Vidiš izmišljene podatke podjetja Primer d.o.o.; Jobly se ne kliče.', 'jobly-integration' ) . '</span></div>';
		echo '<a class="button button-primary" href="' . esc_url( jobly_integration_admin_url( 'jobly-setup' ) ) . '">' . esc_html__( 'Poveži podjetje', 'jobly-integration' ) . '</a></div>';
	}

	$jobs = jobly_integration_all_jobs();
	$apps = jobly_integration_all_applications();
	if ( 200 !== $jobs['code'] ) {
		if ( ! $s['demo'] && ( '' === $s['api_key'] || ! $s['connected'] ) ) {
			jobly_integration_require_connection();
		} else {
			jobly_integration_api_error_notice( $jobs['code'] );
		}
		jobly_integration_footer();
		return;
	}

	$company = jobly_integration_company();
	if ( $s['demo'] || ! empty( $company['slug'] ) ) {
		echo '<div class="jobly-companybar"><span class="jobly-co">';
		jobly_integration_company_badge( 44 );
		echo '</span><div class="jobly-companybar__meta">';
		$bits = array_filter( array( (string) ( $company['industry'] ?? '' ), (string) ( $company['location'] ?? '' ) ) );
		echo '<span>' . esc_html( $bits ? implode( ' · ', $bits ) : __( 'Povezano podjetje', 'jobly-integration' ) ) . '</span></div>';
		if ( ! $s['demo'] && ! empty( $company['profileUrl'] ) ) {
			echo '<a class="button" href="' . esc_url( (string) $company['profileUrl'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Profil na Jobly', 'jobly-integration' ) . '</a>';
		}
		echo '</div>';
	}

	$open = count( jobly_integration_open_jobs() );
	$new  = 0;
	foreach ( $apps['items'] as $app ) {
		if ( strtotime( (string) ( $app['appliedAt'] ?? '' ) ) >= time() - 7 * DAY_IN_SECONDS ) {
			++$new;
		}
	}
	$apps_ok = 200 === $apps['code'];
	$stats   = array(
		array( 'briefcase', $open, __( 'Odprta mesta', 'jobly-integration' ), 'blue' ),
		array( 'inbox', $apps_ok ? $new : '—', __( 'Nove prijave (7 dni)', 'jobly-integration' ), 'green' ),
		array( 'users', $apps_ok ? count( $apps['items'] ) : '—', __( 'Prijave skupaj', 'jobly-integration' ), 'violet' ),
	);
	// Real statistics when Jobly has them; otherwise the three simple cards and a note.
	if ( ! jobly_integration_render_stats() ) :
		?>
	<div class="jobly-stats">
		<?php foreach ( $stats as $stat ) : ?>
			<div class="jobly-stat">
				<span class="jobly-stat__icon jobly-tone--<?php echo esc_attr( $stat[3] ); ?>"><?php jobly_integration_icon( $stat[0], '', 22 ); ?></span>
				<div><strong><?php echo esc_html( (string) $stat[1] ); ?></strong><span><?php echo esc_html( $stat[2] ); ?></span></div>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="description jobly-statsnote"><?php esc_html_e( 'Statistika ogledov in konverzije ni na voljo: ta različica Jobly je ne vrača prek API-ja.', 'jobly-integration' ); ?></p>
	<?php endif; ?>
	<?php
	$list = jobly_integration_checklist( $open );
	$done = count( array_filter( array_column( $list, 'done' ) ) );
	?>
	<div class="jobly-grid">
		<section class="jobly-panel">
			<header class="jobly-panel__head">
				<h2><?php esc_html_e( 'Zadnje prijave', 'jobly-integration' ); ?></h2>
				<a href="<?php echo esc_url( jobly_integration_admin_url( 'jobly-applications' ) ); ?>"><?php esc_html_e( 'Vse prijave', 'jobly-integration' ); ?></a>
			</header>
			<?php
			$latest = array_slice( $apps['items'], 0, 5 );
			if ( ! $apps_ok ) {
				jobly_integration_api_error_notice( $apps['code'] );
			} elseif ( ! $latest ) {
				jobly_integration_empty_state( 'applications', __( 'Še ni prijav', 'jobly-integration' ), __( 'Ko kandidat odda prijavo, se prikaže tukaj.', 'jobly-integration' ) );
			} else {
				echo '<ul class="jobly-rows">';
				foreach ( $latest as $app ) {
					$name = (string) ( $app['applicant']['name'] ?? '' );
					$url  = jobly_integration_admin_url(
						'jobly-applications',
						array(
							'action'      => 'show',
							'application' => $app['id'],
						)
					);
					echo '<li><span class="jobly-avatar">' . esc_html( jobly_integration_initials( $name ) ) . '</span><div class="jobly-rows__main"><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a><span>' . esc_html( (string) ( $app['job']['title'] ?? '' ) ) . '</span></div><div class="jobly-rows__side">';
					echo wp_kses_post( jobly_integration_stage_badge( (string) ( $app['stage'] ?? '' ) ) );
					echo '<span>' . esc_html( jobly_integration_relative_date( $app['appliedAt'] ?? null ) ) . '</span></div></li>';
				}
				echo '</ul>';
			}
			?>
		</section>

		<section class="jobly-panel">
			<header class="jobly-panel__head">
				<h2><?php esc_html_e( 'Začetek dela', 'jobly-integration' ); ?></h2>
				<span class="jobly-muted"><?php echo esc_html( sprintf( /* translators: 1: done steps, 2: all steps. */ __( '%1$d od %2$d', 'jobly-integration' ), $done, count( $list ) ) ); ?></span>
			</header>
			<div class="jobly-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) count( $list ) ); ?>" aria-valuenow="<?php echo esc_attr( (string) $done ); ?>"><span style="width:<?php echo esc_attr( (string) round( 100 * $done / count( $list ) ) ); ?>%"></span></div>
			<ul class="jobly-checklist">
				<?php foreach ( $list as $item ) : ?>
					<li class="<?php echo $item['done'] ? 'is-done' : ''; ?>">
						<?php jobly_integration_icon( $item['done'] ? 'circle-check' : 'circle', 'jobly-checklist__mark', 22 ); ?>
						<div><strong><?php echo esc_html( $item['label'] ); ?></strong><span><?php echo esc_html( $item['hint'] ); ?></span></div>
						<?php if ( ! $item['done'] ) : ?>
							<a class="button" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['cta'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="jobly-panel jobly-panel--wide">
			<header class="jobly-panel__head">
				<h2><?php esc_html_e( 'Delovna mesta', 'jobly-integration' ); ?></h2>
				<a href="<?php echo esc_url( jobly_integration_admin_url( 'jobly-jobs' ) ); ?>"><?php esc_html_e( 'Vsa delovna mesta', 'jobly-integration' ); ?></a>
			</header>
			<?php
			$recent = array_slice( $jobs['items'], 0, 5 );
			if ( ! $recent ) {
				jobly_integration_empty_state( 'jobs', __( 'Še ni delovnih mest', 'jobly-integration' ), __( 'Objavite prvi oglas in prikazal se bo na karierni strani.', 'jobly-integration' ), jobly_integration_admin_url( 'jobly-job-new' ), __( 'Dodaj delovno mesto', 'jobly-integration' ) );
			} else {
				$types = jobly_integration_employment_types();
				echo '<ul class="jobly-rows">';
				foreach ( $recent as $job ) {
					$url  = jobly_integration_admin_url(
						'jobly-jobs',
						array(
							'action' => 'show',
							'job'    => $job['slug'],
						)
					);
					$meta = array_filter( array( (string) ( $job['location'] ?? '' ), $types[ $job['employmentType'] ?? '' ] ?? '' ) );
					echo '<li><span class="jobly-avatar jobly-avatar--sq">';
					jobly_integration_icon( 'briefcase', '', 18 );
					echo '</span><div class="jobly-rows__main"><a href="' . esc_url( $url ) . '">' . esc_html( (string) $job['title'] ) . '</a><span>' . esc_html( implode( ' · ', $meta ) ) . '</span></div><div class="jobly-rows__side">';
					echo wp_kses_post( jobly_integration_status_badge( (string) ( $job['status'] ?? '' ) ) );
					echo '<span>' . esc_html( sprintf( /* translators: %d: number of applications. */ __( 'Prijav: %d', 'jobly-integration' ), (int) ( $job['applicationsCount'] ?? 0 ) ) ) . '</span></div></li>';
				}
				echo '</ul>';
			}
			?>
		</section>
	</div>
	<?php
	jobly_integration_footer();
}
