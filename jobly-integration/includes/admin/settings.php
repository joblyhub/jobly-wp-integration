<?php
/**
 * Nastavitve: tabs Povezava | Prikaz | Demo.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Save one settings tab. Fields of other tabs are left untouched.
 */
function jobly_integration_handle_save_settings() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_save' );

	$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'connection';
	$code = 'saved';

	if ( 'display' === $tab ) {
		$base = isset( $_POST['base_path'] ) ? sanitize_title( wp_unslash( $_POST['base_path'] ) ) : '';
		$old  = jobly_integration_settings()['base_path'];
		$new  = '' !== $base ? $base : 'kariera';
		jobly_integration_update_settings(
			array(
				'base_path' => $new,
				'accent'    => isset( $_POST['accent'] ) ? (string) sanitize_hex_color( wp_unslash( $_POST['accent'] ) ) : '',
				'height'    => isset( $_POST['height'] ) ? max( 300, min( 3000, absint( $_POST['height'] ) ) ) : 720,
			)
		);
		if ( $old !== $new ) {
			update_option( 'jobly_integration_flush', 1, false );
		}
	} elseif ( 'demo' === $tab ) {
		jobly_integration_update_settings( array( 'demo' => empty( $_POST['demo'] ) ? 0 : 1 ) );
	} else {
		$base    = isset( $_POST['base_url'] ) ? esc_url_raw( trim( sanitize_text_field( wp_unslash( $_POST['base_url'] ) ) ), array( 'https', 'http' ) ) : '';
		$changes = array(
			'base_url' => '' !== $base ? untrailingslashit( $base ) : 'https://jobly.si',
			'company'  => isset( $_POST['company'] ) ? sanitize_title( wp_unslash( $_POST['company'] ) ) : '',
		);
		if ( ! empty( $_POST['remove_key'] ) ) {
			$changes['api_key']   = '';
			$changes['connected'] = 0;
			$code                 = 'key_removed';
		}
		$key = isset( $_POST['api_key'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		if ( '' !== $key ) {
			if ( 0 !== strpos( $key, 'jbl_' ) ) {
				$code = 'bad_key';
			} else {
				jobly_integration_update_settings( $changes ); // So the check below uses the new base.
				$result = jobly_integration_verify_key( $key );
				if ( 'ok' === $result ) {
					$changes['api_key']   = $key;
					$changes['connected'] = 1;
					$changes['demo']      = 0; // Connected: demo off (still toggleable).
					$code                 = 'verified';
				} else {
					$code = $result;
				}
			}
		}
		jobly_integration_update_settings( $changes );
		jobly_integration_flush_cache();
	}

	jobly_integration_redirect(
		'jobly-settings',
		array(
			'tab'       => $tab,
			'jobly_msg' => $code,
		)
	);
}
add_action( 'admin_post_jobly_integration_save', 'jobly_integration_handle_save_settings' );

/**
 * "Preveri povezavo": check the stored key.
 */
function jobly_integration_handle_verify() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_verify' );

	$key = jobly_integration_settings()['api_key'];
	if ( '' === $key ) {
		$code = 'no_key';
	} else {
		$code = jobly_integration_verify_key( $key );
		jobly_integration_update_settings( array( 'connected' => 'ok' === $code ? 1 : 0 ) );
	}
	jobly_integration_redirect(
		'jobly-settings',
		array(
			'tab'       => 'connection',
			'jobly_msg' => $code,
		)
	);
}
add_action( 'admin_post_jobly_integration_verify', 'jobly_integration_handle_verify' );

/**
 * "Osveži": drop the cached job list.
 */
function jobly_integration_handle_refresh() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_refresh' );
	jobly_integration_flush_cache();
	$back = isset( $_REQUEST['back'] ) ? sanitize_key( wp_unslash( $_REQUEST['back'] ) ) : 'jobly-jobs';
	jobly_integration_redirect(
		in_array( $back, array( 'jobly-jobs', 'jobly-integration' ), true ) ? $back : 'jobly-jobs',
		array( 'jobly_msg' => 'refreshed' )
	);
}
add_action( 'admin_post_jobly_integration_refresh', 'jobly_integration_handle_refresh' );

/**
 * Open the form of one tab.
 *
 * @param string $tab Tab id.
 */
function jobly_integration_form_open( $tab ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="jobly_integration_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
	wp_nonce_field( 'jobly_integration_save' );
}

/**
 * Screen callback.
 */
function jobly_integration_page_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab routing only.
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection';
	$tabs = array(
		'connection' => __( 'Povezava', 'jobly-integration' ),
		'display'    => __( 'Prikaz', 'jobly-integration' ),
		'demo'       => __( 'Demo', 'jobly-integration' ),
	);
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'connection';
	$s    = jobly_integration_settings();

	jobly_integration_header( __( 'Nastavitve', 'jobly-integration' ) );
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $id => $label ) {
		printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( jobly_integration_admin_url( 'jobly-settings', array( 'tab' => $id ) ) ), $id === $tab ? ' nav-tab-active' : '', esc_html( $label ) );
	}
	echo '</nav>';

	if ( 'connection' === $tab ) {
		jobly_integration_form_open( 'connection' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="jobly-key"><?php esc_html_e( 'API ključ', 'jobly-integration' ); ?></label></th>
				<td>
					<input id="jobly-key" class="regular-text" type="password" name="api_key" autocomplete="off" placeholder="<?php echo esc_attr( $s['api_key'] ? jobly_integration_masked_key() : 'jbl_…' ); ?>">
					<p class="description"><?php esc_html_e( 'Ključ izdaš v Jobly → Integracije → API žetoni. Shranjen ključ se nikoli ne prikaže; za zamenjavo vpiši novega.', 'jobly-integration' ); ?></p>
					<?php if ( $s['api_key'] ) : ?>
						<label><input type="checkbox" name="remove_key" value="1"> <?php esc_html_e( 'Odstrani shranjeni ključ', 'jobly-integration' ); ?></label>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-company"><?php esc_html_e( 'Podjetje (slug)', 'jobly-integration' ); ?></label></th>
				<td>
					<input id="jobly-company" class="regular-text" name="company" value="<?php echo esc_attr( $s['company'] ); ?>" placeholder="moje-podjetje">
					<p class="description"><?php esc_html_e( 'Zadnji del naslova profila na jobly.si/companies/… Rabi ga povezava »Uredi na Jobly« in kratka koda [jobly].', 'jobly-integration' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-base"><?php esc_html_e( 'Naslov Jobly', 'jobly-integration' ); ?></label></th>
				<td>
					<input id="jobly-base" class="regular-text" type="url" name="base_url" value="<?php echo esc_attr( $s['base_url'] ); ?>">
					<p class="description"><?php esc_html_e( 'Pusti https://jobly.si. Drugo samo za testiranje.', 'jobly-integration' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		submit_button( __( 'Shrani in preveri', 'jobly-integration' ) );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="jobly_integration_verify">';
		wp_nonce_field( 'jobly_integration_verify' );
		submit_button( __( 'Preveri povezavo', 'jobly-integration' ), 'secondary', 'submit', false, $s['api_key'] ? array() : array( 'disabled' => 'disabled' ) );
		echo '</form>';
	} elseif ( 'display' === $tab ) {
		jobly_integration_form_open( 'display' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="jobly-path"><?php esc_html_e( 'Osnova povezav', 'jobly-integration' ); ?></label></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="jobly-path" name="base_path" value="<?php echo esc_attr( $s['base_path'] ); ?>" class="small-text"><code>/</code>
					<p class="description"><?php esc_html_e( 'Seznam odprtih mest in podstrani oglasov so pod tem naslovom.', 'jobly-integration' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-accent"><?php esc_html_e( 'Barva poudarka', 'jobly-integration' ); ?></label></th>
				<td><input id="jobly-accent" type="color" name="accent" value="<?php echo esc_attr( $s['accent'] ? $s['accent'] : '#2563eb' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="jobly-height"><?php esc_html_e( 'Višina okvirja (px)', 'jobly-integration' ); ?></label></th>
				<td><input id="jobly-height" type="number" min="300" max="3000" name="height" value="<?php echo esc_attr( (string) $s['height'] ); ?>"></td>
			</tr>
		</table>
		<?php
		submit_button();
		echo '</form>';
	} else {
		jobly_integration_form_open( 'demo' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Demo podatki', 'jobly-integration' ); ?></th>
				<td>
					<label><input type="checkbox" name="demo" value="1" <?php checked( 1, (int) $s['demo'] ); ?>> <?php esc_html_e( 'Prikaži izmišljen primer (Primer d.o.o.) namesto podatkov z Jobly', 'jobly-integration' ); ?></label>
					<p class="description"><?php esc_html_e( 'Za gradnjo in predstavitev strani; vtičnik v tem načinu ne pokliče Jobly. Izklopi se samodejno, ko shraniš veljaven ključ.', 'jobly-integration' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		submit_button();
		echo '</form>';
	}
	jobly_integration_footer();
}
