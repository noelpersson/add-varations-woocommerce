<?php
/**
 * WC_Bulk_Variations_Admin class
 * 
 * Handles the admin interface for bulk variations with comprehensive edge case handling
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Bulk_Variations_Admin {
    
    private static $instance = null;
    
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
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_wc_bulk_variations_start_process', array($this, 'handle_start_process'));
        add_action('wp_ajax_wc_bulk_variations_get_progress', array($this, 'handle_get_progress'));
        add_action('wp_ajax_wc_bulk_variations_cancel_process', array($this, 'handle_cancel_process'));
        add_action('wp_ajax_wc_bulk_variations_process_batch', array($this, 'handle_process_batch'));
        add_action('wp_ajax_wc_bulk_variations_get_summary', array($this, 'handle_get_summary'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Bulk Variations', 'wc-bulk-variations'),
            __('Bulk Variations', 'wc-bulk-variations'),
            'manage_woocommerce',
            'wc-bulk-variations',
            array($this, 'render_admin_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'woocommerce_page_wc-bulk-variations') {
            return;
        }
        
        // WooCommerce admin styles
        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION);
        
        // Select2
        wp_enqueue_style('select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION);
        wp_enqueue_script('select2', WC()->plugin_url() . '/assets/js/select2/select2.min.js', array('jquery'), WC_VERSION);
        
        // Our custom styles
        wp_enqueue_style(
            'wc-bulk-variations-admin',
            WC_BULK_VARIATIONS_PLUGIN_URL . 'assets/css/admin.css',
            array('woocommerce_admin_styles', 'select2'),
            WC_BULK_VARIATIONS_VERSION
        );
        
        // Our custom scripts
        wp_enqueue_script(
            'wc-bulk-variations-admin',
            WC_BULK_VARIATIONS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'select2', 'wp-util', 'wp-ajax-response'),
            WC_BULK_VARIATIONS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wc-bulk-variations-admin', 'wc_bulk_variations_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_bulk_variations_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'wc-bulk-variations'),
                'completed' => __('Completed!', 'wc-bulk-variations'),
                'cancelled' => __('Cancelled', 'wc-bulk-variations'),
                'error' => __('Error occurred', 'wc-bulk-variations'),
                'cancel' => __('Cancel', 'wc-bulk-variations'),
                'cancel_confirm' => __('Are you sure you want to cancel the process?', 'wc-bulk-variations'),
                'no_products_selected' => __('Please select at least one product or category.', 'wc-bulk-variations'),
                'no_attribute_name' => __('Please enter an attribute name.', 'wc-bulk-variations'),
                'no_attribute_values' => __('Please enter at least one attribute value.', 'wc-bulk-variations'),
                'attribute' => __('Attribute', 'wc-bulk-variations'),
                'values' => __('Values', 'wc-bulk-variations'),
                'progress' => __('Progress', 'wc-bulk-variations'),
                'select_placeholder' => __('Select...', 'wc-bulk-variations'),
                'view_details' => __('View Details', 'wc-bulk-variations'),
                'hide_details' => __('Hide Details', 'wc-bulk-variations'),
                'product' => __('Product', 'wc-bulk-variations'),
                'status' => __('Status', 'wc-bulk-variations'),
                'message' => __('Message', 'wc-bulk-variations'),
                'created' => __('Created', 'wc-bulk-variations'),
                'skipped' => __('Skipped', 'wc-bulk-variations'),
                'failed' => __('Failed', 'wc-bulk-variations'),
                'success' => __('Success', 'wc-bulk-variations'),
                'no_active_processes' => __('No active processes', 'wc-bulk-variations'),
                'batch_summary' => __('Batch Summary', 'wc-bulk-variations'),
                'total_products' => __('Total Products', 'wc-bulk-variations'),
                'total_created' => __('Variations Created', 'wc-bulk-variations'),
                'total_skipped' => __('Variations Skipped', 'wc-bulk-variations'),
                'success_rate' => __('Success Rate', 'wc-bulk-variations'),
                'duration' => __('Duration', 'wc-bulk-variations'),
                'seconds' => __('seconds', 'wc-bulk-variations'),
            ),
        ));
    }
    
    public function render_admin_page() {
        $products = $this->get_all_products();
        $categories = $this->get_product_categories();
        
        include WC_BULK_VARIATIONS_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    private function get_all_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        );
        
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $products[$post->ID] = $post->post_title;
            }
        }
        
        wp_reset_postdata();
        return $products;
    }
    
    private function get_product_categories() {
        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        );
        
        $terms = get_terms($args);
        $categories = array();
        
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $categories[$term->term_id] = $term->name;
            }
        }
        
        return $categories;
    }
    
    public function handle_start_process() {
        check_ajax_referer('wc_bulk_variations_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wc-bulk-variations')));
        }
        
        $product_selection = isset($_POST['product_selection']) ? sanitize_text_field($_POST['product_selection']) : '';
        $selected_products = isset($_POST['selected_products']) ? array_map('intval', $_POST['selected_products']) : array();
        $selected_categories = isset($_POST['selected_categories']) ? array_map('intval', $_POST['selected_categories']) : array();
        $attribute_name = isset($_POST['attribute_name']) ? sanitize_text_field($_POST['attribute_name']) : '';
        $attribute_values = isset($_POST['attribute_values']) ? sanitize_text_field($_POST['attribute_values']) : '';
        
        // Validate inputs
        if (empty($attribute_name)) {
            wp_send_json_error(array('message' => __('Please enter an attribute name.', 'wc-bulk-variations')));
        }
        
        if (empty($attribute_values)) {
            wp_send_json_error(array('message' => __('Please enter at least one attribute value.', 'wc-bulk-variations')));
        }
        
        // Parse attribute values (comma separated)
        $values = array_map('trim', explode(',', $attribute_values));
        $values = array_filter($values);
        
        if (empty($values)) {
            wp_send_json_error(array('message' => __('Please enter at least one attribute value.', 'wc-bulk-variations')));
        }
        
        // Get product IDs based on selection
        $product_ids = $this->get_product_ids_from_selection($product_selection, $selected_products, $selected_categories);
        
        if (empty($product_ids)) {
            wp_send_json_error(array('message' => __('No products found matching your criteria.', 'wc-bulk-variations')));
        }
        
        // Start background process
        $processor = WC_Bulk_Variations_Plugin::get_instance()->get_background_processor();
        $batch_key = $processor->start_process($product_ids, $attribute_name, $values);
        
        if (!$batch_key) {
            wp_send_json_error(array('message' => __('Failed to start process. Please try again.', 'wc-bulk-variations')));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Started processing %d products', 'wc-bulk-variations'), count($product_ids)),
            'batch_key' => $batch_key,
            'total_products' => count($product_ids),
        ));
    }
    
    public function handle_get_progress() {
        check_ajax_referer('wc_bulk_variations_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wc-bulk-variations')));
        }
        
        $batch_key = isset($_POST['batch_key']) ? sanitize_text_field($_POST['batch_key']) : '';
        $processor = WC_Bulk_Variations_Plugin::get_instance()->get_background_processor();
        $progress = $processor->get_progress($batch_key);
        
        wp_send_json_success($progress);
    }
    
    public function handle_cancel_process() {
        check_ajax_referer('wc_bulk_variations_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wc-bulk-variations')));
        }
        
        $batch_key = isset($_POST['batch_key']) ? sanitize_text_field($_POST['batch_key']) : '';
        $processor = WC_Bulk_Variations_Plugin::get_instance()->get_background_processor();
        $result = $processor->cancel_process($batch_key);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Process cancelled', 'wc-bulk-variations')));
        } else {
            wp_send_json_error(array('message' => __('Failed to cancel process', 'wc-bulk-variations')));
        }
    }
    
    public function handle_process_batch() {
        check_ajax_referer('wc_bulk_variations_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wc-bulk-variations')));
        }
        
        $batch_key = isset($_POST['batch_key']) ? sanitize_text_field($_POST['batch_key']) : '';
        $processor = WC_Bulk_Variations_Plugin::get_instance()->get_background_processor();
        
        // Process the next batch
        $processor->process_next_batch($batch_key);
        
        // Return updated progress
        $progress = $processor->get_progress($batch_key);
        
        wp_send_json_success($progress);
    }
    
    public function handle_get_summary() {
        check_ajax_referer('wc_bulk_variations_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wc-bulk-variations')));
        }
        
        $batch_key = isset($_POST['batch_key']) ? sanitize_text_field($_POST['batch_key']) : '';
        $processor = WC_Bulk_Variations_Plugin::get_instance()->get_background_processor();
        $summary = $processor->get_batch_summary($batch_key);
        
        if ($summary) {
            wp_send_json_success($summary);
        } else {
            wp_send_json_error(array('message' => __('Batch not found', 'wc-bulk-variations')));
        }
    }
    
    private function get_product_ids_from_selection($selection_type, $selected_products, $selected_categories) {
        $product_ids = array();
        
        switch ($selection_type) {
            case 'all':
                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'fields' => 'ids',
                );
                $query = new WP_Query($args);
                $product_ids = $query->posts;
                wp_reset_postdata();
                break;
                
            case 'products':
                if (!empty($selected_products)) {
                    $product_ids = $selected_products;
                }
                break;
                
            case 'categories':
                if (!empty($selected_categories)) {
                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'fields' => 'ids',
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'product_cat',
                                'field' => 'term_id',
                                'terms' => $selected_categories,
                            ),
                        ),
                    );
                    $query = new WP_Query($args);
                    $product_ids = $query->posts;
                    wp_reset_postdata();
                }
                break;
        }
        
        // Filter out invalid IDs and duplicates
        return array_unique(array_filter(array_map('intval', $product_ids)));
    }
    
    public function display_admin_notices() {
        $screen = get_current_screen();
        
        if ($screen && $screen->id === 'woocommerce_page_wc-bulk-variations') {
            $processor = WC_Bulk_Variations_Plugin::get_instance()->get_background_processor();
            $completed = $processor->get_completed_batches();
            
            if (!empty($completed)) {
                foreach ($completed as $batch) {
                    $summary = $processor->get_batch_summary($batch['key']);
                    if ($summary) {
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p>' . sprintf(
                            __('Bulk variation process completed! %d of %d products processed successfully.', 'wc-bulk-variations'),
                            $summary['processed'],
                            $summary['total_products']
                        ) . '</p>';
                        
                        if ($summary['total_created'] > 0) {
                            echo '<p>' . sprintf(
                                _n('%d variation created.', '%d variations created.', $summary['total_created'], 'wc-bulk-variations'),
                                $summary['total_created']
                            ) . '</p>';
                        }
                        
                        if ($summary['total_skipped'] > 0) {
                            echo '<p>' . sprintf(
                                _n('%d variation skipped (already existed).', '%d variations skipped (already existed).', $summary['total_skipped'], 'wc-bulk-variations'),
                                $summary['total_skipped']
                            ) . '</p>';
                        }
                        
                        echo '</div>';
                    }
                }
                // Clear completed notices
                $processor->clear_completed_batches();
            }
        }
    }
}
