<?php
/**
 * LearnDash Course Sync Module
 *
 * Handles course enrollment, completion, revocation events and tag-based auto-enrollment.
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
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Course Sync Module
 *
 * Responsibilities:
 * - Handle course enrollment/completion/revocation events
 * - Queue course-related sync jobs
 * - Process course payloads for GHL API sync
 * - Tag-based auto-enrollment for courses
 *
 * @since 1.0.0
 */
class CourseSync {
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
	 * Cache of auto-enroll course IDs keyed by tag slug.
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
	 * Handle new enrollment events.
	 *
	 * @since 1.0.0
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return void
	 */
	public function handle_course_enrollment( int $user_id, int $course_id ): void {
		if ( $user_id <= 0 || $course_id <= 0 ) {
			return;
		}

		$this->queue_course_event( $user_id, $course_id, 'enrolled' );
	}

	/**
	 * Handle revoked access events.
	 *
	 * @since 1.0.0
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return void
	 */
	public function handle_course_revoked( int $user_id, int $course_id ): void {
		if ( $user_id <= 0 || $course_id <= 0 ) {
			return;
		}

		$this->queue_course_event( $user_id, $course_id, 'revoked' );
	}

	/**
	 * Handle completion payloads from LearnDash.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $data LearnDash completion payload.
	 * @return void
	 */
	public function handle_course_completed( array $data ): void {
		$user_id   = 0;
		$course_id = 0;

		// Extract user ID from WP_User object or direct ID
		if ( isset( $data['user'] ) && $data['user'] instanceof WP_User ) {
			$user_id = (int) $data['user']->ID;
		} elseif ( isset( $data['user_id'] ) ) {
			$user_id = (int) $data['user_id'];
		}

		// Extract course ID from post object or direct ID
		if ( isset( $data['course'] ) && is_object( $data['course'] ) && isset( $data['course']->ID ) ) {
			$course_id = (int) $data['course']->ID;
		} elseif ( isset( $data['course_id'] ) ) {
			$course_id = (int) $data['course_id'];
		}

		if ( $user_id <= 0 || $course_id <= 0 ) {
			return;
		}

		$this->queue_course_event( $user_id, $course_id, 'completed' );
	}

	/**
	 * Register queue entry for downstream sync.
	 *
	 * @since 1.0.0
	 * @param int    $user_id   WordPress user ID.
	 * @param int    $course_id LearnDash course ID.
	 * @param string $status    Course state (enrolled|revoked|completed).
	 * @return void
	 */
	public function queue_course_event( int $user_id, int $course_id, string $status ): void {
		if ( $user_id <= 0 || $course_id <= 0 ) {
			return;
		}

		$valid_statuses = [ 'enrolled', 'revoked', 'completed' ];
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return;
		}

		$payload = [
			'user_id'   => $user_id,
			'course_id' => $course_id,
			'status'    => $status,
			'tags'      => $this->resolve_course_tags( $course_id, $status ),
			'queued_at' => current_time( 'mysql' ),
		];

		$this->queue_manager->add_to_queue( 'course', $course_id, 'sync_course_event', $payload );
	}

	/**
	 * Process course event and sync to GoHighLevel contact.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Queue payload containing user_id, course_id, status, and tags.
	 * @return array|false API response array on success, false on failure.
	 */
	public function process_course_payload( array $payload ) {
		$user_id   = (int) ( $payload['user_id'] ?? 0 );
		$course_id = (int) ( $payload['course_id'] ?? 0 );
		$status    = sanitize_key( (string) ( $payload['status'] ?? '' ) );
		$new_tags  = $this->normalize_tags( $payload['tags'] ?? [] );

		error_log( sprintf(
			'[GHL Course Sync] Processing course %s for user %d, course %d, new tags: %s',
			$status,
			$user_id,
			$course_id,
			implode( ', ', $new_tags )
		) );

		if ( $user_id <= 0 || $course_id <= 0 ) {
			error_log( '[GHL Course Sync] Invalid user_id or course_id, aborting' );
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			error_log( '[GHL Course Sync] User not found or no email, aborting' );
			return false;
		}

		error_log( sprintf(
			'[GHL Course Sync] User email: %s',
			$user->user_email
		) );

		try {
			// Find or create contact
			$existing   = $this->contact_resource->find_by_email( $user->user_email );
			$contact_id = ! empty( $existing['id'] ) ? (string) $existing['id'] : null;

			error_log( sprintf(
				'[GHL Course Sync] Initial contact fetch - ID: %s, existing tags: %s',
				$contact_id ?? 'NEW',
				isset( $existing['tags'] ) ? implode( ', ', $existing['tags'] ) : 'none'
			) );

			// If contact exists, re-fetch to get the absolute latest tags (prevents race conditions)
			if ( $contact_id ) {
				error_log( '[GHL Course Sync] Re-fetching contact to get latest tags (race condition prevention)' );
				$fresh_contact = $this->contact_resource->find_by_email( $user->user_email );
				if ( $fresh_contact && ! empty( $fresh_contact['tags'] ) && is_array( $fresh_contact['tags'] ) ) {
					$existing_tags = $fresh_contact['tags'];
				} else {
					$existing_tags = [];
				}
				error_log( sprintf(
					'[GHL Content Sync] Fresh tags from GHL: %s',
					implode( ', ', $existing_tags )
				) );
			} else {
				$existing_tags = [];
				error_log( '[GHL Course Sync] New contact, no existing tags' );
			}

			// CRITICAL: Also check WordPress user meta for pending tags that may still be in queue
			$pending_tags = get_user_meta( $user_id, '_ghl_pending_tags', true );
			if ( ! empty( $pending_tags ) && is_array( $pending_tags ) ) {
				error_log( sprintf(
					'[GHL Course Sync] Found %d pending tags in user meta: %s',
					count( $pending_tags ),
					implode( ', ', $pending_tags )
				) );
				// Merge GHL tags + pending tags + new tags
				$all_tags = array_values( array_unique( array_merge( $existing_tags, $pending_tags, $new_tags ) ) );
			} else {
				error_log( '[GHL Course Sync] No pending tags in user meta' );
				$all_tags = array_values( array_unique( array_merge( $existing_tags, $new_tags ) ) );
			}

			// Update pending tags cache to include what we're about to send
			update_user_meta( $user_id, '_ghl_pending_tags', $all_tags );

			error_log( sprintf(
				'[GHL Course Sync] Final merged tags (%d total): %s',
				count( $all_tags ),
				implode( ', ', $all_tags )
			) );

			$contact_payload = [
				'email'     => $user->user_email,
				'firstName' => $user->first_name ?? '',
				'lastName'  => $user->last_name ?? '',
				'tags'      => $all_tags,
				'source'    => 'learndash_' . $status,
			];

			// Update or create contact
			if ( $contact_id ) {
				error_log( sprintf(
					'[GHL Course Sync] Updating existing contact %s with %d tags',
					$contact_id,
					count( $all_tags )
				) );
				$api_result = $this->contact_resource->update( $contact_id, $contact_payload );
			} else {
				error_log( '[GHL Course Sync] Creating new contact' );
				$api_result = $this->contact_resource->create( $contact_payload );
				$contact_id = (string) ( $api_result['contact']['id'] ?? $api_result['id'] ?? '' );
			}

			error_log( sprintf(
				'[GHL Course Sync] API call completed, contact ID: %s',
				$contact_id ?? 'unknown'
			) );

			// After successful sync, update pending tags cache with actual GHL response
			if ( isset( $api_result['contact']['tags'] ) && is_array( $api_result['contact']['tags'] ) ) {
				update_user_meta( $user_id, '_ghl_pending_tags', $api_result['contact']['tags'] );
				error_log( sprintf(
					'[GHL Course Sync] Updated pending tags cache with %d tags from GHL response',
					count( $api_result['contact']['tags'] )
				) );
			}

			// Ensure we have a contact ID for downstream hooks
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

			do_action( 'ghl_crm_learndash_event_synced', $payload, $contact_payload );

			return $response;

		} catch ( \Throwable $error ) {
			do_action( 'ghl_crm_sync_error', 'learndash_course_sync', $payload, $error );
			return false;
		}
	}

	/**
	 * Retrieve tags configured for a specific course and status.
	 *
	 * @since 1.0.0
	 * @param int    $course_id LearnDash course ID.
	 * @param string $status    Event type: enrolled|completed|revoked.
	 * @return array<int,string> Sanitized tag array.
	 */
	public function resolve_course_tags( int $course_id, string $status ): array {
		if ( $course_id <= 0 ) {
			return [];
		}

		$valid_statuses = [ 'enrolled', 'completed', 'revoked' ];
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return [];
		}

		$meta_key    = sprintf( '_ghl_ld_%s_tags', $status );
		$course_tags = get_post_meta( $course_id, $meta_key, true );

		return $this->normalize_tags( $course_tags );
	}

	/**
	 * Handle tag updates for course auto-enrollment.
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

		$course_ids = $this->get_courses_for_tags( $tag_names );

		error_log( sprintf(
			'[GHL LearnDash Auto-Enroll] Found %d courses configured for auto-enrollment',
			count( $course_ids )
		) );

		foreach ( $course_ids as $course_id ) {
			$this->auto_enroll_user_in_course( $user_id, $course_id );
		}
	}

	/**
	 * Process batch of users for course auto-enrollment.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_batch_enrollment(): void {
		$lock_key = 'ghl_ld_batch_processing';

		if ( get_transient( $lock_key ) ) {
			return;
		}

		set_transient( $lock_key, time(), 5 * MINUTE_IN_SECONDS );

		try {
			$offset     = (int) $this->settings->get_setting( 'ghl_ld_batch_offset', 0 );
			$batch_size = apply_filters( 'ghl_crm_ld_batch_enrollment_size', 500 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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

				$course_ids = $this->get_courses_for_tags( $tag_names );

				foreach ( $course_ids as $course_id ) {
					$this->auto_enroll_user_in_course( $user_id, $course_id );
				}
			}

			if ( $processed_count < $batch_size ) {
				$this->settings->delete_setting( 'ghl_ld_batch_offset' );
			} else {
				$new_offset = $offset + $batch_size;
				$this->settings->update_setting( 'ghl_ld_batch_offset', $new_offset );

				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time() + 10, 'ghl_ld_process_batch_enrollment', [], 'ghl-crm' );
				} else {
					wp_schedule_single_event( time() + 10, 'ghl_ld_process_batch_enrollment' );
				}
			}
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Retrieve all course IDs configured to auto-enroll for any provided tag.
	 *
	 * @since 1.0.0
	 * @param array<int,string> $tags Normalized tag strings.
	 * @return array<int> Unique course IDs.
	 */
	private function get_courses_for_tags( array $tags ): array {
		$course_ids = [];

		foreach ( $tags as $tag ) {
			foreach ( $this->get_courses_for_auto_enroll_tag( $tag ) as $course_id ) {
				$course_ids[ $course_id ] = true;
			}
		}

		return array_keys( $course_ids );
	}

	/**
	 * Retrieve courses where the auto-enroll tag matches the provided tag.
	 *
	 * @since 1.0.0
	 * @param string $tag Tag identifier (sanitized).
	 * @return array<int> Course IDs matching the auto-enroll tag.
	 */
	private function get_courses_for_auto_enroll_tag( string $tag ): array {
		$tag = sanitize_text_field( $tag );

		if ( '' === $tag ) {
			return [];
		}

		if ( isset( $this->auto_enroll_cache[ $tag ] ) ) {
			return $this->auto_enroll_cache[ $tag ];
		}

		$args = [
			'post_type'      => 'sfwd-courses',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => '_ghl_ld_auto_enroll_tag',
					'value'   => '"' . $tag . '"',
					'compare' => 'LIKE',
				],
			],
		];

		$course_ids                      = array_map( 'intval', get_posts( $args ) );
		$this->auto_enroll_cache[ $tag ] = $course_ids;

		return $course_ids;
	}

	/**
	 * Enroll user in course if not already enrolled.
	 *
	 * @since 1.0.0
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnDash course ID.
	 * @return void
	 */
	private function auto_enroll_user_in_course( int $user_id, int $course_id ): void {
		if ( $user_id <= 0 || $course_id <= 0 || ! function_exists( 'ld_update_course_access' ) ) {
			return;
		}

		if ( function_exists( 'sfwd_lms_has_access' ) && sfwd_lms_has_access( $course_id, $user_id ) ) {
			return;
		}

		ld_update_course_access( $user_id, $course_id );

		/**
		 * Fires after a user is auto-enrolled in a LearnDash course via GHL tag.
		 *
		 * @since 1.0.0
		 * @param int $user_id   WordPress user ID.
		 * @param int $course_id LearnDash course ID.
		 */
		do_action( 'ghl_crm_learndash_auto_enrolled', $user_id, $course_id );
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
