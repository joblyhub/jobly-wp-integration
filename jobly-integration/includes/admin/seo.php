<?php
/**
 * Nastavitve → SEO tab, per-job SEO box with Google and social previews.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google snippet and social card preview (updates live while typing, see admin.js).
 *
 * @param string     $title Title or template.
 * @param string     $desc  Description or template.
 * @param string     $url   Page URL.
 * @param string     $image Image URL or ''.
 * @param array|null $job   Job used to fill the variables, or null (landing).
 * @param string     $uid   Preview id (several can be on a screen).
 */
function jobly_integration_seo_preview( $title, $desc, $url, $image, $job, $uid ) {
	$vars = jobly_integration_seo_vars( $job );
	$host = wp_parse_url( $url, PHP_URL_HOST );
	?>
	<div class="jobly-preview-seo" data-jobly-seo-preview="<?php echo esc_attr( $uid ); ?>" data-vars="<?php echo esc_attr( (string) wp_json_encode( $vars ) ); ?>">
		<p class="jobly-serp__label"><?php esc_html_e( 'Predogled v Googlu', 'jobly-integration' ); ?></p>
		<div class="jobly-serp">
			<span class="jobly-serp__url"><?php echo esc_html( $url ); ?></span>
			<span class="jobly-serp__title" data-serp-title><?php echo esc_html( jobly_integration_seo_fill( $title, $vars ) ); ?></span>
			<span class="jobly-serp__desc" data-serp-desc><?php echo esc_html( jobly_integration_seo_fill( $desc, $vars ) ); ?></span>
		</div>
		<p class="jobly-serp__label"><?php esc_html_e( 'Predogled na družbenih omrežjih', 'jobly-integration' ); ?></p>
		<div class="jobly-og">
			<div class="jobly-og__img"><?php echo '' !== $image ? '<img src="' . esc_url( $image ) . '" alt="">' : ''; ?></div>
			<div class="jobly-og__body">
				<span class="jobly-og__host"><?php echo esc_html( strtoupper( (string) $host ) ); ?></span>
				<strong data-serp-title><?php echo esc_html( jobly_integration_seo_fill( $title, $vars ) ); ?></strong>
				<span data-serp-desc><?php echo esc_html( jobly_integration_seo_fill( $desc, $vars ) ); ?></span>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Save the SEO tab.
 */
function jobly_integration_handle_seo_save() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_seo' );

	$str                  = static function ( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in the handler above; value sanitized here.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	};
	$seo                  = jobly_integration_seo_settings();
	$seo['title_landing'] = $str( 'title_landing' );
	$seo['desc_landing']  = $str( 'desc_landing' );
	$seo['title_job']     = $str( 'title_job' );
	$seo['desc_job']      = $str( 'desc_job' );
	foreach ( array( 'og', 'twitter', 'noindex_filtered', 'noindex_all', 'breadcrumb', 'organization', 'itemlist' ) as $flag ) {
		$seo[ $flag ] = empty( $_POST[ $flag ] ) ? 0 : 1;
	}
	foreach ( array( 'og_image', 'og_image_landing' ) as $img ) {
		$id          = isset( $_POST[ $img ] ) ? absint( $_POST[ $img ] ) : 0;
		$seo[ $img ] = ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
	}
	$canonical            = $str( 'canonical' );
	$seo['canonical']     = 'jobly' === $canonical ? 'jobly' : 'site';
	$closed               = $str( 'closed_action' );
	$seo['closed_action'] = in_array( $closed, array( 'noindex', 'gone', 'redirect' ), true ) ? $closed : 'gone';

	if ( false === get_option( JOBLY_INTEGRATION_SEO_OPTION ) ) {
		add_option( JOBLY_INTEGRATION_SEO_OPTION, $seo, '', false );
	} else {
		update_option( JOBLY_INTEGRATION_SEO_OPTION, $seo, false );
	}
	jobly_integration_redirect(
		'jobly-settings',
		array(
			'tab'       => 'seo',
			'jobly_msg' => 'saved',
		)
	);
}
add_action( 'admin_post_jobly_integration_seo_save', 'jobly_integration_handle_seo_save' );

/**
 * Save the SEO override of one job.
 */
function jobly_integration_handle_seo_job_save() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_seo_job' );
	$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
	if ( '' === $slug ) {
		jobly_integration_redirect( 'jobly-jobs', array( 'jobly_msg' => 'not_found' ) );
	}
	$all   = jobly_integration_seo_job_overrides();
	$entry = array(
		'title'       => isset( $_POST['seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_title'] ) ) : '',
		'description' => isset( $_POST['seo_description'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_description'] ) ) : '',
		'noindex'     => empty( $_POST['seo_noindex'] ) ? 0 : 1,
	);
	if ( '' === $entry['title'] && '' === $entry['description'] && ! $entry['noindex'] ) {
		unset( $all[ $slug ] );
	} else {
		$all[ $slug ] = $entry;
	}
	if ( false === get_option( JOBLY_INTEGRATION_SEO_JOBS_OPTION ) ) {
		add_option( JOBLY_INTEGRATION_SEO_JOBS_OPTION, $all, '', false );
	} else {
		update_option( JOBLY_INTEGRATION_SEO_JOBS_OPTION, $all, false );
	}
	jobly_integration_redirect(
		'jobly-jobs',
		array(
			'action'    => 'show',
			'job'       => $slug,
			'jobly_msg' => 'saved',
		)
	);
}
add_action( 'admin_post_jobly_integration_seo_job_save', 'jobly_integration_handle_seo_job_save' );

/**
 * SEO box on the job screen (side column).
 *
 * @param array $job Job (with detail).
 */
function jobly_integration_seo_job_box( array $job ) {
	$slug = (string) $job['slug'];
	$over = jobly_integration_seo_job_overrides()[ $slug ] ?? array();
	$seo  = jobly_integration_seo_settings();
	$desc = ! empty( $over['description'] ) ? $over['description'] : ( '' !== trim( $seo['desc_job'] ) ? $seo['desc_job'] : jobly_integration_seo_excerpt( (string) ( $job['description'] ?? '' ) ) );
	?>
	<section class="jobly-panel">
		<header class="jobly-panel__head"><h2><?php esc_html_e( 'SEO', 'jobly-integration' ); ?></h2></header>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-jobly-seo-form="job">
			<input type="hidden" name="action" value="jobly_integration_seo_job_save">
			<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>">
			<?php wp_nonce_field( 'jobly_integration_seo_job' ); ?>
			<div class="jobly-field"><label for="jseo-title"><?php esc_html_e( 'SEO naslov', 'jobly-integration' ); ?></label><input id="jseo-title" name="seo_title" value="<?php echo esc_attr( $over['title'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $seo['title_job'] ); ?>" data-serp-input="title"></div>
			<div class="jobly-field"><label for="jseo-desc"><?php esc_html_e( 'Meta opis', 'jobly-integration' ); ?></label><textarea id="jseo-desc" name="seo_description" rows="3" data-serp-input="desc"><?php echo esc_textarea( $over['description'] ?? '' ); ?></textarea></div>
			<p><label><input type="checkbox" name="seo_noindex" value="1" <?php checked( 1, (int) ( $over['noindex'] ?? 0 ) ); ?>> <?php esc_html_e( 'Ne indeksiraj (noindex)', 'jobly-integration' ); ?></label></p>
			<?php
			jobly_integration_seo_preview( ! empty( $over['title'] ) ? $over['title'] : $seo['title_job'], $desc, jobly_integration_job_canonical( $job ), jobly_integration_seo_image( false ), $job, 'job' );
			submit_button( __( 'Shrani SEO', 'jobly-integration' ), 'secondary', 'submit', false );
			?>
		</form>
	</section>
	<?php
}

/**
 * The SEO tab.
 */
function jobly_integration_seo_tab() {
	$seo    = jobly_integration_seo_settings();
	$plugin = jobly_integration_seo_plugin();
	$names  = array(
		'yoast'    => 'Yoast SEO',
		'rankmath' => 'Rank Math',
		'seopress' => 'SEOPress',
		'aioseo'   => 'All in One SEO',
	);
	$jobs   = jobly_integration_open_jobs();
	$sample = $jobs ? jobly_integration_job_detail( $jobs[0] ) : null;

	echo '<form class="jobly-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="jobly_integration_seo_save">';
	wp_nonce_field( 'jobly_integration_seo' );
	if ( $plugin ) {
		echo '<div class="notice notice-info inline"><p>' . esc_html( sprintf( /* translators: %s: SEO plugin name. */ __( 'Zaznan je %s: naslovi, opisi, kanonični naslov, robots in strukturirani podatki se podajo temu vtičniku, naših oznak ni (brez podvajanja).', 'jobly-integration' ), $names[ $plugin ] ) ) . '</p></div>';
	}
	if ( jobly_integration_is_demo() ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Demo način: strani so vedno noindex in brez strukturiranih podatkov o oglasih.', 'jobly-integration' ) . '</p></div>';
	}
	?>
	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Naslovi in opisi', 'jobly-integration' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Spremenljivke:', 'jobly-integration' ); ?> <code>%job_title%</code> <code>%company%</code> <code>%location%</code> <code>%employment_type%</code> <code>%site_name%</code> <code>%sep%</code></p>
		<div class="jobly-field"><label for="seo-tl"><?php esc_html_e( 'Naslov: karierna stran', 'jobly-integration' ); ?></label><input id="seo-tl" name="title_landing" value="<?php echo esc_attr( $seo['title_landing'] ); ?>" data-serp-input="title" data-serp-for="landing"></div>
		<div class="jobly-field"><label for="seo-dl"><?php esc_html_e( 'Opis: karierna stran', 'jobly-integration' ); ?></label><textarea id="seo-dl" name="desc_landing" rows="2" data-serp-input="desc" data-serp-for="landing"><?php echo esc_textarea( $seo['desc_landing'] ); ?></textarea></div>
		<?php jobly_integration_seo_preview( $seo['title_landing'], $seo['desc_landing'], jobly_integration_careers_url(), jobly_integration_seo_image( true ), null, 'landing' ); ?>
		<div class="jobly-field"><label for="seo-tj"><?php esc_html_e( 'Naslov: stran oglasa', 'jobly-integration' ); ?></label><input id="seo-tj" name="title_job" value="<?php echo esc_attr( $seo['title_job'] ); ?>" data-serp-input="title" data-serp-for="job"></div>
		<div class="jobly-field"><label for="seo-dj"><?php esc_html_e( 'Opis: stran oglasa', 'jobly-integration' ); ?></label><textarea id="seo-dj" name="desc_job" rows="2" data-serp-input="desc" data-serp-for="job"><?php echo esc_textarea( $seo['desc_job'] ); ?></textarea><p class="description"><?php esc_html_e( 'Prazno = prvih 155 znakov opisa oglasa.', 'jobly-integration' ); ?></p></div>
		<?php
		if ( $sample ) {
			$sample_desc = trim( $seo['desc_job'] );
			if ( '' === $sample_desc ) {
				$sample_desc = jobly_integration_seo_excerpt( (string) ( $sample['description'] ?? '' ) );
			}
			jobly_integration_seo_preview( $seo['title_job'], $sample_desc, jobly_integration_job_canonical( $sample ), jobly_integration_seo_image( false ), $sample, 'job' );
		}
		?>
	</section>

	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Družbena omrežja', 'jobly-integration' ); ?></h2>
		<p><label><input type="checkbox" name="og" value="1" <?php checked( 1, (int) $seo['og'] ); ?>> <?php esc_html_e( 'Open Graph oznake (Facebook, LinkedIn …)', 'jobly-integration' ); ?></label></p>
		<p><label><input type="checkbox" name="twitter" value="1" <?php checked( 1, (int) $seo['twitter'] ); ?>> <?php esc_html_e( 'Twitter/X Card', 'jobly-integration' ); ?></label></p>
		<?php foreach ( array( 'og_image_landing' => __( 'Slika karierne strani', 'jobly-integration' ), 'og_image' => __( 'Privzeta slika (oglasi)', 'jobly-integration' ) ) as $field => $label ) : // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>
			<div class="jobly-field"><span class="jobly-label"><?php echo esc_html( $label ); ?></span>
				<div class="jobly-media" data-jobly-media>
					<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( (string) $seo[ $field ] ); ?>">
					<div class="jobly-media__preview"><?php echo $seo[ $field ] ? wp_get_attachment_image( (int) $seo[ $field ], 'medium' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes attachment markup. ?></div>
					<button type="button" class="button" data-jobly-media-pick data-title="<?php esc_attr_e( 'Izberi sliko', 'jobly-integration' ); ?>"><?php esc_html_e( 'Izberi iz knjižnice', 'jobly-integration' ); ?></button>
					<button type="button" class="button-link" data-jobly-media-clear><?php esc_html_e( 'Odstrani', 'jobly-integration' ); ?></button>
				</div>
			</div>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'Brez izbire: logotip podjetja z Jobly, nato ikona spletnega mesta.', 'jobly-integration' ); ?></p>
	</section>

	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Indeksiranje', 'jobly-integration' ); ?></h2>
		<div class="jobly-field"><span class="jobly-label"><?php esc_html_e( 'Kanonični naslov oglasa', 'jobly-integration' ); ?></span>
			<label><input type="radio" name="canonical" value="site" <?php checked( 'site', $seo['canonical'] ); ?>> <?php esc_html_e( 'To spletno mesto (privzeto)', 'jobly-integration' ); ?></label><br>
			<label><input type="radio" name="canonical" value="jobly" <?php checked( 'jobly', $seo['canonical'] ); ?>> <?php esc_html_e( 'Stran oglasa na Jobly (če želite, da se uvršča Jobly)', 'jobly-integration' ); ?></label>
		</div>
		<div class="jobly-field"><label for="seo-closed"><?php esc_html_e( 'Zaprt ali potekel oglas', 'jobly-integration' ); ?></label>
			<select id="seo-closed" name="closed_action">
				<option value="gone" <?php selected( 'gone', $seo['closed_action'] ); ?>><?php esc_html_e( '410 Gone (priporočeno)', 'jobly-integration' ); ?></option>
				<option value="noindex" <?php selected( 'noindex', $seo['closed_action'] ); ?>><?php esc_html_e( 'Prikaži stran z noindex', 'jobly-integration' ); ?></option>
				<option value="redirect" <?php selected( 'redirect', $seo['closed_action'] ); ?>><?php esc_html_e( 'Preusmeri na karierno stran (301)', 'jobly-integration' ); ?></option>
			</select>
		</div>
		<p><label><input type="checkbox" name="noindex_filtered" value="1" <?php checked( 1, (int) $seo['noindex_filtered'] ); ?>> <?php esc_html_e( 'noindex za filtrirane in strani seznama (kanonični naslov kaže na čisto karierno stran)', 'jobly-integration' ); ?></label></p>
		<p><label><input type="checkbox" name="noindex_all" value="1" <?php checked( 1, (int) $seo['noindex_all'] ); ?>> <?php esc_html_e( 'noindex za celotno karierno sekcijo', 'jobly-integration' ); ?></label></p>
	</section>

	<section class="jobly-panel">
		<h2><?php esc_html_e( 'Strukturirani podatki', 'jobly-integration' ); ?></h2>
		<p class="description"><?php esc_html_e( 'JobPosting (Google for Jobs) vklopite v zavihku Google for Jobs. Tu so dodatni zapisi.', 'jobly-integration' ); ?></p>
		<p><label><input type="checkbox" name="breadcrumb" value="1" <?php checked( 1, (int) $seo['breadcrumb'] ); ?>> <?php esc_html_e( 'BreadcrumbList na straneh oglasov', 'jobly-integration' ); ?></label></p>
		<p><label><input type="checkbox" name="organization" value="1" <?php checked( 1, (int) $seo['organization'] ); ?>> <?php esc_html_e( 'Organization na karierni strani', 'jobly-integration' ); ?></label></p>
		<p><label><input type="checkbox" name="itemlist" value="1" <?php checked( 1, (int) $seo['itemlist'] ); ?>> <?php esc_html_e( 'ItemList oglasov na karierni strani', 'jobly-integration' ); ?></label></p>
		<p class="description"><?php echo esc_html( sprintf( /* translators: %s: sitemap URL. */ __( 'Zemljevid oglasov: v WordPressovem zemljevidu strani; z Yoast/Rank Math tudi %s.', 'jobly-integration' ), jobly_integration_sitemap_url() ) ); ?></p>
	</section>
	<p class="jobly-actions"><?php submit_button( __( 'Shrani', 'jobly-integration' ), 'primary', 'submit', false ); ?></p>
	</form>
	<?php
}
