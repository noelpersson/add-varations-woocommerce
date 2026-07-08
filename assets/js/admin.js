jQuery(function($) {
    'use strict';

    var wc_bulk_variations = {
        batch_key: null,
        total_products: 0,
        processed_count: 0,
        check_interval: null,
        is_processing: false,
        active_batches: {},
        
        init: function() {
            this.setup_toggle_fields();
            this.setup_form_submission();
            this.setup_cancel_buttons();
            this.setup_select2();
            this.setup_details_toggle();
            this.check_existing_progress();
        },
        
        setup_toggle_fields: function() {
            var self = this;
            
            $('input[name="product_selection"]').on('change', function() {
                var selection = $(this).val();
                
                // Hide all selectors
                $('#categories-selector, #products-selector').hide();
                
                // Show appropriate selector
                if (selection === 'categories') {
                    $('#categories-selector').show();
                } else if (selection === 'products') {
                    $('#products-selector').show();
                }
            });
        },
        
        setup_select2: function() {
            // Initialize select2 for enhanced selects
            $('.wc-enhanced-select').select2({
                allowClear: true,
                placeholder: wc_bulk_variations_admin.strings.select_placeholder || 'Select...',
                width: '100%'
            });
        },
        
        setup_form_submission: function() {
            var self = this;
            
            $('#wc-bulk-variations-form').on('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (!self.validate_form()) {
                    return;
                }
                
                // Show spinner
                $('#wc-bulk-variations-spinner').show();
                $('#wc-bulk-variations-submit').prop('disabled', true);
                self.is_processing = true;
                
                // Collect form data
                var form_data = $(this).serialize();
                
                // Start the process
                $.ajax({
                    url: wc_bulk_variations_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_bulk_variations_start_process',
                        nonce: wc_bulk_variations_admin.nonce,
                        product_selection: $('input[name="product_selection"]:checked').val(),
                        selected_products: $('select[name="selected_products[]"]').val() || [],
                        selected_categories: $('select[name="selected_categories[]"]').val() || [],
                        attribute_name: $('#attribute_name').val(),
                        attribute_values: $('#attribute_values').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            self.batch_key = response.data.batch_key;
                            self.total_products = response.data.total_products;
                            self.processed_count = 0;
                            self.active_batches[self.batch_key] = true;
                            
                            // Show results container
                            $('#wc-bulk-variations-results').show();
                            
                            // Start processing batches
                            self.process_next_batch();
                        } else {
                            self.show_error(response.data.message);
                            $('#wc-bulk-variations-spinner').hide();
                            $('#wc-bulk-variations-submit').prop('disabled', false);
                            self.is_processing = false;
                        }
                    },
                    error: function(xhr, status, error) {
                        self.show_error(wc_bulk_variations_admin.strings.error + ': ' + error);
                        $('#wc-bulk-variations-spinner').hide();
                        $('#wc-bulk-variations-submit').prop('disabled', false);
                        self.is_processing = false;
                    }
                });
            });
        },
        
        setup_cancel_buttons: function() {
            var self = this;
            
            $(document).on('click', '.wc-bulk-variations-cancel', function() {
                var batch_key = $(this).data('batch-key');
                
                if (confirm(wc_bulk_variations_admin.strings.cancel_confirm)) {
                    $.ajax({
                        url: wc_bulk_variations_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wc_bulk_variations_cancel_process',
                            nonce: wc_bulk_variations_admin.nonce,
                            batch_key: batch_key
                        },
                        success: function(response) {
                            if (response.success) {
                                // Stop processing
                                self.is_processing = false;
                                delete self.active_batches[batch_key];
                                
                                // Update UI
                                $('.wc-bulk-variations-progress[data-batch-key="' + batch_key + '"] .progress-bar-fill').css('width', '100%');
                                $('.wc-bulk-variations-progress[data-batch-key="' + batch_key + '"] .status-text').text(wc_bulk_variations_admin.strings.cancelled);
                                
                                // Hide cancel button
                                $('.wc-bulk-variations-cancel[data-batch-key="' + batch_key + '"]').hide();
                                
                                // Re-enable form
                                $('#wc-bulk-variations-spinner').hide();
                                $('#wc-bulk-variations-submit').prop('disabled', false);
                            } else {
                                self.show_error(response.data.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            self.show_error(wc_bulk_variations_admin.strings.error + ': ' + error);
                        }
                    });
                }
            });
        },
        
        setup_details_toggle: function() {
            var self = this;
            
            $(document).on('click', '.wc-bulk-variations-toggle-details', function() {
                var batch_key = $(this).data('batch-key');
                var content = $('.wc-bulk-variations-details-content[data-batch-key="' + batch_key + '"]');
                var button = $(this);
                
                if (content.is(':visible')) {
                    content.hide();
                    button.text(wc_bulk_variations_admin.strings.view_details);
                } else {
                    content.show();
                    button.text(wc_bulk_variations_admin.strings.hide_details);
                }
            });
        },
        
        check_existing_progress: function() {
            var self = this;
            
            // Check for existing progress on page load
            $.ajax({
                url: wc_bulk_variations_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_bulk_variations_get_progress',
                    nonce: wc_bulk_variations_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var progress = response.data;
                        for (var batch_key in progress) {
                            if (progress.hasOwnProperty(batch_key)) {
                                self.batch_key = batch_key;
                                self.active_batches[batch_key] = true;
                                self.update_progress_ui(progress[batch_key]);
                                
                                // If still processing, continue
                                if (progress[batch_key].status === 'processing' || progress[batch_key].status === 'queued') {
                                    self.is_processing = true;
                                    self.process_next_batch();
                                }
                            }
                        }
                    }
                }
            });
        },
        
        process_next_batch: function() {
            var self = this;
            
            // Find first active batch
            var active_batch_key = null;
            for (var batch_key in self.active_batches) {
                if (self.active_batches.hasOwnProperty(batch_key)) {
                    active_batch_key = batch_key;
                    break;
                }
            }
            
            if (!active_batch_key || !self.is_processing) {
                return;
            }
            
            $.ajax({
                url: wc_bulk_variations_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_bulk_variations_process_batch',
                    nonce: wc_bulk_variations_admin.nonce,
                    batch_key: active_batch_key
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.update_progress_ui(response.data);
                        
                        // Check if process is complete
                        if (response.data.status === 'completed' || response.data.status === 'cancelled') {
                            delete self.active_batches[active_batch_key];
                            
                            // Check if there are more active batches
                            var has_active = false;
                            for (var key in self.active_batches) {
                                if (self.active_batches.hasOwnProperty(key)) {
                                    has_active = true;
                                    break;
                                }
                            }
                            
                            if (!has_active) {
                                self.is_processing = false;
                                $('#wc-bulk-variations-spinner').hide();
                                $('#wc-bulk-variations-submit').prop('disabled', false);
                            } else {
                                // Process next batch
                                setTimeout(function() {
                                    self.process_next_batch();
                                }, 100);
                            }
                        } else {
                            // Continue processing next batch
                            setTimeout(function() {
                                self.process_next_batch();
                            }, 100); // Small delay before next batch
                        }
                    } else {
                        // Error occurred, stop processing
                        self.is_processing = false;
                        delete self.active_batches[active_batch_key];
                        $('#wc-bulk-variations-spinner').hide();
                        $('#wc-bulk-variations-submit').prop('disabled', false);
                        if (response.data && response.data.message) {
                            self.show_error(response.data.message);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    self.is_processing = false;
                    delete self.active_batches[active_batch_key];
                    $('#wc-bulk-variations-spinner').hide();
                    $('#wc-bulk-variations-submit').prop('disabled', false);
                    self.show_error(wc_bulk_variations_admin.strings.error + ': ' + error);
                }
            });
        },
        
        update_progress_ui: function(progress) {
            var self = this;
            var percentage = 0;
            
            if (progress.total > 0) {
                percentage = Math.min(100, (progress.processed / progress.total) * 100);
            }
            
            var progress_container = $('.wc-bulk-variations-progress[data-batch-key="' + progress.key + '"]');
            
            if (progress_container.length === 0) {
                // Create new progress container
                var html = '<div class="wc-bulk-variations-progress" data-batch-key="' + progress.key + '">';
                html += '<div class="wc-bulk-variations-progress-header">';
                html += '<h3>' + wc_bulk_variations_admin.strings.processing + '</h3>';
                html += '<div class="wc-bulk-variations-summary"></div>';
                html += '</div>';
                html += '<div class="wc-bulk-variations-progress-bar-container">';
                html += '<p>' + wc_bulk_variations_admin.strings.progress + ': <span class="processed-count">0</span> of <span class="total-count">' + progress.total + '</span> products (<span class="percentage">0%</span>)</p>';
                html += '<div class="progress-bar"><div class="progress-bar-fill" style="width: 0%;"></div></div>';
                html += '<p><strong class="status-text">' + wc_bulk_variations_admin.strings.processing + '</strong></p>';
                html += '</div>';
                html += '<div class="wc-bulk-variations-details">';
                html += '<button class="button button-secondary wc-bulk-variations-toggle-details" data-batch-key="' + progress.key + '">';
                html += wc_bulk_variations_admin.strings.view_details + '</button>';
                html += '<div class="wc-bulk-variations-details-content" data-batch-key="' + progress.key + '" style="display: none; margin-top: 10px;">';
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr><th>' + wc_bulk_variations_admin.strings.product + '</th><th>' + wc_bulk_variations_admin.strings.status + '</th><th>' + wc_bulk_variations_admin.strings.message + '</th><th>' + wc_bulk_variations_admin.strings.created + '</th><th>' + wc_bulk_variations_admin.strings.skipped + '</th></tr></thead>';
                html += '<tbody></tbody></table>';
                html += '</div></div>';
                
                if (progress.status === 'processing' || progress.status === 'queued') {
                    html += '<button class="button button-secondary wc-bulk-variations-cancel" data-batch-key="' + progress.key + '">';
                    html += wc_bulk_variations_admin.strings.cancel + '</button>';
                }
                
                html += '</div>';
                
                $('#wc-bulk-variations-progress-container').append(html);
                progress_container = $('.wc-bulk-variations-progress[data-batch-key="' + progress.key + '"]');
            }
            
            // Update progress
            progress_container.find('.processed-count').text(progress.processed);
            progress_container.find('.total-count').text(progress.total);
            progress_container.find('.percentage').text(percentage.toFixed(1) + '%');
            progress_container.find('.progress-bar-fill').css('width', percentage + '%');
            
            // Update status text
            var status_text = wc_bulk_variations_admin.strings.processing;
            if (progress.status === 'queued') {
                status_text = wc_bulk_variations_admin.strings.queued || wc_bulk_variations_admin.strings.processing;
            } else if (progress.status === 'completed') {
                status_text = wc_bulk_variations_admin.strings.completed;
            } else if (progress.status === 'cancelled') {
                status_text = wc_bulk_variations_admin.strings.cancelled;
            }
            progress_container.find('.status-text').text(status_text);
            
            // Hide cancel button if not processing or queued
            if (progress.status !== 'processing' && progress.status !== 'queued') {
                progress_container.find('.wc-bulk-variations-cancel').hide();
            }
            
            // Update summary if available
            if (progress.items && progress.items.length > 0) {
                var total_created = 0;
                var total_skipped = 0;
                var total_failed = 0;
                
                for (var i = 0; i < progress.items.length; i++) {
                    var item = progress.items[i];
                    total_created += item.created || 0;
                    total_skipped += item.skipped || 0;
                    if (item.status === 'failed') {
                        total_failed++;
                    }
                }
                
                var success_rate = progress.total > 0 ? ((progress.processed / progress.total) * 100).toFixed(1) : 0;
                
                var summary_html = '';
                summary_html += '<p><strong>' + wc_bulk_variations_admin.strings.attribute + ':</strong> ' + progress.attribute_name + '</p>';
                summary_html += '<p><strong>' + wc_bulk_variations_admin.strings.values + ':</strong> ' + progress.attribute_values.join(', ') + '</p>';
                summary_html += '<p><strong>' + wc_bulk_variations_admin.strings.total_products + ':</strong> ' + progress.total + '</p>';
                summary_html += '<p><strong>' + wc_bulk_variations_admin.strings.processed + ':</strong> ' + progress.processed + '</p>';
                
                if (total_failed > 0) {
                    summary_html += '<p class="wc-bulk-variations-error"><strong>' + wc_bulk_variations_admin.strings.failed + ':</strong> ' + total_failed + '</p>';
                }
                
                if (total_created > 0) {
                    summary_html += '<p><strong>' + wc_bulk_variations_admin.strings.total_created + ':</strong> ' + total_created + '</p>';
                }
                
                if (total_skipped > 0) {
                    summary_html += '<p><strong>' + wc_bulk_variations_admin.strings.total_skipped + ':</strong> ' + total_skipped + '</p>';
                }
                
                summary_html += '<p><strong>' + wc_bulk_variations_admin.strings.success_rate + ':</strong> ' + success_rate + '%</p>';
                
                progress_container.find('.wc-bulk-variations-summary').html(summary_html);
            }
            
            // Update details table
            if (progress.items && progress.items.length > 0) {
                var tbody = progress_container.find('.wc-bulk-variations-details-content tbody');
                tbody.empty();
                
                for (var i = 0; i < progress.items.length; i++) {
                    var item = progress.items[i];
                    var status_class = item.status === 'success' ? 'success' : 'failed';
                    var row = '<tr class="' + status_class + '">';
                    row += '<td>#' + item.product_id + '</td>';
                    row += '<td>' + (item.status === 'success' ? wc_bulk_variations_admin.strings.success : wc_bulk_variations_admin.strings.failed) + '</td>';
                    row += '<td>' + item.message + '</td>';
                    row += '<td>' + (item.created || 0) + '</td>';
                    row += '<td>' + (item.skipped || 0) + '</td>';
                    row += '</tr>';
                    tbody.append(row);
                }
            }
        },
        
        validate_form: function() {
            var selection = $('input[name="product_selection"]:checked').val();
            var attribute_name = $('#attribute_name').val().trim();
            var attribute_values = $('#attribute_values').val().trim();
            
            // Check attribute name
            if (!attribute_name) {
                this.show_error(wc_bulk_variations_admin.strings.no_attribute_name);
                return false;
            }
            
            // Check attribute values
            if (!attribute_values) {
                this.show_error(wc_bulk_variations_admin.strings.no_attribute_values);
                return false;
            }
            
            // Check product selection
            if (selection === 'products' && !$('select[name="selected_products[]"]').val()) {
                this.show_error(wc_bulk_variations_admin.strings.no_products_selected);
                return false;
            }
            
            if (selection === 'categories' && !$('select[name="selected_categories[]"]').val()) {
                this.show_error(wc_bulk_variations_admin.strings.no_products_selected);
                return false;
            }
            
            return true;
        },
        
        show_error: function(message) {
            var error_html = '<div class="wc-bulk-variations-error"><p>' + message + '</p></div>';
            $('#wc-bulk-variations-results').html(error_html).show();
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $('#wc-bulk-variations-results').offset().top - 100
            }, 500);
        }
    };
    
    // Initialize
    wc_bulk_variations.init();
});
