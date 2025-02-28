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
            add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'custom_add_to_cart_text'), 10, 2); // Add filter for Add to Cart button text
            //add_filter('woocommerce_product_add_to_cart_text', array($this, 'custom_add_to_cart_text'), 10, 2); // Add "Pre-order" on archive/shop pages
            add_filter('woocommerce_loop_add_to_cart_link', array($this, 'customize_add_to_cart_button_html'), 10, 3);
            add_filter('woocommerce_get_item_data', array($this, 'add_availability_to_cart'), 10, 2); // Add availability to cart page
            //add_action('pre_get_posts', array($this, 'move_discontinued_products_to_end')); // Hook into pre_get_posts to modify the main query
            //add_action('elementor/query/archive_products', array($this, 'move_discontinued_products_to_end'));
            add_filter('posts_join', array($this, 'join_postmeta'), 10, 2);
            add_filter('posts_orderby', array($this, 'custom_orderby'), 10, 2);

            // Handle display on backend
            add_filter('woocommerce_product_stock_status_options', array($this, 'add_custom_stock_statuses')); // Add custom stock statuses
            add_filter('woocommerce_admin_stock_html', array($this, 'custom_stock_column_html'), 10, 2);

            // Add settings under Products tab
            add_filter('woocommerce_get_sections_products', array($this, 'add_stock_status_section'));
            add_filter('woocommerce_get_settings_products', array($this, 'add_stock_status_settings'), 10, 2);

            // Add sync to WooCommerce Tools
            add_filter('woocommerce_debug_tools', array($this, 'add_sync_tool')); // Hook into the WooCommerce tools filter
        }

        // Add basic styling
        public function enqueue_styles() {
            if (is_product() || is_cart()) {
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
            // Note: Add JS enqueue here later for popup
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

        public function customize_add_to_cart_button_html($link, $product, $args) {
            // Check the product's stock status
            $stock_status = $product->get_stock_status();
            
            if ($stock_status === 'preorder' && get_option('wc_extended_stock_preorder_enabled', 'yes') === 'yes') {
                return '<a href="' . esc_url($product->add_to_cart_url()) . '" class="button pre-order">Pre-order</a>';
            }

            if ($stock_status === 'enquiries' && get_option('wc_extended_stock_enquiries_enabled', 'yes') === 'yes') {
                return '<a href="' . esc_url($product->add_to_cart_url()) . '" class="button enquiries-only">Enquiries</a>';
            }

            if ($stock_status === 'discontinued' && get_option('wc_extended_stock_discontinued_enabled', 'yes') === 'yes') {
                return '<button class="button discontinued" disabled>Discontinued</button>';
            }
            
            // Return the default button HTML for other products
            return $link;
        }
        
        


        public function custom_add_to_cart_text($text, $product) {
            $stock_status = $product->get_stock_status();

            if ($stock_status === 'preorder' && get_option('wc_extended_stock_preorder_enabled', 'yes') === 'yes') {
                return __('Pre-order', 'wc-extended-stock');
            }
            
            if ($stock_status === 'enquiries' && get_option('wc_extended_stock_enquiries_enabled', 'yes') === 'yes') {
                return __('Contact Us', 'wc-extended-stock');
            }

            if ($stock_status === 'discontinued' && get_option('wc_extended_stock_discontinued_enabled', 'yes') === 'yes') {
                return __('Discontinued', 'wc-extended-stock');
            }

            return $text; // Default "Add to cart" for other statuses
        }




        public function add_availability_to_cart($item_data, $cart_item) {
            $product = $cart_item['data'];
            $stock_status = $product->get_stock_status();
            
            if ($stock_status === 'preorder' && get_option('wc_extended_stock_preorder_enabled', 'yes') === 'yes') {
                $item_data[] = array(
                    'key'     => __('Availability', 'wc-extended-stock'),
                    'value'   => get_option('wc_extended_stock_preorder_text', __('Pre-order', 'wc-extended-stock')),
                    'display' => '<span class="pre-order">' . esc_html(get_option('wc_extended_stock_preorder_text', __('Pre-order', 'wc-extended-stock'))) . '</span>',
                );
            }
            
            return $item_data;
        }


        public function join_postmeta($join, $query) {
            if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
                global $wpdb;
                $join .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_stock_status'";
            }
            return $join;
        }
    
        public function custom_orderby($orderby, $query) {
            if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
                global $wpdb;
                $orderby = "CASE WHEN {$wpdb->postmeta}.meta_value = 'discontinued' THEN 1 ELSE 0 END ASC, " . $orderby;
            }
            return $orderby;
        }


        public function move_discontinued_products_to_end($query) {
            if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
                $discontinued_ids = $this->get_discontinued_product_ids();
                if (!empty($discontinued_ids)) {
                    $query->set('post__not_in', $discontinued_ids);
                    add_action('woocommerce_product_query', function($q) use ($discontinued_ids) {
                        $q->set('post__in', $discontinued_ids);
                        $q->set('orderby', 'date');
                        $q->set('order', 'DESC');
                    }, 20);
                }
            }
        }
    
        private function get_discontinued_product_ids() {
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => array(
                    array(
                        'key' => '_stock_status',
                        'value' => 'discontinued',
                    ),
                ),
            );
            return get_posts($args);
        }


        //* BACKEND */

        // Add new stock status options to dropdown
        public function add_custom_stock_statuses($stock_statuses) {
            if (get_option('wc_extended_stock_preorder_enabled', 'yes') === 'yes') {
                $stock_statuses['preorder'] = __('Pre-order', 'wc-extended-stock');
            }
            if (get_option('wc_extended_stock_enquiries_enabled', 'yes') === 'yes') {
                $stock_statuses['enquiries'] = __('Enquiries', 'wc-extended-stock');
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
                    return '<mark class="preorder">' . __('Pre-order', 'wc-extended-stock') . '</mark>';
                case 'enquiries':
                    return '<mark class="enquiries">' . __('Enquiries', 'wc-extended-stock') . '</mark>';
                case 'discontinued':
                    return '<mark class="discontinued">' . __('Discontinued', 'wc-extended-stock') . '</mark>';
                default:
                    return $stock_html;
            }
        }


        // Add the Sync Stock Status tool to the Tools section
        public function add_sync_tool($tools) {
            $tools['sync_stock_status'] = array(
                'name'     => __('Sync Stock Status', 'wc-extended-stock'),
                'button'   => __('Run Sync', 'wc-extended-stock'),
                'desc'     => __('Sync stock statuses based on the order_status meta for all products.', 'wc-extended-stock'),
                'callback' => array($this, 'sync_stock_status_tool'),
            );
            return $tools;
        }

        // Callback function to execute the sync when the button is clicked
        public function sync_stock_status_tool() {
            // Check user permissions
            if (!current_user_can('manage_woocommerce')) {
                return __('Insufficient permissions to sync stock statuses.', 'wc-extended-stock');
            }

            // Access the WordPress database
            global $wpdb;
            $postmeta_table = $wpdb->postmeta;

            // Fetch all products with the order_status meta
            $results = $wpdb->get_results("
                SELECT post_id, meta_value
                FROM $postmeta_table
                WHERE meta_key = 'order_status'
            ");

            // Handle database errors
            if ($results === false) {
                return __('Database error while syncing stock statuses.', 'wc-extended-stock');
            }

            // Process each product and update stock status
            $updated = 0;
            foreach ($results as $result) {
                $post_id = $result->post_id;
                $order_status = $result->meta_value;

                switch ($order_status) {
                    case '1':
                        update_post_meta($post_id, '_stock_status', 'preorder');
                        $updated++;
                        break;
                    case '2':
                        update_post_meta($post_id, '_stock_status', 'enquiries');
                        $updated++;
                        break;
                    case '3':
                        // No action for this status
                        break;
                    case '4':
                        update_post_meta($post_id, '_stock_status', 'discontinued');
                        $updated++;
                        break;
                }
            }

            // Return feedback
            return sprintf(__('Stock statuses synced successfully. Updated %d products.', 'wc-extended-stock'), $updated);
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