<?php
/**
 * Public helper functions for cross-plugin tag checks.
 *
 * These wrappers keep TagManager encapsulated while exposing a simple API
 * for other plugins to query a user's GHL tags without manual instantiation.
 */

declare(strict_types=1);

use GHL_CRM\Core\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'ghl_crm_get_user_tag_ids' ) ) {
	/**
	 * Get stored GHL tag IDs for a user.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string|null $location_id Optional location override; defaults to configured location.
	 * @return array<string> List of tag IDs (strings).
	 */
	function ghl_crm_get_user_tag_ids( int $user_id, ?string $location_id = null ): array {
		return TagManager::get_instance()->get_user_tag_ids( $user_id, $location_id );
	}
}

if ( ! function_exists( 'ghl_crm_get_user_tag_names' ) ) {
	/**
	 * Get stored GHL tag names for a user.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string|null $location_id Optional location override; defaults to configured location.
	 * @return array<string> List of tag names.
	 */
	function ghl_crm_get_user_tag_names( int $user_id, ?string $location_id = null ): array {
		return TagManager::get_instance()->get_user_tag_names( $user_id, $location_id );
	}
}

if ( ! function_exists( 'ghl_crm_user_has_tag' ) ) {
	/**
	 * Check if a user has at least one of the provided tags.
	 *
	 * Accepts tag IDs or names. Names are converted to IDs using TagManager's
	 * current cache; unknown names fall back to string comparison.
	 *
	 * @param int               $user_id     WordPress user ID.
	 * @param string|array      $tags        Single tag (ID or name) or list of tags.
	 * @param string|null       $location_id Optional location override; defaults to configured location.
	 * @return bool True when user has any of the provided tags.
	 */
	function ghl_crm_user_has_tag( int $user_id, $tags, ?string $location_id = null ): bool {
		$tag_manager  = TagManager::get_instance();
		$user_tag_ids = $tag_manager->get_user_tag_ids( $user_id, $location_id );

		if ( empty( $user_tag_ids ) ) {
			return false;
		}

		$tags = is_array( $tags ) ? $tags : [ $tags ];

		// Normalize provided tags to IDs (when known) while preserving raw strings.
		$normalized = $tag_manager->normalize_tag_input( $tags );
		$target_ids = $normalized['ids'];

		if ( empty( $target_ids ) ) {
			return false;
		}

		return (bool) array_intersect( $target_ids, $user_tag_ids );
	}
}

if ( ! function_exists( 'ghl_crm_get_user_contact_id' ) ) {
	/**
	 * Get the mapped GHL contact ID for a user.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string|null $location_id Optional location override; defaults to configured location.
	 * @return string|null Contact ID or null.
	 */
	function ghl_crm_get_user_contact_id( int $user_id, ?string $location_id = null ): ?string {
		return TagManager::get_instance()->get_user_contact_id( $user_id, $location_id );
	}
}

if ( ! function_exists( 'ghl_crm_add_tags_to_user' ) ) {
	/**
	 * Add tags to a user's contact in GHL (queues for async processing).
	 *
	 * Tags can be provided as IDs or names. The system will normalize them.
	 * This action is queued and processed asynchronously.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param array       $tags        Tag IDs or names to add.
	 * @param string|null $location_id Optional location override; defaults to configured location.
	 * @return bool True if queued successfully, false otherwise.
	 */
	function ghl_crm_add_tags_to_user( int $user_id, array $tags, ?string $location_id = null ): bool {
		if ( empty( $tags ) ) {
			return false;
		}

		$tag_manager = TagManager::get_instance();
		$contact_id  = $tag_manager->get_user_contact_id( $user_id, $location_id );

		if ( ! $contact_id ) {
			return false;
		}

		// Normalize tags (converts names to IDs when possible)
		$normalized = $tag_manager->normalize_tag_input( $tags );
		$tag_names  = $tag_manager->prepare_tags_for_payload( $normalized['ids'], $normalized['pairs'] );

		if ( empty( $tag_names ) ) {
			return false;
		}

		// Queue the tag addition
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_id      = $queue_manager->add_to_queue(
			'user',
			$user_id,
			'add_tags',
			[
				'contact_id' => $contact_id,
				'tags'       => $tag_names,
			]
		);

		return false !== $queue_id;
	}
}

if ( ! function_exists( 'ghl_crm_remove_tags_from_user' ) ) {
	/**
	 * Remove tags from a user's contact in GHL (queues for async processing).
	 *
	 * Tags can be provided as IDs or names. The system will normalize them.
	 * This action is queued and processed asynchronously.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param array       $tags        Tag IDs or names to remove.
	 * @param string|null $location_id Optional location override; defaults to configured location.
	 * @return bool True if queued successfully, false otherwise.
	 */
	function ghl_crm_remove_tags_from_user( int $user_id, array $tags, ?string $location_id = null ): bool {
		if ( empty( $tags ) ) {
			return false;
		}

		$tag_manager = TagManager::get_instance();
		$contact_id  = $tag_manager->get_user_contact_id( $user_id, $location_id );

		if ( ! $contact_id ) {
			return false;
		}

		// Normalize tags (converts names to IDs when possible)
		$normalized = $tag_manager->normalize_tag_input( $tags );
		$tag_names  = $tag_manager->prepare_tags_for_payload( $normalized['ids'], $normalized['pairs'] );

		if ( empty( $tag_names ) ) {
			return false;
		}

		// Queue the tag removal
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_id      = $queue_manager->add_to_queue(
			'user',
			$user_id,
			'remove_tags',
			[
				'contact_id' => $contact_id,
				'tags'       => $tag_names,
			]
		);

		return false !== $queue_id;
	}
}