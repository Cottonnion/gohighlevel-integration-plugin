<?php
/**
 * LearnDash Integration Bridge
 *
 * Enterprise-grade integration that syncs LearnDash course events (enrollment, completion, revocation)
 * with GoHighLevel contacts via queued background jobs. Supports per-course tag configuration and
 * tag-based auto-enrollment with batch processing for existing users.
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/LearnDash
 * @since      1.0.0
 */

declare(strict_types=1);

namespace GHL_CRM\Integrations\LearnDash;

use GHL_CRM\Admin\Profile\UserProfileFields;
use GHL_CRM\API\Resources\ContactResource;
use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\QueueManager;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * LearnDash Sync Manager
 *
 * Responsibilities:
 * - Hook into LearnDash enrollment/completion/revocation events
 * - Queue contact sync jobs with course-specific tags
 * - Handle tag-based auto-enrollment with batch processing
 * - Refresh WordPress user data after successful GHL sync
 *
 * @since 1.0.0
 */
class LearnDashSync {
	/**
	 * Settings accessor.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings;

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
	 * Track whether hooks already registered.
	 *
	 * @var bool
	 */
	private bool $bootstrapped = false;

	/**
	 * Cache of auto-enroll course IDs keyed by tag slug.
	 *
	 * @var array<string,array<int>>
	 */
	private array $auto_enroll_cache = [];

	/**
	 * Build the service with optional dependency overrides for easier testing.
	 *
	 * @param SettingsManager|null $settings         Optional settings dependency.
	 * @param QueueManager|null    $queue_manager    Optional queue dependency.
	 * @param ContactResource|null $contact_resource Optional API dependency.
	 */
	public function __construct(
		?SettingsManager $settings = null,
		?QueueManager $queue_manager = null,
		?ContactResource $contact_resource = null
	) {
		$this->settings         = $settings ?? SettingsManager::get_instance();
		$this->queue_manager    = $queue_manager ?? QueueManager::get_instance();
		$this->contact_resource = $contact_resource ?? new ContactResource();
	}

	/**
	 * Register boot hook.
	 */
	public function init(): void {
		add_action( 'plugins_loaded', [ $this, 'boot' ], 20 );
	}

	/**
	 * Wire LearnDash events once requirements are met.
	 *
	 * Prevents duplicate hook registration and validates LearnDash availability.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		if ( $this->bootstrapped ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->bootstrapped = true;

		// Core LearnDash event hooks
		add_action( 'ld_added_course_access', [ $this, 'handle_course_enrollment' ], 10, 2 );
		add_action( 'ld_removed_course_access', [ $this, 'handle_course_revoked' ], 10, 2 );
		add_action( 'learndash_course_completed', [ $this, 'handle_course_completed' ], 10, 1 );

		// Auto-enrollment system hooks
		add_action( 'init', [ $this, 'maybe_check_existing_users' ], 999 );
		add_action( 'ghl_crm_user_tags_updated', [ $this, 'handle_user_tags_updated' ], 10, 2 );
		add_action( 'ghl_ld_process_batch_enrollment', [ $this, 'process_batch_enrollment' ] );

		// Queue processing hooks
		add_filter( 'ghl_crm_execute_course_sync', [ $this, 'execute_learndash_sync' ], 10, 4 );
		add_action( 'ghl_crm_after_sync_success', [ $this, 'handle_after_queue_sync' ], 20, 4 );
	}

	/**
	 * Check if LearnDash integration is enabled and available.
	 *
	 * @since 1.0.0
	 * @return bool True if LearnDash is active and integration enabled.
	 */
	private function is_enabled(): bool {
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			return false;
		}

		$settings = $this->settings->get_settings_array();
		return ! empty( $settings['learndash_enabled'] );
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
	 * Extracts user and course IDs from LearnDash's completion data structure.
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
	private function queue_course_event( int $user_id, int $course_id, string $status ): void {
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
	 * Execute queued LearnDash course sync operation.
	 *
	 * Filter callback for queue processor to handle 'sync_course_event' actions.
	 *
	 * @since 1.0.0
	 * @param bool|mixed   $handled   Whether another handler already processed the job.
	 * @param string       $action    Queue action name.
	 * @param int          $item_id   Queue item identifier.
	 * @param array<mixed> $payload   Stored payload.
	 * @return bool|array False on failure, API response array on success.
	 */
	public function execute_learndash_sync( $handled, string $action, int $item_id, array $payload ) {
		if ( 'sync_course_event' !== $action ) {
			return $handled;
		}

		try {
			return $this->process_course_payload( $payload );
		} catch ( \Throwable $error ) {
			do_action( 'ghl_crm_sync_error', 'learndash_queue_handler', $payload, $error );
			return false;
		}
	}

	/**
	 * Refresh WordPress user data after successful GHL contact sync.
	 *
	 * Pulls latest contact data from GoHighLevel and updates WordPress user meta.
	 *
	 * @since 1.0.0
	 * @param object       $item       Queue item object.
	 * @param string|null  $contact_id GHL contact identifier.
	 * @param mixed        $result     Raw handler result.
	 * @param array<mixed> $payload    Original payload passed to the queue.
	 * @return void
	 */
	public function handle_after_queue_sync( object $item, ?string $contact_id, $result, array $payload ): void {
		// Only process course sync items
		if ( ! isset( $item->item_type ) || 'course' !== $item->item_type ) {
			return;
		}

		if ( empty( $contact_id ) ) {
			return;
		}

		// Extract user ID from payload or item
		$user_id = (int) ( $payload['user_id'] ?? 0 );

		if ( $user_id <= 0 && ! empty( $item->payload ) ) {
			$decoded_payload = json_decode( $item->payload, true );
			if ( is_array( $decoded_payload ) ) {
				$user_id = (int) ( $decoded_payload['user_id'] ?? 0 );
			}
		}

		if ( $user_id <= 0 ) {
			return;
		}

		// Refresh user profile from GHL
		try {
			$profile_fields = UserProfileFields::get_instance();
			if ( method_exists( $profile_fields, 'refresh_user_from_ghl' ) ) {
				$profile_fields->refresh_user_from_ghl( $user_id, $contact_id );
			}
		} catch ( \Throwable $error ) {
			do_action(
				'ghl_crm_sync_error',
				'learndash_after_sync_refresh',
				[
					'user_id'    => $user_id,
					'contact_id' => $contact_id,
				],
				$error
			);
		}

		do_action( 'ghl_crm_learndash_after_sync_back', $user_id, $contact_id, $item, $payload, $result );
	}

	/**
	 * Process course event and sync to GoHighLevel contact.
	 *
	 * Creates or updates GHL contact with course-specific tags while preserving existing tags.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Queue payload containing user_id, course_id, status, and tags.
	 * @return array|false API response array on success, false on failure.
	 */
	private function process_course_payload( array $payload ) {
		$user_id   = (int) ( $payload['user_id'] ?? 0 );
		$course_id = (int) ( $payload['course_id'] ?? 0 );
		$status    = sanitize_key( (string) ( $payload['status'] ?? '' ) );
		$new_tags  = $this->normalize_tags( $payload['tags'] ?? [] );

		if ( $user_id <= 0 || $course_id <= 0 ) {
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

			// Merge course tags with existing tags (additive, not replacement)
			$existing_tags = [];
			if ( $existing && ! empty( $existing['tags'] ) && is_array( $existing['tags'] ) ) {
				$existing_tags = $existing['tags'];
			}

			$all_tags = array_values( array_unique( array_merge( $existing_tags, $new_tags ) ) );

			$contact_payload = [
				'email'     => $user->user_email,
				'firstName' => $user->first_name ?? '',
				'lastName'  => $user->last_name ?? '',
				'tags'      => $all_tags,
				'source'    => 'learndash_' . $status,
			];

			// Update or create contact
			if ( $contact_id ) {
				$api_result = $this->contact_resource->update( $contact_id, $contact_payload );
			} else {
				$api_result = $this->contact_resource->create( $contact_payload );
				$contact_id = (string) ( $api_result['contact']['id'] ?? $api_result['id'] ?? '' );
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
			do_action( 'ghl_crm_sync_error', 'learndash', $payload, $error );
			return false;
		}
	}

	/**
	 * Retrieve tags configured for a specific course and event type.
	 *
	 * Reads from course post meta (_ghl_ld_{status}_tags).
	 *
	 * @since 1.0.0
	 * @param int    $course_id LearnDash course ID.
	 * @param string $status    Event type: enrolled|completed|revoked.
	 * @return array<int,string> Sanitized tag array.
	 */
	private function resolve_course_tags( int $course_id, string $status ): array {
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
	 * Normalize and sanitize tag input.
	 *
	 * Ensures tags are returned as a clean array of unique, trimmed strings.
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

	/**
	 * Handle tag updates for auto-enrollment triggers.
	 *
	 * When a user's GHL tags are updated, check if any courses should auto-enroll them.
	 *
	 * @since 1.0.0
	 * @param int               $user_id WordPress user ID.
	 * @param array<int,string> $tag_ids Tag IDs that were saved.
	 * @return void
	 */
	public function handle_user_tags_updated( int $user_id, array $tag_ids ): void {
		if ( $user_id <= 0 || empty( $tag_ids ) ) {
			return;
		}

		$tag_manager = \GHL_CRM\Core\TagManager::get_instance();
		$tag_names   = $tag_manager->convert_ids_to_names( $tag_ids );

		if ( empty( $tag_names ) ) {
			return;
		}

		$course_ids = $this->get_courses_for_tags( $tag_names );

		foreach ( $course_ids as $course_id ) {
			$this->auto_enroll_user_in_course( $user_id, $course_id );
		}
	}

	/**
	 * Trigger daily batch enrollment check for existing users.
	 *
	 * Prevents redundant processing with daily transient lock.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_check_existing_users(): void {
		$transient_key = 'ghl_ld_checked_existing_users';

		if ( get_transient( $transient_key ) ) {
			return;
		}

		delete_option( 'ghl_ld_batch_offset' );
		$this->schedule_batch_enrollment_check();
		set_transient( $transient_key, 1, DAY_IN_SECONDS );
	}

	/**
	 * Schedule batch enrollment check via Action Scheduler or WP-Cron.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function schedule_batch_enrollment_check(): void {
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$next_scheduled = as_next_scheduled_action( 'ghl_ld_process_batch_enrollment' );

			if ( false === $next_scheduled ) {
				as_schedule_single_action( time(), 'ghl_ld_process_batch_enrollment', [], 'ghl-crm' );
			}
		} elseif ( ! wp_next_scheduled( 'ghl_ld_process_batch_enrollment' ) ) {
			wp_schedule_single_event( time(), 'ghl_ld_process_batch_enrollment' );
		}
	}

	/**
	 * Process batch of users for auto-enrollment.
	 *
	 * Processes users with GHL tags in batches (up to 500), tracks offset, reschedules until complete.
	 * Uses transient lock to prevent concurrent batch processing.
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
			$offset     = (int) get_option( 'ghl_ld_batch_offset', 0 );
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
				delete_option( 'ghl_ld_batch_offset' );
			} else {
				$new_offset = $offset + $batch_size;
				update_option( 'ghl_ld_batch_offset', $new_offset );

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
	 * Checks existing access and uses LearnDash's enrollment function.
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
}
