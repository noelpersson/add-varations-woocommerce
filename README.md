# WooCommerce Bulk Variations

A WordPress plugin for creating variations in bulk for WooCommerce products. This plugin allows you to add the same set of variations (e.g., sizes or colors) to multiple products at once, saving you hours of manual work.

## Features

- **Bulk Variation Creation**: Add the same variations to multiple products simultaneously
- **Background Processing**: Processes products in batches via AJAX to avoid server timeouts
- **Flexible Product Selection**: Choose all products, products by category, or specific products
- **Custom Attributes**: Define your own attribute names and values
- **Progress Tracking**: Real-time progress bar showing processing status
- **Error Handling**: Comprehensive error logging and user feedback
- **Detailed Reporting**: View detailed results for each product
- **Smart Edge Case Handling**: Automatically handles various product types and scenarios

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.2+

## Installation

1. Upload the `wc-bulk-variations` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce > Bulk Variations to start using the plugin

## Usage

### Step 1: Select Products
Choose which products you want to add variations to:
- **All products**: Process every product in your store
- **Products in selected categories**: Only process products in specific categories
- **Specific products**: Manually select individual products

### Step 2: Define Attribute and Variations
- **Attribute Name**: Enter the name of the attribute (e.g., "Size" or "Color")
- **Attribute Values**: Enter the variation values separated by commas (e.g., "Small, Medium, Large" or "Red, Blue, Green")

### Step 3: Run Process
Click "Start Bulk Variation Creation" to begin the process. The plugin will:
1. Process products in batches of 5 at a time (configurable via filter)
2. Show real-time progress
3. Skip products that already have the specified variations
4. Continue processing until all products are done

## Edge Cases Handled

The plugin intelligently handles the following scenarios:

### Product Type Handling
- **Simple Products**: Automatically converts to variable products
- **Variable Products**: Preserves existing variations and adds new ones
- **Grouped Products**: Skips with appropriate error message
- **External/Affiliate Products**: Skips with appropriate error message
- **Subscription Products**: Skips with appropriate error message
- **Custom Product Types**: Handles gracefully

### Attribute Handling
- **Existing Attributes**: Merges new values with existing attribute values
- **Duplicate Values**: Automatically filters out duplicate values
- **Attribute Position**: Automatically assigns correct position
- **Attribute Visibility**: Sets attributes as visible and enabled for variations

### Variation Handling
- **Duplicate Variations**: Skips variations that already exist
- **Price Inheritance**: New variations inherit prices from parent product
- **Stock Settings**: New variations get default stock settings
- **SKU Generation**: Automatically generates SKUs based on parent SKU
- **Image Inheritance**: New variations inherit parent product image
- **Dimension Inheritance**: New variations inherit parent product dimensions
- **Tax Settings**: New variations inherit parent product tax settings

### Error Handling
- **Invalid Product IDs**: Filters out invalid product IDs
- **Missing Products**: Handles products that no longer exist
- **Permission Issues**: Proper capability checks
- **AJAX Failures**: Graceful error handling for AJAX requests
- **Memory Limits**: Batch processing prevents memory exhaustion

### Process Management
- **Process Cancellation**: Users can cancel running processes
- **Process Resumption**: Processes can resume after page reload
- **Multiple Batches**: Supports multiple concurrent batch processes
- **Progress Persistence**: Progress is saved across page loads

## Filters

The plugin provides several filters for developers:

- `wc_bulk_variations_batch_size`: Change the number of products processed in each batch (default: 5)
  ```php
  add_filter('wc_bulk_variations_batch_size', function() {
      return 10; // Process 10 products at a time
  });
  ```

## Actions

- `wc_bulk_variations_after_process`: Fires after processing completes for a batch
  ```php
  add_action('wc_bulk_variations_after_process', function($batch_key, $batch_data) {
      // Do something after batch processing
  }, 10, 2);
  ```

## Troubleshooting

### Process gets stuck
1. Check WooCommerce > Status > Logs for any error messages (source: wc-bulk-variations)
2. Try refreshing the page - processes should resume automatically
3. Check your server's PHP memory limit and increase if necessary

### Variations not appearing
1. Make sure the product is set as a variable product (plugin does this automatically)
2. Check that the attribute is marked as "Used for variations" (plugin does this automatically)
3. Verify that the variation values don't already exist for the product (plugin skips duplicates)
4. Check if the product has any restrictions or custom code that prevents variations

### Performance issues
1. Reduce the batch size using the `wc_bulk_variations_batch_size` filter
2. Process fewer products at a time
3. Check your server's resources (CPU, memory)
4. Consider running the process during off-peak hours

### Products not found
1. Make sure the products exist and are published
2. Check that the products are not in the trash
3. Verify that you have the correct permissions to edit products

## Frequently Asked Questions

### Q: What happens if a product is already a variable product?
A: The plugin will add the new attribute and variations to the existing variable product without affecting existing variations.

### Q: What happens if I add the same attribute values that already exist?
A: The plugin will merge the new values with existing values and skip creating duplicate variations.

### Q: Can I cancel a process that's already running?
A: Yes, you can cancel any process that's currently running or queued by clicking the "Cancel Process" button.

### Q: What happens if I navigate away from the page while a process is running?
A: The process will continue in the background. When you return to the page, you'll see the current progress and it will continue from where it left off.

### Q: Can I run multiple processes at the same time?
A: Yes, you can start multiple processes. Each process will be tracked separately and you can view the progress of each one.

### Q: What happens to products that fail?
A: Failed products are logged with error messages. You can view the details by clicking "View Details" on the process and see which products failed and why.

### Q: Are the variations created with the same prices as the parent product?
A: Yes, new variations inherit the regular price and sale price from the parent product.

### Q: What about stock management?
A: New variations are created with stock quantity set to 0, manage stock enabled, and stock status set to "on backorder". You can adjust these settings after creation.

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This plugin is licensed under the GPL-2.0+ license.

## Credits

- Built with WooCommerce
- Uses jQuery and Select2 for enhanced UI
- Inspired by the needs of e-commerce store owners everywhere
