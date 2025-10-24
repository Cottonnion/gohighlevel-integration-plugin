<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Notices Manager
 *
 * Centralized system for displaying admin notices across the plugin.
 * Uses action hooks to display notices on the settings page and other admin pages.
 *
 * MULTISITE BEHAVIOR:
 * - Notices use site transients (site-specific in multisite networks)
 * - Each site in a multisite network has isolated notices
 * - This matches the plugin's per-site settings architecture
 * - Uses get_site_transient/set_site_transient/delete_site_transient for proper multisite support
 * - Network admin pages are not currently supported
 *
 * @package    GHL_CRM_Integration
 * @subpackage Core
 */
class AdminNotices {
	/**
	 * Transient prefix for storing notices
	 *
	 * Note: Uses site transients (site-specific in multisite)
	 */
	private const TRANSIENT_PREFIX = 'ghl_crm_notice_';

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get instance
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
	 * Private constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Display notices on settings page
		add_action( 'ghl_crm_settings_notices', [ $this, 'display_notices' ] );

		// Display notices on all admin pages (for global notices)
		add_action( 'admin_notices', [ $this, 'display_global_notices' ] );
	}

	/**
	 * Add a notice to be displayed
	 *
	 * Notices are stored in site-specific transients (multisite-aware).
	 * In multisite environments, notices are isolated per site.
	 *
	 * @param string $message Notice message (will be escaped)
	 * @param string $type    Notice type: 'success', 'error', 'warning', 'info'
	 * @param bool   $dismissible Whether the notice is dismissible
	 * @param bool   $global   Whether to show on all admin pages (not just settings)
	 * @return void
	 */
	public function add_notice( string $message, string $type = 'info', bool $dismissible = true, bool $global = false ): void {
		$notice = [
			'message'     => $message,
			'type'        => $type,
			'dismissible' => $dismissible,
			'global'      => $global,
			'timestamp'   => time(),
		];

		$notices   = $this->get_stored_notices();
		$notices[] = $notice;

		// Use site transient for multisite compatibility
		set_site_transient( self::TRANSIENT_PREFIX . get_current_user_id(), $notices, HOUR_IN_SECONDS );
	}

	/**
	 * Add a success notice
	 *
	 * @param string $message Notice message
	 * @param bool   $global  Whether to show on all admin pages
	 * @return void
	 */
	public function success( string $message, bool $global = false ): void {
		$this->add_notice( $message, 'success', true, $global );
	}

	/**
	 * Add an error notice
	 *
	 * @param string $message Notice message
	 * @param bool   $global  Whether to show on all admin pages
	 * @return void
	 */
	public function error( string $message, bool $global = false ): void {
		$this->add_notice( $message, 'error', true, $global );
	}

	/**
	 * Add a warning notice
	 *
	 * @param string $message Notice message
	 * @param bool   $global  Whether to show on all admin pages
	 * @return void
	 */
	public function warning( string $message, bool $global = false ): void {
		$this->add_notice( $message, 'warning', true, $global );
	}

	/**
	 * Add an info notice
	 *
	 * @param string $message Notice message
	 * @param bool   $global  Whether to show on all admin pages
	 * @return void
	 */
	public function info( string $message, bool $global = false ): void {
		$this->add_notice( $message, 'info', true, $global );
	}

	/**
	 * Get stored notices from transient
	 *
	 * Uses site-specific transients (multisite-aware).
	 * In multisite, notices are isolated per site.
	 *
	 * @return array
	 */
	private function get_stored_notices(): array {
		$notices = get_site_transient( self::TRANSIENT_PREFIX . get_current_user_id() );
		return is_array( $notices ) ? $notices : [];
	}

	/**
	 * Clear all stored notices
	 *
	 * Clears site-specific transients (multisite-aware).
	 *
	 * @return void
	 */
	private function clear_notices(): void {
		delete_site_transient( self::TRANSIENT_PREFIX . get_current_user_id() );
	}

	/**
	 * Display notices on settings page
	 * Called via ghl_crm_settings_notices action hook
	 *
	 * @return void
	 */
	public function display_notices(): void {
		$notices = $this->get_stored_notices();

		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			// Skip global notices (they're handled by display_global_notices)
			if ( ! empty( $notice['global'] ) ) {
				continue;
			}

			$this->render_notice( $notice );
		}

		// Clear notices after displaying
		$this->clear_notices();
	}

	/**
	 * Display global notices on all admin pages
	 * Called via admin_notices action hook
	 *
	 * @return void
	 */
	public function display_global_notices(): void {
		// Only show global notices
		$notices = $this->get_stored_notices();

		if ( empty( $notices ) ) {
			return;
		}

		$has_global = false;
		foreach ( $notices as $notice ) {
			if ( ! empty( $notice['global'] ) ) {
				$this->render_notice( $notice );
				$has_global = true;
			}
		}

		// Clear all notices after displaying global ones
		if ( $has_global ) {
			$this->clear_notices();
		}
	}

	/**
	 * Render a single notice
	 *
	 * @param array $notice Notice data
	 * @return void
	 */
	private function render_notice( array $notice ): void {
		$type        = sanitize_html_class( $notice['type'] ?? 'info' );
		$message     = esc_html( $notice['message'] ?? '' );
		$dismissible = ! empty( $notice['dismissible'] ) ? 'is-dismissible' : '';

		printf(
			'<div class="notice notice-%s %s"><p>%s</p></div>',
			esc_attr( $type ),
			esc_attr( $dismissible ),
			esc_html( $message ) // Already escaped above
		);
	}

	/**
	 * Add notice from exception
	 * Useful for catching and displaying exception messages
	 *
	 * @param \Exception $exception The exception to display
	 * @param bool       $global    Whether to show on all admin pages
	 * @return void
	 */
	public function from_exception( \Exception $exception, bool $global = false ): void {
		$this->error( $exception->getMessage(), $global );
	}
}
