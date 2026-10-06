<?php
/**
 * List table of the company's jobs.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Search, status filter and pagination run locally over the cached API list
 * (the API has no query parameters beyond ?page).
 */
class Jobly_Integration_Jobs_Table extends WP_List_Table {

	/**
	 * HTTP code of the API call (200 = ok).
	 *
	 * @var int
	 */
	public $api_code = 200;

	/**
	 * Number of jobs before filtering.
	 *
	 * @var int
	 */
	public $total_all = 0;

	/**
	 * Status counts for the views.
	 *
	 * @var int[]
	 */
	private $counts = array();

	/**
	 * Set up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'job',
				'plural'   => 'jobs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Table columns.
	 */
	public function get_columns() {
		return array(
			'title'     => __( 'Naziv', 'jobly-integration' ),
			'status'    => __( 'Stanje', 'jobly-integration' ),
			'location'  => __( 'Kraj', 'jobly-integration' ),
			'published' => __( 'Objavljeno', 'jobly-integration' ),
			'apps'      => __( 'Prijav', 'jobly-integration' ),
			'shortcode' => __( 'Kratka koda', 'jobly-integration' ),
		);
	}

	/**
	 * Load, filter and paginate the rows.
	 */
	public function prepare_items() {
		$res             = jobly_integration_all_jobs();
		$this->api_code  = $res['code'];
		$items           = $res['items'];
		$this->total_all = count( $items );

		foreach ( $items as $job ) {
			$st                  = (string) ( $job['status'] ?? '' );
			$this->counts[ $st ] = ( $this->counts[ $st ] ?? 0 ) + 1;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable
		$items = array_filter(
			$items,
			static function ( $job ) use ( $status, $search ) {
				if ( '' !== $status && ( $job['status'] ?? '' ) !== $status ) {
					return false;
				}
				return '' === $search || false !== stripos( ( $job['title'] ?? '' ) . ' ' . ( $job['location'] ?? '' ), $search );
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
	 * Status filter links.
	 */
	protected function get_views() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$views   = array();
		$all     = array_sum( $this->counts );
		$views[] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( jobly_integration_admin_url( 'jobly-jobs' ) ), '' === $current ? ' class="current"' : '', esc_html__( 'Vsa', 'jobly-integration' ), $all );
		foreach ( $this->counts as $st => $n ) {
			$views[] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( jobly_integration_admin_url( 'jobly-jobs', array( 'status' => $st ) ) ), $st === $current ? ' class="current"' : '', esc_html( jobly_integration_status_label( $st ) ), $n );
		}
		return $views;
	}

	/**
	 * Render the "title" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_title( $item ) {
		$slug    = (string) $item['slug'];
		$show    = jobly_integration_admin_url(
			'jobly-jobs',
			array(
				'action' => 'show',
				'job'    => $slug,
			)
		);
		$actions = array( 'show' => '<a href="' . esc_url( $show ) . '">' . esc_html__( 'Poglej', 'jobly-integration' ) . '</a>' );
		if ( 'active' === ( $item['status'] ?? '' ) ) {
			$actions['site'] = '<a href="' . esc_url( jobly_integration_job_url( $slug ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Poglej na strani', 'jobly-integration' ) . '</a>';
		}
		if ( ! jobly_integration_is_demo() ) {
			$actions['edit'] = '<a href="' . esc_url( jobly_integration_edit_url( $slug ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Uredi na Jobly', 'jobly-integration' ) . '</a>';
		}
		$actions['copy'] = '<button type="button" class="jobly-copy" data-jobly-copy="' . esc_attr( '[jobly job="' . $slug . '"]' ) . '" data-copied="' . esc_attr__( 'Kopirano', 'jobly-integration' ) . '">' . esc_html__( 'Kopiraj kratko kodo', 'jobly-integration' ) . '</button>';

		return '<strong><a class="row-title" href="' . esc_url( $show ) . '">' . esc_html( (string) $item['title'] ) . '</a></strong>' . $this->row_actions( $actions );
	}

	/**
	 * Render the "status" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_status( $item ) {
		return jobly_integration_status_badge( (string) ( $item['status'] ?? '' ) );
	}

	/**
	 * Render the "location" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_location( $item ) {
		$loc = (string) ( $item['location'] ?? '' );
		return '' === $loc ? '—' : esc_html( $loc );
	}

	/**
	 * Render the "published" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_published( $item ) {
		return esc_html( jobly_integration_format_date( $item['publishedAt'] ?? null ) );
	}

	/**
	 * Render the "apps" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_apps( $item ) {
		$n = (int) ( $item['applicationsCount'] ?? 0 );
		return '<span class="jobly-count-chip' . ( $n > 0 ? ' has' : '' ) . '">' . esc_html( (string) $n ) . '</span>';
	}

	/**
	 * Render the "shortcode" column.
	 *
	 * @param array $item Row from the API.
	 */
	protected function column_shortcode( $item ) {
		return '<code class="jobly-shortcode">' . esc_html( '[jobly job="' . $item['slug'] . '"]' ) . '</code>';
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
		esc_html_e( 'Ni zadetkov za izbrane filtre.', 'jobly-integration' );
	}
}
