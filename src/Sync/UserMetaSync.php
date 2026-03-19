<?php
/**
 * Centralized user meta synchronization after queue completion.
 *
 * Listens to 'ghl_crm_after_sync_success' and handles all user-meta
 * updates (contact ID, tags, last-sync timestamp, pending tags) so
 * QueueManager stays focused on queue orchestration.
 *
 * @package GHL_CRM_Integration
 */

declare(strict_types=1);

namespace GHL_CRM\Sync;

use GHL_CRM\Core\TagManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class UserMetaSync
 */
class UserMetaSync {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Tag manager.
	 *
	 * @var TagManager
	 */
	private TagManager $tag_manager;

	/**
	 * Queue manager (for queuing pending-tag follow-ups).
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Get singleton instance.
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
	 * Constructor — wires up the hook.
	 */
	private function __construct() {
		$this->tag_manager   = TagManager::get_instance();
		$this->queue_manager = QueueManager::get_instance();
	}

	/**
	 * Initialize (called by Loader).
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'ghl_crm_after_sync_success', [ $this, 'handle_sync_success' ], 10, 4 );
	}

	/**
	 * Central handler for all post-sync user meta updates.
	 *
	 * @param object      $item       Queue item from the database.
	 * @param string|null $contact_id GHL Contact ID.
	 * @param mixed       $result     Sync result data.
	 * @param array       $payload    Original sync payload.
	 * @return void
	 */
	public function handle_sync_success( object $item, ?string $contact_id, $result = null, array $payload = [] ): void {
		if ( empty( $contact_id ) ) {
			return;
		}

		$user_id = $this->resolve_user_id( $item, $payload );

		if ( empty( $user_id ) ) {
			return;
		}

		// Always store contact ID + last sync time.
		$this->tag_manager->store_user_contact_id( $user_id, (string) $contact_id );
		update_user_meta( $user_id, '_ghl_last_sync', time() );

		// Flush pending tags (queued before contact existed).
		$this->flush_pending_tags( $user_id, $contact_id, $item );

		// Store/merge tags from result or payload.
		$this->sync_tags( $user_id, $item, $result, $payload );

		// For add_tags / remove_tags / update — full refresh from GHL.
		$this->maybe_refresh_from_ghl( $user_id, $contact_id, $item );
	}

	// ------------------------------------------------------------------
	//  User ID resolution
	// ------------------------------------------------------------------

	/**
	 * Resolve WordPress user ID from any queue item type.
	 *
	 * @param object $item    Queue item.
	 * @param array  $payload Sync payload.
	 * @return int|null User ID or null.
	 */
	private function resolve_user_id( object $item, array $payload ): ?int {
		switch ( $item->item_type ) {
			case 'user':
				return (int) $item->item_id;

			case 'wc_customer':
				return $this->resolve_wc_user( (int) $item->item_id );

			case 'wc_product_tags':
				$order_id = (int) ( $payload['order_id'] ?? $item->item_id );
				return $this->resolve_wc_user( $order_id );

			default:
				/**
				 * Allow extensions to resolve user IDs for custom item types.
				 *
				 * @param int|null $user_id  Resolved user ID (null by default).
				 * @param object   $item     Queue item.
				 * @param array    $payload  Sync payload.
				 */
				return apply_filters( 'ghl_crm_resolve_sync_user_id', null, $item, $payload );
		}
	}

	/**
	 * Get the customer user ID from a WooCommerce order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return int|null User ID or null for guest orders.
	 */
	private function resolve_wc_user( int $order_id ): ?int {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$user_id = (int) $order->get_customer_id();
		return $user_id > 0 ? $user_id : null;
	}

	// ------------------------------------------------------------------
	//  Pending tags (queued before GHL contact existed)
	// ------------------------------------------------------------------

	/**
	 * Queue any pending / family-pending tags now that we have a contact ID.
	 *
	 * Only applies to the 'user' item type because pending tags are stored
	 * against WordPress user IDs during registration.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $contact_id GHL contact ID.
	 * @param object $item       Queue item.
	 * @return void
	 */
	private function flush_pending_tags( int $user_id, string $contact_id, object $item ): void {
		if ( 'user' !== $item->item_type ) {
			return;
		}

		// Regular pending tags.
		$pending_tags = get_user_meta( $user_id, '_ghl_pending_tags', true );
		if ( is_array( $pending_tags ) && ! empty( $pending_tags ) ) {
			$normalized   = $this->tag_manager->normalize_tag_input( $pending_tags );
			$payload_tags = $this->tag_manager->prepare_tags_for_payload( $normalized['ids'], $normalized['pairs'] );

			if ( ! empty( $payload_tags ) ) {
				$this->queue_manager->add_to_queue(
					'user',
					$user_id,
					'add_tags',
					[
						'contact_id' => $contact_id,
						'tags'       => array_values( array_unique( $payload_tags ) ),
						'reason'     => 'Pending tags applied after contact creation',
					]
				);
			}

			delete_user_meta( $user_id, '_ghl_pending_tags' );
		}

		// Family-inherited pending tags.
		$family_tags = get_user_meta( $user_id, '_ghl_pending_family_tags', true );
		if ( is_array( $family_tags ) && ! empty( $family_tags ) ) {
			$payload_tags = $this->tag_manager->prepare_tags_for_payload( $family_tags );

			$this->queue_manager->add_to_queue(
				'user',
				$user_id,
				'add_tags',
				[
					'contact_id' => $contact_id,
					'tags'       => array_values( array_unique( $payload_tags ) ),
					'reason'     => 'Family inheritance - pending tags applied after registration',
				]
			);

			delete_user_meta( $user_id, '_ghl_pending_family_tags' );
		}
	}

	// ------------------------------------------------------------------
	//  Tag storage
	// ------------------------------------------------------------------

	/**
	 * Store or merge tags into user meta.
	 *
	 * - For 'user' items: replaces tags with the full set from the API response/payload
	 *   (the response contains the full contact tag list).
	 * - For WooCommerce items: merges new tags with existing user tags because the
	 *   payload only contains the tags being added, not the full contact tag list.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param object $item    Queue item.
	 * @param mixed  $result  API response.
	 * @param array  $payload Sync payload.
	 * @return void
	 */
	private function sync_tags( int $user_id, object $item, $result, array $payload ): void {
		if ( 'user' === $item->item_type ) {
			$this->store_user_tags_from_result( $user_id, $result, $payload );
			return;
		}

		// WooCommerce types: merge payload tags with existing.
		if ( in_array( $item->item_type, [ 'wc_customer', 'wc_product_tags' ], true ) ) {
			$this->merge_tags( $user_id, $payload );
		}
	}

	/**
	 * For 'user' item type: extract tags from API result or payload and store.
	 *
	 * The API response typically contains the full tag list so a replace is safe.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param mixed $result  API response.
	 * @param array $payload Sync payload.
	 * @return void
	 */
	private function store_user_tags_from_result( int $user_id, $result, array $payload ): void {
		$tags_to_cache = null;

		// Try API response first.
		if ( is_array( $result ) ) {
			if ( ! empty( $result['tags'] ) && is_array( $result['tags'] ) ) {
				$tags_to_cache = $result['tags'];
			} else {
				$contact_data = $result['contact'] ?? $result;
				if ( ! empty( $contact_data['tags'] ) && is_array( $contact_data['tags'] ) ) {
					$tags_to_cache = $contact_data['tags'];
				}
			}
		}

		// Fall back to payload.
		if ( null === $tags_to_cache && ! empty( $payload['tags'] ) && is_array( $payload['tags'] ) ) {
			$tags_to_cache = $payload['tags'];
		}

		if ( ! empty( $tags_to_cache ) ) {
			$this->tag_manager->store_user_tags( $user_id, $tags_to_cache );
		}
	}

	/**
	 * Merge new tags from payload with existing user tags.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $payload Sync payload.
	 * @return void
	 */
	private function merge_tags( int $user_id, array $payload ): void {
		if ( empty( $payload['tags'] ) || ! is_array( $payload['tags'] ) ) {
			return;
		}

		$existing    = $this->tag_manager->get_user_tag_ids( $user_id );
		$merged_tags = array_values( array_unique( array_merge( $existing, $payload['tags'] ) ) );
		$this->tag_manager->store_user_tags( $user_id, $merged_tags );
	}

	// ------------------------------------------------------------------
	//  Full GHL refresh (for tag ops / updates)
	// ------------------------------------------------------------------

	/**
	 * For specific actions, do a full refresh from GHL to keep user meta authoritative.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $contact_id GHL contact ID.
	 * @param object $item       Queue item.
	 * @return void
	 */
	private function maybe_refresh_from_ghl( int $user_id, string $contact_id, object $item ): void {
		$refresh_actions = [ 'add_tags', 'remove_tags', 'update' ];

		if ( ! in_array( $item->action, $refresh_actions, true ) ) {
			return;
		}

		try {
			$profile_fields = \GHL_CRM\Admin\Profile\UserProfileFields::get_instance();

			if ( method_exists( $profile_fields, 'refresh_user_from_ghl' ) ) {
				$profile_fields->refresh_user_from_ghl( $user_id, $contact_id );
			} else {
				// Fallback: direct API call.
				$client   = \GHL_CRM\API\Client\Client::get_instance();
				$response = $client->get( "contacts/{$contact_id}" );

				if ( ! empty( $response['contact'] ) ) {
					$contact = $response['contact'];

					$this->tag_manager->store_user_contact_id( $user_id, $contact_id );
					update_user_meta( $user_id, '_ghl_last_sync', time() );

					if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
						$this->tag_manager->store_user_tags( $user_id, $contact['tags'] );
					}

					if ( ! empty( $contact['type'] ) ) {
						update_user_meta( $user_id, '_ghl_contact_type', $contact['type'] );
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Silently fail — don't break queue processing.
		}
	}
}
