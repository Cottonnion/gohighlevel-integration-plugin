<?php
declare(strict_types=1);

namespace GHL_CRM\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Family Relationships Repository
 *
 * Manages parent-child account relationships
 *
 * @package    GHL_CRM_Integration
 * @subpackage Database
 */
class FamilyRelationshipsRepository {
	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Table name
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Get instance
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'ghl_family_relationships';
		
		// Register cleanup hooks
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks for automatic cleanup
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Cleanup relationships when a user is deleted
		add_action( 'delete_user', [ $this, 'cleanup_deleted_user_relationships' ], 10, 2 );
		add_action( 'deleted_user', [ $this, 'cleanup_deleted_user_relationships' ], 10, 2 );
		
		// Multisite specific - cleanup when user removed from site
		add_action( 'remove_user_from_blog', [ $this, 'cleanup_user_from_site' ], 10, 2 );
	}

	/**
	 * Create a parent-child relationship
	 *
	 * @param int    $parent_user_id Parent WordPress user ID.
	 * @param int    $child_user_id  Child WordPress user ID.
	 * @param string $family_group_id Optional family group ID (auto-generated if empty).
	 * @return int|false Relationship ID on success, false on failure.
	 */
	public function create_relationship( int $parent_user_id, int $child_user_id, string $family_group_id = '' ) {
		global $wpdb;

		// Validation
		if ( $parent_user_id === $child_user_id ) {
			return false; // Can't be your own parent
		}

		// Check if child already has a parent
		if ( $this->get_parent( $child_user_id ) ) {
			return false; // Child already linked to a parent
		}

		// Check if parent is already someone's child (prevent circular relationships)
		if ( $this->get_parent( $parent_user_id ) ) {
			return false; // Parent can't also be a child
		}

		// Generate family group ID if not provided
		if ( empty( $family_group_id ) ) {
			$family_group_id = 'family-wp-' . $parent_user_id;
		}

		$current_site_id = get_current_blog_id();

		// Insert relationship
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table_name,
			[
				'parent_user_id'  => $parent_user_id,
				'child_user_id'   => $child_user_id,
				'family_group_id' => $family_group_id,
				'status'          => 'active',
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'site_id'         => $current_site_id,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( false === $result ) {
			return false;
		}

		// Update user meta for quick lookups
		update_user_meta( $child_user_id, '_ghl_parent_id', $parent_user_id );
		update_user_meta( $child_user_id, '_ghl_account_type', 'child' );
		update_user_meta( $parent_user_id, '_ghl_account_type', 'parent' );

		$relationship_id = (int) $wpdb->insert_id;

		// Queue sync of parent's tags to child's GHL contact
		do_action( 'ghl_family_relationship_created', $parent_user_id, $child_user_id, $relationship_id );

		return $relationship_id;
	}

	/**
	 * Get parent user ID for a child
	 *
	 * @param int $child_user_id Child WordPress user ID.
	 * @return int|null Parent user ID or null if not found.
	 */
	public function get_parent( int $child_user_id ): ?int {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$parent_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT parent_user_id FROM {$this->table_name} WHERE child_user_id = %d AND site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$child_user_id,
				$current_site_id
			)
		);

		return $parent_id ? (int) $parent_id : null;
	}

	/**
	 * Get all children for a parent
	 *
	 * @param int $parent_user_id Parent WordPress user ID.
	 * @return array Array of child user IDs.
	 */
	public function get_children( int $parent_user_id ): array {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT child_user_id FROM {$this->table_name} WHERE parent_user_id = %d AND site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$parent_user_id,
				$current_site_id
			)
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Get all children relationships for a parent (with full details)
	 *
	 * @param int $parent_user_id Parent WordPress user ID.
	 * @return array Array of relationship objects.
	 */
	public function get_children_relationships( int $parent_user_id ): array {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE parent_user_id = %d AND site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$parent_user_id,
				$current_site_id
			)
		);

		return $results ?: [];
	}

	/**
	 * Get full relationship data
	 *
	 * @param int $relationship_id Relationship ID.
	 * @return object|null Relationship object or null if not found.
	 */
	public function get_relationship( int $relationship_id ): ?object {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$relationship = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d AND site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$relationship_id,
				$current_site_id
			)
		);

		return $relationship ?: null;
	}

	/**
	 * Delete a relationship
	 *
	 * @param int $parent_user_id Parent WordPress user ID.
	 * @param int $child_user_id  Child WordPress user ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_relationship( int $parent_user_id, int $child_user_id ): bool {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table_name,
			[
				'parent_user_id' => $parent_user_id,
				'child_user_id'  => $child_user_id,
				'site_id'        => $current_site_id,
			],
			[ '%d', '%d', '%d' ]
		);

		if ( $result ) {
			// Clean up user meta
			delete_user_meta( $child_user_id, '_ghl_parent_id' );
			delete_user_meta( $child_user_id, '_ghl_account_type' );

			// Check if parent still has other children
			$remaining_children = $this->get_children( $parent_user_id );
			if ( empty( $remaining_children ) ) {
				delete_user_meta( $parent_user_id, '_ghl_account_type' );
			}

			return true;
		}

		return false;
	}

	/**
	 * Check if a relationship exists
	 *
	 * @param int $parent_user_id Parent WordPress user ID.
	 * @param int $child_user_id  Child WordPress user ID.
	 * @return bool True if relationship exists, false otherwise.
	 */
	public function relationship_exists( int $parent_user_id, int $child_user_id ): bool {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE parent_user_id = %d AND child_user_id = %d AND site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$parent_user_id,
				$child_user_id,
				$current_site_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get family group ID for a user (parent or child)
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null Family group ID or null if not found.
	 */
	public function get_family_group_id( int $user_id ): ?string {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// Try as parent first
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$family_group_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT family_group_id FROM {$this->table_name} WHERE parent_user_id = %d AND site_id = %d AND status = 'active' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$current_site_id
			)
		);

		// If not found, try as child
		if ( ! $family_group_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$family_group_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT family_group_id FROM {$this->table_name} WHERE child_user_id = %d AND site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$current_site_id
				)
			);
		}

		return $family_group_id ?: null;
	}

	/**
	 * Get all family members by family group ID
	 *
	 * @param string $family_group_id Family group ID.
	 * @return array Array of user IDs (parent + children).
	 */
	public function get_family_members( string $family_group_id ): array {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT parent_user_id, child_user_id FROM {$this->table_name} WHERE family_group_id = %s AND site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$family_group_id,
				$current_site_id
			)
		);

		$members = [];
		foreach ( $results as $row ) {
			$members[] = (int) $row->parent_user_id;
			$members[] = (int) $row->child_user_id;
		}

		return array_unique( $members );
	}

	/**
	 * Check if a user is a child account
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True if user is a child, false otherwise.
	 */
	public function is_child( int $user_id ): bool {
		return null !== $this->get_parent( $user_id );
	}

	/**
	 * Check if a user is a parent account
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True if user is a parent, false otherwise.
	 */
	public function is_parent( int $user_id ): bool {
		$children = $this->get_children( $user_id );
		return ! empty( $children );
	}

	/**
	 * Get all parent user IDs
	 *
	 * @return array Array of parent user IDs
	 */
	public function get_all_parents(): array {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$parent_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT parent_user_id FROM {$this->table_name} WHERE site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id
			)
		);

		return array_map( 'intval', $parent_ids );
	}

	/**
	 * Get relationship statistics
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics(): array {
		global $wpdb;

		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_relationships = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_parents = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT parent_user_id) FROM {$this->table_name} WHERE site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_families = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT family_group_id) FROM {$this->table_name} WHERE site_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id
			)
		);

		return [
			'total_relationships' => (int) $total_relationships,
			'total_parents'       => (int) $total_parents,
			'total_children'      => (int) $total_relationships,
			'total_families'      => (int) $total_families,
		];
	}

	/**
	 * Cleanup relationships when a user is deleted
	 *
	 * Handles cascade delete behavior since MySQL FOREIGN KEY constraints
	 * can cause performance issues in WordPress.
	 *
	 * @param int      $user_id User ID being deleted.
	 * @param int|null $reassign ID of user to reassign posts to (unused here).
	 * @return void
	 */
	public function cleanup_deleted_user_relationships( int $user_id, ?int $reassign = null ): void {
		global $wpdb;

		// If user is a parent, delete all their child relationships
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted_as_parent = $wpdb->delete(
			$this->table_name,
			[ 'parent_user_id' => $user_id ],
			[ '%d' ]
		);

		// If user is a child, delete their relationship with parent
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted_as_child = $wpdb->delete(
			$this->table_name,
			[ 'child_user_id' => $user_id ],
			[ '%d' ]
		);

		// Log cleanup for debugging
		if ( $deleted_as_parent || $deleted_as_child ) {
			error_log(
				sprintf(
					'GHL CRM: Cleaned up family relationships for deleted user %d (parent: %d, child: %d)',
					$user_id,
					$deleted_as_parent,
					$deleted_as_child
				)
			);
		}

		// Cleanup user meta
		delete_user_meta( $user_id, '_ghl_parent_id' );
		delete_user_meta( $user_id, '_ghl_account_type' );
	}

	/**
	 * Cleanup relationships when a user is removed from a site (multisite)
	 *
	 * @param int $user_id User ID being removed.
	 * @param int $blog_id Site ID user is being removed from.
	 * @return void
	 */
	public function cleanup_user_from_site( int $user_id, int $blog_id ): void {
		global $wpdb;

		// Delete relationships specific to this site
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			$this->table_name,
			[
				'parent_user_id' => $user_id,
				'site_id'        => $blog_id,
			],
			[ '%d', '%d' ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted += $wpdb->delete(
			$this->table_name,
			[
				'child_user_id' => $user_id,
				'site_id'       => $blog_id,
			],
			[ '%d', '%d' ]
		);

		if ( $deleted ) {
			error_log(
				sprintf(
					'GHL CRM: Cleaned up family relationships for user %d removed from site %d (%d records)',
					$user_id,
					$blog_id,
					$deleted
				)
			);
		}
	}
}
