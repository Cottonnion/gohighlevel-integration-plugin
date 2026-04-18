<?php
/**
 * PHPUnit bootstrap for GHL CRM Integration tests.
 *
 * Loads Composer autoloader and Brain\Monkey, defines WordPress
 * constants/stubs so plugin classes can be instantiated without
 * a running WordPress installation.
 *
 * @package GHL_CRM_Integration\Tests
 */

declare(strict_types=1);

// Composer autoloader (loads plugin classes + Brain\Monkey + PHPUnit).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants the plugin expects.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'GHL_CRM_VERSION' ) ) {
	define( 'GHL_CRM_VERSION', '1.1.1' );
}
if ( ! defined( 'GHL_CRM_PATH' ) ) {
	define( 'GHL_CRM_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'GHL_CRM_URL' ) ) {
	define( 'GHL_CRM_URL', 'https://example.com/wp-content/plugins/ghl-crm-integration/' );
}
if ( ! defined( 'GHL_CRM_BASENAME' ) ) {
	define( 'GHL_CRM_BASENAME', 'ghl-crm-integration/gohighlevel-crm-integration.php' );
}
if ( ! defined( 'GHL_CRM_TEXTDOMAIN' ) ) {
	define( 'GHL_CRM_TEXTDOMAIN', 'ghl-crm-integration' );
}

// WordPress time constants the plugin may reference.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// Minimal WooCommerce stub so class_exists( 'WooCommerce' ) returns true
// without needing to mock a PHP internal function via Patchwork.
if ( ! class_exists( 'WooCommerce' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	class WooCommerce {}
}

// Stub ActionScheduler so class_exists( 'ActionScheduler' ) and
// ActionScheduler::is_initialized() work without the real library.
if ( ! class_exists( 'ActionScheduler' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	class ActionScheduler {
		public static function is_initialized(): bool {
			return true;
		}
	}
}
