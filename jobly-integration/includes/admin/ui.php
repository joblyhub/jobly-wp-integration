<?php
/**
 * Admin UI building blocks: branded header, badges, empty states, copy fields, help, footer.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_DOCS_URL    = 'https://github.com/joblyhub/jobly-wp-integration#readme';
const JOBLY_INTEGRATION_SUPPORT_URL = 'https://github.com/joblyhub/jobly-wp-integration/issues';

/**
 * The current admin screen belongs to this plugin.
 *
 * @return bool
 */
function jobly_integration_is_plugin_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && false !== strpos( (string) $screen->id, 'jobly' );
}

/**
 * URL of the Jobly app for this company.
 *
 * @return string
 */
function jobly_integration_app_url() {
	return untrailingslashit( jobly_integration_settings()['base_url'] ) . '/app';
}

/**
 * Company logo (from Jobly) or a letter avatar; prints escaped markup.
 *
 * @param int $size Pixel size.
 */
function jobly_integration_company_badge( $size = 28 ) {
	$s    = jobly_integration_settings();
	$name = jobly_integration_company_name();
	if ( ! $s['demo'] && $s['company_logo'] ) {
		echo '<img class="jobly-co__logo" src="' . esc_url( $s['company_logo'] ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '">';
	} else {
		$svg             = sprintf(
			'<svg class="jobly-co__logo" xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" rx="10" fill="#dbeafe"/><text x="20" y="27" text-anchor="middle" font-family="system-ui,sans-serif" font-size="20" font-weight="700" fill="#1d4ed8">%2$s</text></svg>',
			(int) $size,
			esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) )
		);
		$allowed         = jobly_integration_svg_kses();
		$allowed['text'] = array(
			'x'           => true,
			'y'           => true,
			'text-anchor' => true,
			'font-family' => true,
			'font-size'   => true,
			'font-weight' => true,
			'fill'        => true,
		);
		echo wp_kses( $svg, $allowed );
	}
	echo '<span class="jobly-co__name">' . esc_html( $name ) . '</span>';
}

/**
 * Connection status pill.
 *
 * @return string Escaped HTML.
 */
function jobly_integration_status_pill() {
	$s = jobly_integration_settings();
	if ( $s['demo'] ) {
		return '<span class="jobly-pill jobly-pill--demo"><span class="jobly-dot"></span>' . esc_html__( 'Demo · izmišljeni podatki', 'jobly-integration' ) . '</span>';
	}
	if ( $s['connected'] ) {
		return '<span class="jobly-pill jobly-pill--ok"><span class="jobly-dot"></span>' . esc_html__( 'Povezano z Jobly', 'jobly-integration' ) . '</span>';
	}
	return '<span class="jobly-pill jobly-pill--off"><span class="jobly-dot"></span>' . esc_html__( 'Ni povezano', 'jobly-integration' ) . '</span>';
}

/**
 * Status badge of a job.
 *
 * @param string $status API status.
 * @return string Escaped HTML.
 */
function jobly_integration_status_badge( $status ) {
	$tones = array(
		'active'            => 'green',
		'closed'            => 'gray',
		'draft'             => 'amber',
		'private'           => 'gray',
		'ready_for_publish' => 'blue',
	);
	$tone  = $tones[ $status ] ?? 'gray';
	return '<span class="jobly-badge jobly-badge--' . esc_attr( $tone ) . '">' . esc_html( jobly_integration_status_label( $status ) ) . '</span>';
}

/**
 * Stage badge of an application.
 *
 * @param string $stage API stage.
 * @return string Escaped HTML.
 */
function jobly_integration_stage_badge( $stage ) {
	$tones = array(
		'new'             => 'blue',
		'reviewed'        => 'gray',
		'intro_round'     => 'violet',
		'technical_round' => 'violet',
		'final_round'     => 'violet',
		'decision'        => 'violet',
		'offer'           => 'green',
		'hired'           => 'green',
		'rejected'        => 'red',
		'cancelled'       => 'gray',
		'withdrawn'       => 'gray',
	);
	$tone  = $tones[ $stage ] ?? 'gray';
	return '<span class="jobly-badge jobly-badge--' . esc_attr( $tone ) . '">' . esc_html( jobly_integration_stage_label( $stage ) ) . '</span>';
}

/**
 * Two-letter initials for an avatar.
 *
 * @param string $name Full name.
 * @return string
 */
function jobly_integration_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( $name ) );
	$out   = '';
	foreach ( array_slice( (array) $parts, 0, 2 ) as $part ) {
		$out .= mb_strtoupper( mb_substr( (string) $part, 0, 1 ) );
	}
	return '' !== $out ? $out : '?';
}

/**
 * Small inline illustration for empty states.
 *
 * @param string $name jobs | applications | connect.
 */
function jobly_integration_illustration( $name ) {
	$art = array(
		'jobs'         => '<rect x="22" y="40" width="116" height="78" rx="12" fill="#eff6ff"/><rect x="22" y="40" width="116" height="78" rx="12" stroke="#93c5fd" stroke-width="2"/><path d="M62 40v-8a8 8 0 0 1 8-8h20a8 8 0 0 1 8 8v8" stroke="#2563eb" stroke-width="3" stroke-linecap="round"/><path d="M22 76h116" stroke="#93c5fd" stroke-width="2"/><rect x="70" y="68" width="20" height="16" rx="4" fill="#2563eb"/><circle cx="132" cy="30" r="5" fill="#60a5fa"/><circle cx="26" cy="24" r="3" fill="#bfdbfe"/><path d="M142 78l3 7l7 3l-7 3l-3 7l-3-7l-7-3l7-3z" fill="#2563eb" opacity=".55"/>',
		'applications' => '<rect x="34" y="22" width="92" height="104" rx="12" fill="#eff6ff"/><rect x="34" y="22" width="92" height="104" rx="12" stroke="#93c5fd" stroke-width="2"/><circle cx="80" cy="56" r="14" fill="#2563eb"/><path d="M52 104c0-15 12-26 28-26s28 11 28 26" stroke="#2563eb" stroke-width="3" stroke-linecap="round" fill="#dbeafe"/><rect x="50" y="108" width="60" height="6" rx="3" fill="#bfdbfe"/><circle cx="134" cy="34" r="6" fill="#60a5fa"/><circle cx="24" cy="100" r="4" fill="#bfdbfe"/>',
		'connect'      => '<rect x="14" y="52" width="48" height="48" rx="12" fill="#eff6ff" stroke="#93c5fd" stroke-width="2"/><rect x="98" y="52" width="48" height="48" rx="12" fill="#2563eb"/><path d="M62 76h36" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-dasharray="2 8"/><path d="M30 76h16M38 68v16" stroke="#2563eb" stroke-width="3" stroke-linecap="round"/><path d="M114 76l8 8l12-14" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>',
	);
	if ( ! isset( $art[ $name ] ) ) {
		return;
	}
	$svg = '<svg class="jobly-illustration" xmlns="http://www.w3.org/2000/svg" width="160" height="144" viewBox="0 0 160 144" fill="none" aria-hidden="true" focusable="false">' . $art[ $name ] . '</svg>';
	echo wp_kses( $svg, jobly_integration_svg_kses() );
}

/**
 * Empty state card.
 *
 * @param string $art       Illustration name.
 * @param string $title     Heading.
 * @param string $text      Explanation.
 * @param string $cta_url   Primary button URL ('' = none).
 * @param string $cta_label Primary button label.
 */
function jobly_integration_empty_state( $art, $title, $text, $cta_url = '', $cta_label = '' ) {
	echo '<div class="jobly-empty">';
	jobly_integration_illustration( $art );
	echo '<h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p>';
	if ( '' !== $cta_url ) {
		echo '<a class="button button-primary button-hero" href="' . esc_url( $cta_url ) . '">' . esc_html( $cta_label ) . '</a>';
	}
	echo '</div>';
}

/**
 * A read-only value with a copy button (feedback "Kopirano" comes from admin.js).
 *
 * @param string $label Field label.
 * @param string $value Text to copy.
 * @param bool   $multi Show as a textarea.
 */
function jobly_integration_copy_field( $label, $value, $multi = false ) {
	echo '<div class="jobly-copyfield"><span class="jobly-copyfield__label">' . esc_html( $label ) . '</span><div class="jobly-copyfield__row">';
	if ( $multi ) {
		echo '<textarea readonly rows="3" class="jobly-copyfield__value">' . esc_textarea( $value ) . '</textarea>';
	} else {
		echo '<code class="jobly-copyfield__value">' . esc_html( $value ) . '</code>';
	}
	echo '<button type="button" class="button jobly-copybtn" data-jobly-copy="' . esc_attr( $value ) . '" data-copied="' . esc_attr__( 'Kopirano', 'jobly-integration' ) . '">';
	jobly_integration_icon( 'copy', '', 16 );
	echo '<span class="jobly-copybtn__label">' . esc_html__( 'Kopiraj', 'jobly-integration' ) . '</span></button></div></div>';
}

/**
 * Media Library image field (hidden input + preview + pick/clear buttons, see admin.js).
 *
 * @param string $name Input name.
 * @param int    $id   Attachment ID or 0.
 */
function jobly_integration_media_field( $name, $id ) {
	?>
	<div class="jobly-media" data-jobly-media>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $id ); ?>">
		<div class="jobly-media__preview"><?php echo $id ? wp_get_attachment_image( (int) $id, 'medium' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core builds and escapes the attachment <img>. ?></div>
		<button type="button" class="button" data-jobly-media-pick data-title="<?php esc_attr_e( 'Izberi sliko', 'jobly-integration' ); ?>"><?php esc_html_e( 'Izberi iz knjižnice', 'jobly-integration' ); ?></button>
		<button type="button" class="button-link" data-jobly-media-clear><?php esc_html_e( 'Odstrani', 'jobly-integration' ); ?></button>
	</div>
	<?php
}

/**
 * Branded header: logo, status, links, section tabs, title.
 *
 * @param string $title      Screen title.
 * @param string $action_url URL of the title action button, or ''.
 * @param string $action_txt Its label.
 */
function jobly_integration_header( $title, $action_url = '', $action_txt = '' ) {
	$s_hdr = jobly_integration_settings();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$tabs = array(
		'jobly-integration'  => array( __( 'Pregled', 'jobly-integration' ), 'trending' ),
		'jobly-jobs'         => array( __( 'Delovna mesta', 'jobly-integration' ), 'briefcase' ),
		'jobly-applications' => array( __( 'Prijave', 'jobly-integration' ), 'users' ),
		'jobly-job-new'      => array( __( 'Dodaj novo', 'jobly-integration' ), 'plus' ),
		'jobly-settings'     => array( __( 'Nastavitve', 'jobly-integration' ), 'sliders' ),
	);

	echo '<div class="wrap jobly-admin"><header class="jobly-hdr"><div class="jobly-hdr__brand">';
	jobly_integration_logo( 34 );
	echo '<span class="jobly-hdr__name">Jobly<span>.si</span> HRM</span><span class="jobly-ver">v' . esc_html( JOBLY_INTEGRATION_VERSION ) . '</span></div><div class="jobly-hdr__right">';
	if ( $s_hdr['demo'] || ( $s_hdr['connected'] && $s_hdr['api_key'] ) ) {
		echo '<span class="jobly-co">';
		jobly_integration_company_badge( 26 );
		echo '</span>';
	}
	echo wp_kses_post( jobly_integration_status_pill() );
	echo '<a class="jobly-hdr__link" href="' . esc_url( jobly_integration_app_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Odpri Jobly', 'jobly-integration' ) . ' ';
	jobly_integration_icon( 'external', '', 16 );
	echo '</a></div></header>';

	echo '<nav class="jobly-tabs" aria-label="' . esc_attr__( 'Jobly HRM', 'jobly-integration' ) . '">';
	foreach ( $tabs as $slug => $tab ) {
		echo '<a href="' . esc_url( jobly_integration_admin_url( $slug ) ) . '" class="jobly-tabs__item' . ( $slug === $page ? ' is-active' : '' ) . '"' . ( $slug === $page ? ' aria-current="page"' : '' ) . '>';
		jobly_integration_icon( $tab[1], '', 18 );
		echo esc_html( $tab[0] ) . '</a>';
	}
	echo '</nav>';

	echo '<div class="jobly-titlebar"><h1>' . esc_html( $title ) . '</h1>';
	if ( '' !== $action_url ) {
		echo '<a href="' . esc_url( $action_url ) . '" class="button button-primary">' . esc_html( $action_txt ) . '</a>';
	}
	echo '</div><hr class="wp-header-end">';
	jobly_integration_notices();
}

/**
 * Close the wrap opened by jobly_integration_header().
 */
function jobly_integration_footer() {
	echo '</div>';
}

/**
 * Footer line on our screens only: "Jobly.si HRM vX · Dokumentacija · Podpora".
 *
 * @param string $text Default footer text.
 * @return string
 */
function jobly_integration_admin_footer_text( $text ) {
	if ( ! jobly_integration_is_plugin_screen() ) {
		return $text;
	}
	return sprintf(
		'<span class="jobly-footer">Jobly.si HRM v%1$s &middot; <a href="%2$s" target="_blank" rel="noopener">%3$s</a> &middot; <a href="%4$s" target="_blank" rel="noopener">%5$s</a></span>',
		esc_html( JOBLY_INTEGRATION_VERSION ),
		esc_url( JOBLY_INTEGRATION_DOCS_URL ),
		esc_html__( 'Dokumentacija', 'jobly-integration' ),
		esc_url( JOBLY_INTEGRATION_SUPPORT_URL ),
		esc_html__( 'Podpora', 'jobly-integration' )
	);
}
add_filter( 'admin_footer_text', 'jobly_integration_admin_footer_text' );

/**
 * "Pomoč" tab on our screens.
 */
function jobly_integration_help_tabs() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}
	$screen->add_help_tab(
		array(
			'id'      => 'jobly-start',
			'title'   => __( 'Začetek', 'jobly-integration' ),
			'content' => '<p>' . esc_html__( '1. V Jobly → Integracije → API žetoni ustvarite ključ. 2. V Nastavitvah ga prilepite in shranite. 3. Karierna stran se prikaže na naslovu, ki ga izberete v nastavitvah Prikaz.', 'jobly-integration' ) . '</p>',
		)
	);
	$screen->add_help_tab(
		array(
			'id'      => 'jobly-shortcodes',
			'title'   => __( 'Kratke kode in bloki', 'jobly-integration' ),
			'content' => '<p><code>[jobly]</code> · <code>[jobly job="slug" show="full"]</code> · <code>[jobly_jobs number="6" filters="1" layout="grid"]</code></p><p>' . esc_html__( 'V urejevalniku blokov poiščite »Jobly«: seznam delovnih mest in prijavni obrazec.', 'jobly-integration' ) . '</p>',
		)
	);
	$screen->add_help_tab(
		array(
			'id'      => 'jobly-privacy',
			'title'   => __( 'Zasebnost', 'jobly-integration' ),
			'content' => '<p>' . esc_html__( 'Prijave, privolitve in zaščita pred neželeno pošto ostanejo na Jobly. Podatki kandidatov se v WordPressu ne shranjujejo ne predpomnijo.', 'jobly-integration' ) . '</p>',
		)
	);
	$screen->set_help_sidebar(
		'<p><strong>' . esc_html__( 'Več', 'jobly-integration' ) . '</strong></p><p><a href="' . esc_url( JOBLY_INTEGRATION_DOCS_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Dokumentacija', 'jobly-integration' ) . '</a></p><p><a href="' . esc_url( JOBLY_INTEGRATION_SUPPORT_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Podpora', 'jobly-integration' ) . '</a></p>'
	);
}

/**
 * Suggested privacy-policy text (Nastavitve → Zasebnost → Pravilnik o zasebnosti).
 */
function jobly_integration_privacy_policy() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$text  = '<h3>' . esc_html__( 'Jobly.si HRM (karierna stran)', 'jobly-integration' ) . '</h3>';
	$text .= '<p>' . esc_html__( 'Na naši karierni strani so delovna mesta prikazana iz sistema Jobly.si, prijavni obrazec pa je vgrajen kot okvir (iframe), ki se naloži s strežnika Jobly.si. Ko odprete stran z oglasom ali obrazcem, vaš brskalnik vzpostavi povezavo z Jobly.si, ki prejme vaš IP naslov in naslov strani, s katere prihajate.', 'jobly-integration' ) . '</p>';
	$text .= '<p>' . esc_html__( 'Podatke, ki jih vpišete v prijavni obrazec (ime, e-naslov, telefon, sporočilo, življenjepis), prejme in obdeluje Jobly.si v skladu z lastnimi pogoji in pravilnikom o zasebnosti (https://jobly.si/privacy); nam jih posreduje kot del postopka zaposlovanja. Ta spletna stran podatkov kandidatov ne shranjuje in ne predpomni; skrbniki jih lahko vidijo v skrbniškem delu le za čas ogleda.', 'jobly-integration' ) . '</p>';
	wp_add_privacy_policy_content( 'Jobly.si HRM', wp_kses_post( wpautop( $text, false ) ) );
}
add_action( 'admin_init', 'jobly_integration_privacy_policy' );
