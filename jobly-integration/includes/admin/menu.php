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
	$top = add_menu_page( 'Jobly.si HRM', 'Jobly HRM', $cap, 'jobly-integration', 'jobly_integration_page_overview', jobly_integration_menu_icon(), 58 );
	add_submenu_page( 'jobly-integration', __( 'Pregled', 'jobly-integration' ), __( 'Pregled', 'jobly-integration' ), $cap, 'jobly-integration', 'jobly_integration_page_overview' );
	$jobs = add_submenu_page( 'jobly-integration', __( 'Delovna mesta', 'jobly-integration' ), __( 'Delovna mesta', 'jobly-integration' ), $cap, 'jobly-jobs', 'jobly_integration_page_jobs' );
	$new  = add_submenu_page( 'jobly-integration', __( 'Dodaj novo', 'jobly-integration' ), __( 'Dodaj novo', 'jobly-integration' ), $cap, 'jobly-job-new', 'jobly_integration_page_job_new' );
	$apps = add_submenu_page( 'jobly-integration', __( 'Prijave', 'jobly-integration' ), __( 'Prijave', 'jobly-integration' ), $cap, 'jobly-applications', 'jobly_integration_page_applications' );
	$set  = add_submenu_page( 'jobly-integration', __( 'Nastavitve', 'jobly-integration' ), __( 'Nastavitve', 'jobly-integration' ), $cap, 'jobly-settings', 'jobly_integration_page_settings' );
	// Empty parent: reachable by URL (redirect, links), not listed in the menu.
	$wiz = add_submenu_page( '', __( 'Čarovnik za nastavitev', 'jobly-integration' ), __( 'Čarovnik za nastavitev', 'jobly-integration' ), $cap, 'jobly-setup', 'jobly_integration_page_wizard' );

	add_action( 'load-' . $wiz, 'jobly_integration_wizard_title' );
	foreach ( array( $top, $jobs, $apps, $set, $new, $wiz ) as $hook ) {
		add_action( 'load-' . $hook, 'jobly_integration_help_tabs' );
	}

	// Screen options (per page) on the two list screens.
	add_action( 'load-' . $jobs, 'jobly_integration_screen_options' );
	add_action( 'load-' . $apps, 'jobly_integration_screen_options' );
}
add_action( 'admin_menu', 'jobly_integration_admin_menu' );

/**
 * Page title of the hidden wizard screen (core would pass null to strip_tags()).
 */
function jobly_integration_wizard_title() {
	global $title;
	$title = __( 'Čarovnik za nastavitev', 'jobly-integration' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- core reads $title for the admin header.
}

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
		'saved'             => array( 'success', __( 'Nastavitve so shranjene.', 'jobly-integration' ) ),
		'verified'          => array( 'success', __( 'Povezava deluje. Demo je izklopljen.', 'jobly-integration' ) ),
		'ok'                => array( 'success', __( 'Povezava deluje.', 'jobly-integration' ) ),
		'unauthorized'      => array( 'error', __( 'Napačen ključ: Jobly ga ne sprejme.', 'jobly-integration' ) ),
		'network'           => array( 'error', __( 'Ni povezave z Jobly. Preveri naslov in internetno povezavo strežnika.', 'jobly-integration' ) ),
		'error'             => array( 'error', __( 'Jobly je vrnil nepričakovan odgovor. Poskusi znova čez nekaj minut.', 'jobly-integration' ) ),
		'app_token_missing' => array( 'error', __( 'Jobly zahteva App-Token (žeton aplikacije). Vpišite ga v Povezavi ali ga določite s konstanto JOBLY_APP_TOKEN.', 'jobly-integration' ) ),
		'app_token_invalid' => array( 'error', __( 'App-Token ni veljaven: Jobly ga ne sprejme. Preverite žeton aplikacije (jba_…).', 'jobly-integration' ) ),
		'no_key'            => array( 'error', __( 'Najprej vpiši API ključ.', 'jobly-integration' ) ),
		'bad_key'           => array( 'error', __( 'Ključ ni v obliki jbl_…', 'jobly-integration' ) ),
		'key_removed'       => array( 'success', __( 'Ključ je odstranjen.', 'jobly-integration' ) ),
		'wizard_skip'       => array( 'info', __( 'Nastavitev lahko kadar koli dokončate v Nastavitvah.', 'jobly-integration' ) ),
		'created'           => array( 'success', __( 'Delovno mesto je objavljeno na Jobly.', 'jobly-integration' ) ),
		'refreshed'         => array( 'success', __( 'Seznam je osvežen.', 'jobly-integration' ) ),
		'forbidden'         => array( 'error', __( 'Jobly objave ne dovoli (npr. pogodba za delodajalce še ni podpisana).', 'jobly-integration' ) ),
		'not_found'         => array( 'error', __( 'Zapisa ni mogoče najti.', 'jobly-integration' ) ),
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
	jobly_integration_empty_state( 'connect', __( 'Podjetje še ni povezano', 'jobly-integration' ), __( 'Vpišite API ključ iz Jobly ali poskusite z demo podatki.', 'jobly-integration' ), jobly_integration_admin_url( 'jobly-setup' ), __( 'Zaženi čarovnika', 'jobly-integration' ) );
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
	echo '<div class="notice notice-error inline"><p>' . esc_html( $txt ) . ' <a href="' . esc_url( jobly_integration_admin_url( 'jobly-settings' ) ) . '">' . esc_html__( 'Preveri povezavo', 'jobly-integration' ) . '</a></p></div>';
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
