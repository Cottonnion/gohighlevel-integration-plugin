<?php
declare(strict_types=1);

namespace GHL_CRM\Admin;

use GHL_CRM\Admin\Columns\UserColumns;
use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI
 *
 * Coordinates all admin UI customizations and enhancements
 *
 * @package    GHL_CRM_Integration
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

		// Future: Initialize other admin components
		// UserProfileFields::init();
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
