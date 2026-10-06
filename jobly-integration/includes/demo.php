<?php
/**
 * Demo mode: an invented company ("Primer d.o.o."), invented jobs and applicants.
 * No call to Jobly is made and no real company data is ever shown.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Invented jobs, in the shape of GET /api/v1/jobs.
 *
 * @return array[]
 */
function jobly_integration_demo_jobs() {
	$rows = array(
		array( 'demo-1', 'Skladiščnik (m/ž)', 'skladiscnik', 'active', 'Ljubljana', 'full_time', 'on_site', 1800, 2200, 3 ),
		array( 'demo-2', 'Računovodja (m/ž)', 'racunovodja', 'active', 'Ljubljana', 'full_time', 'hybrid', 2200, 2800, 5 ),
		array( 'demo-3', 'Spletni razvijalec (m/ž)', 'spletni-razvijalec', 'active', 'Maribor', 'full_time', 'remote', 2800, 3600, 2 ),
		array( 'demo-4', 'Prodajni referent (m/ž)', 'prodajni-referent', 'closed', 'Celje', 'part_time', 'on_site', 1200, 1500, 7 ),
	);
	$out  = array();
	foreach ( $rows as $i => $r ) {
		$out[] = array(
			'id'                => $r[0],
			'title'             => $r[1],
			'slug'              => $r[2],
			'status'            => $r[3],
			'location'          => $r[4],
			'country'           => 'SI',
			'employmentType'    => $r[5],
			'arrangement'       => $r[6],
			'arrangementLabel'  => jobly_integration_work_arrangements()[ $r[6] ],
			'salaryMin'         => $r[7],
			'salaryMax'         => $r[8],
			'applicationsCount' => $r[9],
			'featured'          => false,
			'featuredUntil'     => null,
			'publishedAt'       => gmdate( 'c', time() - ( $i + 1 ) * 3 * DAY_IN_SECONDS ),
			'hiredCount'        => 0,
		);
	}
	return $out;
}

/**
 * Invented job description.
 *
 * @param string $slug Demo job slug.
 * @return string
 */
function jobly_integration_demo_description( $slug ) {
	$text = array(
		'skladiscnik'        => __( 'Prevzem in izdaja blaga, inventura, delo z ročnim čitalcem.', 'jobly-integration' ),
		'racunovodja'        => __( 'Knjiženje prejetih in izdanih računov, obračun DDV, mesečna poročila.', 'jobly-integration' ),
		'spletni-razvijalec' => __( 'Razvoj spletne trgovine, sodelovanje z oblikovalcem in podporo.', 'jobly-integration' ),
		'prodajni-referent'  => __( 'Svetovanje strankam in prodaja v trgovini.', 'jobly-integration' ),
	);
	return $text[ $slug ] ?? '';
}

/**
 * Invented company profile, in the shape of GET /api/v1/company.
 *
 * @return array
 */
function jobly_integration_demo_company() {
	return array(
		'id'              => 'demo-company',
		'name'            => 'Primer d.o.o.',
		'slug'            => 'primer',
		'logoUrl'         => '',
		'website'         => 'https://primer.example',
		'industry'        => 'Trgovina',
		'location'        => 'Ljubljana',
		'description'     => '',
		'profileUrl'      => '',
		'careersEmbedUrl' => '',
		'openJobsCount'   => 3,
	);
}

/**
 * Invented job content, in the shape of GET /api/v1/jobs/{slug} (extra fields only).
 *
 * @param string $slug Demo job slug.
 * @return array
 */
function jobly_integration_demo_detail( $slug ) {
	return array(
		'description'      => jobly_integration_demo_description( $slug ),
		'responsibilities' => array( __( 'Prevzem in izdaja blaga', 'jobly-integration' ), __( 'Delo z ročnim čitalcem', 'jobly-integration' ) ),
		'requirements'     => array( __( 'Natančnost in zanesljivost', 'jobly-integration' ), __( 'Osnovno računalniško znanje', 'jobly-integration' ) ),
		'benefits'         => array( __( 'Topli obrok', 'jobly-integration' ), __( 'Izobraževalni budget', 'jobly-integration' ) ),
	);
}

/**
 * Invented applications, in the shape of GET /api/v1/applications.
 *
 * @return array[]
 */
function jobly_integration_demo_applications() {
	$rows = array(
		array( 'demo-a1', 'new', 'Ana Primerna', 'ana@primer.example', 0, 1 ),
		array( 'demo-a2', 'reviewed', 'Boris Testni', 'boris@primer.example', 1, 2 ),
		array( 'demo-a3', 'intro_round', 'Cvetka Izmišljena', 'cvetka@primer.example', 1, 4 ),
		array( 'demo-a4', 'new', 'Darko Vzorec', 'darko@primer.example', 2, 6 ),
		array( 'demo-a5', 'rejected', 'Eva Demo', 'eva@primer.example', 3, 20 ),
	);
	$jobs = jobly_integration_demo_jobs();
	$out  = array();
	foreach ( $rows as $r ) {
		$out[] = array(
			'id'          => $r[0],
			'stage'       => $r[1],
			'coverLetter' => __( 'To je izmišljeno motivacijsko pismo za predogled.', 'jobly-integration' ),
			'appliedAt'   => gmdate( 'c', time() - $r[5] * DAY_IN_SECONDS ),
			'job'         => array(
				'id'    => $jobs[ $r[4] ]['id'],
				'title' => $jobs[ $r[4] ]['title'],
			),
			'applicant'   => array(
				'name'  => $r[2],
				'email' => $r[3],
			),
		);
	}
	return $out;
}

/**
 * Static sample of the careers embed. Everything here is invented (company,
 * jobs, places) and the form cannot be submitted, so a site can be built and
 * shown before it is connected to a real company.
 *
 * @param string $accent Hex colour or ''.
 * @param string $only   Show only the job with this slug ('' = all open).
 * @return string
 */
function jobly_integration_demo( $accent, $only = '' ) {
	$accent = $accent ? $accent : '#2563eb';
	$jobs   = array();
	foreach ( jobly_integration_demo_jobs() as $row ) {
		if ( 'active' !== $row['status'] || ( '' !== $only && $only !== $row['slug'] ) ) {
			continue;
		}
		$jobs[] = array(
			$row['title'],
			jobly_integration_employment_types()[ $row['employmentType'] ] . ' · ' . $row['arrangementLabel'],
			jobly_integration_demo_description( $row['slug'] ),
		);
	}
	$id = 'jobly-demo-' . wp_rand( 1000, 9999 );

	ob_start();
	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="jobly-demo" style="--jobly-accent:<?php echo esc_attr( $accent ); ?>">
		<style>
			.jobly-demo{font:15px/1.5 system-ui,sans-serif;color:#0f172a;max-width:36rem;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;background:#fff}
			.jobly-demo__badge{display:inline-block;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#92400e;background:#fef3c7;border-radius:999px;padding:.15rem .6rem;margin-bottom:.75rem}
			.jobly-demo label{display:block;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin:.25rem 0}
			.jobly-demo select,.jobly-demo input,.jobly-demo textarea{width:100%;box-sizing:border-box;border:1px solid #e2e8f0;border-radius:8px;padding:.55rem .7rem;margin-bottom:.6rem;font:inherit;background:#f8fafc}
			.jobly-demo h3{margin:.5rem 0 0;font-size:1.1rem}
			.jobly-demo__meta{color:#64748b;font-size:13px;margin-bottom:.4rem}
			.jobly-demo button{width:100%;border:0;border-radius:8px;padding:.7rem;background:var(--jobly-accent);color:#fff;font-weight:600;opacity:.6;cursor:not-allowed}
			.jobly-demo__foot{text-align:center;font-size:12px;color:#94a3b8;margin-top:.6rem}
		</style>
		<span class="jobly-demo__badge"><?php esc_html_e( 'Demo · izmišljeni podatki', 'jobly-integration' ); ?></span>
		<label for="<?php echo esc_attr( $id ); ?>-job"><?php esc_html_e( 'Odprta delovna mesta', 'jobly-integration' ); ?></label>
		<?php if ( count( $jobs ) > 1 ) : ?>
		<select id="<?php echo esc_attr( $id ); ?>-job" onchange="var d=this.closest('.jobly-demo');d.querySelectorAll('[data-job]').forEach(function(e){e.hidden=e.dataset.job!==this.value}.bind(this))">
			<?php foreach ( $jobs as $i => $job ) : ?>
				<option value="<?php echo esc_attr( (string) $i ); ?>"><?php echo esc_html( $job[0] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php endif; ?>
		<?php foreach ( $jobs as $i => $job ) : ?>
			<div data-job="<?php echo esc_attr( (string) $i ); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?>>
				<h3><?php echo esc_html( $job[0] ); ?></h3>
				<div class="jobly-demo__meta">Primer d.o.o. · Ljubljana · <?php echo esc_html( $job[1] ); ?></div>
				<p><?php echo esc_html( $job[2] ); ?></p>
			</div>
		<?php endforeach; ?>
		<input disabled placeholder="<?php esc_attr_e( 'Ime in priimek', 'jobly-integration' ); ?>">
		<input disabled placeholder="<?php esc_attr_e( 'E-naslov', 'jobly-integration' ); ?>">
		<textarea disabled rows="3" placeholder="<?php esc_attr_e( 'Nekaj vrstic o sebi', 'jobly-integration' ); ?>"></textarea>
		<button type="button" disabled><?php esc_html_e( 'Oddaj prijavo', 'jobly-integration' ); ?></button>
		<div class="jobly-demo__foot"><?php esc_html_e( 'Predogled. Ko v Jobly HRM povežete podjetje in izklopite demo, se tu prikažejo vaša odprta mesta.', 'jobly-integration' ); ?></div>
	</div>
	<?php
	return (string) ob_get_clean();
}
