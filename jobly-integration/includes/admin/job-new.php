<?php
/**
 * Dodaj novo: form mirroring POST /api/v1/jobs (StoreJobRequest).
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

const JOBLY_INTEGRATION_LIST_FIELDS = array( 'responsibilities', 'requirements', 'benefits', 'technologies' );

/**
 * Handle the form: POST to Jobly, show 422 errors beside the fields.
 */
function jobly_integration_handle_create() {
	jobly_integration_require_cap();
	check_admin_referer( 'jobly_integration_create' );
	if ( jobly_integration_is_demo() || '' === jobly_integration_settings()['api_key'] ) {
		jobly_integration_redirect( 'jobly-job-new' );
	}

	$body = array();
	foreach ( array( 'title', 'location', 'employment_type', 'work_arrangement', 'category' ) as $f ) {
		$body[ $f ] = isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : '';
	}
	$body['description'] = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
	$body['salary_min']  = isset( $_POST['salary_min'] ) && '' !== $_POST['salary_min'] ? absint( $_POST['salary_min'] ) : '';
	$body['salary_max']  = isset( $_POST['salary_max'] ) && '' !== $_POST['salary_max'] ? absint( $_POST['salary_max'] ) : '';
	if ( '' === $body['location'] ) {
		unset( $body['location'] );
	}
	foreach ( JOBLY_INTEGRATION_LIST_FIELDS as $f ) {
		$lines      = isset( $_POST[ $f ] ) ? preg_split( '/\R/', sanitize_textarea_field( wp_unslash( $_POST[ $f ] ) ) ) : array();
		$body[ $f ] = array_values( array_filter( array_map( 'trim', (array) $lines ), 'strlen' ) );
	}

	$res = jobly_integration_api_request( 'POST', '/api/v1/jobs', $body );
	if ( 201 === $res['code'] ) {
		jobly_integration_flush_cache();
		delete_transient( 'jobly_integration_form_' . get_current_user_id() );
		jobly_integration_redirect( 'jobly-jobs', array( 'jobly_msg' => 'created' ) );
	}

	// Keep the input and the errors for one round trip.
	$errors = array();
	foreach ( (array) ( $res['data']['errors'] ?? array() ) as $field => $messages ) {
		$errors[ explode( '.', (string) $field )[0] ][] = implode( ' ', array_map( 'sanitize_text_field', (array) $messages ) );
	}
	$general = 422 === $res['code'] ? '' : ( 403 === $res['code'] ? 'forbidden' : ( 0 === $res['code'] ? 'network' : ( 401 === $res['code'] ? 'unauthorized' : 'error' ) ) );
	set_transient(
		'jobly_integration_form_' . get_current_user_id(),
		array(
			'old'     => $body,
			'errors'  => $errors,
			'general' => $general,
		),
		5 * MINUTE_IN_SECONDS
	);
	jobly_integration_redirect( 'jobly-job-new', array( 'jobly_msg' => $general ) );
}
add_action( 'admin_post_jobly_integration_create', 'jobly_integration_handle_create' );

/**
 * Field error block.
 *
 * @param array  $errors Errors by field.
 * @param string $field  Field name.
 */
function jobly_integration_field_errors( array $errors, $field ) {
	foreach ( $errors[ $field ] ?? array() as $msg ) {
		echo '<p class="jobly-errors">' . esc_html( $msg ) . '</p>';
	}
}

/**
 * Screen callback.
 */
function jobly_integration_page_job_new() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	jobly_integration_header( __( 'Dodaj delovno mesto', 'jobly-integration' ) );

	$demo = jobly_integration_is_demo();
	if ( ! $demo && ! jobly_integration_require_connection() ) {
		jobly_integration_footer();
		return;
	}
	if ( $demo ) {
		echo '<div class="jobly-banner">';
		jobly_integration_icon( 'sparkles', '', 22 );
		echo '<div><strong>' . esc_html__( 'Demo način: obrazec je onemogočen', 'jobly-integration' ) . '</strong><span>' . esc_html__( 'Za objavo oglasa povežite podjetje.', 'jobly-integration' ) . '</span></div><a class="button button-primary" href="' . esc_url( jobly_integration_admin_url( 'jobly-setup' ) ) . '">' . esc_html__( 'Poveži podjetje', 'jobly-integration' ) . '</a></div>';
	}

	$state  = $demo ? false : get_transient( 'jobly_integration_form_' . get_current_user_id() );
	$old    = is_array( $state ) ? $state['old'] : array();
	$errors = is_array( $state ) ? $state['errors'] : array();
	delete_transient( 'jobly_integration_form_' . get_current_user_id() );
	$val           = static function ( $k ) use ( $old ) {
		$v = $old[ $k ] ?? '';
		return is_array( $v ) ? implode( "\n", $v ) : (string) $v;
	};
	$category_list = $demo ? array() : jobly_integration_categories();
	$cats          = array( 'Administracija', 'Backend razvoj', 'Frontend razvoj', 'Računovodstvo', 'Prodaja', 'Skladiščenje', 'Marketing', 'Proizvodnja', 'Gostinstvo in turizem', 'Logistika in transport' );
	?>
	<form class="jobly-form jobly-panel" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="jobly_integration_create">
		<?php wp_nonce_field( 'jobly_integration_create' ); ?>
		<p class="description"><?php esc_html_e( 'Oglas se objavi neposredno na Jobly. Plačni razpon je obvezen (v EUR).', 'jobly-integration' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="jf-title"><?php esc_html_e( 'Naziv', 'jobly-integration' ); ?> *</label></th>
				<td><input id="jf-title" name="title" class="regular-text" maxlength="255" value="<?php echo esc_attr( $val( 'title' ) ); ?>"<?php disabled( $demo ); ?>><?php jobly_integration_field_errors( $errors, 'title' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="jf-description"><?php esc_html_e( 'Opis', 'jobly-integration' ); ?> *</label></th>
				<td><textarea id="jf-description" name="description" rows="8"<?php disabled( $demo ); ?>><?php echo esc_textarea( $val( 'description' ) ); ?></textarea><?php jobly_integration_field_errors( $errors, 'description' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="jf-type"><?php esc_html_e( 'Vrsta zaposlitve', 'jobly-integration' ); ?> *</label></th>
				<td>
					<select id="jf-type" name="employment_type"<?php disabled( $demo ); ?>>
						<?php foreach ( jobly_integration_employment_types() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $val( 'employment_type' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select><?php jobly_integration_field_errors( $errors, 'employment_type' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jf-arr"><?php esc_html_e( 'Način dela', 'jobly-integration' ); ?> *</label></th>
				<td>
					<select id="jf-arr" name="work_arrangement"<?php disabled( $demo ); ?>>
						<?php foreach ( jobly_integration_work_arrangements() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $val( 'work_arrangement' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select><?php jobly_integration_field_errors( $errors, 'work_arrangement' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="jf-location"><?php esc_html_e( 'Kraj', 'jobly-integration' ); ?></label></th>
				<td><input id="jf-location" name="location" class="regular-text" maxlength="255" value="<?php echo esc_attr( $val( 'location' ) ); ?>"<?php disabled( $demo ); ?>><?php jobly_integration_field_errors( $errors, 'location' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="jf-category"><?php esc_html_e( 'Kategorija', 'jobly-integration' ); ?> *</label></th>
				<td>
					<?php if ( $category_list ) : ?>
						<select id="jf-category" name="category"<?php disabled( $demo ); ?>>
							<option value=""><?php esc_html_e( '— izberi —', 'jobly-integration' ); ?></option>
							<?php
							$by_group = array();
							foreach ( $category_list as $cat ) {
								if ( empty( $cat['group'] ) ) {
									continue; // Root node.
								}
								$by_group[ (string) ( $cat['group'] ?? '' ) ][] = $cat;
							}
							foreach ( $by_group as $group => $group_cats ) :
								echo '' !== $group ? '<optgroup label="' . esc_attr( $group ) . '">' : '';
								foreach ( $group_cats as $cat ) :
									?>
									<option value="<?php echo esc_attr( (string) $cat['name'] ); ?>" <?php selected( $val( 'category' ), (string) $cat['name'] ); ?>><?php echo esc_html( (string) $cat['name'] ); ?></option>
									<?php
								endforeach;
								echo '' !== $group ? '</optgroup>' : '';
							endforeach;
							?>
						</select>
					<?php else : ?>
						<input id="jf-category" name="category" class="regular-text" list="jf-categories" value="<?php echo esc_attr( $val( 'category' ) ); ?>"<?php disabled( $demo ); ?>>
						<datalist id="jf-categories">
						<?php foreach ( $cats as $c ) : ?>
							<option value="<?php echo esc_attr( $c ); ?>">
						<?php endforeach; ?>
						</datalist>
						<p class="description"><?php esc_html_e( 'Točno ime kategorije na Jobly (npr. Računovodstvo). Neznana kategorija se zavrne.', 'jobly-integration' ); ?></p>
					<?php endif; ?>
					<?php jobly_integration_field_errors( $errors, 'category' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Plačni razpon (EUR)', 'jobly-integration' ); ?> *</th>
				<td>
					<input name="salary_min" type="number" min="1" step="1" class="small-text" aria-label="<?php esc_attr_e( 'Najnižja plača', 'jobly-integration' ); ?>" placeholder="min" value="<?php echo esc_attr( $val( 'salary_min' ) ); ?>"<?php disabled( $demo ); ?>>
					–
					<input name="salary_max" type="number" min="1" step="1" class="small-text" aria-label="<?php esc_attr_e( 'Najvišja plača', 'jobly-integration' ); ?>" placeholder="max" value="<?php echo esc_attr( $val( 'salary_max' ) ); ?>"<?php disabled( $demo ); ?>>
					<?php jobly_integration_field_errors( $errors, 'salary_min' ); ?>
					<?php jobly_integration_field_errors( $errors, 'salary_max' ); ?>
				</td>
			</tr>
			<?php
			$lists = array(
				'responsibilities' => __( 'Naloge', 'jobly-integration' ),
				'requirements'     => __( 'Zahteve', 'jobly-integration' ),
				'benefits'         => __( 'Ugodnosti', 'jobly-integration' ),
				'technologies'     => __( 'Tehnologije', 'jobly-integration' ),
			);
			foreach ( $lists as $f => $label ) :
				?>
				<tr>
					<th scope="row"><label for="jf-<?php echo esc_attr( $f ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<textarea id="jf-<?php echo esc_attr( $f ); ?>" name="<?php echo esc_attr( $f ); ?>" rows="4"<?php disabled( $demo ); ?>><?php echo esc_textarea( $val( $f ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Ena postavka na vrstico.', 'jobly-integration' ); ?></p>
						<?php jobly_integration_field_errors( $errors, $f ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php submit_button( __( 'Objavi na Jobly', 'jobly-integration' ), 'primary', 'submit', true, $demo ? array( 'disabled' => 'disabled' ) : array() ); ?>
	</form>
	<?php
	jobly_integration_footer();
}
