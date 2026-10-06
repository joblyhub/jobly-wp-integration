<?php
/**
 * Plugin Name:       Jobly.si — prijavni obrazec
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
 * @return array{base_url: string, company: string, accent: string, height: int}
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
		),
		$atts,
		'jobly'
	);

	$job     = sanitize_title( $atts['job'] );
	$company = sanitize_title( $atts['company'] );

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
 * Settings → Jobly.si
 */
function jobly_integration_admin_menu() {
	add_options_page( 'Jobly.si', 'Jobly.si', 'manage_options', 'jobly-integration', 'jobly_integration_settings_page' );
}
add_action( 'admin_menu', 'jobly_integration_admin_menu' );

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
		<h1>Jobly.si</h1>
		<p><?php esc_html_e( 'Prijavni obrazec in seznam odprtih mest prikaže Jobly. Prijave, privolitve in zaščita pred neželeno pošto ostanejo na Jobly — WordPress podatkov kandidatov ne vidi.', 'jobly-integration' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'jobly_integration' ); ?>
			<table class="form-table" role="presentation">
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
		<?php if ( '' !== $s['company'] ) : ?>
			<h2><?php esc_html_e( 'Predogled', 'jobly-integration' ); ?></h2>
			<?php echo jobly_integration_shortcode( array() ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
		<?php endif; ?>
	</div>
	<?php
}
