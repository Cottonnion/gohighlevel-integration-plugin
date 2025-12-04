<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Object Field Discovery
 *
 * Dynamically discovers available fields for WordPress Custom Post Types
 * to map to GoHighLevel Custom Objects
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 * @since      1.0.0
 */
class CustomObjectFieldDiscovery {

	/**
	 * Get all available fields for a specific post type
	 *
	 * @param string $post_type The post type slug
	 * @return array Grouped fields array
	 */
	public static function get_fields_for_post_type( string $post_type ): array {
		if ( empty( $post_type ) ) {
			return [];
		}

		$fields = [
			'core_fields'     => self::get_core_post_fields( $post_type ),
			'meta_fields'     => self::get_post_meta_fields( $post_type ),
			'taxonomies'      => self::get_post_taxonomies( $post_type ),
			'acf_fields'      => self::get_acf_fields( $post_type ),
			'contact_options' => self::get_contact_options( $post_type ),
			'sync_triggers'   => self::get_sync_triggers( $post_type ),
		];

		return $fields;
	}

	/**
	 * Get core WordPress post fields
	 *
	 * @param string $post_type Post type slug
	 * @return array Core fields
	 */
	private static function get_core_post_fields( string $post_type ): array {
		$core_fields = [
			[
				'key'   => 'post_title',
				'label' => __( 'Title', 'ghl-crm-integration' ),
				'type'  => 'text',
			],
			[
				'key'   => 'post_content',
				'label' => __( 'Content', 'ghl-crm-integration' ),
				'type'  => 'textarea',
			],
			[
				'key'   => 'post_excerpt',
				'label' => __( 'Excerpt', 'ghl-crm-integration' ),
				'type'  => 'textarea',
			],
			[
				'key'   => 'post_date',
				'label' => __( 'Published Date', 'ghl-crm-integration' ),
				'type'  => 'date',
			],
			[
				'key'   => 'post_modified',
				'label' => __( 'Modified Date', 'ghl-crm-integration' ),
				'type'  => 'date',
			],
			[
				'key'   => 'post_status',
				'label' => __( 'Status', 'ghl-crm-integration' ),
				'type'  => 'text',
			],
			[
				'key'   => 'post_name',
				'label' => __( 'Slug', 'ghl-crm-integration' ),
				'type'  => 'text',
			],
			[
				'key'   => 'post_author',
				'label' => __( 'Author ID', 'ghl-crm-integration' ),
				'type'  => 'number',
			],
		];

		// Add ID field
		array_unshift(
			$core_fields,
			[
				'key'   => 'ID',
				'label' => __( 'Post ID', 'ghl-crm-integration' ),
				'type'  => 'number',
			]
		);

		return $core_fields;
	}

	/**
	 * Get post meta fields for a specific post type
	 *
	 * @param string $post_type Post type slug
	 * @return array Meta fields
	 */
	private static function get_post_meta_fields( string $post_type ): array {
		global $wpdb;

		// Get a sample of posts to discover meta keys
		$query = $wpdb->prepare(
			"SELECT DISTINCT pm.meta_key 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = %s
			AND pm.meta_key NOT LIKE '\\_%%'
			AND pm.meta_key NOT LIKE 'ghl_%%'
			ORDER BY pm.meta_key ASC
			LIMIT 100",
			$post_type
		);

		$meta_keys = $wpdb->get_col( $query );
		$meta_fields = [];

		foreach ( $meta_keys as $meta_key ) {
			// Skip internal WordPress and plugin meta
			if ( self::is_internal_meta_key( $meta_key ) ) {
				continue;
			}

			$meta_fields[] = [
				'key'   => 'meta:' . $meta_key,
				'label' => self::format_meta_label( $meta_key ),
				'type'  => 'meta',
			];
		}

		return $meta_fields;
	}

	/**
	 * Get taxonomies associated with post type
	 *
	 * @param string $post_type Post type slug
	 * @return array Taxonomies
	 */
	private static function get_post_taxonomies( string $post_type ): array {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$tax_fields = [];

		foreach ( $taxonomies as $taxonomy ) {
			$tax_fields[] = [
				'key'   => 'taxonomy:' . $taxonomy->name,
				'label' => $taxonomy->label,
				'type'  => 'taxonomy',
			];
		}

		return $tax_fields;
	}

	/**
	 * Get ACF fields if ACF is active
	 *
	 * @param string $post_type Post type slug
	 * @return array ACF fields
	 */
	private static function get_acf_fields( string $post_type ): array {
		// Check if ACF is active
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return [];
		}

		$acf_fields = [];
		$field_groups = acf_get_field_groups( [ 'post_type' => $post_type ] );

		foreach ( $field_groups as $group ) {
			$fields = acf_get_fields( $group['key'] );

			if ( ! empty( $fields ) ) {
				foreach ( $fields as $field ) {
					$acf_fields[] = [
						'key'   => 'acf:' . $field['name'],
						'label' => $field['label'] . ' (' . $field['type'] . ')',
						'type'  => 'acf',
						'acf_type' => $field['type'],
					];
				}
			}
		}

		return $acf_fields;
	}

	/**
	 * Get contact linking options based on post type
	 *
	 * @param string $post_type Post type slug
	 * @return array Contact options
	 */
	private static function get_contact_options( string $post_type ): array {
		$options = [
			'primary' => [
				[
					'key'   => 'post_author',
					'label' => __( 'Post Author', 'ghl-crm-integration' ),
				],
				[
					'key'   => 'meta_field',
					'label' => __( 'Custom Meta Field (email)', 'ghl-crm-integration' ),
				],
			],
			'secondary' => [
				[
					'key'   => 'post_author',
					'label' => __( 'Post Author', 'ghl-crm-integration' ),
				],
			],
		];

		// Add context-aware options based on post type
		switch ( $post_type ) {
			case 'product':
				$options['primary'][] = [
					'key'   => 'product_purchasers',
					'label' => __( 'Product Purchaser', 'ghl-crm-integration' ),
				];
				$options['secondary'][] = [
					'key'   => 'recent_purchasers',
					'label' => __( 'Recent Purchasers (last 30 days)', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-courses':
				$options['primary'][] = [
					'key'   => 'course_students',
					'label' => __( 'Enrolled Students (creates object per student)', 'ghl-crm-integration' ),
				];
				$options['secondary'][] = [
					'key'   => 'course_instructor',
					'label' => __( 'Course Instructor', 'ghl-crm-integration' ),
				];
				$options['secondary'][] = [
					'key'   => 'completed_students',
					'label' => __( 'Students Who Completed', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-assignment':
				$options['primary'][] = [
					'key'   => 'assignment_student',
					'label' => __( 'Student (assignment author)', 'ghl-crm-integration' ),
				];
				$options['secondary'][] = [
					'key'   => 'assignment_instructor',
					'label' => __( 'Grading Instructor', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-lessons':
			case 'sfwd-topic':
				$options['secondary'][] = [
					'key'   => 'lesson_instructor',
					'label' => __( 'Lesson Instructor', 'ghl-crm-integration' ),
				];
				break;
		}

		return $options;
	}

	/**
	 * Check if meta key is internal/should be excluded
	 *
	 * @param string $meta_key Meta key
	 * @return bool True if internal
	 */
	private static function is_internal_meta_key( string $meta_key ): bool {
		$excluded_prefixes = [
			'_',
			'ghl_',
			'wp_',
			'bp_',
			'bb_',
			'edd_',
			'wc_',
			'_sku',
			'_thumbnail_id',
			'_edit_',
		];

		foreach ( $excluded_prefixes as $prefix ) {
			if ( strpos( $meta_key, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Format meta key as human-readable label
	 *
	 * @param string $meta_key Meta key
	 * @return string Formatted label
	 */
	private static function format_meta_label( string $meta_key ): string {
		// Remove common prefixes
		$label = preg_replace( '/^(product_|course_|ld_|learndash_)/', '', $meta_key );

		// Convert underscores to spaces and capitalize
		$label = str_replace( '_', ' ', $label );
		$label = ucwords( $label );

		return $label . ' (meta)';
	}

	/**
	 * Get context-aware sync triggers based on post type
	 *
	 * @param string $post_type Post type slug
	 * @return array Available sync triggers
	 */
	private static function get_sync_triggers( string $post_type ): array {
		// Default triggers available for all post types
		$triggers = [
			[
				'key'         => 'publish',
				'label'       => __( 'Post Published', 'ghl-crm-integration' ),
				'description' => __( 'Sync when post is published or updated', 'ghl-crm-integration' ),
			],
			[
				'key'         => 'update',
				'label'       => __( 'Post Updated', 'ghl-crm-integration' ),
				'description' => __( 'Sync when post content is updated', 'ghl-crm-integration' ),
			],
			[
				'key'         => 'delete',
				'label'       => __( 'Post Deleted', 'ghl-crm-integration' ),
				'description' => __( 'Delete custom object when post is deleted', 'ghl-crm-integration' ),
			],
		];

		// Add context-specific triggers based on post type
		switch ( $post_type ) {
			case 'product':
				$triggers[] = [
					'key'         => 'product_purchased',
					'label'       => __( 'Product Purchased (Order Completed)', 'ghl-crm-integration' ),
					'description' => __( 'Create object when product is purchased and order is completed', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'order_processing',
					'label'       => __( 'Order Processing', 'ghl-crm-integration' ),
					'description' => __( 'Create object when order enters processing status', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'thankyou_page',
					'label'       => __( 'Thank You Page Viewed', 'ghl-crm-integration' ),
					'description' => __( 'Create object when customer views order thank you page', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'stock_changed',
					'label'       => __( 'Stock Level Changed', 'ghl-crm-integration' ),
					'description' => __( 'Sync when product stock quantity changes', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-courses':
				$triggers[] = [
					'key'         => 'student_enrolled',
					'label'       => __( 'Student Enrolled', 'ghl-crm-integration' ),
					'description' => __( 'Create object per student when they enroll in the course', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'student_completed',
					'label'       => __( 'Student Completed Course', 'ghl-crm-integration' ),
					'description' => __( 'Update object when student completes the course', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'student_unenrolled',
					'label'       => __( 'Student Unenrolled', 'ghl-crm-integration' ),
					'description' => __( 'Delete or archive object when student is removed from course', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-lessons':
			case 'sfwd-topic':
				$triggers[] = [
					'key'         => 'lesson_completed',
					'label'       => __( 'Lesson Completed', 'ghl-crm-integration' ),
					'description' => __( 'Create object when student completes this lesson', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'lesson_accessed',
					'label'       => __( 'Lesson Accessed', 'ghl-crm-integration' ),
					'description' => __( 'Track when students access this lesson', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-assignment':
				$triggers[] = [
					'key'         => 'assignment_submitted',
					'label'       => __( 'Assignment Submitted', 'ghl-crm-integration' ),
					'description' => __( 'Create object when student submits assignment', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'assignment_graded',
					'label'       => __( 'Assignment Graded', 'ghl-crm-integration' ),
					'description' => __( 'Update object when assignment is graded', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'assignment_approved',
					'label'       => __( 'Assignment Approved', 'ghl-crm-integration' ),
					'description' => __( 'Update when assignment is approved by instructor', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-quiz':
				$triggers[] = [
					'key'         => 'quiz_completed',
					'label'       => __( 'Quiz Completed', 'ghl-crm-integration' ),
					'description' => __( 'Create object when student completes quiz', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'quiz_passed',
					'label'       => __( 'Quiz Passed', 'ghl-crm-integration' ),
					'description' => __( 'Track when student passes the quiz', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'quiz_failed',
					'label'       => __( 'Quiz Failed', 'ghl-crm-integration' ),
					'description' => __( 'Track when student fails the quiz', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-certificates':
				$triggers[] = [
					'key'         => 'certificate_earned',
					'label'       => __( 'Certificate Earned', 'ghl-crm-integration' ),
					'description' => __( 'Create object when student earns certificate', 'ghl-crm-integration' ),
				];
				break;

			case 'groups':
				$triggers[] = [
					'key'         => 'group_created',
					'label'       => __( 'Group Created', 'ghl-crm-integration' ),
					'description' => __( 'Sync when BuddyBoss group is created', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'member_joined',
					'label'       => __( 'Member Joined Group', 'ghl-crm-integration' ),
					'description' => __( 'Create object when member joins group', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'member_left',
					'label'       => __( 'Member Left Group', 'ghl-crm-integration' ),
					'description' => __( 'Delete object when member leaves group', 'ghl-crm-integration' ),
				];
				break;

			case 'sfwd-transactions':
				$triggers[] = [
					'key'         => 'order_completed',
					'label'       => __( 'Order Completed', 'ghl-crm-integration' ),
					'description' => __( 'Create object when order is completed', 'ghl-crm-integration' ),
				];
				$triggers[] = [
					'key'         => 'order_refunded',
					'label'       => __( 'Order Refunded', 'ghl-crm-integration' ),
					'description' => __( 'Update object when order is refunded', 'ghl-crm-integration' ),
				];
				break;
		}

		return $triggers;
	}
}
