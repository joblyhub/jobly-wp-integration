<?php
/**
 * List table of applications.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table of applications.
 */
class Jobly_Integration_Applications_Table extends WP_List_Table {

	/**
	 * HTTP code of the API call (200 = ok).
	 *
	 * @var int
	 */
	public $api_code = 200;

	/**
	 * Number of applications before filtering.
	 *
	 * @var int
	 */
	public $total_all = 0;

	/**
	 * Set up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'application',
				'plural'   => 'applications',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Table columns.
	 */
	public function get_columns() {
		return array(
			'candidate' => __( 'Kandidat', 'jobly-integration' ),
			'job'       => __( 'Delovno mesto', 'jobly-integration' ),
			'stage'     => __( 'Stanje', 'jobly-integration' ),
			'applied'   => __( 'Datum', 'jobly-integration' ),
		);
	}

	/**
	 * Load, filter and paginate the rows.
	 */
	public function prepare_items() {
		$res             = jobly_integration_all_applications();
		$this->api_code  = $res['code'];
		$this->total_all = count( $res['items'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$items  = array_filter(
			$res['items'],
			static function ( $a ) use ( $search ) {
				return '' === $search || false !== stripos( ( $a['applicant']['name'] ?? '' ) . ' ' . ( $a['job']['title'] ?? '' ), $search );
			}
		);

		$per_page = jobly_integration_per_page();
		$this->set_pagination_args(
			array(
				'total_items' => count( $items ),
				'per_page'    => $per_page,
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = array_slice( array_values( $items ), ( $this->get_pagenum() - 1 ) * $per_page, $per_page );
	}

	/**
	 * Render the "candidate" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_candidate( $item ) {
		$url  = jobly_integration_admin_url(
			'jobly-applications',
			array(
				'action'      => 'show',
				'application' => $item['id'],
			)
		);
		$name = (string) ( $item['applicant']['name'] ?? '' );
		return '<span class="jobly-avatar">' . esc_html( jobly_integration_initials( $name ) ) . '</span><strong><a class="row-title" href="' . esc_url( $url ) . '">' . esc_html( (string) ( $item['applicant']['name'] ?? '' ) ) . '</a></strong>'
			. $this->row_actions( array( 'show' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Poglej', 'jobly-integration' ) . '</a>' ) );
	}

	/**
	 * Render the "job" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_job( $item ) {
		return esc_html( (string) ( $item['job']['title'] ?? '' ) );
	}

	/**
	 * Render the "stage" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_stage( $item ) {
		return jobly_integration_stage_badge( (string) ( $item['stage'] ?? '' ) );
	}

	/**
	 * Render the "applied" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_applied( $item ) {
		return esc_html( jobly_integration_format_date( $item['appliedAt'] ?? null ) );
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param array  $item Row from the API.
	 * @param string $column_name Column id.
	 */
	protected function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Empty-state text.
	 */
	public function no_items() {
		esc_html_e( 'Ni prijav.', 'jobly-integration' );
	}
}
