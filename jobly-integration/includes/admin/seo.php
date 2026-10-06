<?php
/**
 * Nastavitve → SEO tab (global defaults), per-job and landing SEO tabs with Google and social previews.
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
 * Store one of the SEO options (autoload off).
 *
 * @param string $option Option name.
 * @param array  $value  Value.
 */
function jobly_integration_seo_store( $option, array $value ) {
	if ( false === get_option( $option ) ) {
		add_option( $option, $value, '', false );
	} else {
		update_option( $option, $value, false );
	}
}

/**
 * An image id from the form, only when it is an image attachment.
 *
 * @param string $field POST field.
 * @return int
 */
function jobly_integration_posted_image( $field ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
	$id = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * Save the SEO tab (global defaults and templates).
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
		$seo[ $img ] = jobly_integration_posted_image( $img );
	}
	$canonical            = $str( 'canonical' );
	$seo['canonical']     = 'jobly' === $canonical ? 'jobly' : 'site';
	$closed               = $str( 'closed_action' );
	$seo['closed_action'] = in_array( $closed, array( 'noindex', 'gone', 'redirect' ), true ) ? $closed : 'gone';

	jobly_integration_seo_store( JOBLY_INTEGRATION_SEO_OPTION, $seo );
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
 * The text of an override from the posted form.
 *
 * @param string $field POST field.
 * @return string
 */
function jobly_integration_posted_text( $field ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce.
	return isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
}

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
	$all = jobly_integration_seo_job_overrides();
	// Only for jobs that exist (an existing entry can always be cleared below).
	if ( ! isset( $all[ $slug ] ) && ! in_array( $slug, array_column( jobly_integration_all_jobs()['items'], 'slug' ), true ) ) {
		jobly_integration_redirect( 'jobly-jobs', array( 'jobly_msg' => 'not_found' ) );
	}
	$entry = array(
		'title'       => jobly_integration_posted_text( 'seo_title' ),
		'description' => jobly_integration_posted_text( 'seo_description' ),
		'image'       => jobly_integration_posted_image( 'seo_image' ),
		'noindex'     => empty( $_POST['seo_noindex'] ) ? 0 : 1,
	);
	if ( '' === $entry['title'] && '' === $entry['description'] && ! $entry['image'] && ! $entry['noindex'] ) {
		unset( $all[ $slug ] );
	} else {
		$all[ $slug ] = $entry;
	}
	jobly_integration_seo_store( JOBLY_INTEGRATION_SEO_JOBS_OPTION, $all );
	jobly_integration_redirect(
		'jobly-jobs',
		array(
			'action'    => 'show',
			'job'       => $slug,
			'tab'       => 'seo',
			'jobly_msg' => 'saved',
		)
	);
}
add_action( 'admin_post_jobly_integration_seo_job_save', 'jobly_integration_handle_seo_job_save' );

/**
 * Save the SEO override of the careers landing page.
 */
function jobly_integration_handle_seo_landing_save() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_seo_landing' );
	$seo                    = jobly_integration_seo_settings();
	$seo['landing_title']   = jobly_integration_posted_text( 'seo_title' );
	$seo['landing_desc']    = jobly_integration_posted_text( 'seo_description' );
	$seo['landing_image']   = jobly_integration_posted_image( 'seo_image' );
	$seo['landing_noindex'] = empty( $_POST['seo_noindex'] ) ? 0 : 1;
	jobly_integration_seo_store( JOBLY_INTEGRATION_SEO_OPTION, $seo );
	jobly_integration_redirect(
		'jobly-settings',
		array(
			'tab'       => 'landing',
			'view'      => 'seo',
			'jobly_msg' => 'saved',
		)
	);
}
add_action( 'admin_post_jobly_integration_seo_landing_save', 'jobly_integration_handle_seo_landing_save' );

/**
 * Form of a per-page SEO override: title, description, social image, noindex, Google and social previews.
 *
 * @param array $c scope (job|landing), action, nonce, hidden (name => value), over, tpl_title, tpl_desc, url, job.
 */
function jobly_integration_seo_override_form( array $c ) {
	$over  = $c['over'];
	$image = ! empty( $over['image'] ) ? wp_get_attachment_image_url( (int) $over['image'], 'large' ) : '';
	$scope = $c['scope'];
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-jobly-seo-form="<?php echo esc_attr( $scope ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $c['action'] ); ?>">
		<?php
		foreach ( $c['hidden'] as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( $c['nonce'] );
		?>
		<div class="jobly-field"><label for="jseo-title"><?php esc_html_e( 'SEO naslov', 'jobly-integration' ); ?></label><input id="jseo-title" name="seo_title" value="<?php echo esc_attr( $over['title'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $c['tpl_title'] ); ?>" data-serp-input="title" data-serp-for="<?php echo esc_attr( $scope ); ?>"></div>
		<div class="jobly-field"><label for="jseo-desc"><?php esc_html_e( 'Meta opis', 'jobly-integration' ); ?></label><textarea id="jseo-desc" name="seo_description" rows="3" placeholder="<?php echo esc_attr( $c['tpl_desc'] ); ?>" data-serp-input="desc" data-serp-for="<?php echo esc_attr( $scope ); ?>"><?php echo esc_textarea( $over['description'] ?? '' ); ?></textarea><p class="description"><?php esc_html_e( 'Prazno = predloga iz Nastavitve → SEO.', 'jobly-integration' ); ?></p></div>
		<div class="jobly-field"><span class="jobly-label"><?php esc_html_e( 'Slika za družbena omrežja (OG)', 'jobly-integration' ); ?></span>
			<?php jobly_integration_media_field( 'seo_image', (int) ( $over['image'] ?? 0 ) ); ?>
		</div>
		<p><label><input type="checkbox" name="seo_noindex" value="1" <?php checked( 1, (int) ( $over['noindex'] ?? 0 ) ); ?>> <?php esc_html_e( 'Ne indeksiraj (noindex)', 'jobly-integration' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'Velja tudi, ko je aktiven Yoast SEO ali Rank Math.', 'jobly-integration' ); ?></p>
		<?php
		jobly_integration_seo_preview( ! empty( $over['title'] ) ? $over['title'] : $c['tpl_title'], ! empty( $over['description'] ) ? $over['description'] : $c['tpl_desc'], $c['url'], $image ? (string) $image : jobly_integration_seo_image( 'landing' === $scope ), $c['job'], $scope );
		submit_button( __( 'Shrani SEO', 'jobly-integration' ), 'primary', 'submit', false );
		?>
	</form>
	<?php
}

/**
 * SEO tab of the job screen.
 *
 * @param array $job Job (with detail).
 */
function jobly_integration_seo_job_box( array $job ) {
	$slug = (string) $job['slug'];
	$seo  = jobly_integration_seo_settings();
	$desc = '' !== trim( $seo['desc_job'] ) ? $seo['desc_job'] : jobly_integration_seo_excerpt( (string) ( $job['description'] ?? '' ) );
	echo '<section class="jobly-panel"><header class="jobly-panel__head"><h2>' . esc_html__( 'SEO', 'jobly-integration' ) . '</h2></header>';
	jobly_integration_seo_override_form(
		array(
			'scope'     => 'job',
			'action'    => 'jobly_integration_seo_job_save',
			'nonce'     => 'jobly_integration_seo_job',
			'hidden'    => array( 'slug' => $slug ),
			'over'      => jobly_integration_seo_job_overrides()[ $slug ] ?? array(),
			'tpl_title' => $seo['title_job'],
			'tpl_desc'  => $desc,
			'url'       => jobly_integration_job_canonical( $job ),
			'job'       => $job,
		)
	);
	echo '</section>';
}

/**
 * SEO sub-tab of Karierna stran: this page's own title, description, image and noindex.
 */
function jobly_integration_landing_seo_panel() {
	$seo = jobly_integration_seo_settings();
	echo '<section class="jobly-panel"><h2>' . esc_html__( 'SEO karierne strani', 'jobly-integration' ) . '</h2>';
	jobly_integration_seo_override_form(
		array(
			'scope'     => 'landing',
			'action'    => 'jobly_integration_seo_landing_save',
			'nonce'     => 'jobly_integration_seo_landing',
			'hidden'    => array(),
			'over'      => array(
				'title'       => $seo['landing_title'],
				'description' => $seo['landing_desc'],
				'image'       => $seo['landing_image'],
				'noindex'     => $seo['landing_noindex'],
			),
			'tpl_title' => $seo['title_landing'],
			'tpl_desc'  => $seo['desc_landing'],
			'url'       => jobly_integration_careers_url(),
			'job'       => null,
		)
	);
	echo '</section>';
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
	echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Tu so samo splošne privzete vrednosti in predloge. Naslov, opis, sliko in noindex posamezne strani nastavite na njej: pri delovnem mestu v zavihku SEO, za karierno stran v Karierna stran → SEO. Nastavitev strani prevlada nad predlogo.', 'jobly-integration' ) . '</p></div>';
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
		<?php
		$jobly_integration_images = array(
			'og_image_landing' => __( 'Slika karierne strani', 'jobly-integration' ),
			'og_image'         => __( 'Privzeta slika (oglasi)', 'jobly-integration' ),
		);
		foreach ( $jobly_integration_images as $field => $label ) :
			?>
			<div class="jobly-field"><span class="jobly-label"><?php echo esc_html( $label ); ?></span>
				<?php jobly_integration_media_field( $field, (int) $seo[ $field ] ); ?>
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
