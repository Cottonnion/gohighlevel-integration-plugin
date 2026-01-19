<?php
declare(strict_types=1);

namespace GHL_CRM\Admin\Users;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Integrations\Users\UserHooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Bulk Actions
 *
 * Adds GoHighLevel sync options to the WordPress users bulk actions menu.
 *
 * @package    GHL_CRM_Integration
 * @subpackage Admin/Users
 */
class UserBulkActions {
	private const BULK_ACTION_KEY      = 'ghl_sync_users';
	private const QUERY_ARG_SUCCESS    = 'ghl_sync_users';
	private const QUERY_ARG_FAILURE    = 'ghl_sync_users_failed';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings manager reference.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * User hooks handler reference.
	 *
	 * @var UserHooks
	 */
	private UserHooks $user_hooks;

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
	 * Private constructor.
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
		$this->user_hooks       = UserHooks::get_instance();

		$this->register_hooks();
	}

	/**
	 * Register admin hooks for the bulk action.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Keep multisite behaviour per-site to honour data isolation.
		if ( is_network_admin() ) {
			return;
		}

		if ( ! $this->settings_manager->is_connection_verified() ) {
			return;
		}

		add_filter( 'bulk_actions-users', [ $this, 'register_bulk_action' ] );
		add_filter( 'handle_bulk_actions-users', [ $this, 'handle_bulk_action' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );
	}

	/**
	 * Add the GoHighLevel sync option to the bulk actions dropdown.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array Modified actions.
	 */
	public function register_bulk_action( array $actions ): array {
		$actions[ self::BULK_ACTION_KEY ] = __( 'Sync to GoHighLevel', 'ghl-crm-integration' );

		return $actions;
	}

	/**
	 * Handle the selected bulk action.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction    Current bulk action key.
	 * @param array  $user_ids    Selected user IDs.
	 * @return string Redirect URL with status query args.
	 */
	public function handle_bulk_action( string $redirect_to, string $doaction, array $user_ids ): string {
		if ( self::BULK_ACTION_KEY !== $doaction ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'promote_users' ) ) {
			return $redirect_to;
		}

		$user_ids = array_filter( array_map( 'absint', $user_ids ) );
		if ( empty( $user_ids ) ) {
			return add_query_arg(
				[
					self::QUERY_ARG_SUCCESS => 0,
					self::QUERY_ARG_FAILURE => 0,
				],
				$redirect_to
			);
		}

		$queued = 0;
		$failed = 0;

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user instanceof \WP_User ) {
				$failed++;
				continue;
			}

			$old_user_data = clone $user;

			if ( $this->user_hooks->queue_user_profile_sync( $user_id, $old_user_data ) ) {
				$queued++;
				continue;
			}

			$failed++;
		}

		$redirect_to = remove_query_arg(
			[
				self::QUERY_ARG_SUCCESS,
				self::QUERY_ARG_FAILURE,
			],
			$redirect_to
		);

		return add_query_arg(
			[
				self::QUERY_ARG_SUCCESS => $queued,
				self::QUERY_ARG_FAILURE => $failed,
			],
			$redirect_to
		);
	}

	/**
	 * Display an admin notice summarising the bulk action results.
	 *
	 * @return void
	 */
	public function render_admin_notice(): void {
		if ( ! isset( $_GET[ self::QUERY_ARG_SUCCESS ] ) && ! isset( $_GET[ self::QUERY_ARG_FAILURE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
			return;
		}

		$queued = isset( $_GET[ self::QUERY_ARG_SUCCESS ] ) ? absint( wp_unslash( $_GET[ self::QUERY_ARG_SUCCESS ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
		$failed = isset( $_GET[ self::QUERY_ARG_FAILURE ] ) ? absint( wp_unslash( $_GET[ self::QUERY_ARG_FAILURE ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.

		if ( 0 === ( $queued + $failed ) ) {
			return;
		}

		$class = 'notice notice-success';

		if ( $failed && $queued ) {
			$class = 'notice notice-warning';
		} elseif ( $failed && ! $queued ) {
			$class = 'notice notice-error';
		}

		if ( $queued && $failed ) {
			$message = sprintf(
				/* translators: 1: number of users queued, 2: number of failures */
				__( '%1$d users queued for GoHighLevel sync. %2$d users could not be queued.', 'ghl-crm-integration' ),
				$queued,
				$failed
			);
		} elseif ( $queued ) {
			$message = sprintf(
				/* translators: %d: number of users queued */
				_n( '%d user queued for GoHighLevel sync.', '%d users queued for GoHighLevel sync.', $queued, 'ghl-crm-integration' ),
				$queued
			);
		} else {
			$message = __( 'No users were queued for GoHighLevel sync.', 'ghl-crm-integration' );
		}

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}

	/**
	 * Initialize the bulk action handler.
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}