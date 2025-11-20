<?php
/**
 * LearnDash Content Sync Module
 *
 * Handles lesson, topic, and quiz completion events with score-based tagging.
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/LearnDash/Modules
 * @since      1.0.0
 */

declare(strict_types=1);

namespace GHL_CRM\Integrations\LearnDash\Modules;

use GHL_CRM\API\Resources\ContactResource;
use GHL_CRM\Sync\QueueManager;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Content Sync Module
 *
 * Responsibilities:
 * - Handle lesson/topic/quiz completion events
 * - Queue content-related sync jobs
 * - Process content payloads for GHL API sync
 * - Quiz score-based threshold tagging
 *
 * @since 1.0.0
 */
class ContentSync {
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
	 * Constructor.
	 *
	 * @param QueueManager|null    $queue_manager    Optional queue dependency.
	 * @param ContactResource|null $contact_resource Optional API dependency.
	 */
	public function __construct(
		?QueueManager $queue_manager = null,
		?ContactResource $contact_resource = null
	) {
		$this->queue_manager    = $queue_manager ?? QueueManager::get_instance();
		$this->contact_resource = $contact_resource ?? new ContactResource();
	}

	/**
	 * Handle lesson completion events.
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
	 * @since 1.0.0
	 * @param array<string,mixed> $data LearnDash quiz completion payload.
	 * @param WP_User|null        $user WordPress user object.
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
	 * Register queue entry for lesson, topic, or quiz completion sync.
	 *
	 * @since 1.0.0
	 * @param int        $user_id    WordPress user ID.
	 * @param int        $content_id LearnDash lesson/topic/quiz ID.
	 * @param string     $type       Content type (lesson|topic|quiz).
	 * @param float|null $score      Quiz score percentage (0-100), null for non-quiz content.
	 * @return void
	 */
	public function queue_content_event( int $user_id, int $content_id, string $type, ?float $score = null ): void {
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
	 * Process lesson/topic/quiz event and sync to GoHighLevel contact.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $payload Queue payload containing user_id, content_id, type, and tags.
	 * @return array|false API response array on success, false on failure.
	 */
	public function process_content_payload( array $payload ) {
		$user_id    = (int) ( $payload['user_id'] ?? 0 );
		$content_id = (int) ( $payload['content_id'] ?? 0 );
		$type       = sanitize_key( (string) ( $payload['type'] ?? '' ) );
		$new_tags   = $this->normalize_tags( $payload['tags'] ?? [] );
		$quiz_score = $payload['quiz_score'] ?? null;

		error_log( sprintf(
			'[GHL Content Sync] Processing %s completion for user %d, content %d%s, new tags: %s',
			$type,
			$user_id,
			$content_id,
			$quiz_score !== null ? sprintf( ' (score: %.2f%%)', $quiz_score ) : '',
			implode( ', ', $new_tags )
		) );

		if ( $user_id <= 0 || $content_id <= 0 || empty( $new_tags ) ) {
			error_log( '[GHL Content Sync] Invalid user_id, content_id, or no tags, aborting' );
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			error_log( '[GHL Content Sync] User not found or no email, aborting' );
			return false;
		}

		error_log( sprintf(
			'[GHL Content Sync] User email: %s',
			$user->user_email
		) );

		try {
			// Find or create contact
			$existing   = $this->contact_resource->find_by_email( $user->user_email );
			$contact_id = ! empty( $existing['id'] ) ? (string) $existing['id'] : null;

			error_log( sprintf(
				'[GHL Content Sync] Initial contact fetch - ID: %s, existing tags: %s',
				$contact_id ?? 'NEW',
				isset( $existing['tags'] ) ? implode( ', ', $existing['tags'] ) : 'none'
			) );

			// If contact exists, re-fetch to get the absolute latest tags (prevents race conditions)
			if ( $contact_id ) {
				error_log( '[GHL Content Sync] Re-fetching contact to get latest tags (race condition prevention)' );
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
				error_log( '[GHL Content Sync] New contact, no existing tags' );
			}

			// CRITICAL: Also check WordPress user meta for pending tags that may still be in queue
			$pending_tags = get_user_meta( $user_id, '_ghl_pending_tags', true );
			if ( ! empty( $pending_tags ) && is_array( $pending_tags ) ) {
				error_log( sprintf(
					'[GHL Content Sync] Found %d pending tags in user meta: %s',
					count( $pending_tags ),
					implode( ', ', $pending_tags )
				) );
				// Merge GHL tags + pending tags + new tags
				$all_tags = array_values( array_unique( array_merge( $existing_tags, $pending_tags, $new_tags ) ) );
			} else {
				error_log( '[GHL Content Sync] No pending tags in user meta' );
				$all_tags = array_values( array_unique( array_merge( $existing_tags, $new_tags ) ) );
			}

			// Update pending tags cache to include what we're about to send
			update_user_meta( $user_id, '_ghl_pending_tags', $all_tags );

			error_log( sprintf(
				'[GHL Content Sync] Final merged tags (%d total): %s',
				count( $all_tags ),
				implode( ', ', $all_tags )
			) );

			$contact_payload = [
				'email'     => $user->user_email,
				'firstName' => $user->first_name ?? '',
				'lastName'  => $user->last_name ?? '',
				'tags'      => $all_tags,
				'source'    => 'learndash_' . $type . '_completed',
			];

			// Update or create contact
			if ( $contact_id ) {
				error_log( sprintf(
					'[GHL Content Sync] Updating existing contact %s with %d tags',
					$contact_id,
					count( $all_tags )
				) );
				$api_result = $this->contact_resource->update( $contact_id, $contact_payload );
			} else {
				error_log( '[GHL Content Sync] Creating new contact' );
				$api_result = $this->contact_resource->create( $contact_payload );
				$contact_id = (string) ( $api_result['contact']['id'] ?? $api_result['id'] ?? '' );
			}

			error_log( sprintf(
				'[GHL Content Sync] API call completed, contact ID: %s, final tags in response: %s',
				$contact_id ?? 'unknown',
				isset( $api_result['contact']['tags'] ) ? implode( ', ', $api_result['contact']['tags'] ) : 'none'
			) );

			// After successful sync, update pending tags cache with actual GHL response
			if ( isset( $api_result['contact']['tags'] ) && is_array( $api_result['contact']['tags'] ) ) {
				update_user_meta( $user_id, '_ghl_pending_tags', $api_result['contact']['tags'] );
				error_log( sprintf(
					'[GHL Content Sync] Updated pending tags cache with %d tags from GHL response',
					count( $api_result['contact']['tags'] )
				) );
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
	 * Retrieve tags configured for a specific lesson, topic, or quiz.
	 *
	 * @since 1.0.0
	 * @param int        $content_id LearnDash content ID (lesson/topic/quiz).
	 * @param string     $type       Content type: lesson|topic|quiz.
	 * @param float|null $score      Quiz score percentage (0-100), null for non-quiz content.
	 * @return array<int,string> Sanitized tag array.
	 */
	public function resolve_content_tags( int $content_id, string $type, ?float $score = null ): array {
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
