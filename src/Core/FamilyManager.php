<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

use GHL_CRM\Core\Family\FamilyBuddyBossService;
use GHL_CRM\Core\Family\FamilyInvitationService;
use GHL_CRM\Core\Family\FamilyTagService;
use GHL_CRM\Database\FamilyRelationshipsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Family Manager facade for modular services.
 */
class FamilyManager {
	/**
	 * Singleton instance holder.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Repository for persisted family relationships.
	 *
	 * @var FamilyRelationshipsRepository
	 */
	private FamilyRelationshipsRepository $family_repo;

	/**
	 * Invitation workflow coordinator.
	 *
	 * @var FamilyInvitationService
	 */
	private FamilyInvitationService $invitation_service;

	/**
	 * Tag inheritance orchestrator.
	 *
	 * @var FamilyTagService
	 */
	private FamilyTagService $tag_service;

	/**
	 * BuddyBoss integration helper.
	 *
	 * @var FamilyBuddyBossService
	 */
	private FamilyBuddyBossService $buddyboss_service;

	/**
	 * Retrieve or create the manager instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire dependencies and register hooks.
	 *
	 * @param FamilyRelationshipsRepository|null $repository         Data repository.
	 * @param FamilyInvitationService|null       $invitation_service Invitation service.
	 * @param FamilyTagService|null              $tag_service        Tag service.
	 * @param FamilyBuddyBossService|null        $buddyboss_service  BuddyBoss service.
	 */
	private function __construct(
		?FamilyRelationshipsRepository $repository = null,
		?FamilyInvitationService $invitation_service = null,
		?FamilyTagService $tag_service = null,
		?FamilyBuddyBossService $buddyboss_service = null
	) {
		$this->family_repo        = $repository ?? FamilyRelationshipsRepository::get_instance();
		$this->tag_service        = $tag_service ?? new FamilyTagService( $this->family_repo );
		$this->buddyboss_service  = $buddyboss_service ?? new FamilyBuddyBossService( $this->family_repo );
		$this->invitation_service = $invitation_service ?? new FamilyInvitationService(
			$this->family_repo,
			$this->tag_service,
			$this->buddyboss_service
		);

		$this->init();
	}

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_action( 'wp_ajax_ghl_crm_search_user', [ $this, 'ajax_search_user' ] );
		add_action( 'wp_ajax_ghl_crm_get_children', [ $this, 'ajax_get_children' ] );
		add_action( 'wp_ajax_ghl_crm_link_child', [ $this, 'ajax_link_child' ] );
		add_action( 'wp_ajax_ghl_crm_unlink_child', [ $this, 'ajax_unlink_child' ] );
		add_action( 'wp_ajax_ghl_crm_get_all_parents', [ $this, 'ajax_get_all_parents' ] );
		add_action( 'wp_ajax_ghl_crm_sync_families_buddyboss', [ $this, 'ajax_sync_families_to_buddyboss' ] );
		add_action( 'template_redirect', [ $this, 'handle_accept_invitation' ] );
	}

	/**
	 * Locate a potential child account.
	 *
	 * @param string $identifier Email or username.
	 * @param int    $parent_id  Parent user ID.
	 *
	 * @return array
	 */
	public function search_user( string $identifier, int $parent_id ): array {
		return $this->invitation_service->search( $identifier, $parent_id );
	}

	/**
	 * Create a brand-new child account and issue an invite.
	 *
	 * @param string $email     Email for the new account.
	 * @param int    $parent_id Parent user ID.
	 *
	 * @return array
	 */
	public function create_and_invite( string $email, int $parent_id ): array {
		return $this->invitation_service->create_and_invite( $email, $parent_id );
	}

	/**
	 * Invite an existing WP user to join the family.
	 *
	 * @param int $user_id   Existing user ID.
	 * @param int $parent_id Parent user ID.
	 *
	 * @return array
	 */
	public function invite_existing_user( int $user_id, int $parent_id ): array {
		return $this->invitation_service->invite_existing_user( $user_id, $parent_id );
	}

	/**
	 * Proxy public invitation acceptance handling.
	 */
	public function handle_accept_invitation(): void {
		$this->invitation_service->handle_accept_invitation_request();
	}

	/**
	 * Fetch children linked to a parent user.
	 *
	 * @param int $parent_id Parent user ID.
	 *
	 * @return array
	 */
	public function get_children( int $parent_id ): array {
		$relationships = $this->family_repo->get_children_relationships( $parent_id );
		$children      = [];

		foreach ( $relationships as $relationship ) {
			$child_id = (int) $relationship->child_user_id;
			$user     = get_userdata( $child_id );

			if ( ! $user ) {
				continue;
			}

			$status_meta = get_user_meta( $child_id, '_ghl_invite_status', true );
			$status      = ! empty( $status_meta ) ? (string) $status_meta : 'active';
			$linked_date = ! empty( $relationship->created_at ) ? $relationship->created_at : $user->user_registered;

			$children[] = [
				'id'          => $child_id,
				'name'        => $user->display_name,
				'email'       => $user->user_email,
				'linked_date' => $linked_date,
				'status'      => $status,
			];
		}

		return $children;
	}

	/**
	 * Remove an existing parent/child association.
	 *
	 * @param int $parent_id Parent user ID.
	 * @param int $child_id  Child user ID.
	 *
	 * @return array
	 */
	public function unlink_child( int $parent_id, int $child_id ): array {
		$deleted = $this->family_repo->delete_relationship( $parent_id, $child_id );

		if ( ! $deleted ) {
			return [
				'success' => false,
				'message' => __( 'Failed to unlink child.', 'ghl-crm-integration' ),
			];
		}

		$this->buddyboss_service->remove_child_from_group( $parent_id, $child_id );

		return [
			'success' => true,
			'message' => __( 'Child unlinked successfully.', 'ghl-crm-integration' ),
		];
	}

	/**
	 * AJAX: search for a child account before linking.
	 */
	public function ajax_search_user(): void {
		check_ajax_referer( 'ghl_crm_nonce', 'nonce' );

		$current_parent = get_current_user_id();
		$parent_id      = isset( $_POST['parent_id'] ) ? absint( wp_unslash( $_POST['parent_id'] ) ) : $current_parent;
		$identifier     = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';

		if ( $parent_id !== $current_parent && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ghl-crm-integration' ) ] );
		}

		if ( '' === $identifier ) {
			wp_send_json_error( [ 'message' => __( 'Please provide an email or username.', 'ghl-crm-integration' ) ] );
		}

		$result = $this->search_user( $identifier, $parent_id );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: fetch children for the current or specified parent.
	 */
	public function ajax_get_children(): void {
		check_ajax_referer( 'ghl_crm_nonce', 'nonce' );

		$current_parent = get_current_user_id();
		$parent_id      = isset( $_POST['parent_id'] ) ? absint( wp_unslash( $_POST['parent_id'] ) ) : $current_parent;

		if ( $parent_id !== $current_parent && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ghl-crm-integration' ) ] );
		}

		$children = $this->get_children( $parent_id );
		wp_send_json_success( [ 'children' => $children ] );
	}

	/**
	 * AJAX: link or invite a child based on identifier or user ID.
	 */
	public function ajax_link_child(): void {
		check_ajax_referer( 'ghl_crm_nonce', 'nonce' );

		$parent_id  = get_current_user_id();
		$identifier = isset( $_POST['child_identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['child_identifier'] ) ) : '';
		$user_id    = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

		if ( isset( $_POST['parent_id'] ) && current_user_can( 'manage_options' ) ) {
			$parent_id = absint( wp_unslash( $_POST['parent_id'] ) );
		}

		$result = $user_id ? $this->invite_existing_user( $user_id, $parent_id ) : $this->create_and_invite( $identifier, $parent_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( [ 'message' => $result['message'] ?? __( 'Unable to send invitation.', 'ghl-crm-integration' ) ] );
	}

	/**
	 * AJAX: unlink a child from a parent.
	 */
	public function ajax_unlink_child(): void {
		check_ajax_referer( 'ghl_crm_nonce', 'nonce' );

		$parent_id = get_current_user_id();
		$child_id  = isset( $_POST['child_id'] ) ? absint( wp_unslash( $_POST['child_id'] ) ) : 0;

		if ( isset( $_POST['parent_id'] ) && current_user_can( 'manage_options' ) ) {
			$parent_id = absint( wp_unslash( $_POST['parent_id'] ) );
		}

		if ( $child_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid child ID.', 'ghl-crm-integration' ) ] );
		}

		$result = $this->unlink_child( $parent_id, $child_id );

		if ( $result['success'] ) {
			wp_send_json_success( [ 'message' => $result['message'] ] );
		}

		wp_send_json_error( [ 'message' => $result['message'] ] );
	}

	/**
	 * AJAX: list all parents (admin only).
	 */
	public function ajax_get_all_parents(): void {
		check_ajax_referer( 'ghl_crm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ghl-crm-integration' ) ] );
		}

		$parents     = $this->family_repo->get_all_parents();
		$parent_list = [];

		foreach ( $parents as $parent_id ) {
			$user = get_userdata( $parent_id );

			if ( ! $user ) {
				continue;
			}

			$parent_list[] = [
				'id'    => (int) $parent_id,
				'name'  => $user->display_name,
				'email' => $user->user_email,
			];
		}

		wp_send_json_success( [ 'parents' => $parent_list ] );
	}

	/**
	 * Retrieve inherited tag names for a child.
	 *
	 * @param int   $user_id  User ID.
	 * @param array $user_tags Optional cache of tag names.
	 *
	 * @return array
	 */
	public function get_inherited_tags( int $user_id, array $user_tags = [] ): array {
		return $this->tag_service->get_inherited_tags( $user_id, $user_tags );
	}

	/**
	 * Retrieve inherited tag IDs for a child.
	 *
	 * @param int   $user_id      User ID.
	 * @param array $user_tag_ids Optional cache of IDs.
	 *
	 * @return array
	 */
	public function get_inherited_tag_ids( int $user_id, array $user_tag_ids = [] ): array {
		return $this->tag_service->get_inherited_tag_ids( $user_id, $user_tag_ids );
	}

	/**
	 * Clear cached inherited tags for a user and descendants.
	 *
	 * @param int $user_id User ID to flush.
	 */
	public function clear_tags_cache( int $user_id ): void {
		$this->tag_service->clear_cache_for_user( $user_id );
	}

	/**
	 * Trigger GHL tag sync from parent to child.
	 *
	 * @param int      $parent_user_id Parent WP user ID.
	 * @param int      $child_user_id  Child WP user ID.
	 * @param int|null $relationship_id Legacy parameter (unused).
	 */
	public function sync_parent_tags_to_child( int $parent_user_id, int $child_user_id, ?int $relationship_id = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->tag_service->sync_parent_tags_to_child( $parent_user_id, $child_user_id );
	}

	/**
	 * Batch sync BuddyBoss groups for all families.
	 *
	 * @return array
	 */
	public function sync_all_families_to_buddyboss(): array {
		return $this->buddyboss_service->sync_all_families();
	}

	/**
	 * AJAX: trigger BuddyBoss synchronization.
	 */
	public function ajax_sync_families_to_buddyboss(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ghl_crm_settings_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'ghl-crm-integration' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ghl-crm-integration' ) ] );
		}

		$result = $this->sync_all_families_to_buddyboss();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result );
	}
}
