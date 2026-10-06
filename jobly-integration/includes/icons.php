<?php
/**
 * Inline SVG icons (Tabler style, MIT) and the Jobly mark. No icon font, no CDN.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Icon bodies by name (24x24, stroke).
 *
 * @return array<string,string>
 */
function jobly_integration_icon_paths() {
	return array(
		'briefcase'     => '<path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 13a20 20 0 0 0 18 0"/>',
		'users'         => '<path d="M5 7a4 4 0 1 0 8 0a4 4 0 1 0-8 0"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.85"/>',
		'user'          => '<path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0-8 0"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>',
		'file-text'     => '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M9 9h1"/><path d="M9 13h6"/><path d="M9 17h6"/>',
		'inbox'         => '<path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M4 13h3l3 3h4l3-3h3"/>',
		'map-pin'       => '<path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"/>',
		'clock'         => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0-18 0"/><path d="M12 7v5l3 3"/>',
		'home'          => '<path d="M5 12H3l9-9l9 9h-2"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M9 21v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6"/>',
		'external'      => '<path d="M12 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/><path d="M11 13l9-9"/><path d="M15 4h5v5"/>',
		'copy'          => '<path d="M7 9.667A2.667 2.667 0 0 1 9.667 7h8.666A2.667 2.667 0 0 1 21 9.667v8.666A2.667 2.667 0 0 1 18.333 21H9.667A2.667 2.667 0 0 1 7 18.333z"/><path d="M4.012 16.737A2.005 2.005 0 0 1 3 15V5c0-1.1.9-2 2-2h10c.75 0 1.158.385 1.5 1"/>',
		'search'        => '<path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0-14 0"/><path d="M21 21l-6-6"/>',
		'arrow-left'    => '<path d="M5 12h14"/><path d="M5 12l6 6"/><path d="M5 12l6-6"/>',
		'arrow-right'   => '<path d="M5 12h14"/><path d="M13 18l6-6"/><path d="M13 6l6 6"/>',
		'plus'          => '<path d="M12 5v14"/><path d="M5 12h14"/>',
		'check'         => '<path d="M5 12l5 5l10-10"/>',
		'circle-check'  => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/><path d="M9 12l2 2l4-4"/>',
		'circle'        => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/>',
		'sliders'       => '<path d="M4 6h8"/><path d="M16 6h4"/><path d="M12 6a2 2 0 1 0 4 0a2 2 0 1 0-4 0"/><path d="M4 12h2"/><path d="M10 12h10"/><path d="M6 12a2 2 0 1 0 4 0a2 2 0 1 0-4 0"/><path d="M4 18h10"/><path d="M18 18h2"/><path d="M14 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0"/>',
		'plug'          => '<path d="M9.785 6l8.215 8.215l-2.054 2.054a5.81 5.81 0 1 1-8.215-8.215l2.054-2.054z"/><path d="M4 20l3.5-3.5"/><path d="M15 4l-3.5 3.5"/><path d="M20 9l-3.5 3.5"/>',
		'world'         => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/><path d="M3.6 9h16.8"/><path d="M3.6 15h16.8"/><path d="M11.5 3a17 17 0 0 0 0 18"/><path d="M12.5 3a17 17 0 0 1 0 18"/>',
		'rocket'        => '<path d="M4 13a8 8 0 0 1 7 7a6 6 0 0 0 3-5a9 9 0 0 0 6-8a3 3 0 0 0-3-3a9 9 0 0 0-8 6a6 6 0 0 0-5 3"/><path d="M7 14a6 6 0 0 0-3 6a6 6 0 0 0 6-3"/><path d="M14 9a1 1 0 1 0 2 0a1 1 0 1 0-2 0"/>',
		'help'          => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/><path d="M12 17v.01"/><path d="M12 13.5a1.5 1.5 0 0 1 1-1.5a2.6 2.6 0 1 0-3-4"/>',
		'alert'         => '<path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636-2.87l-8.106-13.536a1.914 1.914 0 0 0-3.274 0z"/><path d="M12 16h.01"/>',
		'chevron-right' => '<path d="M9 6l6 6l-6 6"/>',
		'refresh'       => '<path d="M20 11a8.1 8.1 0 0 0-15.5-2m-.5-4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>',
		'link'          => '<path d="M9 15l6-6"/><path d="M11 6l.463-.536a5 5 0 0 1 7.071 7.072l-.534.464"/><path d="M13 18l-.397.534a5.068 5.068 0 0 1-7.127 0a4.972 4.972 0 0 1 0-7.071l.524-.463"/>',
		'code'          => '<path d="M7 8l-4 4l4 4"/><path d="M17 8l4 4l-4 4"/><path d="M14 4l-4 16"/>',
		'palette'       => '<path d="M12 21a9 9 0 0 1 0-18c4.97 0 9 3.582 9 8c0 1.06-.474 2.078-1.318 2.828c-.844.75-1.989 1.172-3.182 1.172h-2.5a2 2 0 0 0-1 3.75a1.3 1.3 0 0 1-.5 2.25"/><path d="M7.5 10.5a1 1 0 1 0 2 0a1 1 0 1 0-2 0"/><path d="M11.5 7.5a1 1 0 1 0 2 0a1 1 0 1 0-2 0"/><path d="M15.5 10.5a1 1 0 1 0 2 0a1 1 0 1 0-2 0"/>',
		'layout-list'   => '<path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M4 16a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/>',
		'layout-grid'   => '<path d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z"/><path d="M14 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z"/><path d="M4 15a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z"/><path d="M14 15a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z"/>',
		'building'      => '<path d="M3 21h18"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/><path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>',
		'calendar'      => '<path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M16 3v4"/><path d="M8 3v4"/><path d="M4 11h16"/>',
		'coin'          => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/><path d="M14.5 9a3.5 3.5 0 1 0 0 6"/><path d="M8 11h5"/><path d="M8 13h5"/>',
		'trending'      => '<path d="M3 17l6-6l4 4l8-8"/><path d="M14 7h7v7"/>',
		'mail'          => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 7l9 6l9-6"/>',
		'x'             => '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
		'book'          => '<path d="M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0"/><path d="M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0"/><path d="M3 6v13"/><path d="M12 6v13"/><path d="M21 6v13"/>',
		'filter'        => '<path d="M4 4h16v2.172a2 2 0 0 1-.586 1.414L15 12v7l-6 2v-8.5L4.52 7.572A2 2 0 0 1 4 6.172z"/>',
		'lifebuoy'      => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/><path d="M9 12a3 3 0 1 0 6 0a3 3 0 1 0-6 0"/><path d="M15 15l3.35 3.35"/><path d="M9 15l-3.35 3.35"/><path d="M5.65 5.65L9 9"/><path d="M18.35 5.65L15 9"/>',
		'shield-check'  => '<path d="M11.46 20.846A12 12 0 0 1 4 6a12 12 0 0 0 8-3a12 12 0 0 0 8 3a12 12 0 0 1-.09 7.06"/><path d="M15 19l2 2l4-4"/>',
		'sparkles'      => '<path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2-2a2 2 0 0 1-2-2a2 2 0 0 1-2 2z"/><path d="M16 6a2 2 0 0 1 2 2a2 2 0 0 1 2-2a2 2 0 0 1-2-2a2 2 0 0 1-2 2z"/><path d="M9 18a6 6 0 0 1 6-6a6 6 0 0 1-6-6a6 6 0 0 1-6 6a6 6 0 0 1 6 6z"/>',
		'point'         => '<path d="M9 12a3 3 0 1 0 6 0a3 3 0 1 0-6 0"/>',
	);
}

/**
 * Allowed tags/attributes for our own SVG output.
 *
 * @return array<string,array<string,bool>>
 */
function jobly_integration_svg_kses() {
	$common = array(
		'class'               => true,
		'fill'                => true,
		'stroke'              => true,
		'stroke-width'        => true,
		'stroke-linecap'      => true,
		'stroke-linejoin'     => true,
		'opacity'             => true,
		'transform'           => true,
		'id'                  => true,
		'aria-hidden'         => true,
		'focusable'           => true,
		'role'                => true,
		'aria-label'          => true,
		'aria-labelledby'     => true,
		'style'               => true,
		'd'                   => true,
		'cx'                  => true,
		'cy'                  => true,
		'r'                   => true,
		'x'                   => true,
		'y'                   => true,
		'rx'                  => true,
		'width'               => true,
		'height'              => true,
		'x1'                  => true,
		'x2'                  => true,
		'y1'                  => true,
		'y2'                  => true,
		'offset'              => true,
		'stop-color'          => true,
		'gradientunits'       => true,
		'viewbox'             => true,
		'xmlns'               => true,
		'preserveaspectratio' => true,
	);
	return array(
		'svg'            => $common,
		'g'              => $common,
		'path'           => $common,
		'circle'         => $common,
		'rect'           => $common,
		'ellipse'        => $common,
		'defs'           => $common,
		'lineargradient' => $common,
		'stop'           => $common,
		'title'          => array(),
	);
}

/**
 * Icon markup (already filtered with wp_kses).
 *
 * @param string $name  Icon name.
 * @param string $css_class Extra CSS class.
 * @param int    $size  Pixel size.
 * @return string
 */
function jobly_integration_icon_html( $name, $css_class = '', $size = 20 ) {
	$paths = jobly_integration_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	$svg = sprintf(
		'<svg class="jobly-icon %s" xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $css_class ),
		(int) $size,
		(int) $size,
		$paths[ $name ]
	);
	return wp_kses( $svg, jobly_integration_svg_kses() );
}

/**
 * Print an icon.
 *
 * @param string $name  Icon name.
 * @param string $css_class Extra CSS class.
 * @param int    $size  Pixel size.
 */
function jobly_integration_icon( $name, $css_class = '', $size = 20 ) {
	echo jobly_integration_icon_html( $name, $css_class, $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses'd in jobly_integration_icon_html().
}

/**
 * The Jobly "J" mark (from the Jobly.si logo).
 *
 * @param int $size Pixel size.
 */
function jobly_integration_logo( $size = 32 ) {
	static $n = 0;
	++$n;
	$a   = 'jobly-lg-a' . $n;
	$b   = 'jobly-lg-b' . $n;
	$svg = sprintf(
		'<svg class="jobly-logo" xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 1024 1024" role="img" aria-label="Jobly.si"><defs><linearGradient id="%2$s" x1="210" y1="210" x2="850" y2="900" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#0EA5FF"/><stop offset="0.52" stop-color="#086BFF"/><stop offset="1" stop-color="#0536B8"/></linearGradient><linearGradient id="%3$s" x1="215" y1="430" x2="585" y2="850" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#16B7FF"/><stop offset="1" stop-color="#075CFF"/></linearGradient></defs><path fill="url(#%2$s)" d="M612 80h198c18 0 32 14 32 32v505c0 193-157 350-350 350-163 0-300-112-339-263-5-19 10-37 30-37h177c14 0 26 9 30 23 18 57 71 98 134 98 78 0 141-63 141-141V262c0-100 47-182 129-182z"/><path fill="url(#%3$s)" d="M188 574c0-17 14-31 31-31h205c18 0 31 14 31 31v6c0 23-19 42-42 42h-49v68c0 69 56 125 125 125h105c-38 62-107 103-185 103-122 0-221-99-221-221V574z"/><path fill="#082A92" opacity="0.92" d="M426 546c18 0 33 15 33 33v11c0 58 47 105 105 105h54c-25 38-69 63-119 63-79 0-143-64-143-143v-8c0-34 27-61 61-61h9z"/><circle cx="322" cy="420" r="75" fill="url(#%2$s)"/></svg>',
		(int) $size,
		$a,
		$b
	);
	echo wp_kses( $svg, jobly_integration_svg_kses() );
}

/**
 * Monochrome mark as a data URI for the admin menu icon (WordPress recolours fill="black").
 *
 * @return string
 */
function jobly_integration_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="black" d="M612 80h198c18 0 32 14 32 32v505c0 193-157 350-350 350-163 0-300-112-339-263-5-19 10-37 30-37h177c14 0 26 9 30 23 18 57 71 98 134 98 78 0 141-63 141-141V262c0-100 47-182 129-182z"/><path fill="black" d="M188 574c0-17 14-31 31-31h205c18 0 31 14 31 31v6c0 23-19 42-42 42h-49v68c0 69 56 125 125 125h105c-38 62-107 103-185 103-122 0-221-99-221-221V574z"/><circle cx="322" cy="420" r="75" fill="black"/></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- static SVG for the menu icon.
}
