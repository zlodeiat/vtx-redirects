<?php
/**
 * Site content usage scanner.
 *
 * @package VTX_Redirects
 */

namespace Vortex\Vtx_Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Usage_Scanner {
	const RESULT_LIMIT = 100;

	/**
	 * Search post content for source/destination references.
	 *
	 * @param string $source      Source path.
	 * @param string $destination Destination.
	 * @return array<int,array<string,mixed>>
	 */
	public function scan( $source, $destination ) {
		global $wpdb;

		$needles = array_filter(
			array_unique(
				array(
					Normalizer::source_path( $source ),
					Normalizer::destination( $destination ),
				)
			)
		);

		if ( empty( $needles ) ) {
			return array();
		}

		$where_parts = array();
		$values      = array();

		foreach ( $needles as $needle ) {
			$like          = '%' . $wpdb->esc_like( $needle ) . '%';
			$where_parts[] = '(post_content LIKE %s OR post_excerpt LIKE %s OR post_title LIKE %s)';
			$values[]      = $like;
			$values[]      = $like;
			$values[]      = $like;
		}

		$sql = "SELECT ID, post_title, post_type, post_status
			FROM {$wpdb->posts}
			WHERE post_status NOT IN ('trash', 'auto-draft')
			AND (" . implode( ' OR ', $where_parts ) . ')
			ORDER BY post_modified_gmt DESC
			LIMIT %d';

		$values[] = self::RESULT_LIMIT;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic fragments contain only static placeholders created above; all values are prepared below.
		$prepared_sql = $wpdb->prepare( $sql, $values );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared immediately above.
		$posts = $wpdb->get_results( $prepared_sql );

		$results = array();
		foreach ( (array) $posts as $post ) {
			$results[] = array(
				'id'     => (int) $post->ID,
				'title'  => get_the_title( $post->ID ) ? get_the_title( $post->ID ) : __( '(no title)', 'vtx-redirects' ),
				'type'   => sanitize_key( $post->post_type ),
				'status' => sanitize_key( $post->post_status ),
				'edit'   => get_edit_post_link( $post->ID, 'raw' ),
				'view'   => get_permalink( $post->ID ),
			);
		}

		return $results;
	}
}
