<div class="wrap woocommerce">
    <h1><?php _e('Bulk Variations', 'wc-bulk-variations'); ?></h1>
    
    <?php
    // Display current progress if any
    $processor = WC_Bulk_Variations_Plugin::get_instance()->get_background_processor();
    $active_progress = $processor->get_progress();
    if ($active_progress && !empty($active_progress)) {
        echo '<div class="wc-bulk-variations-active-processes">';
        echo '<h2>' . esc_html__('Active Processes', 'wc-bulk-variations') . '</h2>';
        foreach ($active_progress as $batch_key => $progress) {
            $this->display_progress_bar($progress, $batch_key);
        }
        echo '</div>';
    }
    ?>
    
    <div class="wc-bulk-variations-container">
        <form id="wc-bulk-variations-form" method="post">
            <?php wp_nonce_field('wc_bulk_variations_nonce'); ?>
            
            <div class="wc-bulk-variations-section">
                <h2><?php _e('Step 1: Select Products', 'wc-bulk-variations'); ?></h2>
                
                <div class="wc-bulk-variations-field">
                    <label>
                        <input type="radio" name="product_selection" id="product_selection_all" value="all" checked>
                        <?php _e('All products', 'wc-bulk-variations'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="product_selection" id="product_selection_categories" value="categories">
                        <?php _e('Products in selected categories', 'wc-bulk-variations'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="product_selection" id="product_selection_products" value="products">
                        <?php _e('Specific products', 'wc-bulk-variations'); ?>
                    </label>
                </div>
                
                <div class="wc-bulk-variations-field" id="categories-selector" style="display: none; margin-top: 15px;">
                    <label for="selected_categories"><?php _e('Select Categories:', 'wc-bulk-variations'); ?></label>
                    <select name="selected_categories[]" id="selected_categories" class="wc-enhanced-select" multiple="multiple" style="width: 100%;">
                        <?php foreach ($categories as $term_id => $name) : ?>
                            <option value="<?php echo esc_attr($term_id); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select one or more categories. All products in these categories will be processed.', 'wc-bulk-variations'); ?></p>
                </div>
                
                <div class="wc-bulk-variations-field" id="products-selector" style="display: none; margin-top: 15px;">
                    <label for="selected_products"><?php _e('Select Products:', 'wc-bulk-variations'); ?></label>
                    <select name="selected_products[]" id="selected_products" class="wc-enhanced-select" multiple="multiple" style="width: 100%;">
                        <?php foreach ($products as $product_id => $title) : ?>
                            <option value="<?php echo esc_attr($product_id); ?>"><?php echo esc_html($title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select one or more products. Only selected products will be processed.', 'wc-bulk-variations'); ?></p>
                </div>
            </div>
            
            <div class="wc-bulk-variations-section">
                <h2><?php _e('Step 2: Define Attribute and Variations', 'wc-bulk-variations'); ?></h2>
                
                <div class="wc-bulk-variations-field">
                    <label for="attribute_name"><?php _e('Attribute Name:', 'wc-bulk-variations'); ?></label>
                    <input type="text" name="attribute_name" id="attribute_name" class="regular-text" 
                           placeholder="<?php _e('e.g. Size, Color, Material', 'wc-bulk-variations'); ?>" required>
                    <p class="description"><?php _e('Enter the name of the attribute (e.g., "Size" or "Color"). This will be created as a product attribute.', 'wc-bulk-variations'); ?></p>
                </div>
                
                <div class="wc-bulk-variations-field">
                    <label for="attribute_values"><?php _e('Attribute Values (Variations):', 'wc-bulk-variations'); ?></label>
                    <input type="text" name="attribute_values" id="attribute_values" class="regular-text" 
                           placeholder="<?php _e('e.g. Small, Medium, Large or Red, Blue, Green', 'wc-bulk-variations'); ?>" required>
                    <p class="description"><?php _e('Enter the variation values separated by commas. Each value will create a separate variation.', 'wc-bulk-variations'); ?></p>
                </div>
            </div>
            
            <div class="wc-bulk-variations-section">
                <h2><?php _e('Step 3: Run Process', 'wc-bulk-variations'); ?></h2>
                <p><?php _e('Click the button below to start creating variations for the selected products. This process will run in the background.', 'wc-bulk-variations'); ?></p>
                
                <button type="submit" class="button button-primary button-large" id="wc-bulk-variations-submit">
                    <?php _e('Start Bulk Variation Creation', 'wc-bulk-variations'); ?>
                </button>
                
                <span class="spinner" id="wc-bulk-variations-spinner" style="display: none; margin-left: 10px;"></span>
            </div>
            
            <div id="wc-bulk-variations-results" style="margin-top: 20px; display: none;">
                <h3><?php _e('Processing Results', 'wc-bulk-variations'); ?></h3>
                <div id="wc-bulk-variations-progress-container"></div>
            </div>
        </form>
    </div>
</div>

<?php
// Helper method to display progress bar
function display_progress_bar($progress, $batch_key) {
    $percentage = 0;
    if ($progress['total'] > 0) {
        $percentage = min(100, ($progress['processed'] / $progress['total']) * 100);
    }
    
    $status_text = '';
    switch ($progress['status']) {
        case 'processing':
            $status_text = __('Processing...', 'wc-bulk-variations');
            break;
        case 'queued':
            $status_text = __('Queued for processing', 'wc-bulk-variations');
            break;
        case 'completed':
            $status_text = __('Completed!', 'wc-bulk-variations');
            break;
        case 'cancelled':
            $status_text = __('Cancelled', 'wc-bulk-variations');
            break;
    }
    
    echo '<div class="wc-bulk-variations-progress" data-batch-key="' . esc_attr($batch_key) . '">';
    echo '<div class="wc-bulk-variations-progress-header">';
    echo '<h3>' . esc_html__('Processing Status', 'wc-bulk-variations') . '</h3>';
    
    // Show summary if available
    $processor = WC_Bulk_Variations_Plugin::get_instance()->get_background_processor();
    $summary = $processor->get_batch_summary($batch_key);
    
    if ($summary) {
        echo '<div class="wc-bulk-variations-summary">';
        echo '<p><strong>' . esc_html__('Attribute:', 'wc-bulk-variations') . '</strong> ' . esc_html($progress['attribute_name']) . '</p>';
        echo '<p><strong>' . esc_html__('Values:', 'wc-bulk-variations') . '</strong> ' . esc_html(implode(', ', $progress['attribute_values'])) . '</p>';
        
        if ($summary['total_products'] > 0) {
            echo '<p><strong>' . esc_html__('Total Products:', 'wc-bulk-variations') . '</strong> ' . esc_html($summary['total_products']) . '</p>';
        }
        
        if ($summary['processed'] > 0) {
            echo '<p><strong>' . esc_html__('Processed:', 'wc-bulk-variations') . '</strong> ' . esc_html($summary['processed']) . '</p>';
        }
        
        if ($summary['failed'] > 0) {
            echo '<p class="wc-bulk-variations-error"><strong>' . esc_html__('Failed:', 'wc-bulk-variations') . '</strong> ' . esc_html($summary['failed']) . '</p>';
        }
        
        if ($summary['total_created'] > 0) {
            echo '<p><strong>' . esc_html__('Variations Created:', 'wc-bulk-variations') . '</strong> ' . esc_html($summary['total_created']) . '</p>';
        }
        
        if ($summary['total_skipped'] > 0) {
            echo '<p><strong>' . esc_html__('Variations Skipped:', 'wc-bulk-variations') . '</strong> ' . esc_html($summary['total_skipped']) . '</p>';
        }
        
        if ($summary['success_rate'] > 0) {
            echo '<p><strong>' . esc_html__('Success Rate:', 'wc-bulk-variations') . '</strong> ' . number_format($summary['success_rate'], 1) . '%</p>';
        }
        
        if ($summary['duration'] > 0) {
            echo '<p><strong>' . esc_html__('Duration:', 'wc-bulk-variations') . '</strong> ' . esc_html($summary['duration']) . ' ' . esc_html__('seconds', 'wc-bulk-variations') . '</p>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    
    echo '<div class="wc-bulk-variations-progress-bar-container">';
    echo '<p>' . sprintf(
        esc_html__('Progress: %d of %d products (%s%%)', 'wc-bulk-variations'),
        $progress['processed'],
        $progress['total'],
        number_format($percentage, 1)
    ) . '</p>';
    echo '<div class="progress-bar">';
    echo '<div class="progress-bar-fill" style="width: ' . esc_attr($percentage) . '%;"></div>';
    echo '</div>';
    echo '<p><strong class="status-text">' . esc_html($status_text) . '</strong></p>';
    echo '</div>';
    
    // Show details toggle
    echo '<div class="wc-bulk-variations-details">';
    echo '<button class="button button-secondary wc-bulk-variations-toggle-details" data-batch-key="' . esc_attr($batch_key) . '">';
    echo esc_html__('View Details', 'wc-bulk-variations');
    echo '</button>';
    echo '<div class="wc-bulk-variations-details-content" data-batch-key="' . esc_attr($batch_key) . '" style="display: none; margin-top: 10px;">';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Product', 'wc-bulk-variations') . '</th>';
    echo '<th>' . esc_html__('Status', 'wc-bulk-variations') . '</th>';
    echo '<th>' . esc_html__('Message', 'wc-bulk-variations') . '</th>';
    echo '<th>' . esc_html__('Created', 'wc-bulk-variations') . '</th>';
    echo '<th>' . esc_html__('Skipped', 'wc-bulk-variations') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    if (!empty($progress['items'])) {
        foreach ($progress['items'] as $item) {
            $status_class = $item['status'] === 'success' ? 'success' : 'failed';
            echo '<tr class="' . esc_attr($status_class) . '">';
            echo '<td>' . esc_html(get_the_title($item['product_id'])) . ' (#' . esc_html($item['product_id']) . ')</td>';
            echo '<td>' . esc_html(ucfirst($item['status'])) . '</td>';
            echo '<td>' . esc_html($item['message']) . '</td>';
            echo '<td>' . esc_html(isset($item['created']) ? $item['created'] : 0) . '</td>';
            echo '<td>' . esc_html(isset($item['skipped']) ? $item['skipped'] : 0) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">' . esc_html__('No details available', 'wc-bulk-variations') . '</td></tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';
    
    if ($progress['status'] === 'processing' || $progress['status'] === 'queued') {
        echo '<button class="button button-secondary wc-bulk-variations-cancel" data-batch-key="' . esc_attr($batch_key) . '">';
        echo esc_html__('Cancel Process', 'wc-bulk-variations');
        echo '</button>';
    }
    
    echo '</div>';
}
