<?php
/**
 * LearnDash Group Sync Module
 *
 * Handles group membership events, tag tracking, and tag-based auto-enrollment.
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/LearnDash/Modules
 * @since      1.0.0
 */

declare(strict_types=1);

namespace GHL_CRM\Integrations\LearnDash\Modules;

use GHL_CRM\API\Resources\ContactResource;
use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\QueueManager;

defined( 'ABSPATH' ) || exit;

/**
 * Group Sync Module
 *
 * Responsibilities:
 * - Handle group join/leave events
 * - Track which tags each group applied to users
 * - Queue group-related sync jobs
 * - Process group payloads for GHL API sync
 * - Tag-based auto-enrollment for groups
 *
 * @since 1.0.0
 */
class GroupSync {
	/**
	 * Queue manager dependency.
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Contact API wrapper.
	 *
	 * @var ContactResource
	 */
	private ContactResource $contact_resource;

	/**
	 * Settings accessor.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings;

	/**
	 * Cache of auto-enroll group IDs keyed by tag slug.
	 *
	 * @var array<string,array<int>>
	 */
	private array $auto_enroll_cache = [];

	/**
	 * Constructor.
	 *
	 * @param QueueManager|null    $queue_manager    Optional queue dependency.
	 * @param ContactResource|null $contact_resource Optional API dependency.
	 * @param SettingsManager|null $settings         Optional settings dependency.
	 */
	public function __construct(
		?QueueManager $queue_manager = null,
		?ContactResource $contact_resource = null,
		?SettingsManager $settings = null
	) {
		$this->queue_manager    = $queue_manager ?? QueueManager::get_instance();
		$this->contact_resource = $contact_resource ?? new ContactResource();
		$this->settings         = $settings ?? SettingsManager::get_instance();
	}

	/**
	 * Handle user added to LearnDash group.
	 *
	 * Checks existing user tags and only applies tags the user doesn't already have.
	 * Stores which tags were actually applied in user meta for safe removal later.
	 *
	 * @since 1.0.0
	 * @param int $user_id  WordPress user ID.
	 * @param int $group_id LearnDash group ID.
	 * @return void
	 */
	public function handle_group_access_granted( int $user_id, int $group_id ): void {
		if ( $user_id <= 0 || $group_id <= 0 ) {
			return;
		}

		error_log( sprintf(
			'[GHL LearnDash Group] User %d joined group %d',
			$user_id,
			$group_id
		) );

		// Get configured group tags
		$configured_tags = $this->resolve_group_tags( $group_id, 'joined' );
		
		error_log( sprintf(
			'[GHL LearnDash Group] Group %d has %d configured tag(s): %s',
			$group_id,
			count( $configured_tags ),
			implode( ', ', $configured_tags )
		) );

		if ( empty( $configured_tags ) ) {
			error_log( '[GHL LearnDash Group] No tags configured for this group, skipping' );
			return;
		}

		// Get user's existing GHL tags
		$tag_manager   = \GHL_CRM\Core\TagManager::get_instance();
		$user_tag_ids  = $tag_manager->get_user_tag_ids( $user_id );
		$existing_tags = $tag_manager->convert_ids_to_names( $user_tag_ids );

		error_log( sprintf(
			'[GHL LearnDash Group] User %d has %d existing GHL tag(s): %s',
			$user_id,
			count( $existing_tags ),
			implode( ', ', $existing_tags )
		) );

		// Filter out tags the user already has
		$tags_to_apply = array_values( array_diff( $configured_tags, $existing_tags ) );

		error_log( sprintf(
			'[GHL LearnDash Group] After filtering, will apply %d NEW tag(s): %s',
			count( $tags_to_apply ),
			implode( ', ', $tags_to_apply )
		) );

		if ( empty( $tags_to_apply ) ) {
			error_log( '[GHL LearnDash Group] User already has all tags, nothing to apply' );
			return;
		}

		// Store which tags THIS GROUP is applying to the user
		$this->update_group_applied_tags( $user_id, $group_id, $tags_to_apply );

		error_log( sprintf(
			'[GHL LearnDash Group] Stored applied tags in user meta: _ghl_ld_group_%d_applied_tags',
			$group_id
		) );

		// Queue the tags that will actually be applied
		$this->queue_group_event( $user_id, $group_id, 'joined', $tags_to_apply );

		error_log( '[GHL LearnDash Group] Queued group join event for GHL sync' );
	}

	/**
	 * Handle user removed from LearnDash group.
	 *
	 * Only queues tag removal if the group has "remove on leave" enabled.
	 * Only removes tags that were actually applied BY THIS GROUP.
	 *
	 * @since 1.0.0
	 * @param int $user_id  WordPress user ID.
	 * @param int $group_id LearnDash group ID.
	 * @return void
	 */
	public function handle_group_access_removed( int $user_id, int $group_id ): void {
		if ( $user_id <= 0 || $group_id <= 0 ) {
			return;
		}

		// Check if this group has "remove on leave" enabled
		$remove_on_leave = get_post_meta( $group_id, '_ghl_ld_group_remove_on_leave', true );
		
		if ( '1' !== $remove_on_leave ) {
			// Group is configured to NOT remove tags on leave
			return;
		}

		// Get tags that were actually applied by this group
		$applied_tags = $this->get_group_applied_tags( $user_id, $group_id );

		if ( empty( $applied_tags ) ) {
			// This group didn't apply any tags to this user
			return;
		}

		// Queue removal of only the tags this group applied
		$this->queue_group_event( $user_id, $group_id, 'left', $applied_tags );
	}

	/**
	 * Register queue entry for group join/leave sync.
	 *
	 * @since 1.0.0
	 * @param int                    $user_id  WordPress user ID.
	 * @param int                    $group_id LearnDash group ID.
	 * @param string                 $action   Group action (joined|left).
	 * @param array<int,string>|null $tags     Optional. Tags to apply/remove (if already filtered).
	 * @return void
	 */
	public function queue_group_event( int $user_id, int $group_id, string $action, ?array $tags = null ): void {
		if ( $user_id <= 0 || $group_id <= 0 ) {
			return;
		}

		$valid_actions = [ 'joined', 'left' ];
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return;
		}

		// Use provided tags or resolve from group meta
		if ( null === $tags ) {
			$tags = $this->resolve_group_tags( $group_id, $action );
		}

		if ( empty( $tags ) ) {
			return;
		}

		$payload = [
			'user_id'   => $user_id,
			'group_id'  => $group_id,
			'action'    => $action,
			'tags'      => $tags,
			'queued_at' => current_time( 'mysql' ),
		];

		$this->queue_manager->add_to_queue( 'group', $group_id, 'sync_group_event', $payload );
	}

	/**
	 * Process group join/leave event and sync to GoHighLevel contact.
	 *
	 * Adds or removes GHL contact tags based on group membership.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Queue payload containing user_id, group_id, action, and tags.
	 * @return array|false API response array on success, false on failure.
	 */
	public function process_group_payload( array $payload ) {
		$user_id  = (int) ( $payload['user_id'] ?? 0 );
		$group_id = (int) ( $payload['group_id'] ?? 0 );
		$action   = sanitize_key( (string) ( $payload['action'] ?? '' ) );
		$new_tags = $this->normalize_tags( $payload['tags'] ?? [] );

		if ( $user_id <= 0 || $group_id <= 0 || empty( $new_tags ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return false;
		}

		try {
			// Find or create contact
			$existing   = $this->contact_resource->find_by_email( $user->user_email );
			$contact_id = ! empty( $existing['id'] ) ? (string) $existing['id'] : null;

			// If contact exists, re-fetch to get the absolute latest tags (prevents race conditions)
			if ( $contact_id ) {
				$fresh_contact = $this->contact_resource->find_by_email( $user->user_email );
				if ( $fresh_contact && ! empty( $fresh_contact['tags'] ) && is_array( $fresh_contact['tags'] ) ) {
					$existing_tags = $fresh_contact['tags'];
				} else {
					$existing_tags = [];
				}
			} else {
				$existing_tags = [];
			}

			// CRITICAL: Also check WordPress user meta for pending tags that may still be in queue
			$pending_tags = get_user_meta( $user_id, '_ghl_pending_tags', true );
			if ( ! empty( $pending_tags ) && is_array( $pending_tags ) ) {
				// Merge GHL tags + pending tags
				$existing_tags = array_values( array_unique( array_merge( $existing_tags, $pending_tags ) ) );
			}

			// Merge or remove tags based on action
			if ( 'joined' === $action ) {
				// Add group tags (only tags user didn't already have)
				$all_tags = array_values( array_unique( array_merge( $existing_tags, $new_tags ) ) );
			} else {
				// Remove ONLY the tags in payload (which are tags THIS GROUP applied)
				// This preserves tags from other sources
				$all_tags = array_values( array_diff( $existing_tags, $new_tags ) );

				// Clean up user meta - this group no longer has applied tags
				$this->delete_group_applied_tags( $user_id, $group_id );
			}

			// Update pending tags cache
			update_user_meta( $user_id, '_ghl_pending_tags', $all_tags );

			$contact_payload = [
				'email'     => $user->user_email,
				'firstName' => $user->first_name ?? '',
				'lastName'  => $user->last_name ?? '',
				'tags'      => $all_tags,
				'source'    => 'learndash_group_' . $action,
			];

			// Update or create contact
			if ( $contact_id ) {
				$api_result = $this->contact_resource->update( $contact_id, $contact_payload );
			} else {
				$api_result = $this->contact_resource->create( $contact_payload );
				$contact_id = (string) ( $api_result['contact']['id'] ?? $api_result['id'] ?? '' );
			}			// Ensure we have a contact ID for downstream hooks
			if ( empty( $contact_id ) ) {
				$refetched = $this->contact_resource->find_by_email( $user->user_email );
				if ( $refetched && ! empty( $refetched['id'] ) ) {
					$contact_id = (string) $refetched['id'];
					if ( empty( $api_result ) ) {
						$api_result = [ 'contact' => $refetched ];
					}
				}
			}

			// Normalize response structure
			$response = is_array( $api_result ) ? $api_result : [];

			if ( empty( $response['contact'] ) ) {
				$response['contact'] = [];
			}

			if ( ! empty( $contact_id ) ) {
				$response['contact']['id'] = $contact_id;
			}

			if ( empty( $response['contact']['tags'] ) ) {
				$response['contact']['tags'] = $all_tags;
			}

			if ( empty( $response['tags'] ) ) {
				$response['tags'] = $all_tags;
			}

			// After successful sync, update pending tags cache with actual GHL response
			if ( isset( $api_result['contact']['tags'] ) && is_array( $api_result['contact']['tags'] ) ) {
				update_user_meta( $user_id, '_ghl_pending_tags', $api_result['contact']['tags'] );
			}

			do_action( 'ghl_crm_learndash_group_synced', $payload, $contact_payload, $action );

			return $response;

		} catch ( \Throwable $error ) {
			do_action( 'ghl_crm_sync_error', 'learndash_group_sync', $payload, $error );
			return false;
		}
	}

	/**
	 * Retrieve tags configured for a specific group and action.
	 *
	 * Uses the same tag set for both join and leave actions - tags are applied on join
	 * and removed on leave to maintain group membership state in GHL.
	 *
	 * @since 1.0.0
	 * @param int    $group_id LearnDash group ID.
	 * @param string $action   Event type: joined|left.
	 * @return array<int,string> Sanitized tag array.
	 */
	public function resolve_group_tags( int $group_id, string $action ): array {
		if ( $group_id <= 0 ) {
			return [];
		}

		$valid_actions = [ 'joined', 'left' ];
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return [];
		}

		// Use same meta key for both actions - tags are applied on join, removed on leave
		$meta_key   = '_ghl_ld_group_tags';
		$group_tags = get_post_meta( $group_id, $meta_key, true );

		return $this->normalize_tags( $group_tags );
	}

	/**
	 * Handle tag updates for group auto-enrollment.
	 *
	 * @since 1.0.0
	 * @param int               $user_id   WordPress user ID.
	 * @param array<int,string> $tag_names Tag names that were saved.
	 * @return void
	 */
	public function handle_auto_enrollment( int $user_id, array $tag_names ): void {
		if ( $user_id <= 0 || empty( $tag_names ) ) {
			return;
		}

		$group_ids = $this->get_groups_for_tags( $tag_names );

		error_log( sprintf(
			'[GHL LearnDash Auto-Enroll] Found %d groups configured for auto-enrollment',
			count( $group_ids )
		) );

		foreach ( $group_ids as $group_id ) {
			$this->auto_enroll_user_in_group( $user_id, $group_id );
		}
	}

	/**
	 * Process batch group enrollment for existing users with matching tags.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_batch_group_enrollment(): void {
		$lock_key = 'ghl_ld_batch_group_lock';

		if ( get_transient( $lock_key ) ) {
			return;
		}

		set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

		try {
			$offset     = (int) $this->settings->get_setting( 'ghl_ld_batch_group_offset', 0 );
			$batch_size = apply_filters( 'ghl_crm_ld_batch_group_enrollment_size', 500 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$batch_size = max( 1, min( 1000, $batch_size ) );

			$users = get_users(
				[
					'meta_key' => '_ghl_contact_tags', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'number'   => $batch_size,
					'offset'   => $offset,
					'fields'   => 'ID',
					'orderby'  => 'ID',
					'order'    => 'ASC',
				]
			);

			$processed_count = count( $users );
			$tag_manager     = \GHL_CRM\Core\TagManager::get_instance();

			foreach ( $users as $user_id ) {
				$tag_ids   = $tag_manager->get_user_tag_ids( $user_id );
				$tag_names = $tag_manager->convert_ids_to_names( $tag_ids );

				if ( empty( $tag_names ) ) {
					continue;
				}

				$group_ids = $this->get_groups_for_tags( $tag_names );

				foreach ( $group_ids as $group_id ) {
					$this->auto_enroll_user_in_group( $user_id, $group_id );
				}
			}

			if ( $processed_count < $batch_size ) {
				$this->settings->delete_setting( 'ghl_ld_batch_group_offset' );
			} else {
				$new_offset = $offset + $batch_size;
				$this->settings->update_setting( 'ghl_ld_batch_group_offset', $new_offset );

				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time() + 10, 'ghl_ld_process_batch_group_enrollment', [], 'ghl-crm' );
				} else {
					wp_schedule_single_event( time() + 10, 'ghl_ld_process_batch_group_enrollment' );
				}
			}
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Retrieve all group IDs configured to auto-enroll for any provided tag.
	 *
	 * @since 1.0.0
	 * @param array<int,string> $tags Normalized tag strings.
	 * @return array<int> Unique group IDs.
	 */
	private function get_groups_for_tags( array $tags ): array {
		$group_ids = [];

		foreach ( $tags as $tag ) {
			foreach ( $this->get_groups_for_auto_enroll_tag( $tag ) as $group_id ) {
				$group_ids[ $group_id ] = true;
			}
		}

		return array_keys( $group_ids );
	}

	/**
	 * Retrieve groups where the auto-enroll tag matches the provided tag.
	 *
	 * @since 1.0.0
	 * @param string $tag Tag identifier (sanitized).
	 * @return array<int> Group IDs matching the auto-enroll tag.
	 */
	private function get_groups_for_auto_enroll_tag( string $tag ): array {
		$tag = sanitize_text_field( $tag );

		if ( '' === $tag ) {
			return [];
		}

		$cache_key = 'group_' . $tag;

		if ( isset( $this->auto_enroll_cache[ $cache_key ] ) ) {
			error_log( sprintf(
				'[GHL LearnDash Auto-Enroll] Using cached groups for tag "%s": %d groups',
				$tag,
				count( $this->auto_enroll_cache[ $cache_key ] )
			) );
			return $this->auto_enroll_cache[ $cache_key ];
		}

		$args = [
			'post_type'      => 'groups',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => '_ghl_ld_group_auto_enroll_tags',
					'value'   => '"' . $tag . '"',
					'compare' => 'LIKE',
				],
			],
		];

		$group_ids                               = array_map( 'intval', get_posts( $args ) );
		$this->auto_enroll_cache[ $cache_key ] = $group_ids;

		error_log( sprintf(
			'[GHL LearnDash Auto-Enroll] Found %d groups configured for tag "%s": %s',
			count( $group_ids ),
			$tag,
			implode( ', ', $group_ids )
		) );

		return $group_ids;
	}

	/**
	 * Enroll user in group if not already enrolled.
	 *
	 * @since 1.0.0
	 * @param int $user_id  WordPress user ID.
	 * @param int $group_id LearnDash group ID.
	 * @return void
	 */
	private function auto_enroll_user_in_group( int $user_id, int $group_id ): void {
		if ( $user_id <= 0 || $group_id <= 0 || ! function_exists( 'ld_update_group_access' ) ) {
			error_log( sprintf(
				'[GHL LearnDash Auto-Enroll] Cannot enroll user %d in group %d - invalid IDs or missing function',
				$user_id,
				$group_id
			) );
			return;
		}

		// Check if user already has access to this group
		if ( function_exists( 'learndash_is_user_in_group' ) && learndash_is_user_in_group( $user_id, $group_id ) ) {
			error_log( sprintf(
				'[GHL LearnDash Auto-Enroll] User %d already enrolled in group %d, skipping',
				$user_id,
				$group_id
			) );
			return;
		}

		error_log( sprintf(
			'[GHL LearnDash Auto-Enroll] Enrolling user %d in group %d via ld_update_group_access()',
			$user_id,
			$group_id
		) );

		ld_update_group_access( $user_id, $group_id );

		error_log( sprintf(
			'[GHL LearnDash Auto-Enroll] Successfully enrolled user %d in group %d',
			$user_id,
			$group_id
		) );

		/**
		 * Fires after a user is auto-enrolled in a LearnDash group via GHL tag.
		 *
		 * @since 1.0.0
		 * @param int $user_id  WordPress user ID.
		 * @param int $group_id Group post ID.
		 */
		do_action( 'ghl_crm_learndash_group_auto_enrolled', $user_id, $group_id );
	}

	/**
	 * Get tags that were actually applied to a user by a specific group.
	 *
	 * @since 1.0.0
	 * @param int $user_id  WordPress user ID.
	 * @param int $group_id LearnDash group ID.
	 * @return array<int,string> Tags applied by this group.
	 */
	private function get_group_applied_tags( int $user_id, int $group_id ): array {
		$meta_key = sprintf( '_ghl_ld_group_%d_applied_tags', $group_id );
		$tags     = get_user_meta( $user_id, $meta_key, true );

		return is_array( $tags ) ? array_values( $tags ) : [];
	}

	/**
	 * Store which tags were actually applied to a user by a specific group.
	 *
	 * @since 1.0.0
	 * @param int               $user_id  WordPress user ID.
	 * @param int               $group_id LearnDash group ID.
	 * @param array<int,string> $tags     Tags that were applied.
	 * @return void
	 */
	private function update_group_applied_tags( int $user_id, int $group_id, array $tags ): void {
		if ( empty( $tags ) ) {
			$this->delete_group_applied_tags( $user_id, $group_id );
			return;
		}

		$meta_key = sprintf( '_ghl_ld_group_%d_applied_tags', $group_id );
		update_user_meta( $user_id, $meta_key, array_values( $tags ) );
	}

	/**
	 * Delete stored applied tags for a user/group combination.
	 *
	 * @since 1.0.0
	 * @param int $user_id  WordPress user ID.
	 * @param int $group_id LearnDash group ID.
	 * @return void
	 */
	private function delete_group_applied_tags( int $user_id, int $group_id ): void {
		$meta_key = sprintf( '_ghl_ld_group_%d_applied_tags', $group_id );
		delete_user_meta( $user_id, $meta_key );
	}

	/**
	 * Normalize and sanitize tag input.
	 *
	 * @since 1.0.0
	 * @param mixed $tags Raw tag data (string, array, or other).
	 * @return array<int,string> Sanitized unique tags.
	 */
	private function normalize_tags( $tags ): array {
		if ( empty( $tags ) ) {
			return [];
		}

		if ( ! is_array( $tags ) ) {
			$tags = [ $tags ];
		}

		$clean = array_filter(
			array_map(
				static function ( $tag ) {
					$tag = sanitize_text_field( (string) $tag );
					return '' === $tag ? null : $tag;
				},
				$tags
			),
			static function ( $value ) {
				return null !== $value;
			}
		);

		return array_values( array_unique( $clean ) );
	}
}
