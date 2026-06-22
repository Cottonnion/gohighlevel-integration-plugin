<?php
/**
 * Plugin Name:       Syncly for GoHighLevel
 * Plugin URI:        https://highlevelsync.com/
 * Description:       WordPress integration plugin that connects WordPress, WooCommerce, BuddyBoss, and LearnDash with GoHighLevel CRM for real-time two-way sync and automation.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            LabGenz Team
 * Author URI:        https://labgenz.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       syncly
 * Domain Path:       /languages
 *
 * @package Syncly_GHL
 */

declare(strict_types=1);

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'GHL_CRM_VERSION', '1.4.0' );
define( 'GHL_CRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'GHL_CRM_URL', plugin_dir_url( __FILE__ ) );
define( 'GHL_CRM_BASENAME', plugin_basename( __FILE__ ) );
define( 'GHL_CRM_TEXTDOMAIN', 'syncly' );

if ( ! defined( 'GHLBRIDGE_LOG' ) ) {
	define( 'GHLBRIDGE_LOG', true );
}

// Require Composer autoloader
if ( file_exists( GHL_CRM_PATH . 'vendor/autoload.php' ) ) {
	require_once GHL_CRM_PATH . 'vendor/autoload.php';
} else {
	// Show admin notice if autoloader is missing
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p>
				<?php
				esc_html_e(
					'Syncly for GoHighLevel: Composer autoloader not found. Please run "composer install" in the plugin directory.',
					'syncly'
				);
				?>
			</p>
		</div>
			<?php
		}
	);
	return;
}

// Initialize Action Scheduler
if ( file_exists( GHL_CRM_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once GHL_CRM_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// Initialize the plugin
function syncly_init() {
	return \GHL_CRM\Core\Loader::get_instance();
}

// Start the plugin
syncly_init();

require_once GHL_CRM_PATH . 'functions.php';