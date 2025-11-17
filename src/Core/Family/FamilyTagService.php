<?php
/**
 * Family tag orchestration utilities.
 *
 * @package GHL_CRM_Integration
 */

declare(strict_types=1);

namespace GHL_CRM\Core\Family;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Core\TagManager;
use GHL_CRM\Database\FamilyRelationshipsRepository;
use GHL_CRM\Sync\QueueManager;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates inherited tag logic between parents and children.
 */
class FamilyTagService {
	/**
	 * Relationship repository dependency.
	 *
	 * @var FamilyRelationshipsRepository
	 */
	private FamilyRelationshipsRepository $repository;

	/**
	 * Tag orchestration utility.
	 *
	 * @var TagManager
	 */
	private TagManager $tag_manager;

	/**
	 * Plugin settings accessor.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings;

	/**
	 * Queue manager for async tag operations.
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Service constructor.
	 *
	 * @param FamilyRelationshipsRepository|null $repository    Repository dependency.
	 * @param TagManager|null                    $tag_manager   Tag manager utility.
	 * @param SettingsManager|null               $settings      Settings accessor.
	 * @param QueueManager|null                  $queue_manager Queue manager.
	 */
	public function __construct(
		?FamilyRelationshipsRepository $repository = null,
		?TagManager $tag_manager = null,
		?SettingsManager $settings = null,
		?QueueManager $queue_manager = null
	) {
		$this->repository    = $repository ?? FamilyRelationshipsRepository::get_instance();
		$this->tag_manager   = $tag_manager ?? TagManager::get_instance();
		$this->settings      = $settings ?? SettingsManager::get_instance();
		$this->queue_manager = $queue_manager ?? QueueManager::get_instance();
	}

	/**
	 * Retrieve inherited tag names for a user.
	 *
	 * @param int   $user_id   Target user ID.
	 * @param array $user_tags Optional tag names for the user.
	 */
	public function get_inherited_tags( int $user_id, array $user_tags = [] ): array {
		$tag_ids = $this->get_inherited_tag_ids( $user_id, $user_tags );
		$names   = $this->tag_manager->convert_ids_to_names( $tag_ids );

		return apply_filters( 'ghl_family_inherited_tags', $names, $user_id, $this->repository->get_parent( $user_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Retrieve inherited tag IDs for a user.
	 *
	 * @param int        $user_id      Target user ID.
	 * @param array|null $user_tag_ids Optional list of IDs (skips lookup when available).
	 */
	public function get_inherited_tag_ids( int $user_id, ?array $user_tag_ids = null ): array {
		$user_tag_ids = $user_tag_ids ?? $this->tag_manager->get_user_tag_ids( $user_id );
		$user_tag_ids = $this->filter_parent_only_tag( $user_tag_ids );

		$parent_id = $this->repository->get_parent( $user_id );
		if ( ! $parent_id ) {
			return array_values( array_unique( $user_tag_ids ) );
		}

		$parent_tag_ids = $this->filter_parent_only_tag( $this->tag_manager->get_user_tag_ids( $parent_id ) );
		$merged         = array_values( array_unique( array_merge( $user_tag_ids, $parent_tag_ids ) ) );

		return apply_filters( 'ghl_family_inherited_tag_ids', $merged, $user_id, $parent_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Clear cached effective tags for a user and their children.
	 *
	 * @param int $user_id User identifier.
	 */
	public function clear_cache_for_user( int $user_id ): void {
		wp_cache_delete( 'ghl_effective_tags_' . $user_id, 'ghl_crm' );

		foreach ( $this->repository->get_children( $user_id ) as $child_id ) {
			wp_cache_delete( 'ghl_effective_tags_' . (int) $child_id, 'ghl_crm' );
		}
	}

	/**
	 * Ensure child inherits parent tags locally or via queue.
	 *
	 * @param int $parent_user_id Parent user identifier.
	 * @param int $child_user_id  Child user identifier.
	 */
	public function sync_parent_tags_to_child( int $parent_user_id, int $child_user_id ): void {
		$parent_tags = $this->filter_parent_only_tag( $this->tag_manager->get_user_tag_ids( $parent_user_id ) );
		if ( empty( $parent_tags ) ) {
			return;
		}

		$child_contact_id = get_user_meta( $child_user_id, '_ghl_contact_id', true );
		if ( empty( $child_contact_id ) ) {
			$pending_tags = get_user_meta( $child_user_id, '_ghl_pending_family_tags', true );
			if ( ! is_array( $pending_tags ) ) {
				$pending_tags = [];
			}

			$merged = array_unique( array_merge( $pending_tags, $parent_tags ) );
			update_user_meta( $child_user_id, '_ghl_pending_family_tags', $merged );
			return;
		}

		$payload_tags = $this->tag_manager->prepare_tags_for_payload( $parent_tags );
		$this->queue_manager->add_to_queue(
			'user',
			$child_user_id,
			'add_tags',
			[
				'contact_id' => $child_contact_id,
				'tags'       => array_values( array_unique( $payload_tags ) ),
				'reason'     => sprintf( 'Family inheritance from parent user ID %d', $parent_user_id ),
			]
		);

		wp_cache_delete( 'ghl_effective_tags_' . $child_user_id, 'ghl_crm' );
	}

	/**
	 * Filter out the configured parent-only tag from a list.
	 *
	 * @param array $tags Raw tag list.
	 */
	private function filter_parent_only_tag( array $tags ): array {
		if ( empty( $tags ) ) {
			return [];
		}

		$parent_tag_id   = (string) $this->settings->get_setting( 'family_parent_tag', '' );
		$parent_tag_name = '';

		if ( '' !== $parent_tag_id ) {
			$map             = $this->tag_manager->map_ids_to_names( [ $parent_tag_id ] );
			$parent_tag_name = strtolower( trim( $map[ $parent_tag_id ] ?? '' ) );
		}

		return array_values(
			array_filter(
				$tags,
				static function ( $tag_value ) use ( $parent_tag_id, $parent_tag_name ) {
					if ( ! is_string( $tag_value ) && ! is_numeric( $tag_value ) ) {
						return false;
					}

					$value = trim( (string) $tag_value );
					if ( '' === $value ) {
						return false;
					}

					if ( '' !== $parent_tag_id && $value === $parent_tag_id ) {
						return false;
					}

					if ( '' !== $parent_tag_name && strtolower( $value ) === $parent_tag_name ) {
						return false;
					}

					return true;
				}
			)
		);
	}
}
