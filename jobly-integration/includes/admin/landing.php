<?php
/**
 * Nastavitve → Karierna stran: landing builder (sections or own page) and code slots.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_BENEFIT_SLOTS = 6;

/**
 * Save the landing builder.
 */
function jobly_integration_handle_landing_save() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_landing' );

	// Every field below is unslashed and sanitized by its own rule; the array is never used as a whole.
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$in       = isset( $_POST['landing'] ) && is_array( $_POST['landing'] ) ? $_POST['landing'] : array();
	$text     = static function ( $key ) use ( $in ) {
		return isset( $in[ $key ] ) && is_string( $in[ $key ] ) ? wp_unslash( $in[ $key ] ) : '';
	};
	$landing  = jobly_integration_landing();
	$sections = jobly_integration_landing_sections();
	$icons    = jobly_integration_landing_icons();

	foreach ( array_keys( $sections ) as $key ) {
		$landing['enabled'][ $key ] = ! empty( $in['enabled'][ $key ] ) ? 1 : 0;
	}
	$positions = array();
	foreach ( array_keys( $sections ) as $i => $key ) {
		$positions[ $key ] = isset( $in['order'][ $key ] ) ? (int) $in['order'][ $key ] : $i + 1;
	}
	asort( $positions );
	$landing['order']          = array_keys( $positions );
	$landing['filters']        = empty( $in['filters'] ) ? 0 : 1;
	$landing['hero_title']     = sanitize_text_field( $text( 'hero_title' ) );
	$landing['hero_subtitle']  = sanitize_textarea_field( $text( 'hero_subtitle' ) );
	$landing['hero_cta']       = sanitize_text_field( $text( 'hero_cta' ) );
	$image                     = absint( $text( 'hero_image' ) );
	$landing['hero_image']     = ( $image && wp_attachment_is_image( $image ) ) ? $image : 0;
	$landing['intro_html']     = wp_kses_post( $text( 'intro_html' ) );
	$landing['benefits_title'] = sanitize_text_field( $text( 'benefits_title' ) );

	$benefits = array();
	for ( $i = 0; $i < JOBLY_INTEGRATION_BENEFIT_SLOTS; $i++ ) {
		$row   = isset( $in['benefits'][ $i ] ) && is_array( $in['benefits'][ $i ] ) ? $in['benefits'][ $i ] : array();
		$title = isset( $row['title'] ) && is_string( $row['title'] ) ? sanitize_text_field( wp_unslash( $row['title'] ) ) : '';
		$icon  = isset( $row['icon'] ) && is_string( $row['icon'] ) ? sanitize_key( wp_unslash( $row['icon'] ) ) : '';
		if ( '' === $title ) {
			continue;
		}
		$benefits[] = array(
			'icon'  => in_array( $icon, $icons, true ) ? $icon : 'sparkles',
			'title' => $title,
			'text'  => isset( $row['text'] ) && is_string( $row['text'] ) ? sanitize_textarea_field( wp_unslash( $row['text'] ) ) : '',
		);
	}
	$landing['benefits'] = $benefits;

	// Code slots: HTML is filtered unless the user may post unfiltered HTML; CSS never carries tags; JS only for unfiltered_html users.
	$raw                    = current_user_can( 'unfiltered_html' );
	$landing['html_raw']    = $raw ? 1 : 0;
	$landing['html_top']    = $raw ? $text( 'html_top' ) : wp_kses_post( $text( 'html_top' ) );
	$landing['html_bottom'] = $raw ? $text( 'html_bottom' ) : wp_kses_post( $text( 'html_bottom' ) );
	$landing['css']         = wp_strip_all_tags( $text( 'css' ) );
	if ( $raw ) {
		$landing['js'] = $text( 'js' );
	}
	// phpcs:enable

	// Mode: sections (automatic page) or an own WordPress page.
	$page_id = 0;
	if ( isset( $_POST['landing_mode'] ) && 'page' === sanitize_key( wp_unslash( $_POST['landing_mode'] ) ) && isset( $_POST['landing_page'] ) ) {
		$candidate = absint( $_POST['landing_page'] );
		$page_id   = ( $candidate && 'page' === get_post_type( $candidate ) ) ? $candidate : 0;
	}
	jobly_integration_landing_save( $landing );
	jobly_integration_update_settings(
		array(
			'careers_page' => $page_id,
			'careers_set'  => 1,
		)
	);
	update_option( 'jobly_integration_flush', 1, false ); // The list URL may have changed.
	jobly_integration_redirect(
		'jobly-settings',
		array(
			'tab'       => 'landing',
			'jobly_msg' => 'saved',
		)
	);
}
add_action( 'admin_post_jobly_integration_landing_save', 'jobly_integration_handle_landing_save' );

/**
 * Reset the landing to the plain list.
 */
function jobly_integration_handle_landing_reset() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_landing_reset' );
	delete_option( JOBLY_INTEGRATION_LANDING_OPTION );
	jobly_integration_update_settings( array( 'careers_page' => 0 ) );
	update_option( 'jobly_integration_flush', 1, false );
	jobly_integration_redirect(
		'jobly-settings',
		array(
			'tab'       => 'landing',
			'jobly_msg' => 'landing_reset',
		)
	);
}
add_action( 'admin_post_jobly_integration_landing_reset', 'jobly_integration_handle_landing_reset' );

/**
 * Code editor (CodeMirror), media picker and editor assets on the landing tab only.
 *
 * @param string $hook Admin page hook.
 */
function jobly_integration_landing_admin_assets( $hook ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen routing only.
	if ( false === strpos( $hook, 'jobly-settings' ) || ! isset( $_GET['tab'] ) || 'landing' !== sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
		return;
	}
	wp_enqueue_media();
	$fields = array(
		'jobly-code-html-top'    => 'text/html',
		'jobly-code-html-bottom' => 'text/html',
		'jobly-code-css'         => 'text/css',
	);
	if ( current_user_can( 'unfiltered_html' ) ) {
		$fields['jobly-code-js'] = 'application/javascript';
	}
	foreach ( $fields as $id => $type ) {
		$settings = wp_enqueue_code_editor( array( 'type' => $type ) );
		if ( false === $settings ) {
			continue; // The user turned syntax highlighting off.
		}
		wp_add_inline_script( 'code-editor', sprintf( 'jQuery( function () { wp.codeEditor.initialize( %s, %s ); } );', wp_json_encode( $id ), wp_json_encode( $settings ) ) );
	}
}
add_action( 'admin_enqueue_scripts', 'jobly_integration_landing_admin_assets' );

/**
 * The tab.
 */
function jobly_integration_landing_tab() {
	$landing  = jobly_integration_landing();
	$s        = jobly_integration_settings();
	$sections = jobly_integration_landing_sections();
	$page_id  = jobly_integration_careers_page_id();
	$can_js   = current_user_can( 'unfiltered_html' );
	$reset    = wp_nonce_url( admin_url( 'admin-post.php?action=jobly_integration_landing_reset' ), 'jobly_integration_landing_reset' );

	echo '<form class="jobly-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="jobly_integration_landing_save">';
	wp_nonce_field( 'jobly_integration_landing' );
	?>
	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Vrsta karierne strani', 'jobly-integration' ); ?></h2>
		<label class="jobly-option">
			<input type="radio" name="landing_mode" value="sections" <?php checked( 0, (int) $page_id ); ?>>
			<span class="jobly-option__body"><strong><?php esc_html_e( 'Sestavljena stran (sekcije)', 'jobly-integration' ); ?></strong>
			<span><?php echo esc_html( sprintf( /* translators: %s: careers URL. */ __( 'Plugin sestavi stran na %s iz sekcij spodaj.', 'jobly-integration' ), jobly_integration_careers_url() ) ); ?></span></span>
		</label>
		<label class="jobly-option">
			<input type="radio" name="landing_mode" value="page" <?php checked( true, (bool) $page_id ); ?>>
			<span class="jobly-option__body"><strong><?php esc_html_e( 'Moja stran', 'jobly-integration' ); ?></strong>
			<span><?php esc_html_e( 'Uporabi obstoječo WordPress stran: njena vsebina (bloki) je landing; vanjo vstavite blok »Jobly – seznam delovnih mest«. Oglasi dobijo naslove pod to stranjo.', 'jobly-integration' ); ?></span>
			<?php
			wp_dropdown_pages(
				array(
					'name'              => 'landing_page',
					'selected'          => (int) $page_id,
					'show_option_none'  => esc_html__( '— izberi stran —', 'jobly-integration' ),
					'option_none_value' => '0',
				)
			);
			?>
			</span>
		</label>
	</section>

	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Sekcije (sestavljena stran)', 'jobly-integration' ); ?></h2>
		<table class="widefat jobly-table jobly-sections">
			<thead><tr><th scope="col"><?php esc_html_e( 'Prikaži', 'jobly-integration' ); ?></th><th scope="col"><?php esc_html_e( 'Sekcija', 'jobly-integration' ); ?></th><th scope="col"><?php esc_html_e( 'Vrstni red', 'jobly-integration' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $landing['order'] as $i => $key ) : ?>
				<?php
				if ( ! isset( $sections[ $key ] ) ) {
					continue;
				}
				?>
				<tr>
					<td><input type="checkbox" name="landing[enabled][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( 1, (int) $landing['enabled'][ $key ] ); ?> aria-label="<?php echo esc_attr( $sections[ $key ] ); ?>"></td>
					<td><?php echo esc_html( $sections[ $key ] ); ?></td>
					<td><input type="number" min="1" max="9" class="small-text" name="landing[order][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $i + 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Vrstni red', 'jobly-integration' ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><label><input type="checkbox" name="landing[filters]" value="1" <?php checked( 1, (int) $landing['filters'] ); ?>> <?php esc_html_e( 'Iskanje in filtri nad seznamom oglasov', 'jobly-integration' ); ?></label></p>
	</section>

	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Hero', 'jobly-integration' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="jl-hero-title"><?php esc_html_e( 'Naslov', 'jobly-integration' ); ?></label></th><td><input id="jl-hero-title" class="regular-text" name="landing[hero_title]" value="<?php echo esc_attr( $landing['hero_title'] ); ?>"></td></tr>
			<tr><th scope="row"><label for="jl-hero-sub"><?php esc_html_e( 'Podnaslov', 'jobly-integration' ); ?></label></th><td><textarea id="jl-hero-sub" rows="3" name="landing[hero_subtitle]"><?php echo esc_textarea( $landing['hero_subtitle'] ); ?></textarea></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Slika', 'jobly-integration' ); ?></th><td>
				<div class="jobly-media" data-jobly-media>
					<input type="hidden" name="landing[hero_image]" value="<?php echo esc_attr( (string) $landing['hero_image'] ); ?>">
					<div class="jobly-media__preview"><?php echo $landing['hero_image'] ? wp_get_attachment_image( (int) $landing['hero_image'], 'medium' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes attachment markup. ?></div>
					<button type="button" class="button" data-jobly-media-pick data-title="<?php esc_attr_e( 'Izberi sliko', 'jobly-integration' ); ?>"><?php esc_html_e( 'Izberi iz knjižnice', 'jobly-integration' ); ?></button>
					<button type="button" class="button-link" data-jobly-media-clear><?php esc_html_e( 'Odstrani', 'jobly-integration' ); ?></button>
				</div>
			</td></tr>
			<tr><th scope="row"><label for="jl-hero-cta"><?php esc_html_e( 'Besedilo gumba', 'jobly-integration' ); ?></label></th><td><input id="jl-hero-cta" class="regular-text" name="landing[hero_cta]" value="<?php echo esc_attr( $landing['hero_cta'] ); ?>" placeholder="<?php esc_attr_e( 'Poglej odprta mesta', 'jobly-integration' ); ?>"><p class="description"><?php esc_html_e( 'Gumb se pomakne na seznam oglasov.', 'jobly-integration' ); ?></p></td></tr>
		</table>
	</section>

	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Uvodno besedilo', 'jobly-integration' ); ?></h2>
		<?php
		wp_editor(
			$landing['intro_html'],
			'jobly_landing_intro',
			array(
				'textarea_name' => 'landing[intro_html]',
				'media_buttons' => false,
				'textarea_rows' => 8,
			)
		);
		?>
	</section>

	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Zakaj delati pri nas', 'jobly-integration' ); ?></h2>
		<div class="jobly-field"><label for="jl-ben-title"><?php esc_html_e( 'Naslov sekcije', 'jobly-integration' ); ?></label><input id="jl-ben-title" name="landing[benefits_title]" value="<?php echo esc_attr( $landing['benefits_title'] ); ?>"></div>
		<p class="description"><?php esc_html_e( 'Do šest prednosti; prazne vrstice se preskočijo.', 'jobly-integration' ); ?></p>
		<?php for ( $i = 0; $i < JOBLY_INTEGRATION_BENEFIT_SLOTS; $i++ ) : ?>
			<?php
			$row = $landing['benefits'][ $i ] ?? array(
				'icon'  => 'sparkles',
				'title' => '',
				'text'  => '',
			); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine 
			?>
			<div class="jobly-benefitrow">
				<select name="landing[benefits][<?php echo esc_attr( (string) $i ); ?>][icon]" aria-label="<?php esc_attr_e( 'Ikona', 'jobly-integration' ); ?>">
					<?php foreach ( jobly_integration_landing_icons() as $icon ) : ?>
						<option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $row['icon'], $icon ); ?>><?php echo esc_html( $icon ); ?></option>
					<?php endforeach; ?>
				</select>
				<input name="landing[benefits][<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( $row['title'] ); ?>" placeholder="<?php esc_attr_e( 'Naslov', 'jobly-integration' ); ?>" aria-label="<?php esc_attr_e( 'Naslov', 'jobly-integration' ); ?>">
				<input name="landing[benefits][<?php echo esc_attr( (string) $i ); ?>][text]" value="<?php echo esc_attr( $row['text'] ); ?>" placeholder="<?php esc_attr_e( 'Besedilo', 'jobly-integration' ); ?>" aria-label="<?php esc_attr_e( 'Besedilo', 'jobly-integration' ); ?>">
			</div>
		<?php endfor; ?>
	</section>

	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Koda po meri', 'jobly-integration' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Izpiše se samo na karierni strani, nikoli v skrbniškem delu. HTML se filtrira, razen če imate pravico unfiltered_html; JavaScript lahko shrani samo uporabnik s to pravico.', 'jobly-integration' ); ?></p>
		<div class="jobly-field"><label for="jobly-code-html-top"><?php esc_html_e( 'HTML na vrhu (samo sestavljena stran)', 'jobly-integration' ); ?></label><textarea id="jobly-code-html-top" rows="5" name="landing[html_top]"><?php echo esc_textarea( $landing['html_top'] ); ?></textarea></div>
		<div class="jobly-field"><label for="jobly-code-html-bottom"><?php esc_html_e( 'HTML na dnu (samo sestavljena stran)', 'jobly-integration' ); ?></label><textarea id="jobly-code-html-bottom" rows="5" name="landing[html_bottom]"><?php echo esc_textarea( $landing['html_bottom'] ); ?></textarea></div>
		<div class="jobly-field"><label for="jobly-code-css"><?php esc_html_e( 'CSS (karierna stran in strani oglasov)', 'jobly-integration' ); ?></label><textarea id="jobly-code-css" rows="8" name="landing[css]"><?php echo esc_textarea( $landing['css'] ); ?></textarea></div>
		<div class="jobly-field"><label for="jobly-code-js"><?php esc_html_e( 'JavaScript (karierna stran)', 'jobly-integration' ); ?></label>
			<textarea id="jobly-code-js" rows="6" name="landing[js]" <?php disabled( ! $can_js ); ?>><?php echo esc_textarea( $landing['js'] ); ?></textarea>
			<?php if ( ! $can_js ) : ?>
				<p class="description"><?php esc_html_e( 'Polje je onemogočeno: nimate pravice unfiltered_html (na večmestnih namestitvah jo ima samo super skrbnik).', 'jobly-integration' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<p class="jobly-actions">
		<?php submit_button( __( 'Shrani', 'jobly-integration' ), 'primary', 'submit', false ); ?>
		<a class="button" href="<?php echo esc_url( jobly_integration_careers_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Predogled', 'jobly-integration' ); ?></a>
		<a class="button-link-delete" href="<?php echo esc_url( $reset ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Ponastavim karierno stran na privzeto?', 'jobly-integration' ) ); ?>')"><?php esc_html_e( 'Ponastavi na privzeto', 'jobly-integration' ); ?></a>
	</p>
	</form>
	<?php
	unset( $s );
}
