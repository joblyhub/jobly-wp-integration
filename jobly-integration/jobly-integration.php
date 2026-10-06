<?php
/**
 * Plugin Name:       Jobly.si HRM
 * Plugin URI:        https://github.com/joblyhub/jobly-wp-integration
 * Description:       Vgradi Jobly.si prijavni obrazec ali celotno karierno stran podjetja v WordPress s kratko kodo [jobly].
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jobly.si
 * Author URI:        https://jobly.si
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_OPTION = 'jobly_integration';

/**
 * Saved settings merged over defaults.
 *
 * @return array{base_url: string, company: string, accent: string, height: int, demo: int}
 */
function jobly_integration_settings() {
	$saved = get_option( JOBLY_INTEGRATION_OPTION, array() );

	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'base_url' => 'https://jobly.si',
			'company'  => '',
			'accent'   => '',
			'height'   => 720,
			'demo'     => 1,
		)
	);
}

/**
 * [jobly] — the iframe embed Jobly already serves at /embed/…
 *
 * [jobly]                          celotna karierna stran podjetja iz nastavitev
 * [jobly company="podjetje"]       karierna stran drugega podjetja
 * [jobly job="slug-oglasa"]        obrazec za en oglas
 * [jobly show="full"]              z vsebino oglasa (privzeto: basic)
 * [jobly filter="title,benefits"]  natančno izbrani deli
 * [jobly accent="#2563eb" height="900"]
 * [jobly demo="1"]                 izmišljeni primer, brez klica na Jobly
 *
 * All rendering, validation, consent and anti-spam stay on Jobly; the plugin
 * only builds the URL, so a WordPress site never handles applicant data.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function jobly_integration_shortcode( $atts ) {
	$settings = jobly_integration_settings();
	$atts     = shortcode_atts(
		array(
			'company' => $settings['company'],
			'job'     => '',
			'show'    => '',
			'filter'  => '',
			'accent'  => $settings['accent'],
			'height'  => $settings['height'],
			'title'   => __( 'Prijava na delo', 'jobly-integration' ),
			'demo'    => (string) $settings['demo'],
		),
		$atts,
		'jobly'
	);

	$job     = sanitize_title( $atts['job'] );
	$company = sanitize_title( $atts['company'] );

	// Demo until a company is connected: invented data only, nothing from Jobly.
	if ( in_array( strtolower( (string) $atts['demo'] ), array( '1', 'true', 'yes', 'da' ), true ) ) {
		return jobly_integration_demo( (string) sanitize_hex_color( $atts['accent'] ) );
	}

	if ( '' === $job && '' === $company ) {
		return current_user_can( 'manage_options' )
			? '<p><em>' . esc_html__( 'Jobly: v nastavitvah vpiši podjetje ali v kratki kodi podaj job="…".', 'jobly-integration' ) . '</em></p>'
			: '';
	}

	$base = untrailingslashit( esc_url_raw( $settings['base_url'] ) );
	$url  = '' !== $job
		? $base . '/embed/jobs/' . rawurlencode( $job )
		: $base . '/embed/companies/' . rawurlencode( $company );

	$query = array();
	if ( in_array( $atts['show'], array( 'basic', 'full' ), true ) ) {
		$query['show'] = $atts['show'];
	}
	$filter = preg_replace( '/[^a-z,]/', '', strtolower( (string) $atts['filter'] ) );
	if ( '' !== $filter ) {
		$query['filter'] = $filter;
	}
	$accent = sanitize_hex_color( $atts['accent'] );
	if ( $accent && 7 === strlen( $accent ) ) {
		$query['accent'] = $accent;
	}
	if ( $query ) {
		$url = add_query_arg( array_map( 'rawurlencode', $query ), $url );
	}

	$height = max( 300, min( 3000, absint( $atts['height'] ) ) );

	return sprintf(
		'<iframe class="jobly-embed" src="%s" width="100%%" height="%d" style="border:0;width:100%%" loading="lazy" title="%s"></iframe>',
		esc_url( $url ),
		$height,
		esc_attr( $atts['title'] )
	);
}
add_shortcode( 'jobly', 'jobly_integration_shortcode' );

/**
 * Static sample of the careers embed. Everything here is invented (company,
 * jobs, places) and the form cannot be submitted, so a site can be built and
 * shown before it is connected to a real company.
 *
 * @param string $accent Hex colour or ''.
 * @return string
 */
function jobly_integration_demo( $accent ) {
	$accent = $accent ? $accent : '#2563eb';
	$jobs   = array(
		array( 'Skladiščnik (m/ž)', 'Polni delovni čas · Na lokaciji', 'Prevzem in izdaja blaga, inventura, delo z ročnim čitalcem.' ),
		array( 'Računovodja (m/ž)', 'Polni delovni čas · Hibridno', 'Knjiženje prejetih in izdanih računov, obračun DDV, mesečna poročila.' ),
		array( 'Spletni razvijalec (m/ž)', 'Polni delovni čas · Na daljavo', 'Razvoj spletne trgovine, sodelovanje z oblikovalcem in podporo.' ),
	);
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
		<select id="<?php echo esc_attr( $id ); ?>-job" onchange="var d=this.closest('.jobly-demo');d.querySelectorAll('[data-job]').forEach(function(e){e.hidden=e.dataset.job!==this.value}.bind(this))">
			<?php foreach ( $jobs as $i => $job ) : ?>
				<option value="<?php echo esc_attr( (string) $i ); ?>"><?php echo esc_html( $job[0] ); ?></option>
			<?php endforeach; ?>
		</select>
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

/**
 * Own entry in the admin sidebar: Jobly HRM.
 */
function jobly_integration_admin_menu() {
	add_menu_page( 'Jobly.si HRM', 'Jobly HRM', 'manage_options', 'jobly-integration', 'jobly_integration_settings_page', 'dashicons-groups', 58 );
	add_submenu_page( 'jobly-integration', 'Jobly.si HRM', __( 'Nastavitve', 'jobly-integration' ), 'manage_options', 'jobly-integration', 'jobly_integration_settings_page' );
}
add_action( 'admin_menu', 'jobly_integration_admin_menu' );

/**
 * "Nastavitve" link next to Deactivate on the Plugins screen.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function jobly_integration_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=jobly-integration' ) ) . '">' . esc_html__( 'Nastavitve', 'jobly-integration' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'jobly_integration_action_links' );

function jobly_integration_register_setting() {
	register_setting(
		'jobly_integration',
		JOBLY_INTEGRATION_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'jobly_integration_sanitize',
		)
	);
}
add_action( 'admin_init', 'jobly_integration_register_setting' );

/**
 * @param mixed $input Raw form input.
 * @return array
 */
function jobly_integration_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	$base  = esc_url_raw( trim( (string) ( $input['base_url'] ?? '' ) ), array( 'https', 'http' ) );

	return array(
		'base_url' => '' !== $base ? untrailingslashit( $base ) : 'https://jobly.si',
		'company'  => sanitize_title( (string) ( $input['company'] ?? '' ) ),
		'accent'   => (string) sanitize_hex_color( (string) ( $input['accent'] ?? '' ) ),
		'height'   => max( 300, min( 3000, absint( $input['height'] ?? 720 ) ) ),
		'demo'     => empty( $input['demo'] ) ? 0 : 1,
	);
}

function jobly_integration_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s    = jobly_integration_settings();
	$name = JOBLY_INTEGRATION_OPTION;
	?>
	<div class="wrap">
		<h1>Jobly.si HRM</h1>
		<p><?php esc_html_e( 'Prijavni obrazec in seznam odprtih mest prikaže Jobly. Prijave, privolitve in zaščita pred neželeno pošto ostanejo na Jobly — WordPress podatkov kandidatov ne vidi.', 'jobly-integration' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'jobly_integration' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Demo podatki', 'jobly-integration' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[demo]" value="1" <?php checked( 1, (int) $s['demo'] ); ?>> <?php esc_html_e( 'Prikaži izmišljen primer namesto podatkov z Jobly', 'jobly-integration' ); ?></label>
						<p class="description"><?php esc_html_e( 'Za gradnjo in predstavitev strani. Izklopi, ko vpišeš svoje podjetje.', 'jobly-integration' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jobly-company"><?php esc_html_e( 'Podjetje (slug)', 'jobly-integration' ); ?></label></th>
					<td>
						<input id="jobly-company" class="regular-text" name="<?php echo esc_attr( $name ); ?>[company]" value="<?php echo esc_attr( $s['company'] ); ?>" placeholder="moje-podjetje">
						<p class="description"><?php esc_html_e( 'Zadnji del naslova profila na jobly.si/companies/…', 'jobly-integration' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jobly-accent"><?php esc_html_e( 'Barva poudarka', 'jobly-integration' ); ?></label></th>
					<td><input id="jobly-accent" type="color" name="<?php echo esc_attr( $name ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ? $s['accent'] : '#2563eb' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="jobly-height"><?php esc_html_e( 'Višina okvirja (px)', 'jobly-integration' ); ?></label></th>
					<td><input id="jobly-height" type="number" min="300" max="3000" name="<?php echo esc_attr( $name ); ?>[height]" value="<?php echo esc_attr( (string) $s['height'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="jobly-base"><?php esc_html_e( 'Naslov Jobly', 'jobly-integration' ); ?></label></th>
					<td>
						<input id="jobly-base" class="regular-text" type="url" name="<?php echo esc_attr( $name ); ?>[base_url]" value="<?php echo esc_attr( $s['base_url'] ); ?>">
						<p class="description"><?php esc_html_e( 'Pusti https://jobly.si. Drugo samo za testiranje.', 'jobly-integration' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2><?php esc_html_e( 'Uporaba', 'jobly-integration' ); ?></h2>
		<p><code>[jobly]</code> — <?php esc_html_e( 'vsa odprta mesta podjetja z obrazcem', 'jobly-integration' ); ?></p>
		<p><code>[jobly job="slug-oglasa" show="full"]</code> — <?php esc_html_e( 'en oglas z vsebino in obrazcem', 'jobly-integration' ); ?></p>
		<?php if ( $s['demo'] || '' !== $s['company'] ) : ?>
			<h2><?php esc_html_e( 'Predogled', 'jobly-integration' ); ?></h2>
			<?php echo jobly_integration_shortcode( array() ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
		<?php endif; ?>
	</div>
	<?php
}
