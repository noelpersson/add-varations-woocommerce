<?php
/**
 * WC_Bulk_Variations_Simple_Background class
 * 
 * Simple background processing using WordPress transients and AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Bulk_Variations_Simple_Background {
    
    private static $instance = null;
    
    private $progress_data = array();
    private $completed_batches = array();
    
    private function __construct() {
        $this->load_progress_data();
    }
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function start_process($product_ids, $attribute_name, $attribute_values) {
        $batch_key = uniqid('wc_bulk_var_', true);
        
        // Store batch data
        $batch_data = array(
            'key' => $batch_key,
            'product_ids' => $product_ids,
            'attribute_name' => $attribute_name,
            'attribute_values' => $attribute_values,
            'total' => count($product_ids),
            'processed' => 0,
            'failed' => 0,
            'start_time' => time(),
            'status' => 'processing',
            'items' => array(),
        );
        
        $this->progress_data[$batch_key] = $batch_data;
        $this->save_progress_data();
        
        // Process first batch immediately
        $this->process_next_batch($batch_key);
        
        return $batch_key;
    }
    
    public function process_next_batch($batch_key) {
        if (!isset($this->progress_data[$batch_key])) {
            return false;
        }
        
        $batch_data = $this->progress_data[$batch_key];
        
        // Get remaining products (not yet processed)
        $processed_ids = array_column($batch_data['items'], 'product_id');
        $remaining_products = array_diff($batch_data['product_ids'], $processed_ids);
        
        if (empty($remaining_products)) {
            // All products processed
            $this->progress_data[$batch_key]['status'] = 'completed';
            $this->progress_data[$batch_key]['end_time'] = time();
            $this->completed_batches[$batch_key] = $this->progress_data[$batch_key];
            $this->save_progress_data();
            
            // Trigger completion action
            do_action('wc_bulk_variations_after_process', $batch_key, $this->progress_data[$batch_key]);
            
            return true;
        }
        
        // Process up to batch_size products at a time
        $batch_size = apply_filters('wc_bulk_variations_batch_size', 5);
        $batch = array_slice($remaining_products, 0, $batch_size);
        
        $handler = WC_Bulk_Variations_Plugin::get_instance()->get_variation_handler();
        
        foreach ($batch as $product_id) {
            $result = $handler->create_variations_for_product($product_id, $batch_data['attribute_name'], $batch_data['attribute_values']);
            
            $item = array(
                'product_id' => $product_id,
                'status' => $result['success'] ? 'success' : 'failed',
                'message' => $result['message'],
            );
            
            if ($result['success']) {
                $this->progress_data[$batch_key]['processed']++;
            } else {
                $this->progress_data[$batch_key]['failed']++;
            }
            
            $this->progress_data[$batch_key]['items'][] = $item;
        }
        
        $this->save_progress_data();
        
        // Check if there are more products to process
        $remaining_after = array_diff($batch_data['product_ids'], array_column($this->progress_data[$batch_key]['items'], 'product_id'));
        
        if (!empty($remaining_after)) {
            // More products to process, mark as queued
            $this->progress_data[$batch_key]['status'] = 'queued';
        } else {
            // All done
            $this->progress_data[$batch_key]['status'] = 'completed';
            $this->progress_data[$batch_key]['end_time'] = time();
            $this->completed_batches[$batch_key] = $this->progress_data[$batch_key];
            
            // Trigger completion action
            do_action('wc_bulk_variations_after_process', $batch_key, $this->progress_data[$batch_key]);
        }
        
        $this->save_progress_data();
        return true;
    }
    
    public function get_progress($batch_key = '') {
        $this->load_progress_data();
        
        if (!empty($batch_key)) {
            if (isset($this->progress_data[$batch_key])) {
                return $this->progress_data[$batch_key];
            }
            return null;
        }
        
        // Return all active batches
        $active = array();
        foreach ($this->progress_data as $key => $data) {
            if ($data['status'] === 'processing' || $data['status'] === 'queued') {
                $active[$key] = $data;
            }
        }
        
        return !empty($active) ? $active : null;
    }
    
    public function get_completed_batches() {
        $this->load_progress_data();
        return $this->completed_batches;
    }
    
    public function cancel_process($batch_key) {
        $this->load_progress_data();
        
        if (isset($this->progress_data[$batch_key])) {
            $this->progress_data[$batch_key]['status'] = 'cancelled';
            $this->progress_data[$batch_key]['end_time'] = time();
            $this->save_progress_data();
            return true;
        }
        return false;
    }
    
    public function clear_completed_batches() {
        $this->completed_batches = array();
        delete_transient('wc_bulk_variations_completed');
        $this->save_progress_data();
    }
    
    public function save_progress_data() {
        // Store progress data in transient for persistence across requests
        if (!empty($this->progress_data)) {
            set_transient('wc_bulk_variations_progress', $this->progress_data, DAY_IN_SECONDS);
        }
        
        if (!empty($this->completed_batches)) {
            set_transient('wc_bulk_variations_completed', $this->completed_batches, DAY_IN_SECONDS);
        }
    }
    
    public function load_progress_data() {
        $this->progress_data = get_transient('wc_bulk_variations_progress') ?: array();
        $this->completed_batches = get_transient('wc_bulk_variations_completed') ?: array();
    }
}
