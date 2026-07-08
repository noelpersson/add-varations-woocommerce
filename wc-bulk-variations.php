<?php
/**
 * Plugin Name: WooCommerce Bulk Variations
 * Plugin URI: https://github.com/noelpersson/add-varations-woocommerce
 * Description: Bulk create variations for WooCommerce products with background processing
 * Version: 1.1.0
 * Author: Noel Persson
 * Author URI: https://github.com/noelpersson
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-bulk-variations
 * Domain Path: /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 8.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load class files explicitly (replaces autoloader for reliability)
require_once WC_BULK_VARIATIONS_PLUGIN_DIR . 'includes/class-wc-bulk-variations-admin.php';
require_once WC_BULK_VARIATIONS_PLUGIN_DIR . 'includes/class-wc-bulk-variations-handler.php';
require_once WC_BULK_VARIATIONS_PLUGIN_DIR . 'includes/class-wc-bulk-variations-simple-background.php';

// Define plugin constants
define('WC_BULK_VARIATIONS_VERSION', '1.1.0');
define('WC_BULK_VARIATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_BULK_VARIATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Uninstall hook
register_uninstall_hook(__FILE__, array('WC_Bulk_Variations_Plugin', 'uninstall'));

// Initialize the plugin on plugins_loaded (WooCommerce is always loaded by then)
add_action('plugins_loaded', function () {
    WC_Bulk_Variations_Plugin::get_instance();
});

/**
 * Add plugin action link to go directly to the plugin's admin page
 * Works regardless of the plugin folder name
 */
add_filter('plugin_action_links', function ($links, $file) {
    // Get our plugin file path
    $our_plugin = plugin_basename(__FILE__);
    
    // Check if this is our plugin (compare basenames to handle different folder names)
    if (basename($file) === basename($our_plugin)) {
        $admin_url = admin_url('admin.php?page=wc-bulk-variations');
        $action_link = '<a href="' . esc_url($admin_url) . '">' . esc_html__('Bulk Variations', 'wc-bulk-variations') . '</a>';
        array_unshift($links, $action_link);
    }
    return $links;
}, 10, 2);

/**
 * Main plugin class
 */
final class WC_Bulk_Variations_Plugin {
    
    private static $instance = null;
    
    private $admin;
    private $background_processor;
    private $variation_handler;
    
    private function __construct() {
        $this->init_hooks();
    }
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function init_hooks() {
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));
        
        // Initialize components on init (when is_admin() is reliable)
        add_action('init', array($this, 'init_components'));
        
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
    }
    
    public function add_cron_interval($schedules) {
        $schedules['wc_bulk_variations_interval'] = array(
            'interval' => 15, // 15 seconds
            'display' => __('WooCommerce Bulk Variations Interval', 'wc-bulk-variations')
        );
        return $schedules;
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'wc-bulk-variations',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    public function init_components() {
        if (is_admin()) {
            $this->admin = WC_Bulk_Variations_Admin::get_instance();
        }
        
        // Use simple background processor by default
        $this->background_processor = WC_Bulk_Variations_Simple_Background::get_instance();
        $this->variation_handler = WC_Bulk_Variations_Handler::get_instance();
    }
    
    public function get_admin() {
        return $this->admin;
    }
    
    public function get_background_processor() {
        return $this->background_processor;
    }
    
    public function get_variation_handler() {
        return $this->variation_handler;
    }
    
    /**
     * Uninstall plugin
     */
    public static function uninstall() {
        // Clear transients
        delete_transient('wc_bulk_variations_progress');
        delete_transient('wc_bulk_variations_completed');
        
        // Clear cron events
        wp_clear_scheduled_hook('wc_bulk_variations_process');
    }
}
