<?php
/**
 * WC_Bulk_Variations_Handler class
 * 
 * Handles the actual creation of variations for products with comprehensive edge case handling
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
     * Create variations for a product with comprehensive edge case handling
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
            
            // Log start
            $this->log_message(sprintf('Starting processing for product #%d (%s)', $product_id, $product->get_name()));
            
            // Handle different product types
            $result = $this->handle_product_type($product, $attribute_name, $attribute_values);
            
            if (!$result['success']) {
                return $result;
            }
            
            // Get updated product after type handling
            $product = wc_get_product($product_id);
            
            // Check if attribute already exists on the product
            $attribute_info = $this->check_existing_attribute($product, $attribute_name, $attribute_values);
            
            // Create or update the attribute
            $this->create_or_update_attribute($product, $attribute_name, $attribute_values, $attribute_info['exists']);
            
            // Save product to ensure attributes are stored
            $product->save();
            
            // Reload product to get updated attribute data
            $product = wc_get_product($product_id);
            
            // Create variations for each value
            $result = $this->create_variations($product, $attribute_name, $attribute_values, $attribute_info['key']);
            
            // Sync product to update price ranges and other data
            $product->save();
            
            // Clear transients
            wc_delete_product_transients($product_id);
            
            $this->log_message(sprintf('Completed processing for product #%d: %s', $product_id, $result['message']));
            
            return $result;
            
        } catch (Exception $e) {
            $this->log_error(sprintf('Error for product #%d: %s', $product_id, $e->getMessage()));
            
            return array(
                'success' => false,
                'message' => sprintf(__('Error: %s', 'wc-bulk-variations'), $e->getMessage()),
            );
        }
    }
    
    /**
     * Handle different product types
     * 
     * @param WC_Product $product
     * @param string $attribute_name
     * @param array $attribute_values
     * @return array
     */
    private function handle_product_type($product, $attribute_name, $attribute_values) {
        $product_type = $product->get_type();
        
        // Handle simple products
        if ($product_type === 'simple') {
            return $this->convert_to_variable($product);
        }
        
        // Handle variable products
        if ($product_type === 'variable') {
            return array('success' => true, 'message' => __('Product is already variable', 'wc-bulk-variations'));
        }
        
        // Handle grouped products
        if ($product_type === 'grouped') {
            $this->log_message(sprintf('Skipping grouped product #%d - not supported', $product->get_id()));
            return array(
                'success' => false,
                'message' => __('Grouped products are not supported for variations', 'wc-bulk-variations')
            );
        }
        
        // Handle external/affiliate products
        if ($product_type === 'external' || $product_type === 'affiliate') {
            $this->log_message(sprintf('Skipping %s product #%d - not supported', $product_type, $product->get_id()));
            return array(
                'success' => false,
                'message' => sprintf(__('%s products are not supported for variations', 'wc-bulk-variations'), ucfirst($product_type))
            );
        }
        
        // Handle subscription products
        if ($product_type === 'subscription' || $product_type === 'variable-subscription') {
            $this->log_message(sprintf('Skipping subscription product #%d - use subscription variation methods', $product->get_id()));
            return array(
                'success' => false,
                'message' => __('Subscription products require special handling', 'wc-bulk-variations')
            );
        }
        
        // Handle any other custom product types
        return array(
            'success' => true,
            'message' => sprintf(__('Product type %s handled', 'wc-bulk-variations'), $product_type)
        );
    }
    
    /**
     * Convert simple product to variable
     * 
     * @param WC_Product $product
     * @return array
     */
    private function convert_to_variable($product) {
        $product_id = $product->get_id();
        
        // Store original data before conversion
        $original_price = $product->get_regular_price();
        $original_sale_price = $product->get_sale_price();
        $original_stock = $product->get_stock_quantity();
        $original_manage_stock = $product->get_manage_stock();
        $original_stock_status = $product->get_stock_status();
        
        try {
            // Convert to variable
            $product->set_type('variable');
            
            // Preserve original data as default for variations
            $product->set_regular_price($original_price);
            if ($original_sale_price) {
                $product->set_sale_price($original_sale_price);
            }
            
            // Save the product
            $product->save();
            
            $this->log_message(sprintf('Converted product #%d from simple to variable', $product_id));
            
            return array(
                'success' => true,
                'message' => __('Converted from simple to variable product', 'wc-bulk-variations')
            );
            
        } catch (Exception $e) {
            $this->log_error(sprintf('Failed to convert product #%d to variable: %s', $product_id, $e->getMessage()));
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to convert to variable: %s', 'wc-bulk-variations'), $e->getMessage())
            );
        }
    }
    
    /**
     * Check if attribute already exists on the product
     * 
     * @param WC_Product $product
     * @param string $attribute_name
     * @param array $attribute_values
     * @return array
     */
    private function check_existing_attribute($product, $attribute_name, $attribute_values) {
        $attribute_key = sanitize_title($attribute_name);
        $existing_attributes = $product->get_attributes();
        $attribute_exists = false;
        $existing_values = array();
        
        foreach ($existing_attributes as $attr) {
            if ($attr->get_name() === $attribute_name) {
                $attribute_exists = true;
                $existing_values = $attr->get_options();
                break;
            }
        }
        
        return array(
            'exists' => $attribute_exists,
            'key' => $attribute_key,
            'existing_values' => $existing_values,
        );
    }
    
    /**
     * Create or update product attribute
     * 
     * @param WC_Product $product
     * @param string $attribute_name
     * @param array $attribute_values
     * @param bool $exists
     */
    private function create_or_update_attribute($product, $attribute_name, $attribute_values, $exists) {
        $attribute = new WC_Product_Attribute();
        $attribute->set_id(0); // 0 for local attributes
        $attribute->set_name($attribute_name);
        $attribute->set_options($attribute_values);
        $attribute->set_position($this->get_next_attribute_position($product));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        
        if ($exists) {
            // Update existing attribute - merge values
            $existing_attributes = $product->get_attributes();
            foreach ($existing_attributes as $key => $attr) {
                if ($attr->get_name() === $attribute_name) {
                    // Merge existing values with new values
                    $merged_values = array_unique(array_merge($attr->get_options(), $attribute_values));
                    $attribute->set_options($merged_values);
                    $existing_attributes[$key] = $attribute;
                    break;
                }
            }
            $product->set_attributes($existing_attributes);
            $this->log_message(sprintf('Updated existing attribute "%s"', $attribute_name));
        } else {
            // Add new attribute
            $product->set_attributes(array_merge($product->get_attributes(), array($attribute)));
            $this->log_message(sprintf('Added new attribute "%s"', $attribute_name));
        }
    }
    
    /**
     * Get next attribute position
     * 
     * @param WC_Product $product
     * @return int
     */
    private function get_next_attribute_position($product) {
        $existing_attributes = $product->get_attributes();
        $positions = array();
        
        foreach ($existing_attributes as $attr) {
            $positions[] = $attr->get_position();
        }
        
        return empty($positions) ? 0 : max($positions) + 1;
    }
    
    /**
     * Create variations for each value
     * 
     * @param WC_Product $product
     * @param string $attribute_name
     * @param array $attribute_values
     * @param string $attribute_key
     * @return array
     */
    private function create_variations($product, $attribute_name, $attribute_values, $attribute_key) {
        $product_id = $product->get_id();
        $created_count = 0;
        $skipped_count = 0;
        $updated_count = 0;
        
        // Get existing variations for this product
        $existing_variations = $product->get_available_variations();
        $existing_variation_attributes = array();
        
        foreach ($existing_variations as $variation) {
            if (isset($variation['attributes']['attribute_' . $attribute_key])) {
                $existing_variation_attributes[$variation['attributes']['attribute_' . $attribute_key]] = $variation['variation_id'];
            }
        }
        
        // Get parent product prices for new variations
        $parent_regular_price = $product->get_regular_price();
        $parent_sale_price = $product->get_sale_price();
        $parent_price = $parent_sale_price ? $parent_sale_price : $parent_regular_price;
        
        foreach ($attribute_values as $value) {
            $value_slug = sanitize_title($value);
            
            // Check if variation already exists for this attribute value
            if (isset($existing_variation_attributes[$value_slug])) {
                $skipped_count++;
                $this->log_message(sprintf('Skipped existing variation with %s=%s for product #%d', $attribute_key, $value_slug, $product_id));
                continue;
            }
            
            // Create new variation
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_attributes(array($attribute_key => $value_slug));
            $variation->set_status('publish');
            
            // Set prices from parent product
            $variation->set_regular_price($parent_regular_price);
            $variation->set_price($parent_price);
            
            // Set stock settings
            $variation->set_stock_quantity(0);
            $variation->set_manage_stock(true);
            $variation->set_stock_status('onbackorder');
            
            // Set other default settings
            $variation->set_weight($product->get_weight() ? $product->get_weight() : '');
            $variation->set_length($product->get_length() ? $product->get_length() : '');
            $variation->set_width($product->get_width() ? $product->get_width() : '');
            $variation->set_height($product->get_height() ? $product->get_height() : '');
            $variation->set_tax_class($product->get_tax_class());
            $variation->set_tax_status($product->get_tax_status());
            $variation->set_shipping_class($product->get_shipping_class());
            $variation->set_shipping_class_id($product->get_shipping_class_id());
            
            // Set SKU if parent has one
            $parent_sku = $product->get_sku();
            if ($parent_sku) {
                $variation->set_sku($parent_sku . '-' . $value_slug);
            }
            
            // Set image if parent has one
            $parent_image_id = $product->get_image_id();
            if ($parent_image_id) {
                $variation->set_image_id($parent_image_id);
            }
            
            $variation_id = $variation->save();
            
            if ($variation_id) {
                $created_count++;
                
                // Update variation meta for compatibility
                update_post_meta($variation_id, '_price', $parent_price);
                update_post_meta($variation_id, '_regular_price', $parent_regular_price);
                if ($parent_sale_price) {
                    update_post_meta($variation_id, '_sale_price', $parent_sale_price);
                }
                
                $this->log_message(sprintf('Created variation #%d for product #%d with %s=%s', $variation_id, $product_id, $attribute_key, $value_slug));
            } else {
                $this->log_error(sprintf('Failed to create variation for product #%d with %s=%s', $product_id, $attribute_key, $value_slug));
            }
        }
        
        $message = $this->get_variation_message($created_count, $skipped_count, $updated_count);
        
        return array(
            'success' => true,
            'message' => $message,
            'created' => $created_count,
            'skipped' => $skipped_count,
            'updated' => $updated_count,
        );
    }
    
    /**
     * Get variation message based on counts
     * 
     * @param int $created
     * @param int $skipped
     * @param int $updated
     * @return string
     */
    private function get_variation_message($created, $skipped, $updated) {
        $parts = array();
        
        if ($created > 0) {
            $parts[] = sprintf(_n('%d variation created', '%d variations created', $created, 'wc-bulk-variations'), $created);
        }
        
        if ($skipped > 0) {
            $parts[] = sprintf(_n('%d skipped', '%d skipped', $skipped, 'wc-bulk-variations'), $skipped);
        }
        
        if ($updated > 0) {
            $parts[] = sprintf(_n('%d updated', '%d updated', $updated, 'wc-bulk-variations'), $updated);
        }
        
        if (empty($parts)) {
            return __('No changes made', 'wc-bulk-variations');
        }
        
        return implode(', ', $parts);
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
            'post_status' => array('publish', 'private', 'draft'),
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
    
    /**
     * Log message
     * 
     * @param string $message
     */
    private function log_message($message) {
        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $logger->info($message, array('source' => 'wc-bulk-variations'));
        }
    }
    
    /**
     * Log error
     * 
     * @param string $message
     */
    private function log_error($message) {
        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $logger->error($message, array('source' => 'wc-bulk-variations'));
        }
    }
}
