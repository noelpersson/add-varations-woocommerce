# WooCommerce Bulk Variations

A WordPress plugin for creating variations in bulk for WooCommerce products. This plugin allows you to add the same set of variations (e.g., sizes or colors) to multiple products at once, saving you hours of manual work.

## Features

- **Bulk Variation Creation**: Add the same variations to multiple products simultaneously
- **Background Processing**: Processes products in batches to avoid server timeouts
- **Flexible Product Selection**: Choose all products, products by category, or specific products
- **Custom Attributes**: Define your own attribute names and values
- **Progress Tracking**: Real-time progress bar showing processing status
- **Error Handling**: Comprehensive error logging and user feedback

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
- **Attribute Name**: Enter the name of the attribute (e.g., "Size", "Color", "Material")
- **Attribute Values**: Enter the variation values separated by commas (e.g., "Small, Medium, Large" or "Red, Blue, Green")

### Step 3: Run Process
Click "Start Bulk Variation Creation" to begin the process. The plugin will:
1. Process products in batches of 5 at a time
2. Show real-time progress
3. Skip products that already have the specified variations
4. Continue in the background even if you navigate away from the page

## Architecture

The plugin uses a robust architecture to handle large numbers of products:

1. **Background Processing**: Uses WP Background Processing to handle large batches without timing out
2. **Batch Processing**: Processes 5 products at a time to minimize server load
3. **Queue System**: Maintains a queue of products to process
4. **Progress Tracking**: Tracks progress across page loads
5. **Error Logging**: Logs errors to WooCommerce's built-in logger for debugging

## Filters

The plugin provides several filters for developers:

- `wc_bulk_variations_batch_size`: Change the number of products processed in each batch (default: 5)
- `wc_bulk_variations_default_time_limit`: Change the time limit for each batch (default: 20 seconds)

## Actions

- `wc_bulk_variations_before_process`: Fires before processing starts
- `wc_bulk_variations_after_process`: Fires after processing completes
- `wc_bulk_variations_before_product`: Fires before processing each product
- `wc_bulk_variations_after_product`: Fires after processing each product

## Troubleshooting

### Process gets stuck
1. Check WooCommerce > Status > Logs for any error messages
2. Try increasing the batch size with the `wc_bulk_variations_batch_size` filter
3. Check your server's PHP memory limit and increase if necessary

### Variations not appearing
1. Make sure the product is set as a variable product
2. Check that the attribute is marked as "Used for variations"
3. Verify that the variation values don't already exist for the product

### Performance issues
1. Reduce the batch size using the `wc_bulk_variations_batch_size` filter
2. Process fewer products at a time
3. Check your server's resources (CPU, memory)

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
- Uses WP Background Processing for background tasks
- Inspired by the needs of e-commerce store owners everywhere
