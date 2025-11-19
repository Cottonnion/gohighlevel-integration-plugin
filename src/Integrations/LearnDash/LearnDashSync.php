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

		// Lesson, Topic, and Quiz completion hooks
		add_action( 'learndash_lesson_completed', [ $this, 'handle_lesson_completed' ], 10, 1 );
		add_action( 'learndash_topic_completed', [ $this, 'handle_topic_completed' ], 10, 1 );
		add_action( 'learndash_quiz_completed', [ $this, 'handle_quiz_completed' ], 10, 2 );

		// Auto-enrollment system hooks
		add_action( 'init', [ $this, 'maybe_check_existing_users' ], 999 );
		add_action( 'ghl_crm_user_tags_updated', [ $this, 'handle_user_tags_updated' ], 10, 2 );
		add_action( 'ghl_ld_process_batch_enrollment', [ $this, 'process_batch_enrollment' ] );

		// Queue processing hooks
		add_filter( 'ghl_crm_execute_course_sync', [ $this, 'execute_learndash_sync' ], 10, 4 );
		add_filter( 'ghl_crm_execute_sync', [ $this, 'execute_learndash_content_sync' ], 10, 5 );
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
	 * Handle lesson completion events.
	 *
	 * Extracts user and lesson IDs from LearnDash's completion data structure.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $data LearnDash lesson completion payload.
	 * @return void
	 */
	public function handle_lesson_completed( array $data ): void {
		$user_id   = 0;
		$lesson_id = 0;

		// Extract user ID from WP_User object or direct ID
		if ( isset( $data['user'] ) && $data['user'] instanceof WP_User ) {
			$user_id = (int) $data['user']->ID;
		} elseif ( isset( $data['user_id'] ) ) {
			$user_id = (int) $data['user_id'];
		}

		// Extract lesson ID from post object or direct ID
		if ( isset( $data['lesson'] ) && is_object( $data['lesson'] ) && isset( $data['lesson']->ID ) ) {
			$lesson_id = (int) $data['lesson']->ID;
		} elseif ( isset( $data['lesson_id'] ) ) {
			$lesson_id = (int) $data['lesson_id'];
		}

		if ( $user_id <= 0 || $lesson_id <= 0 ) {
			return;
		}

		$this->queue_content_event( $user_id, $lesson_id, 'lesson' );
	}

	/**
	 * Handle topic completion events.
	 *
	 * Extracts user and topic IDs from LearnDash's completion data structure.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $data LearnDash topic completion payload.
	 * @return void
	 */
	public function handle_topic_completed( array $data ): void {
		$user_id  = 0;
		$topic_id = 0;

		// Extract user ID from WP_User object or direct ID
		if ( isset( $data['user'] ) && $data['user'] instanceof WP_User ) {
			$user_id = (int) $data['user']->ID;
		} elseif ( isset( $data['user_id'] ) ) {
			$user_id = (int) $data['user_id'];
		}

		// Extract topic ID from post object or direct ID
		if ( isset( $data['topic'] ) && is_object( $data['topic'] ) && isset( $data['topic']->ID ) ) {
			$topic_id = (int) $data['topic']->ID;
		} elseif ( isset( $data['topic_id'] ) ) {
			$topic_id = (int) $data['topic_id'];
		}

		if ( $user_id <= 0 || $topic_id <= 0 ) {
			return;
		}

		$this->queue_content_event( $user_id, $topic_id, 'topic' );
	}

	/**
	 * Handle quiz completion events.
	 *
	 * Extracts user and quiz IDs from LearnDash's quiz completion data structure
	 * and captures the quiz score for threshold-based tagging.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $data    LearnDash quiz completion payload.
	 * @param WP_User|null        $user    WordPress user object.
	 * @return void
	 */
	public function handle_quiz_completed( array $data, $user = null ): void {
		$user_id = 0;
		$quiz_id = 0;
		$score   = null;

		// Extract user ID - quiz hook passes user as second parameter
		if ( $user instanceof WP_User ) {
			$user_id = (int) $user->ID;
		} elseif ( isset( $data['user'] ) && $data['user'] instanceof WP_User ) {
			$user_id = (int) $data['user']->ID;
		} elseif ( isset( $data['user_id'] ) ) {
			$user_id = (int) $data['user_id'];
		}

		// Extract quiz ID from post object or direct ID
		if ( isset( $data['quiz'] ) && is_object( $data['quiz'] ) && isset( $data['quiz']->ID ) ) {
			$quiz_id = (int) $data['quiz']->ID;
		} elseif ( isset( $data['quiz'] ) && is_numeric( $data['quiz'] ) ) {
			$quiz_id = (int) $data['quiz'];
		} elseif ( isset( $data['quiz_id'] ) ) {
			$quiz_id = (int) $data['quiz_id'];
		}

		// Extract quiz score percentage (0-100)
		if ( isset( $data['percentage'] ) ) {
			$score = (float) $data['percentage'];
		} elseif ( isset( $data['score'] ) ) {
			$score = (float) $data['score'];
		}

		if ( $user_id <= 0 || $quiz_id <= 0 ) {
			return;
		}

		$this->queue_content_event( $user_id, $quiz_id, 'quiz', $score );
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
	 * Register queue entry for lesson, topic, or quiz completion sync.
	 *
	 * @since 1.0.0
	 * @param int        $user_id    WordPress user ID.
	 * @param int        $content_id LearnDash lesson/topic/quiz ID.
	 * @param string     $type       Content type (lesson|topic|quiz).
	 * @param float|null $score      Quiz score percentage (0-100), null for non-quiz content.
	 * @return void
	 */
	private function queue_content_event( int $user_id, int $content_id, string $type, ?float $score = null ): void {
		if ( $user_id <= 0 || $content_id <= 0 ) {
			return;
		}

		$valid_types = [ 'lesson', 'topic', 'quiz' ];
		if ( ! in_array( $type, $valid_types, true ) ) {
			return;
		}

		$tags = $this->resolve_content_tags( $content_id, $type, $score );

		if ( empty( $tags ) ) {
			return;
		}

		$payload = [
			'user_id'    => $user_id,
			'content_id' => $content_id,
			'type'       => $type,
			'tags'       => $tags,
			'queued_at'  => current_time( 'mysql' ),
		];

		// Include score in payload for logging/analytics
		if ( 'quiz' === $type && null !== $score ) {
			$payload['quiz_score'] = $score;
		}

		$this->queue_manager->add_to_queue( $type, $content_id, 'sync_content_event', $payload );
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
	 * Execute queued LearnDash content sync operation (lessons, topics, quizzes).
	 *
	 * Filter callback for generic queue processor to handle lesson/topic/quiz completion.
	 *
	 * @since 1.0.0
	 * @param bool|mixed   $handled   Whether another handler already processed the job.
	 * @param string       $item_type Queue item type (lesson|topic|quiz).
	 * @param string       $action    Queue action name.
	 * @param int          $item_id   Queue item identifier.
	 * @param array<mixed> $payload   Stored payload.
	 * @return bool|array False on failure, API response array on success.
	 */
	public function execute_learndash_content_sync( $handled, string $item_type, string $action, int $item_id, array $payload ) {
		// Only handle our content types
		if ( ! in_array( $item_type, [ 'lesson', 'topic', 'quiz' ], true ) ) {
			return $handled;
		}

		// Only handle content completion events
		if ( 'sync_content_event' !== $action ) {
			return $handled;
		}

		try {
			return $this->process_content_payload( $payload );
		} catch ( \Throwable $error ) {
			do_action( 'ghl_crm_sync_error', 'learndash_content_queue_handler', $payload, $error );
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
		// Process both course and content (lesson/topic/quiz) sync items
		$valid_types = [ 'course', 'lesson', 'topic', 'quiz' ];
		if ( ! isset( $item->item_type ) || ! in_array( $item->item_type, $valid_types, true ) ) {
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
			do_action( 'ghl_crm_sync_error', 'learndash_course_sync', $payload, $error );
			return false;
		}
	}

	/**
	 * Process lesson/topic/quiz event and sync to GoHighLevel contact.
	 *
	 * Creates or updates GHL contact with content-specific tags while preserving existing tags.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Queue payload containing user_id, content_id, type, and tags.
	 * @return array|false API response array on success, false on failure.
	 */
	private function process_content_payload( array $payload ) {
		$user_id    = (int) ( $payload['user_id'] ?? 0 );
		$content_id = (int) ( $payload['content_id'] ?? 0 );
		$type       = sanitize_key( (string) ( $payload['type'] ?? '' ) );
		$new_tags   = $this->normalize_tags( $payload['tags'] ?? [] );

		if ( $user_id <= 0 || $content_id <= 0 || empty( $new_tags ) ) {
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

			// Merge content tags with existing tags
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
				'source'    => 'learndash_' . $type . '_completed',
			];

			// Update or create contact
			if ( $contact_id ) {
				$api_result = $this->contact_resource->update( $contact_id, $contact_payload );
			} else {
				$api_result = $this->contact_resource->create( $contact_payload );
				$contact_id = (string) ( $api_result['contact']['id'] ?? $api_result['id'] ?? '' );
			}

			// Ensure we have a contact ID
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

			do_action( 'ghl_crm_learndash_content_synced', $payload, $contact_payload, $type );

			return $response;

		} catch ( \Throwable $error ) {
			do_action( 'ghl_crm_sync_error', 'learndash_content_sync', $payload, $error );
			return false;
		}
	}

	/**
	 * Retrieve tags configured for a specific course and status.
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
	 * Retrieve tags configured for a specific lesson, topic, or quiz.
	 *
	 * Reads from post meta (_ghl_ld_{type}_completed_tags).
	 * For quizzes with a score, also checks score thresholds to add performance-based tags.
	 *
	 * @since 1.0.0
	 * @param int        $content_id LearnDash content ID (lesson/topic/quiz).
	 * @param string     $type       Content type: lesson|topic|quiz.
	 * @param float|null $score      Quiz score percentage (0-100), null for non-quiz content.
	 * @return array<int,string> Sanitized tag array.
	 */
	private function resolve_content_tags( int $content_id, string $type, ?float $score = null ): array {
		if ( $content_id <= 0 ) {
			return [];
		}

		$valid_types = [ 'lesson', 'topic', 'quiz' ];
		if ( ! in_array( $type, $valid_types, true ) ) {
			return [];
		}

		// Get standard completion tags
		$meta_key     = sprintf( '_ghl_ld_%s_completed_tags', $type );
		$content_tags = get_post_meta( $content_id, $meta_key, true );
		$tags         = $this->normalize_tags( $content_tags );

		// For quizzes, add score-based tags if score is available
		if ( 'quiz' === $type && null !== $score ) {
			$score_tags = $this->resolve_score_threshold_tags( $content_id, $score );
			$tags       = array_merge( $tags, $score_tags );
		}

		return array_values( array_unique( $tags ) );
	}

	/**
	 * Get tags to apply based on quiz score thresholds.
	 *
	 * @since 1.0.0
	 * @param int   $quiz_id Quiz post ID.
	 * @param float $score   Quiz score percentage (0-100).
	 * @return array<int,string> Tags matching the score thresholds.
	 */
	private function resolve_score_threshold_tags( int $quiz_id, float $score ): array {
		$thresholds = get_post_meta( $quiz_id, '_ghl_ld_quiz_score_thresholds', true );

		if ( empty( $thresholds ) || ! is_array( $thresholds ) ) {
			return [];
		}

		$matched_tags = [];

		foreach ( $thresholds as $threshold ) {
			if ( ! is_array( $threshold ) ) {
				continue;
			}

			$min_score = isset( $threshold['min_score'] ) ? (float) $threshold['min_score'] : 0;
			$max_score = isset( $threshold['max_score'] ) ? (float) $threshold['max_score'] : 100;
			$tags      = isset( $threshold['tags'] ) ? $threshold['tags'] : [];

			// Check if score falls within this threshold range
			if ( $score >= $min_score && $score <= $max_score ) {
				$normalized_tags = $this->normalize_tags( $tags );
				$matched_tags    = array_merge( $matched_tags, $normalized_tags );
			}
		}

		return array_values( array_unique( $matched_tags ) );
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

		$this->settings->delete_setting( 'ghl_ld_batch_offset' );
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
