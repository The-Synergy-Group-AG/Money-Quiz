/**
 * Migration Notice JavaScript
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

(function($) {
    'use strict';
    
    var MQMigrationNotice = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkForTour();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Dismiss notice
            $(document).on('click', '.mq-dismiss-notice', this.dismissNotice);
            
            // Show menu guide
            $(document).on('click', '.mq-show-menu-guide', this.showMenuGuide);
            
            // Start tour
            $(document).on('click', '.mq-start-tour', this.startTour);
            
            // Complete onboarding
            $(document).on('click', '.mq-complete-onboarding', this.completeOnboarding);
            
            // Close menu guide
            $(document).on('click', '.mq-menu-guide-close', this.closeMenuGuide);
            $(document).on('click', '.mq-menu-guide-overlay', this.closeMenuGuide);
        },
        
        /**
         * Dismiss notice
         */
        dismissNotice: function(e) {
            e.preventDefault();
            
            var $notice = $(this).closest('.mq-migration-notice');
            var index = $notice.data('notice-index');
            
            // Fade out notice
            $notice.fadeOut(300);
            
            // Save dismissal
            $.ajax({
                url: mq_migration.ajax_url,
                type: 'POST',
                data: {
                    action: 'mq_dismiss_migration_notice',
                    index: index,
                    nonce: mq_migration.nonce
                }
            });
        },
        
        /**
         * Show menu guide
         */
        showMenuGuide: function(e) {
            e.preventDefault();
            
            // Create guide if it doesn't exist
            if ($('.mq-menu-guide').length === 0) {
                MQMigrationNotice.createMenuGuide();
            }
            
            // Show guide
            $('.mq-menu-guide-overlay, .mq-menu-guide').fadeIn(300);
        },
        
        /**
         * Create menu guide
         */
        createMenuGuide: function() {
            var html = `
                <div class="mq-menu-guide-overlay"></div>
                <div class="mq-menu-guide">
                    <div class="mq-menu-guide-header">
                        <h2>New Menu Structure</h2>
                        <button type="button" class="mq-menu-guide-close">&times;</button>
                    </div>
                    <div class="mq-menu-guide-content">
                        <p>The Money Quiz menu has been reorganized into logical sections for a better workflow:</p>
                        
                        <div class="mq-menu-comparison">
                            <div class="mq-menu-column mq-menu-old">
                                <h3>Old Menu Structure</h3>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-dashboard"></span>
                                    Money Quiz (Dashboard)
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    Questions
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-groups"></span>
                                    Archetypes
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-id"></span>
                                    Leads
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-chart-area"></span>
                                    Results
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                    Settings
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-admin-plugins"></span>
                                    Integration
                                </div>
                            </div>
                            
                            <div class="mq-menu-column mq-menu-new">
                                <h3>New Menu Structure</h3>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-dashboard"></span>
                                    <strong>Dashboard</strong>
                                    <ul style="margin-left: 28px; margin-top: 5px;">
                                        <li>Overview</li>
                                        <li>Recent Activity</li>
                                        <li>Quick Stats</li>
                                    </ul>
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-forms"></span>
                                    <strong>Quizzes</strong>
                                    <ul style="margin-left: 28px; margin-top: 5px;">
                                        <li>All Quizzes</li>
                                        <li>Add New</li>
                                        <li>Questions</li>
                                        <li>Archetypes</li>
                                    </ul>
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-groups"></span>
                                    <strong>Audience</strong>
                                    <ul style="margin-left: 28px; margin-top: 5px;">
                                        <li>Results</li>
                                        <li>Prospects/Leads</li>
                                        <li>Campaigns</li>
                                    </ul>
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-megaphone"></span>
                                    <strong>Marketing</strong>
                                    <ul style="margin-left: 28px; margin-top: 5px;">
                                        <li>Call-to-Actions</li>
                                        <li>Pop-ups</li>
                                    </ul>
                                </div>
                                <div class="mq-menu-item">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <strong>Settings</strong>
                                    <ul style="margin-left: 28px; margin-top: 5px;">
                                        <li>General</li>
                                        <li>Email</li>
                                        <li>Integrations</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div style="background: #f0f8ff; padding: 20px; border-radius: 8px; margin-top: 20px;">
                            <h3>Key Improvements:</h3>
                            <ul>
                                <li>📊 <strong>Grouped by workflow</strong> - Related features are now together</li>
                                <li>🔍 <strong>Global search</strong> - Find anything quickly with Ctrl/Cmd + K</li>
                                <li>⚡ <strong>Quick actions</strong> - Common tasks are just one click away</li>
                                <li>📱 <strong>Responsive design</strong> - Works great on all devices</li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(html);
        },
        
        /**
         * Close menu guide
         */
        closeMenuGuide: function(e) {
            if (e) e.preventDefault();
            $('.mq-menu-guide-overlay, .mq-menu-guide').fadeOut(300);
        },
        
        /**
         * Start tour
         */
        startTour: function(e) {
            e.preventDefault();
            
            // Initialize tour
            MQMigrationNotice.tour = {
                steps: [
                    {
                        element: '#toplevel_page_money-quiz',
                        title: 'New Menu Location',
                        content: 'The Money Quiz menu has moved here with a new organized structure.',
                        position: 'right'
                    },
                    {
                        element: '.mq-search-input',
                        title: 'Global Search',
                        content: 'Quickly find any page or feature. Press Ctrl/Cmd + K anytime!',
                        position: 'bottom'
                    },
                    {
                        element: '.mq-stat-card:first',
                        title: 'Live Statistics',
                        content: 'See your quiz performance at a glance with auto-updating stats.',
                        position: 'bottom'
                    },
                    {
                        element: '.mq-quick-actions',
                        title: 'Quick Actions',
                        content: 'Common tasks are now just one click away.',
                        position: 'top'
                    }
                ],
                currentStep: 0
            };
            
            // Start first step
            MQMigrationNotice.showTourStep(0);
        },
        
        /**
         * Show tour step
         */
        showTourStep: function(step) {
            var tour = MQMigrationNotice.tour;
            
            if (step >= tour.steps.length) {
                MQMigrationNotice.endTour();
                return;
            }
            
            var stepData = tour.steps[step];
            var $element = $(stepData.element);
            
            if ($element.length === 0) {
                // Skip to next step if element not found
                MQMigrationNotice.showTourStep(step + 1);
                return;
            }
            
            // Remove previous highlights
            $('.mq-tour-highlight').removeClass('mq-tour-highlight');
            $('.mq-tour-tooltip').remove();
            
            // Highlight element
            $element.addClass('mq-tour-highlight');
            
            // Create tooltip
            var tooltip = `
                <div class="mq-tour-tooltip">
                    <h3>${stepData.title}</h3>
                    <p>${stepData.content}</p>
                    <div class="mq-tour-actions">
                        <span class="mq-tour-progress">Step ${step + 1} of ${tour.steps.length}</span>
                        <div class="mq-tour-buttons">
                            ${step > 0 ? '<button type="button" class="button mq-tour-prev">Previous</button>' : ''}
                            ${step < tour.steps.length - 1 ? '<button type="button" class="button button-primary mq-tour-next">Next</button>' : '<button type="button" class="button button-primary mq-tour-finish">Finish</button>'}
                            <button type="button" class="button mq-tour-skip">Skip Tour</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(tooltip);
            
            // Position tooltip
            MQMigrationNotice.positionTooltip($element, stepData.position);
            
            // Bind tooltip events
            $('.mq-tour-next').on('click', function() {
                MQMigrationNotice.showTourStep(step + 1);
            });
            
            $('.mq-tour-prev').on('click', function() {
                MQMigrationNotice.showTourStep(step - 1);
            });
            
            $('.mq-tour-skip, .mq-tour-finish').on('click', function() {
                MQMigrationNotice.endTour();
            });
            
            // Show overlay
            if ($('.mq-tour-overlay').length === 0) {
                $('body').append('<div class="mq-tour-overlay"></div>');
            }
            $('.mq-tour-overlay').show();
        },
        
        /**
         * Position tooltip
         */
        positionTooltip: function($element, position) {
            var $tooltip = $('.mq-tour-tooltip');
            var offset = $element.offset();
            var width = $element.outerWidth();
            var height = $element.outerHeight();
            
            var tooltipWidth = $tooltip.outerWidth();
            var tooltipHeight = $tooltip.outerHeight();
            
            var top, left;
            
            switch(position) {
                case 'top':
                    top = offset.top - tooltipHeight - 10;
                    left = offset.left + (width - tooltipWidth) / 2;
                    break;
                    
                case 'bottom':
                    top = offset.top + height + 10;
                    left = offset.left + (width - tooltipWidth) / 2;
                    break;
                    
                case 'left':
                    top = offset.top + (height - tooltipHeight) / 2;
                    left = offset.left - tooltipWidth - 10;
                    break;
                    
                case 'right':
                    top = offset.top + (height - tooltipHeight) / 2;
                    left = offset.left + width + 10;
                    break;
            }
            
            // Ensure tooltip stays within viewport
            var windowWidth = $(window).width();
            var windowHeight = $(window).height();
            
            if (left < 10) left = 10;
            if (left + tooltipWidth > windowWidth - 10) left = windowWidth - tooltipWidth - 10;
            
            if (top < 10) top = 10;
            if (top + tooltipHeight > windowHeight - 10) top = windowHeight - tooltipHeight - 10;
            
            $tooltip.css({
                top: top + 'px',
                left: left + 'px'
            });
        },
        
        /**
         * End tour
         */
        endTour: function() {
            $('.mq-tour-highlight').removeClass('mq-tour-highlight');
            $('.mq-tour-tooltip').remove();
            $('.mq-tour-overlay').fadeOut(300, function() {
                $(this).remove();
            });
            
            // Mark tour as completed
            $.ajax({
                url: mq_migration.ajax_url,
                type: 'POST',
                data: {
                    action: 'mq_complete_tour',
                    nonce: mq_migration.nonce
                }
            });
        },
        
        /**
         * Complete onboarding
         */
        completeOnboarding: function(e) {
            e.preventDefault();
            
            var hideOldMenu = $('#mq-disable-old-menu').is(':checked');
            
            // Fade out notice
            $('.mq-onboarding-notice').fadeOut(300);
            
            // Save completion
            $.ajax({
                url: mq_migration.ajax_url,
                type: 'POST',
                data: {
                    action: 'mq_complete_onboarding',
                    hide_old_menu: hideOldMenu,
                    nonce: mq_migration.nonce
                },
                success: function() {
                    if (hideOldMenu) {
                        // Reload to hide old menu
                        window.location.reload();
                    }
                }
            });
        },
        
        /**
         * Check if should start tour
         */
        checkForTour: function() {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('mq_tour') === '1') {
                setTimeout(function() {
                    MQMigrationNotice.startTour();
                }, 500);
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MQMigrationNotice.init();
    });
    
})(jQuery);