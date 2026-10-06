<?php
/**
 * Admin menu, shared header, notices and helpers.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens: slug => [menu title, callback file function, per-page option].
 */
function jobly_integration_admin_menu() {
	$cap = 'manage_options';
	add_menu_page( 'Jobly.si HRM', 'Jobly HRM', $cap, 'jobly-integration', 'jobly_integration_page_overview', 'dashicons-groups', 58 );
	add_submenu_page( 'jobly-integration', __( 'Pregled', 'jobly-integration' ), __( 'Pregled', 'jobly-integration' ), $cap, 'jobly-integration', 'jobly_integration_page_overview' );
	$jobs = add_submenu_page( 'jobly-integration', __( 'Delovna mesta', 'jobly-integration' ), __( 'Delovna mesta', 'jobly-integration' ), $cap, 'jobly-jobs', 'jobly_integration_page_jobs' );
	add_submenu_page( 'jobly-integration', __( 'Dodaj novo', 'jobly-integration' ), __( 'Dodaj novo', 'jobly-integration' ), $cap, 'jobly-job-new', 'jobly_integration_page_job_new' );
	$apps = add_submenu_page( 'jobly-integration', __( 'Prijave', 'jobly-integration' ), __( 'Prijave', 'jobly-integration' ), $cap, 'jobly-applications', 'jobly_integration_page_applications' );
	add_submenu_page( 'jobly-integration', __( 'Nastavitve', 'jobly-integration' ), __( 'Nastavitve', 'jobly-integration' ), $cap, 'jobly-settings', 'jobly_integration_page_settings' );

	// Screen options (per page) on the two list screens.
	add_action( 'load-' . $jobs, 'jobly_integration_screen_options' );
	add_action( 'load-' . $apps, 'jobly_integration_screen_options' );
}
add_action( 'admin_menu', 'jobly_integration_admin_menu' );

/**
 * "Zaslonske možnosti": number of rows per page, only on index screens.
 */
function jobly_integration_screen_options() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	if ( isset( $_GET['action'] ) && 'show' === $_GET['action'] ) {
		return;
	}
	add_screen_option(
		'per_page',
		array(
			'label'   => __( 'Vrstic na stran', 'jobly-integration' ),
			'default' => 20,
			'option'  => 'jobly_integration_per_page',
		)
	);
}

/**
 * Persist the screen option.
 *
 * @param mixed  $status Current value.
 * @param string $option Option name.
 * @param mixed  $value  Submitted value.
 * @return mixed
 */
function jobly_integration_save_screen_option( $status, $option, $value ) {
	return 'jobly_integration_per_page' === $option ? max( 1, min( 100, (int) $value ) ) : $status;
}
add_filter( 'set-screen-option', 'jobly_integration_save_screen_option', 10, 3 );

/**
 * Admin CSS/JS only on our screens.
 *
 * @param string $hook Admin page hook.
 */
function jobly_integration_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'jobly-' ) && false === strpos( $hook, 'jobly_' ) ) {
		return;
	}
	wp_enqueue_script( 'jobly-integration-admin', plugins_url( 'assets/admin.js', JOBLY_INTEGRATION_FILE ), array(), JOBLY_INTEGRATION_VERSION, true );
	wp_enqueue_style( 'jobly-integration-admin', plugins_url( 'assets/admin.css', JOBLY_INTEGRATION_FILE ), array(), JOBLY_INTEGRATION_VERSION );
}
add_action( 'admin_enqueue_scripts', 'jobly_integration_admin_assets' );

/**
 * "Nastavitve" link next to Deactivate on the Plugins screen.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function jobly_integration_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( jobly_integration_admin_url( 'jobly-settings' ) ) . '">' . esc_html__( 'Nastavitve', 'jobly-integration' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( JOBLY_INTEGRATION_FILE ), 'jobly_integration_action_links' );

/**
 * URL of one of our admin screens.
 *
 * @param string $page Screen slug.
 * @param array  $args Extra query args.
 * @return string
 */
function jobly_integration_admin_url( $page, array $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

/**
 * Redirect to one of our screens, with an optional notice code.
 *
 * @param string $page Screen slug.
 * @param array  $args Extra query args.
 */
function jobly_integration_redirect( $page, array $args = array() ) {
	wp_safe_redirect( jobly_integration_admin_url( $page, $args ) );
	exit;
}

/**
 * Capability gate for admin-post handlers (each also calls check_admin_referer).
 */
function jobly_integration_require_cap() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Nimate dovoljenja za to dejanje.', 'jobly-integration' ), '', array( 'response' => 403 ) );
	}
}

/**
 * Notice texts by code (the code travels in the URL, never the text).
 *
 * @return array<string,array{0:string,1:string}> code => [class, text].
 */
function jobly_integration_notice_texts() {
	return array(
		'saved'        => array( 'success', __( 'Nastavitve so shranjene.', 'jobly-integration' ) ),
		'verified'     => array( 'success', __( 'Povezava deluje. Demo je izklopljen.', 'jobly-integration' ) ),
		'ok'           => array( 'success', __( 'Povezava deluje.', 'jobly-integration' ) ),
		'unauthorized' => array( 'error', __( 'Napačen ključ: Jobly ga ne sprejme.', 'jobly-integration' ) ),
		'network'      => array( 'error', __( 'Ni povezave z Jobly. Preveri naslov in internetno povezavo strežnika.', 'jobly-integration' ) ),
		'error'        => array( 'error', __( 'Jobly je vrnil nepričakovan odgovor. Poskusi znova čez nekaj minut.', 'jobly-integration' ) ),
		'no_key'       => array( 'error', __( 'Najprej vpiši API ključ.', 'jobly-integration' ) ),
		'bad_key'      => array( 'error', __( 'Ključ ni v obliki jbl_…', 'jobly-integration' ) ),
		'key_removed'  => array( 'success', __( 'Ključ je odstranjen.', 'jobly-integration' ) ),
		'created'      => array( 'success', __( 'Delovno mesto je objavljeno na Jobly.', 'jobly-integration' ) ),
		'refreshed'    => array( 'success', __( 'Seznam je osvežen.', 'jobly-integration' ) ),
		'forbidden'    => array( 'error', __( 'Jobly objave ne dovoli (npr. pogodba za delodajalce še ni podpisana).', 'jobly-integration' ) ),
		'not_found'    => array( 'error', __( 'Zapisa ni mogoče najti.', 'jobly-integration' ) ),
	);
}

/**
 * Notice from ?jobly_msg=code.
 */
function jobly_integration_notices() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, code is whitelisted.
	$code  = isset( $_GET['jobly_msg'] ) ? sanitize_key( wp_unslash( $_GET['jobly_msg'] ) ) : '';
	$texts = jobly_integration_notice_texts();
	if ( isset( $texts[ $code ] ) ) {
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $texts[ $code ][0] ), esc_html( $texts[ $code ][1] ) );
	}
}

/**
 * Page header: title, optional "Dodaj novo" button, connection status.
 *
 * @param string $title      Screen title.
 * @param string $action_url URL of the page-title-action button, or ''.
 * @param string $action_txt Its label.
 */
function jobly_integration_header( $title, $action_url = '', $action_txt = '' ) {
	$s = jobly_integration_settings();
	echo '<div class="wrap jobly-wrap"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
	if ( '' !== $action_url ) {
		echo ' <a href="' . esc_url( $action_url ) . '" class="page-title-action">' . esc_html( $action_txt ) . '</a>';
	}
	echo '<span class="jobly-status">';
	if ( $s['demo'] ) {
		echo '<span class="jobly-badge jobly-badge--demo">' . esc_html__( 'Demo · izmišljeni podatki', 'jobly-integration' ) . '</span>';
	} elseif ( $s['connected'] ) {
		echo '<span class="jobly-badge jobly-badge--ok">✓ ' . esc_html__( 'Povezano z Jobly', 'jobly-integration' ) . ' · ' . esc_html( jobly_integration_masked_key() ) . '</span>';
	} else {
		echo '<span class="jobly-badge jobly-badge--off">' . esc_html__( 'Ni povezano', 'jobly-integration' ) . '</span>';
	}
	echo '</span><hr class="wp-header-end">';
	jobly_integration_notices();
}

/**
 * Close the wrap opened by jobly_integration_header().
 */
function jobly_integration_footer() {
	echo '</div>';
}

/**
 * Whether data screens can show anything (demo or a key). Otherwise prints the
 * "connect first" notice.
 *
 * @return bool
 */
function jobly_integration_require_connection() {
	$s = jobly_integration_settings();
	if ( $s['demo'] || ( $s['api_key'] && $s['connected'] ) ) {
		return true;
	}
	echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Povežite podjetje v Nastavitvah.', 'jobly-integration' ) . ' <a href="' . esc_url( jobly_integration_admin_url( 'jobly-settings' ) ) . '">' . esc_html__( 'Odpri Nastavitve', 'jobly-integration' ) . '</a></p></div>';
	return false;
}

/**
 * Print an error box for a failed API result.
 *
 * @param int $code HTTP code (0 = network).
 */
function jobly_integration_api_error_notice( $code ) {
	$msg = 0 === $code ? 'network' : ( 401 === $code ? 'unauthorized' : 'error' );
	$txt = jobly_integration_notice_texts()[ $msg ][1];
	echo '<div class="notice notice-error inline"><p>' . esc_html( $txt ) . '</p></div>';
}

/**
 * Rows per page for list screens.
 *
 * @return int
 */
function jobly_integration_per_page() {
	$n = (int) get_user_option( 'jobly_integration_per_page' );
	return $n > 0 ? $n : 20;
}

/**
 * URL to edit a job on Jobly (browser-facing).
 *
 * @param string $slug Job slug.
 * @return string
 */
function jobly_integration_edit_url( $slug ) {
	$s    = jobly_integration_settings();
	$base = untrailingslashit( $s['base_url'] );
	return '' !== $s['company']
		? $base . '/app/companies/' . rawurlencode( $s['company'] ) . '/jobs/' . rawurlencode( $slug ) . '/edit'
		: $base . '/app';
}
