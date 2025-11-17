<?php
/**
 * Family invitation and onboarding workflows.
 *
 * @package GHL_CRM_Integration
 */

declare(strict_types=1);

namespace GHL_CRM\Core\Family;

use GHL_CRM\Database\FamilyRelationshipsRepository;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates invitation, validation, and onboarding of family members.
 */
class FamilyInvitationService {
	/**
	 * Repository mediator for family relationships.
	 *
	 * @var FamilyRelationshipsRepository
	 */
	private FamilyRelationshipsRepository $repository;

	/**
	 * Handles tag synchronization logic.
	 *
	 * @var FamilyTagService
	 */
	private FamilyTagService $tag_service;

	/**
	 * BuddyBoss group synchronization service.
	 *
	 * @var FamilyBuddyBossService
	 */
	private FamilyBuddyBossService $buddyboss_service;

	/**
	 * Service constructor.
	 *
	 * @param FamilyRelationshipsRepository|null $repository      Optional repository dependency.
	 * @param FamilyTagService|null              $tag_service     Optional tag service dependency.
	 * @param FamilyBuddyBossService|null        $buddyboss_service Optional BuddyBoss service dependency.
	 */
	public function __construct(
		?FamilyRelationshipsRepository $repository = null,
		?FamilyTagService $tag_service = null,
		?FamilyBuddyBossService $buddyboss_service = null
	) {
		$this->repository        = $repository ?? FamilyRelationshipsRepository::get_instance();
		$this->tag_service       = $tag_service ?? new FamilyTagService();
		$this->buddyboss_service = $buddyboss_service ?? new FamilyBuddyBossService();
	}

	/**
	 * Search for a user via email or username to determine invitation status.
	 *
	 * @param string $identifier Email or username provided by the parent.
	 * @param int    $parent_id  Parent user ID.
	 *
	 * @return array
	 */
	public function search( string $identifier, int $parent_id ): array {
		$identifier = trim( $identifier );
		$is_email   = is_email( $identifier );

		$user = get_user_by( 'email', $identifier );
		if ( ! $user && ! $is_email ) {
			$user = get_user_by( 'login', $identifier );
		}

		if ( ! $user ) {
			return $this->handle_missing_user( $identifier, $is_email );
		}

		return $this->resolve_existing_user_status( $user, $parent_id );
	}

	/**
	 * Create a new user and send the invitation email.
	 *
	 * @param string $email     Email used for the new account.
	 * @param int    $parent_id Parent user ID.
	 *
	 * @return array
	 */
	public function create_and_invite( string $email, int $parent_id ): array {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid email address.', 'ghl-crm-integration' ),
			];
		}

		if ( email_exists( $email ) ) {
			return [
				'success' => false,
				'message' => __( 'A user with this email already exists.', 'ghl-crm-integration' ),
			];
		}

		$username = $this->generate_unique_username( $email );
		$password = wp_generate_password( 12, true, true );
		$user_id  = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message returned by WordPress */
					__( 'Failed to create user: %s', 'ghl-crm-integration' ),
					$user_id->get_error_message()
				),
			];
		}

		$this->mark_invitation_pending( (int) $user_id, $parent_id );
		$this->send_invite_email( (int) $user_id, $email, $password );

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: %s: email address of the invited user */
				__( 'Account created and invitation sent to %s', 'ghl-crm-integration' ),
				$email
			),
			'user'    => [
				'id'     => (int) $user_id,
				'name'   => $username,
				'email'  => $email,
				'status' => 'invited',
			],
		];
	}

	/**
	 * Invite an existing user to join a family.
	 *
	 * @param int $user_id   Existing WordPress user ID.
	 * @param int $parent_id Parent user ID.
	 *
	 * @return array
	 */
	public function invite_existing_user( int $user_id, int $parent_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [
				'success' => false,
				'message' => __( 'User not found.', 'ghl-crm-integration' ),
			];
		}

		update_user_meta( $user_id, '_ghl_pending_parent_id', $parent_id );
		$this->mark_invitation_pending( $user_id, $parent_id );
		$this->send_invite_email( $user_id, $user->user_email );

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: 1: invited user display name, 2: invited user email */
				__( 'Invitation sent to %1$s (%2$s)', 'ghl-crm-integration' ),
				$user->display_name,
				$user->user_email
			),
			'user'    => [
				'id'     => $user_id,
				'name'   => $user->display_name,
				'email'  => $user->user_email,
				'status' => 'invited',
			],
		];
	}

	/**
	 * Handle GET request callback for accepting invitations.
	 */
	public function handle_accept_invitation_request(): void {
		$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'ghl_accept_invite' !== $action ) {
			return;
		}

		$token   = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tokens are delivered via emailed link.
		$user_id = isset( $_GET['uid'] ) ? absint( wp_unslash( $_GET['uid'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tokens are delivered via emailed link.

		if ( empty( $token ) || $user_id <= 0 ) {
			$this->bail( __( 'Invalid invitation link.', 'ghl-crm-integration' ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->bail( __( 'User not found.', 'ghl-crm-integration' ), 404 );
		}

		$meta_token = get_user_meta( $user_id, '_ghl_invite_token', true );
		$expires_at = (int) get_user_meta( $user_id, '_ghl_token_expires', true );

		if ( $meta_token !== $token ) {
			$this->bail( __( 'Invalid or expired invitation token.', 'ghl-crm-integration' ), 403 );
		}

		if ( $expires_at && time() > $expires_at ) {
			$this->bail( __( 'This invitation has expired. Please request a new invitation.', 'ghl-crm-integration' ), 403 );
		}

		$this->activate_relationship( $user_id );
		$this->complete_login( $user );

		$redirect = apply_filters( 'ghl_family_invite_redirect_url', home_url(), $user_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Mark the relationship as active and cascade integrations.
	 *
	 * @param int $child_id Child user ID.
	 */
	private function activate_relationship( int $child_id ): void {
		update_user_meta( $child_id, '_ghl_invite_status', 'active' );
		update_user_meta( $child_id, '_ghl_invite_accepted_at', time() );
		delete_user_meta( $child_id, '_ghl_invite_token' );
		delete_user_meta( $child_id, '_ghl_token_expires' );

		$parent_id = (int) get_user_meta( $child_id, '_ghl_invited_by', true );
		if ( ! $parent_id ) {
			$parent_id = (int) get_user_meta( $child_id, '_ghl_pending_parent_id', true );
		}

		if ( $parent_id > 0 ) {
			$this->repository->create_relationship( $parent_id, $child_id );
			$this->buddyboss_service->add_child_to_group( $parent_id, $child_id );
			$this->tag_service->sync_parent_tags_to_child( $parent_id, $child_id );
			delete_user_meta( $child_id, '_ghl_pending_parent_id' );
		}
	}

	/**
	 * Log the user in once the invitation is accepted.
	 *
	 * @param WP_User $user WordPress user object.
	 */
	private function complete_login( WP_User $user ): void {
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core login hook.
	}

	/**
	 * Provide response metadata when a user cannot be found.
	 *
	 * @param string $identifier Identifier provided by parent.
	 * @param bool   $is_email   Whether the identifier is an email.
	 *
	 * @return array
	 */
	private function handle_missing_user( string $identifier, bool $is_email ): array {
		if ( ! $is_email ) {
			return [
				'exists'  => false,
				'status'  => 'invalid',
				'message' => __( 'Please provide a valid email address to create and invite a new user.', 'ghl-crm-integration' ),
				'action'  => 'none',
			];
		}

		return [
			'exists'       => false,
			'status'       => 'not_found',
			'message'      => sprintf(
				/* translators: %s: email address provided by the parent */
				__( 'No user found with email %s. Click "Send Invite" to create an account and send them an invitation email.', 'ghl-crm-integration' ),
				$identifier
			),
			'action'       => 'create_and_invite',
			'confirm_text' => __( 'Send Invite', 'ghl-crm-integration' ),
			'email'        => $identifier,
		];
	}

	/**
	 * Determine how to proceed when the searched user already exists.
	 *
	 * @param WP_User $user      User object found by the search.
	 * @param int     $parent_id Requesting parent user ID.
	 *
	 * @return array
	 */
	private function resolve_existing_user_status( WP_User $user, int $parent_id ): array {
		$existing_parent = $this->repository->get_parent( $user->ID );

		if ( $existing_parent === $parent_id ) {
			return [
				'exists'  => true,
				'status'  => 'already_child',
				'message' => sprintf(
					/* translators: 1: child display name, 2: child email address */
					__( '%1$s (%2$s) is already linked as your child.', 'ghl-crm-integration' ),
					$user->display_name,
					$user->user_email
				),
				'action'  => 'none',
				'user'    => $this->format_user_payload( $user ),
			];
		}

		if ( $existing_parent && $existing_parent !== $parent_id ) {
			$parent_user = get_userdata( $existing_parent );

			return [
				'exists'  => true,
				'status'  => 'has_parent',
				'message' => sprintf(
					/* translators: 1: child display name, 2: child email, 3: existing parent display name */
					__( '%1$s (%2$s) is already linked to another parent (%3$s). They cannot be linked to multiple parents.', 'ghl-crm-integration' ),
					$user->display_name,
					$user->user_email,
					$parent_user ? $parent_user->display_name : 'Unknown'
				),
				'action'  => 'none',
				'user'    => $this->format_user_payload( $user ),
			];
		}

		return [
			'exists'       => true,
			'status'       => 'available',
			'message'      => sprintf(
				/* translators: 1: user display name, 2: user email address */
				__( 'User %1$s (%2$s) found. Click "Send Invite" to link them as your child and send an invitation email.', 'ghl-crm-integration' ),
				$user->display_name,
				$user->user_email
			),
			'action'       => 'invite',
			'confirm_text' => __( 'Send Invite', 'ghl-crm-integration' ),
			'user'         => $this->format_user_payload( $user ),
		];
	}

	/**
	 * Provide consistent payload structure for AJAX responses.
	 *
	 * @param WP_User $user User instance.
	 *
	 * @return array
	 */
	private function format_user_payload( WP_User $user ): array {
		$status = get_user_meta( $user->ID, '_ghl_invite_status', true );

		return [
			'id'     => $user->ID,
			'name'   => $user->display_name,
			'email'  => $user->user_email,
			'status' => $status ? (string) $status : 'active',
		];
	}

	/**
	 * Persist meta data for pending invitations.
	 *
	 * @param int $user_id   Pending user ID.
	 * @param int $parent_id Parent user ID.
	 */
	private function mark_invitation_pending( int $user_id, int $parent_id ): void {
		$token      = bin2hex( random_bytes( 32 ) );
		$expires_at = time() + ( 7 * DAY_IN_SECONDS );

		update_user_meta( $user_id, '_ghl_invite_status', 'pending' );
		update_user_meta( $user_id, '_ghl_invited_by', $parent_id );
		update_user_meta( $user_id, '_ghl_invite_sent_at', time() );
		update_user_meta( $user_id, '_ghl_invite_token', $token );
		update_user_meta( $user_id, '_ghl_token_expires', $expires_at );
	}

	/**
	 * Compose and dispatch the invitation email.
	 *
	 * @param int         $user_id  User ID being invited.
	 * @param string      $email    Email address for delivery.
	 * @param string|null $password Optional password for new accounts.
	 */
	private function send_invite_email( int $user_id, string $email, ?string $password = null ): void {
		$site_name  = get_bloginfo( 'name' );
		$token      = get_user_meta( $user_id, '_ghl_invite_token', true );
		$accept_url = add_query_arg(
			[
				'action' => 'ghl_accept_invite',
				'token'  => $token,
				'uid'    => $user_id,
			],
			home_url()
		);

		if ( $password ) {
			/* translators: %s: site name */
			$subject = sprintf( __( '[%s] Your Account Has Been Created', 'ghl-crm-integration' ), $site_name );
		} else {
			/* translators: %s: site name */
			$subject = sprintf( __( '[%s] You Have Been Invited', 'ghl-crm-integration' ), $site_name );
		}

		ob_start();
		$template_path = GHL_CRM_PATH . 'templates/emails/family-invitation.php';

		if ( file_exists( $template_path ) ) {
			$parent = get_userdata( (int) get_user_meta( $user_id, '_ghl_invited_by', true ) );
			$user   = get_userdata( $user_id );
			include $template_path;
		} else {
			echo '<p>' . esc_html__( 'You have been invited to join a family account.', 'ghl-crm-integration' ) . '</p>';
			echo '<p><a href="' . esc_url( $accept_url ) . '">' . esc_html__( 'Accept Invitation', 'ghl-crm-integration' ) . '</a></p>';
		}

		$message = ob_get_clean();

		add_filter( 'wp_mail_content_type', [ $this, 'set_html_content_type' ] );
		wp_mail( $email, $subject, $message );
		remove_filter( 'wp_mail_content_type', [ $this, 'set_html_content_type' ] );
	}

	/**
	 * Set HTML content type for the invitation email.
	 *
	 * @return string
	 */
	public function set_html_content_type(): string {
		return 'text/html';
	}

	/**
	 * Generate a unique username from the provided email address.
	 *
	 * @param string $email Email address.
	 *
	 * @return string
	 */
	private function generate_unique_username( string $email ): string {
		$username = sanitize_user( current( explode( '@', $email ) ), true );
		$base     = $username;
		$counter  = 1;

		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			++$counter;
		}

		return $username;
	}

	/**
	 * Helper to stop execution and show message errors.
	 *
	 * @param string $message       Human readable message.
	 * @param int    $response_code HTTP status code.
	 */
	private function bail( string $message, int $response_code = 400 ): void {
		wp_die( esc_html( $message ), esc_html__( 'Error', 'ghl-crm-integration' ), [ 'response' => absint( $response_code ) ] );
	}
}
