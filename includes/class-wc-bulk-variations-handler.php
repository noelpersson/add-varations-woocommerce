<?php
/**
 * WC_Bulk_Variations_Handler class
 * 
 * Handles the actual creation of variations for products
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Bulk_Variations_Handler {
    
    private static $instance = null;
    
    private function __construct() {
        // No direct initialization needed
    }
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create variations for a product
     * 
     * @param int $product_id
     * @param string $attribute_name
     * @param array $attribute_values
     * @return array Result with success status and message
     */
    public function create_variations_for_product($product_id, $attribute_name, $attribute_values) {
        try {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Product #%d not found', 'wc-bulk-variations'), $product_id),
                );
            }
            
            // Convert to variable product if not already
            if ($product->get_type() !== 'variable') {
                $product->set_type('variable');
                $product->save();
                
                // Reload product after type change
                $product = wc_get_product($product_id);
            }
            
            // Check if attribute already exists on the product
            $existing_attributes = $product->get_attributes();
            $attribute_exists = false;
            $attribute_key = sanitize_title($attribute_name);
            
            foreach ($existing_attributes as $attr) {
                if ($attr->get_name() === $attribute_name) {
                    $attribute_exists = true;
                    break;
                }
            }
            
            // Create or update the attribute
            $this->create_product_attribute($product, $attribute_name, $attribute_values, $attribute_exists);
            
            // Save product to ensure attributes are stored
            $product->save();
            
            // Reload product to get updated attribute data
            $product = wc_get_product($product_id);
            
            // Create variations for each value
            $created_count = 0;
            $skipped_count = 0;
            
            foreach ($attribute_values as $value) {
                $value_slug = sanitize_title($value);
                
                // Check if variation already exists
                $existing_variation = $this->get_variation_by_attributes($product_id, array($attribute_key => $value_slug));
                
                if ($existing_variation) {
                    $skipped_count++;
                    continue;
                }
                
                // Create new variation
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $variation->set_attributes(array($attribute_key => $value_slug));
                $variation->set_status('publish');
                
                // Set prices from parent product
                $regular_price = $product->get_regular_price();
                $sale_price = $product->get_sale_price();
                
                $variation->set_regular_price($regular_price);
                $variation->set_price($sale_price ? $sale_price : $regular_price);
                
                // Set stock settings
                $variation->set_stock_quantity(0);
                $variation->set_manage_stock(true);
                $variation->set_stock_status('onbackorder');
                
                $variation_id = $variation->save();
                
                if ($variation_id) {
                    $created_count++;
                    
                    // Update variation meta for compatibility
                    update_post_meta($variation_id, '_price', $sale_price ? $sale_price : $regular_price);
                    update_post_meta($variation_id, '_regular_price', $regular_price);
                }
            }
            
            // Sync product to update price ranges and other data
            $product->save();
            
            // Clear transients
            wc_delete_product_transients($product_id);
            
            $message = sprintf(
                _n(
                    '%d variation created, %d skipped (already existed)',
                    '%d variations created, %d skipped (already existed)',
                    $created_count,
                    'wc-bulk-variations'
                ),
                $created_count,
                $skipped_count
            );
            
            // Log success
            if (class_exists('WC_Logger')) {
                $logger = wc_get_logger();
                $logger->info(
                    sprintf('Bulk Variations: Product #%d - %s', $product_id, $message),
                    array('source' => 'wc-bulk-variations')
                );
            }
            
            return array(
                'success' => true,
                'message' => $message,
                'created' => $created_count,
                'skipped' => $skipped_count,
            );
            
        } catch (Exception $e) {
            // Log the error
            if (class_exists('WC_Logger')) {
                $logger = wc_get_logger();
                $logger->error(
                    sprintf('Bulk Variations Error for product #%d: %s', $product_id, $e->getMessage()),
                    array('source' => 'wc-bulk-variations')
                );
            }
            
            return array(
                'success' => false,
                'message' => sprintf(__('Error: %s', 'wc-bulk-variations'), $e->getMessage()),
            );
        }
    }
    
    /**
     * Create or update product attribute
     * 
     * @param WC_Product $product
     * @param string $attribute_name
     * @param array $attribute_values
     * @param bool $exists
     */
    private function create_product_attribute($product, $attribute_name, $attribute_values, $exists) {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id(0); // 0 for local attributes
        $attribute->set_name($attribute_name);
        $attribute->set_options($attribute_values);
        $attribute->set_position(1);
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        
        if ($exists) {
            // Update existing attribute
            $existing_attributes = $product->get_attributes();
            foreach ($existing_attributes as $key => $attr) {
                if ($attr->get_name() === $attribute_name) {
                    $existing_attributes[$key] = $attribute;
                    break;
                }
            }
            $product->set_attributes($existing_attributes);
        } else {
            // Add new attribute
            $product->set_attributes(array_merge($product->get_attributes(), array($attribute)));
        }
    }
    
    /**
     * Get variation by attributes
     * 
     * @param int $product_id
     * @param array $attributes
     * @return WC_Product_Variation|null
     */
    private function get_variation_by_attributes($product_id, $attributes) {
        $args = array(
            'post_type' => 'product_variation',
            'post_parent' => $product_id,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(),
        );
        
        foreach ($attributes as $key => $value) {
            $args['meta_query'][] = array(
                'key' => 'attribute_' . $key,
                'value' => $value,
                'compare' => '='
            );
        }
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $variation_id = $query->post->ID;
            return wc_get_product($variation_id);
        }
        
        wp_reset_postdata();
        return null;
    }
}
