<?php
/**
 * Core sitemap provider for the job pages.
 *
 * @package jobly-integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * /wp-sitemap-jobly-1.xml: the careers list and every open job.
 */
class Jobly_Integration_Sitemap_Provider extends WP_Sitemaps_Provider {

	/**
	 * Set the provider name.
	 */
	public function __construct() {
		$this->name        = 'jobly';
		$this->object_type = 'jobs';
	}

	/**
	 * URLs of one sitemap page.
	 *
	 * @param int    $page_num Page number.
	 * @param string $object_subtype Unused.
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$urls = array(
			array(
				'loc' => jobly_integration_careers_url(),
			),
		);
		foreach ( jobly_integration_open_jobs() as $job ) {
			$url = array( 'loc' => jobly_integration_job_url( (string) $job['slug'] ) );
			if ( ! empty( $job['publishedAt'] ) && strtotime( (string) $job['publishedAt'] ) ) {
				$url['lastmod'] = gmdate( 'c', (int) strtotime( (string) $job['publishedAt'] ) );
			}
			$urls[] = $url;
		}
		return $urls;
	}

	/**
	 * Number of sitemap pages (one: the API caps a company at 125 jobs).
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
