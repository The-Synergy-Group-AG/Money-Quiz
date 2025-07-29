/**
 * Money Quiz Routing Admin JavaScript
 * 
 * @package MoneyQuiz
 * @since 1.5.0
 */

(function($) {
    'use strict';

    const RoutingAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.startAutoRefresh();
            this.initializeSliders();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Feature flag sliders
            $('.mq-flag-slider').on('input', this.updateSliderValue);
            $('.mq-flag-save').on('click', this.saveFeatureFlag);
            
            // Rollback controls
            $('#mq-manual-rollback').on('click', this.triggerRollback);
            $('#mq-clear-rollback').on('click', this.clearRollback);
            
            // Prevent form submission on enter
            $('input[type="range"]').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                }
            });
        },
        
        /**
         * Initialize sliders
         */
        initializeSliders: function() {
            $('.mq-flag-slider').each(function() {
                const $slider = $(this);
                const $value = $slider.siblings('.mq-flag-value');
                $value.text($slider.val() + '%');
            });
        },
        
        /**
         * Update slider value display
         */
        updateSliderValue: function() {
            const $slider = $(this);
            const $value = $slider.siblings('.mq-flag-value');
            const $saveBtn = $slider.closest('tr').find('.mq-flag-save');
            
            $value.text($slider.val() + '%');
            $saveBtn.addClass('button-primary').prop('disabled', false);
        },
        
        /**
         * Save feature flag
         */
        saveFeatureFlag: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const $row = $btn.closest('tr');
            const $slider = $row.find('.mq-flag-slider');
            const flag = $btn.data('flag');
            const value = $slider.val();
            
            $btn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: mqRoutingAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mq_update_feature_flag',
                    flag: flag,
                    value: value,
                    nonce: mqRoutingAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.removeClass('button-primary').text('Saved!');
                        setTimeout(function() {
                            $btn.text('Save');
                        }, 2000);
                    } else {
                        alert('Error: ' + response.data.message);
                        $btn.prop('disabled', false).text('Save');
                    }
                },
                error: function() {
                    alert('Failed to save feature flag');
                    $btn.prop('disabled', false).text('Save');
                }
            });
        },
        
        /**
         * Trigger manual rollback
         */
        triggerRollback: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to trigger a manual rollback? This will route all traffic to the legacy system.')) {
                return;
            }
            
            const $btn = $(this);
            $btn.prop('disabled', true).text('Triggering rollback...');
            
            $.ajax({
                url: mqRoutingAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mq_trigger_rollback',
                    nonce: mqRoutingAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                        $btn.prop('disabled', false).text('Trigger Manual Rollback');
                    }
                },
                error: function() {
                    alert('Failed to trigger rollback');
                    $btn.prop('disabled', false).text('Trigger Manual Rollback');
                }
            });
        },
        
        /**
         * Clear rollback
         */
        clearRollback: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear the rollback? This will allow traffic routing to resume.')) {
                return;
            }
            
            const $btn = $(this);
            $btn.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: mqRoutingAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mq_clear_rollback',
                    nonce: mqRoutingAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                        $btn.prop('disabled', false).text('Clear Rollback');
                    }
                },
                error: function() {
                    alert('Failed to clear rollback');
                    $btn.prop('disabled', false).text('Clear Rollback');
                }
            });
        },
        
        /**
         * Start auto-refresh for stats
         */
        startAutoRefresh: function() {
            // Only refresh if we're on the routing control page
            if (!$('.mq-health-status').length) {
                return;
            }
            
            // Initial load
            this.refreshStats();
            
            // Set interval
            setInterval(this.refreshStats.bind(this), mqRoutingAdmin.refreshInterval);
        },
        
        /**
         * Refresh statistics
         */
        refreshStats: function() {
            $.ajax({
                url: mqRoutingAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mq_get_routing_stats',
                    nonce: mqRoutingAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RoutingAdmin.updateHealthDisplay(response.data.health);
                        RoutingAdmin.updateStatsDisplay(response.data.stats);
                    }
                }
            });
        },
        
        /**
         * Update health display
         */
        updateHealthDisplay: function(health) {
            // Update health indicator
            const $indicator = $('.mq-health-indicator');
            $indicator.removeClass('mq-health-good mq-health-warning mq-health-critical');
            $indicator.addClass('mq-health-' + health.status);
            $indicator.find('.mq-health-text').text(health.status.charAt(0).toUpperCase() + health.status.slice(1));
            
            // Update metrics
            $('.mq-metrics-grid .mq-metric').each(function() {
                const $metric = $(this);
                const label = $metric.find('.mq-metric-label').text();
                
                switch(label) {
                    case 'Error Rate':
                        $metric.find('.mq-metric-value').text((health.metrics.error_rate * 100).toFixed(2) + '%');
                        break;
                    case 'Avg Response':
                        $metric.find('.mq-metric-value').text(health.metrics.avg_response.toFixed(2) + 's');
                        break;
                    case 'Peak Memory':
                        $metric.find('.mq-metric-value').text(health.metrics.peak_memory + 'MB');
                        break;
                    case 'Modern Traffic':
                        $metric.find('.mq-metric-value').text((health.metrics.modern_percentage * 100).toFixed(1) + '%');
                        break;
                }
            });
            
            // Update issues
            const $issues = $('.mq-health-issues');
            if (health.issues && health.issues.length > 0) {
                let issuesHtml = '<h3>Issues:</h3><ul>';
                health.issues.forEach(function(issue) {
                    issuesHtml += '<li>' + issue + '</li>';
                });
                issuesHtml += '</ul>';
                
                if ($issues.length) {
                    $issues.html(issuesHtml);
                } else {
                    $('.mq-health-status').append('<div class="mq-health-issues">' + issuesHtml + '</div>');
                }
            } else {
                $issues.remove();
            }
        },
        
        /**
         * Update stats display
         */
        updateStatsDisplay: function(stats) {
            // This would update charts if we implement them
            // For now, just update the timestamp
            const now = new Date();
            const timeStr = now.toLocaleTimeString();
            
            if (!$('#mq-last-update').length) {
                $('.mq-traffic-stats h2').append(' <small id="mq-last-update">(Last updated: ' + timeStr + ')</small>');
            } else {
                $('#mq-last-update').text('(Last updated: ' + timeStr + ')');
            }
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        RoutingAdmin.init();
    });
    
})(jQuery);