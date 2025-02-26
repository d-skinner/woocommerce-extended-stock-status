<?php
/*
 * Plugin Name: WooCommerce Extended Stock Status
 * Plugin URI: https://yourwebsite.com/
 * Description: Extends WooCommerce stock status with Pre-order, Enquiries, and Discontinued options
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com/
 * Text Domain: wc-extended-stock
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 7.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    
    class WC_Extended_Stock_Status {
        
        public function __construct() {
            // Add custom stock statuses
            add_filter('woocommerce_product_stock_status_options', array($this, 'add_custom_stock_statuses'));
            // Handle display on frontend
            add_filter('woocommerce_get_availability', array($this, 'custom_stock_availability'), 10, 2);
            // Add custom CSS
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        }
        
        // Add new stock status options to dropdown
        public function add_custom_stock_statuses($stock_statuses) {
            $stock_statuses['preorder'] = __('Pre-order', 'wc-extended-stock');
            $stock_statuses['enquiries'] = __('Enquiries Only', 'wc-extended-stock');
            $stock_statuses['discontinued'] = __('Discontinued', 'wc-extended-stock');
            return $stock_statuses;
        }
        
        // Customize availability text on frontend
        public function custom_stock_availability($availability, $product) {
            $stock_status = $product->get_stock_status();
            
            switch ($stock_status) {
                case 'preorder':
                    $availability['availability'] = __('Available for Pre-order', 'wc-extended-stock');
                    $availability['class'] = 'pre-order';
                    break;
                case 'enquiries':
                    $availability['availability'] = __('Enquiries Only - Contact Us', 'wc-extended-stock');
                    $availability['class'] = 'enquiries-only';
                    break;
                case 'discontinued':
                    $availability['availability'] = __('Discontinued Product', 'wc-extended-stock');
                    $availability['class'] = 'discontinued';
                    break;
            }
            
            return $availability;
        }
        
        // Add basic styling
        public function enqueue_styles() {
            if (is_product()) {
                wp_enqueue_style(
                    'wc-extended-stock-style',
                    plugin_dir_url(__FILE__) . 'css/stock-status.css',
                    array(),
                    '1.0.0'
                );
            }
        }
    }
    
    // Initialize the plugin
    new WC_Extended_Stock_Status();
}

// Activation hook
register_activation_hook(__FILE__, 'wc_extended_stock_activation');
function wc_extended_stock_activation() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and active.');
    }
}