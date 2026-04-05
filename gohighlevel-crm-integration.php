<?php
/**
 * Plugin Name:       GoHighLevel CRM Integration
 * Plugin URI:        https://labgenz.com/
 * Description:       Integrate WordPress + WooCommerce + BuddyBoss + LearnDash with GoHighLevel CRM for seamless two-way sync
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Yahya Eddaqqaq
 * Author URI:        https://labgenz.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ghl-crm-integration
 * Domain Path:       /languages
 *
 * @package GHL_CRM_Integration
 */

declare(strict_types=1);

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'GHL_CRM_VERSION', '1.2.0' );
define( 'GHL_CRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'GHL_CRM_URL', plugin_dir_url( __FILE__ ) );
define( 'GHL_CRM_BASENAME', plugin_basename( __FILE__ ) );
define( 'GHL_CRM_TEXTDOMAIN', 'ghl-crm-integration' );

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'ghl-crm-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

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
					'GoHighLevel CRM Integration: Composer autoloader not found. Please run "composer install" in the plugin directory.',
					'ghl-crm-integration'
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
function ghl_crm_init() {
	return \GHL_CRM\Core\Loader::get_instance();
}

// Start the plugin
ghl_crm_init();

require_once GHL_CRM_PATH . 'functions.php';