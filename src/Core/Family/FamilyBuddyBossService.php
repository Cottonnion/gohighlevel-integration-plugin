<?php
/**
 * BuddyBoss orchestration for family accounts.
 *
 * @package GHL_CRM_Integration
 */

declare(strict_types=1);

namespace GHL_CRM\Core\Family;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Core\TagManager;
use GHL_CRM\Database\FamilyRelationshipsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles BuddyBoss group lifecycle for family relationships.
 */
class FamilyBuddyBossService {
	/**
	 * Repository for family relationships.
	 *
	 * @var FamilyRelationshipsRepository
	 */
	private FamilyRelationshipsRepository $repository;

	/**
	 * Settings manager reference.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings;

	/**
	 * Tag manager reference.
	 *
	 * @var TagManager
	 */
	private TagManager $tag_manager;

	/**
	 * Service constructor.
	 *
	 * @param FamilyRelationshipsRepository|null $repository  Repo dependency.
	 * @param SettingsManager|null               $settings    Settings dependency.
	 * @param TagManager|null                    $tag_manager Tag manager dependency.
	 */
	public function __construct(
		?FamilyRelationshipsRepository $repository = null,
		?SettingsManager $settings = null,
		?TagManager $tag_manager = null
	) {
		$this->repository  = $repository ?? FamilyRelationshipsRepository::get_instance();
		$this->settings    = $settings ?? SettingsManager::get_instance();
		$this->tag_manager = $tag_manager ?? TagManager::get_instance();
	}

	/**
	 * Determine if BuddyBoss integration should run.
	 */
	public function is_enabled(): bool {
		if ( ! $this->settings->get_setting( 'family_buddyboss_groups', false ) ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'buddyboss-platform/bp-loader.php' ) && function_exists( 'bp_is_active' );
	}

	/**
	 * Add a child to the parent's BuddyBoss group.
	 *
	 * @param int $parent_id Parent user ID.
	 * @param int $child_id  Child user ID.
	 *
	 * @return bool
	 */
	public function add_child_to_group( int $parent_id, int $child_id ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$group_id = $this->ensure_group_exists( $parent_id );
		if ( ! $group_id || ! function_exists( 'groups_join_group' ) ) {
			return false;
		}

		$result = groups_join_group( $group_id, $child_id );

		if ( $result ) {
			do_action( 'ghl_family_child_added_to_buddyboss_group', $group_id, $parent_id, $child_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return (bool) $result;
	}

	/**
	 * Remove a child from a BuddyBoss group.
	 *
	 * @param int $parent_id Parent user ID.
	 * @param int $child_id  Child user ID.
	 *
	 * @return bool
	 */
	public function remove_child_from_group( int $parent_id, int $child_id ): bool {
		if ( ! $this->is_enabled() || ! function_exists( 'groups_leave_group' ) ) {
			return false;
		}

		$group_id = get_user_meta( $parent_id, '_ghl_family_group_id', true );
		if ( ! $group_id ) {
			return false;
		}

		$result = groups_leave_group( (int) $group_id, $child_id );

		if ( $result ) {
			do_action( 'ghl_family_child_removed_from_buddyboss_group', (int) $group_id, $parent_id, $child_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return (bool) $result;
	}

	/**
	 * Sync BuddyBoss groups for all recorded parents.
	 *
	 * @return array
	 */
	public function sync_all_families(): array {
		if ( ! $this->is_enabled() ) {
			return [
				'success' => false,
				'message' => __( 'BuddyBoss integration is not enabled.', 'ghl-crm-integration' ),
			];
		}

		$parents        = $this->repository->get_all_parents();
		$groups_created = 0;
		$members_added  = 0;
		$errors         = [];

		foreach ( $parents as $parent_id ) {
			$parent_id = (int) $parent_id;
			if ( $parent_id <= 0 ) {
				continue;
			}

			$group_id = $this->ensure_group_exists( $parent_id );
			if ( ! $group_id ) {
				$errors[] = sprintf(
					/* translators: %d: parent user ID */
					__( 'Failed to create group for parent ID %1$d', 'ghl-crm-integration' ),
					$parent_id
				);
				continue;
			}

			++$groups_created;

			foreach ( $this->repository->get_children( $parent_id ) as $child_id ) {
				$child_id = (int) $child_id;
				if ( $child_id <= 0 ) {
					continue;
				}

				if ( $this->add_child_to_group( $parent_id, $child_id ) ) {
					++$members_added;
				} else {
					$errors[] = sprintf(
						/* translators: 1: child user ID, 2: parent user ID */
						__( 'Failed to add child ID %1$d to group for parent ID %2$d', 'ghl-crm-integration' ),
						$child_id,
						$parent_id
					);
				}
			}
		}

		return [
			'success'        => true,
			'groups_created' => $groups_created,
			'members_added'  => $members_added,
			'total_parents'  => count( $parents ),
			'errors'         => $errors,
			'message'        => sprintf(
				/* translators: 1: number of groups synced, 2: number of members synced, 3: number of sync errors */
				__( 'Synced %1$d groups with %2$d members. %3$d errors.', 'ghl-crm-integration' ),
				$groups_created,
				$members_added,
				count( $errors )
			),
		];
	}

	/**
	 * Ensure a BuddyBoss group exists for the parent.
	 *
	 * @param int $parent_id Parent ID to check.
	 *
	 * @return int|false|\WP_Error
	 */
	public function ensure_group_exists( int $parent_id ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$existing_group_id = (int) get_user_meta( $parent_id, '_ghl_family_group_id', true );
		if ( $existing_group_id && function_exists( 'groups_get_group' ) ) {
			$group = groups_get_group( $existing_group_id );
			if ( ! empty( $group->id ) ) {
				return $existing_group_id;
			}
		}

		return $this->create_group( $parent_id );
	}

	/**
	 * Create a BuddyBoss group for the parent.
	 *
	 * @param int $parent_id Parent identifier.
	 *
	 * @return int|false|\WP_Error
	 */
	private function create_group( int $parent_id ) {
		if ( ! function_exists( 'groups_create_group' ) ) {
			return false;
		}

		$parent = get_userdata( $parent_id );
		if ( ! $parent ) {
			return false;
		}

		$group_args = $this->build_group_arguments( $parent );
		$group_id   = groups_create_group( $group_args );

		if ( ! $group_id || is_wp_error( $group_id ) ) {
			return false;
		}

		update_user_meta( $parent_id, '_ghl_family_group_id', $group_id );
		groups_update_groupmeta( $group_id, '_ghl_family_group', true );
		groups_update_groupmeta( $group_id, '_ghl_parent_id', $parent_id );

		do_action( 'ghl_family_buddyboss_group_created', $group_id, $parent_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		return (int) $group_id;
	}

	/**
	 * Prepare BuddyBoss group arguments.
	 *
	 * @param \WP_User $parent_user Parent user model.
	 *
	 * @return array
	 */
	private function build_group_arguments( \WP_User $parent_user ): array {
		$parent_id   = (int) $parent_user->ID;
		$group_name  = $this->resolve_group_name( $parent_user );
		$description = $this->resolve_group_description( $parent_user );
		$group_slug  = sanitize_title( $group_name . '-' . $parent_id );

		return [
			'creator_id'   => $parent_id,
			'name'         => apply_filters( 'ghl_family_buddyboss_group_name', $group_name, $parent_id, $parent_user ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'description'  => apply_filters( 'ghl_family_buddyboss_group_description', $description, $parent_id, $parent_user ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'slug'         => $group_slug,
			'status'       => 'private',
			'enable_forum' => false,
			'date_created' => bp_core_current_time(),
		];
	}

	/**
	 * Resolve group name with placeholder replacements.
	 *
	 * @param \WP_User $parent_user Parent user model.
	 *
	 * @return string
	 */
	private function resolve_group_name( \WP_User $parent_user ): string {
		$pattern     = $this->settings->get_setting( 'family_buddyboss_group_name', "{parent_name}'s Family" );
		$replacement = $this->resolve_replacements( $parent_user );
		$resolved    = str_replace( array_keys( $replacement ), array_values( $replacement ), $pattern );

		if ( '' !== trim( $resolved ) ) {
			return $resolved;
		}

		return sprintf(
			/* translators: %s: Parent's display name */
			__( "%s's Family", 'ghl-crm-integration' ),
			$parent_user->display_name
		);
	}

	/**
	 * Resolve group description with placeholder replacements.
	 *
	 * @param \WP_User $parent_user Parent user model.
	 *
	 * @return string
	 */
	private function resolve_group_description( \WP_User $parent_user ): string {
		$default  = "Private family group for {parent_name} and their family members.\n\nThis group is automatically managed by {plugin_name}.\nParent Account: {parent_name} (ID: {parent_id})\nCreated: {date_created}";
		$pattern  = $this->settings->get_setting( 'family_buddyboss_group_description', $default );
		$resolved = str_replace( array_keys( $this->resolve_replacements( $parent_user ) ), array_values( $this->resolve_replacements( $parent_user ) ), $pattern );

		if ( '' !== trim( $resolved ) ) {
			return $resolved;
		}

		return __( 'Private family group created by GoHighLevel CRM Integration.', 'ghl-crm-integration' );
	}

	/**
	 * Build placeholder replacements for names/descriptions.
	 *
	 * @param \WP_User $parent_user Parent user model.
	 *
	 * @return array<string,string>
	 */
	private function resolve_replacements( \WP_User $parent_user ): array {
		$children    = $this->repository->get_children( (int) $parent_user->ID );
		$tag_setting = (string) $this->settings->get_setting( 'family_parent_tag', '' );
		$tag_map     = $tag_setting ? $this->tag_manager->map_ids_to_names( [ $tag_setting ] ) : [];
		$tag_name    = $tag_setting ? ( $tag_map[ $tag_setting ] ?? $tag_setting ) : '';

		return [
			'{parent_name}'  => $parent_user->display_name,
			'{parent_email}' => $parent_user->user_email,
			'{parent_id}'    => (string) $parent_user->ID,
			'{parent_tag}'   => $tag_name,
			'{child_count}'  => (string) count( $children ),
			'{plugin_name}'  => 'GoHighLevel CRM Integration',
			'{site_name}'    => get_bloginfo( 'name' ),
			'{site_url}'     => get_bloginfo( 'url' ),
			'{date_created}' => current_time( 'mysql' ),
		];
	}
}
