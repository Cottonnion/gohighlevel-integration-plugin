<?php
declare(strict_types=1);

namespace Syncly\Admin;

use Syncly\Admin\Columns\UserColumns;
use Syncly\Admin\Profile\UserProfileFields;
use Syncly\Admin\Users\UserBulkActions;
use Syncly\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI
 *
 * Coordinates all admin UI customizations and enhancements
 *
 * @package    Syncly
 * @subpackage Admin
 */
class AdminUI {

	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

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
		$this->settings_manager = SettingsManager::get_instance();
		$this->init_components();
	}

	/**
	 * Initialize admin UI components
	 *
	 * @return void
	 */
	private function init_components(): void {
		// Only load in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Check if connection is verified before loading admin UI features
		if ( ! $this->settings_manager->is_connection_verified() ) {
			return;
		}

		// Initialize user columns (adds GHL Contact ID column to users table)
		UserColumns::init();

		// Initialize user profile fields (adds GHL section to user edit pages)
		UserProfileFields::init();

		// Initialize user bulk actions (adds GHL tag assignment bulk actions)
		UserBulkActions::init();
		// Future: Initialize other admin components
		// PostColumns::init();
		// OrderColumns::init();
	}

	/**
	 * Initialize (called by Loader)
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}
