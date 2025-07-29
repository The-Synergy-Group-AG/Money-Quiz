/**
 * Integration Settings JavaScript
 * 
 * Handles the integration settings page functionality
 */

(function($) {
    'use strict';

    var MQIntegration = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initRangeSlider();
            this.startStatusPolling();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Settings form
            $('#mq-integration-settings-form').on('submit', this.saveSettings);
            
            // Migration buttons
            $('#mq-run-migration-dry').on('click', function() {
                MQIntegration.runMigration(true);
            });
            
            $('#mq-run-migration').on('click', function() {
                if (confirm(mqIntegration.strings.confirm_migration)) {
                    MQIntegration.runMigration(false);
                }
            });
            
            // Function toggles
            $('.mq-function-toggle input').on('change', this.updateFunctionStatus);
        },
        
        /**
         * Initialize range slider
         */
        initRangeSlider: function() {
            var $slider = $('#modern_rollout');
            var $value = $('#rollout_value');
            
            $slider.on('input', function() {
                $value.text($(this).val() + '%');
                
                // Update color based on value
                var val = parseInt($(this).val());
                if (val < 30) {
                    $value.css('color', '#46b450'); // Green - safe
                } else if (val < 70) {
                    $value.css('color', '#ffb900'); // Yellow - caution
                } else {
                    $value.css('color', '#dc3232'); // Red - aggressive
                }
            });
        },
        
        /**
         * Save settings
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submit = $form.find('button[type="submit"]');
            var originalText = $submit.text();
            
            // Update button state
            $submit.prop('disabled', true).text(mqIntegration.strings.saving);
            
            // Prepare data
            var data = $form.serializeArray();
            data.push({
                name: 'action',
                value: 'mq_save_integration_settings'
            });
            
            // Send request
            $.post(mqIntegration.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        MQIntegration.showNotice('success', response.data.message || mqIntegration.strings.saved);
                    } else {
                        MQIntegration.showNotice('error', response.data || mqIntegration.strings.error);
                    }
                })
                .fail(function() {
                    MQIntegration.showNotice('error', mqIntegration.strings.error);
                })
                .always(function() {
                    $submit.prop('disabled', false).text(originalText);
                });
        },
        
        /**
         * Run migration
         */
        runMigration: function(dryRun) {
            var $output = $('#migration-output');
            var $button = dryRun ? $('#mq-run-migration-dry') : $('#mq-run-migration');
            var originalText = $button.text();
            
            // Update button state
            $button.prop('disabled', true).text('Running...');
            $output.html('<div class="spinner is-active"></div>');
            
            // Send request
            $.post(mqIntegration.ajaxUrl, {
                action: 'mq_run_migration_tool',
                _wpnonce: mqIntegration.nonce,
                dry_run: dryRun ? 1 : 0
            })
            .done(function(response) {
                if (response.success) {
                    $output.html('<pre>' + response.data.output + '</pre>');
                    if (!dryRun) {
                        MQIntegration.showNotice('success', mqIntegration.strings.migration_complete);
                    }
                } else {
                    $output.html('<div class="notice notice-error"><p>' + (response.data || mqIntegration.strings.migration_error) + '</p></div>');
                }
            })
            .fail(function() {
                $output.html('<div class="notice notice-error"><p>' + mqIntegration.strings.migration_error + '</p></div>');
            })
            .always(function() {
                $button.prop('disabled', false).text(originalText);
            });
        },
        
        /**
         * Update function status
         */
        updateFunctionStatus: function() {
            var $toggle = $(this);
            var $label = $toggle.closest('.mq-function-toggle');
            
            if ($toggle.is(':checked')) {
                $label.addClass('enabled');
            } else {
                $label.removeClass('enabled');
            }
        },
        
        /**
         * Start status polling
         */
        startStatusPolling: function() {
            // Initial load
            this.updateStatus();
            
            // Poll every 30 seconds
            setInterval(function() {
                MQIntegration.updateStatus();
            }, 30000);
        },
        
        /**
         * Update status
         */
        updateStatus: function() {
            $.post(mqIntegration.ajaxUrl, {
                action: 'mq_get_integration_status',
                _wpnonce: mqIntegration.nonce
            })
            .done(function(response) {
                if (response.success) {
                    MQIntegration.updateStatusDisplay(response.data);
                }
            });
        },
        
        /**
         * Update status display
         */
        updateStatusDisplay: function(status) {
            // Update health indicator
            $('.mq-status-value').first()
                .removeClass('healthy warning error')
                .addClass(status.health_class)
                .text(status.health_text);
            
            // Update metrics
            $('.mq-status-value').eq(1).text(status.safe_queries + '%');
            $('.mq-status-value').eq(2).text(status.error_rate);
            $('.mq-status-value').eq(3).text(status.avg_load_time + 's');
            
            // Update metrics table
            if (status.metrics) {
                $.each(status.metrics, function(key, metric) {
                    var $row = $('.mq-metrics-table tr').filter(function() {
                        return $(this).find('td').first().text() === metric.label;
                    });
                    
                    if ($row.length) {
                        $row.find('td').eq(1).text(metric.current);
                        $row.find('td').eq(2).text(metric.avg);
                        $row.find('td').eq(3)
                            .removeClass('positive negative')
                            .addClass(metric.change > 0 ? 'positive' : 'negative')
                            .text(metric.change + '%');
                    }
                });
            }
        },
        
        /**
         * Show notice
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap > h1').after($notice);
            
            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MQIntegration.init();
    });
    
})(jQuery);