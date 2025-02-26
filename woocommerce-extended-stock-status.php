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
            // Add custom CSS
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles')); // Add admin styles

            // Handle display on frontend
            add_filter('woocommerce_get_availability', array($this, 'custom_stock_availability'), 10, 2);
            add_filter('woocommerce_is_purchasable', array($this, 'control_purchasability'), 10, 2);

            // Handle display on backend
            add_filter('woocommerce_product_stock_status_options', array($this, 'add_custom_stock_statuses')); // Add custom stock statuses
            add_filter('woocommerce_admin_stock_html', array($this, 'custom_stock_column_html'), 10, 2);

            // Add settings under Products tab
            add_filter('woocommerce_get_sections_products', array($this, 'add_stock_status_section'));
            add_filter('woocommerce_get_settings_products', array($this, 'add_stock_status_settings'), 10, 2);
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

        // New function to enqueue admin styles
        public function enqueue_admin_styles($hook) {
            // Only load on the WooCommerce Products page
            if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'product') {
                wp_enqueue_style(
                    'wc-extended-stock-admin-style',
                    plugin_dir_url(__FILE__) . 'css/admin-stock-status.css',
                    array(),
                    '1.0.0'
                );
            }
        }

        
        /* FRONTEND */

        // Customize availability text on frontend
        public function custom_stock_availability($availability, $product) {
            $stock_status = $product->get_stock_status();
            
            switch ($stock_status) {
                case 'preorder':
                    if (get_option('wc_extended_stock_preorder_enabled', 'yes') === 'yes') {
                        $availability['availability'] = get_option('wc_extended_stock_preorder_text', __('Pre-order Now', 'wc-extended-stock'));
                        $availability['class'] = 'pre-order';
                    }
                    break;
                case 'enquiries':
                    if (get_option('wc_extended_stock_enquiries_enabled', 'yes') === 'yes') {
                        $availability['availability'] = get_option('wc_extended_stock_enquiries_text', __('Enquiries Only', 'wc-extended-stock'));
                        $availability['class'] = 'enquiries-only';
                    }
                    break;
                case 'discontinued':
                    if (get_option('wc_extended_stock_discontinued_enabled', 'yes') === 'yes') {
                        $availability['availability'] = get_option('wc_extended_stock_discontinued_text', __('Discontinued', 'wc-extended-stock'));
                        $availability['class'] = 'discontinued';
                    }
                    break;
            }
            
            return $availability;
        }

        // Add this new method to control purchasability
        public function control_purchasability($purchasable, $product) {
            $stock_status = $product->get_stock_status();
            
            // Make both 'enquiries' and 'discontinued' non-purchasable
            if (in_array($stock_status, array('enquiries', 'discontinued'))) {
                return false; // Hides the entire Add to Cart form
            }

            return $purchasable;
        }


        //* BACKEND */

        // Add new stock status options to dropdown
        public function add_custom_stock_statuses($stock_statuses) {
            if (get_option('wc_extended_stock_preorder_enabled', 'yes') === 'yes') {
                $stock_statuses['preorder'] = __('Pre-order Now', 'wc-extended-stock');
            }
            if (get_option('wc_extended_stock_enquiries_enabled', 'yes') === 'yes') {
                $stock_statuses['enquiries'] = __('Enquiries Only', 'wc-extended-stock');
            }
            if (get_option('wc_extended_stock_discontinued_enabled', 'yes') === 'yes') {
                $stock_statuses['discontinued'] = __('Discontinued', 'wc-extended-stock');
            }
            return $stock_statuses;
        }

        // Added this function to handle the "Stock" column HTML in admin screen
        public function custom_stock_column_html($stock_html, $product) {
            $stock_status = $product->get_stock_status();
            
            switch ($stock_status) {
                case 'preorder':
                    return '<mark class="preorder">' . __('Pre-order Now', 'wc-extended-stock') . '</mark>';
                case 'enquiries':
                    return '<mark class="enquiries">' . __('Enquiries Only', 'wc-extended-stock') . '</mark>';
                case 'discontinued':
                    return '<mark class="discontinued">' . __('Discontinued', 'wc-extended-stock') . '</mark>';
                default:
                    return $stock_html;
            }
        }


        /* SETTINGS */

        public function add_stock_status_section($sections) {
            $sections['stock_status'] = __('Stock Status', 'wc-extended-stock');
            return $sections;
        }

        public function add_stock_status_settings($settings, $current_section) {
            if ($current_section === 'stock_status') {
                $settings = array(
                    array(
                        'title' => __('Extended Stock Status Options', 'wc-extended-stock'),
                        'type' => 'title',
                        'desc' => __('Customize the extended stock status options for your products.', 'wc-extended-stock'),
                        'id' => 'wc_extended_stock_options',
                    ),
                    array(
                        'title' => __('Pre-order Status', 'wc-extended-stock'),
                        'type' => 'checkbox',
                        'desc' => __('Enable Pre-order status', 'wc-extended-stock'),
                        'id' => 'wc_extended_stock_preorder_enabled',
                        'default' => 'yes',
                    ),
                    array(
                        'title' => __('Pre-order Display Text', 'wc-extended-stock'),
                        'type' => 'text',
                        'desc' => __('Text to display on the front-end for Pre-order products', 'wc-extended-stock'),
                        'id' => 'wc_extended_stock_preorder_text',
                        'default' => __('Pre-order Now', 'wc-extended-stock'),
                        'css' => 'width: 300px;',
                    ),
                    array(
                        'title' => __('Enquiries Only Status', 'wc-extended-stock'),
                        'type' => 'checkbox',
                        'desc' => __('Enable Enquiries Only status', 'wc-extended-stock'),
                        'id' => 'wc_extended_stock_enquiries_enabled',
                        'default' => 'yes',
                    ),
                    array(
                        'title' => __('Enquiries Display Text', 'wc-extended-stock'),
                        'type' => 'text',
                        'desc' => __('Text to display on the front-end for Enquiries Only products', 'wc-extended-stock'),
                        'id' => 'wc_extended_stock_enquiries_text',
                        'default' => __('Enquiries Only', 'wc-extended-stock'),
                        'css' => 'width: 300px;',
                    ),
                    array(
                        'title' => __('Discontinued Status', 'wc-extended-stock'),
                        'type' => 'checkbox',
                        'desc' => __('Enable Discontinued status', 'wc-extended-stock'),
                        'id' => 'wc_extended_stock_discontinued_enabled',
                        'default' => 'yes',
                    ),
                    array(
                        'title' => __('Discontinued Display Text', 'wc-extended-stock'),
                        'type' => 'text',
                        'desc' => __('Text to display on the front-end for Discontinued products', 'wc-extended-stock'),
                        'id' => 'wc_extended_stock_discontinued_text',
                        'default' => __('Discontinued', 'wc-extended-stock'),
                        'css' => 'width: 300px;',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'wc_extended_stock_options',
                    ),
                );
            }
            return $settings;
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