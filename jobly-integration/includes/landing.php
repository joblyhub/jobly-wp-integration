<?php
/**
 * Custom careers landing: sections, code slots and where they are output.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_LANDING_OPTION = 'jobly_integration_landing';

/**
 * Sections that can be switched on and ordered.
 *
 * @return array<string,string> key => label.
 */
function jobly_integration_landing_sections() {
	return array(
		'hero'     => __( 'Hero (naslov, podnaslov, slika, gumb)', 'jobly-integration' ),
		'intro'    => __( 'Uvodno besedilo', 'jobly-integration' ),
		'benefits' => __( 'Zakaj delati pri nas', 'jobly-integration' ),
		'jobs'     => __( 'Seznam oglasov', 'jobly-integration' ),
	);
}

/**
 * Icons offered for the benefits.
 *
 * @return string[]
 */
function jobly_integration_landing_icons() {
	return array( 'briefcase', 'users', 'coin', 'home', 'clock', 'rocket', 'shield-check', 'book', 'world', 'calendar', 'trending', 'sparkles' );
}

/**
 * Defaults: the plain list with filters, as before the landing builder existed.
 *
 * @return array
 */
function jobly_integration_landing_defaults() {
	return array(
		'enabled'        => array(
			'hero'     => 0,
			'intro'    => 0,
			'benefits' => 0,
			'jobs'     => 1,
		),
		'filters'        => 1,
		'order'          => array( 'hero', 'intro', 'benefits', 'jobs' ),
		'hero_title'     => '',
		'hero_subtitle'  => '',
		'hero_image'     => 0,
		'hero_cta'       => '',
		'intro_html'     => '',
		'benefits_title' => '',
		'benefits'       => array(),
		'html_top'       => '',
		'html_bottom'    => '',
		'html_raw'       => 0,
		'css'            => '',
		'js'             => '',
	);
}

/**
 * Saved landing settings merged over the defaults.
 *
 * @return array
 */
function jobly_integration_landing() {
	$saved          = get_option( JOBLY_INTEGRATION_LANDING_OPTION, array() );
	$out            = wp_parse_args( is_array( $saved ) ? $saved : array(), jobly_integration_landing_defaults() );
	$out['enabled'] = wp_parse_args( (array) $out['enabled'], jobly_integration_landing_defaults()['enabled'] );
	return $out;
}

/**
 * Save the landing settings (autoload off).
 *
 * @param array $landing Full settings.
 */
function jobly_integration_landing_save( array $landing ) {
	if ( false === get_option( JOBLY_INTEGRATION_LANDING_OPTION ) ) {
		add_option( JOBLY_INTEGRATION_LANDING_OPTION, $landing, '', false );
	} else {
		update_option( JOBLY_INTEGRATION_LANDING_OPTION, $landing, false );
	}
}

/**
 * The request is a careers page: our virtual pages or the chosen WordPress page.
 *
 * @return bool
 */
function jobly_integration_is_careers_request() {
	$id = jobly_integration_careers_page_id();
	return jobly_integration_is_virtual() || ( $id && is_page( $id ) );
}

/**
 * The request is the careers index (not a single job).
 *
 * @return bool
 */
function jobly_integration_is_careers_index() {
	$id = jobly_integration_careers_page_id();
	return (bool) get_query_var( 'jobly_index' ) || ( $id && is_page( $id ) );
}

/**
 * Custom CSS (every careers page) and JS (careers index) are added only here, never in wp-admin.
 */
function jobly_integration_landing_assets() {
	if ( is_admin() || ! jobly_integration_is_careers_request() ) {
		return;
	}
	$landing = jobly_integration_landing();
	if ( '' !== trim( $landing['css'] ) ) {
		jobly_integration_enqueue_frontend();
		wp_add_inline_style( 'jobly-integration', wp_strip_all_tags( $landing['css'] ) );
	}
	if ( '' !== trim( $landing['js'] ) && jobly_integration_is_careers_index() ) {
		wp_register_script( 'jobly-integration-landing', false, array(), JOBLY_INTEGRATION_VERSION, true );
		wp_enqueue_script( 'jobly-integration-landing' );
		wp_add_inline_script( 'jobly-integration-landing', $landing['js'] ); // Only users with unfiltered_html can save this field.
	}
}
add_action( 'wp_enqueue_scripts', 'jobly_integration_landing_assets', 20 );

/**
 * One saved HTML slot, filtered with wp_kses_post() unless it was saved by a user with unfiltered_html.
 *
 * @param string $key html_top or html_bottom.
 */
function jobly_integration_landing_slot( $key ) {
	$landing = jobly_integration_landing();
	$html    = (string) $landing[ $key ];
	if ( '' === trim( $html ) ) {
		return;
	}
	if ( $landing['html_raw'] ) {
		echo do_shortcode( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-written, saved by a user with the unfiltered_html capability.
	} else {
		echo do_shortcode( wp_kses_post( $html ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd at save and again here; shortcodes run only in this admin-written slot, never in API data.
	}
}

/**
 * The landing page content (sections mode).
 *
 * @return string Escaped HTML.
 */
function jobly_integration_render_landing() {
	$landing = jobly_integration_landing();
	$order   = array_values( array_unique( array_merge( array_intersect( (array) $landing['order'], array_keys( jobly_integration_landing_sections() ) ), array_keys( jobly_integration_landing_sections() ) ) ) );
	jobly_integration_enqueue_frontend();
	return jobly_integration_render_template(
		'landing.php',
		array(
			'landing' => $landing,
			'order'   => $order,
			'demo'    => jobly_integration_is_demo(),
		)
	);
}

/**
 * Title of the careers index: the hero title when the hero is on.
 *
 * @return string
 */
function jobly_integration_index_title() {
	$landing = jobly_integration_landing();
	if ( ! jobly_integration_careers_page_id() && $landing['enabled']['hero'] && '' !== trim( (string) $landing['hero_title'] ) ) {
		return (string) $landing['hero_title'];
	}
	return __( 'Kariera', 'jobly-integration' );
}
