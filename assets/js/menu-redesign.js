/**
 * Money Quiz Menu Redesign JavaScript
 * 
 * Enhances the menu system with interactive features
 */

(function($) {
    'use strict';

    var MoneyQuizMenu = {
        
        /**
         * Initialize menu enhancements
         */
        init: function() {
            this.initSearch();
            this.initKeyboardShortcuts();
            this.initQuickActions();
            this.initTabNavigation();
            this.trackMenuUsage();
            this.showMigrationNotice();
        },
        
        /**
         * Initialize global search
         */
        initSearch: function() {
            // Add search box to admin bar
            var searchHtml = `
                <div class="mq-admin-search">
                    <input type="text" class="mq-search-input" placeholder="Search Money Quiz..." />
                    <div class="mq-search-results"></div>
                </div>
            `;
            
            $('#wp-admin-bar-money-quiz-quick').append(searchHtml);
            
            // Search functionality
            var searchTimeout;
            $('.mq-search-input').on('input', function() {
                var query = $(this).val();
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    $('.mq-search-results').hide();
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    MoneyQuizMenu.performSearch(query);
                }, 300);
            });
            
            // Close search on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.mq-admin-search').length) {
                    $('.mq-search-results').hide();
                }
            });
        },
        
        /**
         * Perform search
         */
        performSearch: function(query) {
            var results = this.searchMenuItems(query);
            var $resultsContainer = $('.mq-search-results');
            
            if (results.length === 0) {
                $resultsContainer.html('<div class="no-results">No results found</div>');
            } else {
                var html = '<ul>';
                results.forEach(function(item) {
                    html += `<li><a href="${item.url}">
                        <span class="result-title">${item.title}</span>
                        <span class="result-path">${item.path}</span>
                    </a></li>`;
                });
                html += '</ul>';
                $resultsContainer.html(html);
            }
            
            $resultsContainer.show();
        },
        
        /**
         * Search menu items
         */
        searchMenuItems: function(query) {
            var items = [
                // Dashboard
                { title: 'Dashboard Overview', path: 'Dashboard > Overview', url: 'admin.php?page=money-quiz-dashboard-overview' },
                { title: 'Recent Activity', path: 'Dashboard > Recent Activity', url: 'admin.php?page=money-quiz-dashboard-activity' },
                { title: 'Quick Stats', path: 'Dashboard > Quick Stats', url: 'admin.php?page=money-quiz-dashboard-stats' },
                
                // Quizzes
                { title: 'All Quizzes', path: 'Quizzes > All Quizzes', url: 'admin.php?page=money-quiz-quizzes-all' },
                { title: 'Add New Quiz', path: 'Quizzes > Add New', url: 'admin.php?page=money-quiz-quizzes-add-new' },
                { title: 'Questions Bank', path: 'Quizzes > Questions', url: 'admin.php?page=money-quiz-quizzes-questions' },
                { title: 'Archetypes', path: 'Quizzes > Archetypes', url: 'admin.php?page=money-quiz-quizzes-archetypes' },
                
                // Audience
                { title: 'Results & Analytics', path: 'Audience > Results', url: 'admin.php?page=money-quiz-audience-results' },
                { title: 'Prospects/Leads', path: 'Audience > Prospects', url: 'admin.php?page=money-quiz-audience-prospects' },
                { title: 'Email Campaigns', path: 'Audience > Campaigns', url: 'admin.php?page=money-quiz-audience-campaigns' },
                
                // Marketing
                { title: 'Call-to-Actions', path: 'Marketing > CTAs', url: 'admin.php?page=money-quiz-marketing-cta' },
                { title: 'Pop-ups', path: 'Marketing > Pop-ups', url: 'admin.php?page=money-quiz-marketing-popups' },
                
                // Settings
                { title: 'General Settings', path: 'Settings > General', url: 'admin.php?page=money-quiz-settings-general' },
                { title: 'Email Configuration', path: 'Settings > Email', url: 'admin.php?page=money-quiz-settings-email' },
                { title: 'Integrations', path: 'Settings > Integrations', url: 'admin.php?page=money-quiz-settings-integrations' },
                { title: 'Security & Privacy', path: 'Settings > Security', url: 'admin.php?page=money-quiz-settings-security' }
            ];
            
            query = query.toLowerCase();
            
            return items.filter(function(item) {
                return item.title.toLowerCase().includes(query) || 
                       item.path.toLowerCase().includes(query);
            }).slice(0, 10); // Limit to 10 results
        },
        
        /**
         * Initialize keyboard shortcuts
         */
        initKeyboardShortcuts: function() {
            $(document).on('keydown', function(e) {
                // Ctrl/Cmd + K for search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    $('.mq-search-input').focus();
                }
                
                // Ctrl/Cmd + N for new quiz
                if ((e.ctrlKey || e.metaKey) && e.key === 'n' && !e.shiftKey) {
                    e.preventDefault();
                    window.location.href = 'admin.php?page=money-quiz-quizzes-add-new';
                }
            });
        },
        
        /**
         * Initialize quick actions
         */
        initQuickActions: function() {
            // Add floating action button
            if ($('body').hasClass('mq-section-dashboard')) {
                var fabHtml = `
                    <div class="mq-fab">
                        <button class="mq-fab-button">
                            <span class="dashicons dashicons-plus"></span>
                        </button>
                        <div class="mq-fab-menu">
                            <a href="admin.php?page=money-quiz-quizzes-add-new" class="mq-fab-item">
                                <span class="dashicons dashicons-forms"></span>
                                New Quiz
                            </a>
                            <a href="admin.php?page=money-quiz-quizzes-questions" class="mq-fab-item">
                                <span class="dashicons dashicons-editor-help"></span>
                                New Question
                            </a>
                            <a href="admin.php?page=money-quiz-marketing-cta" class="mq-fab-item">
                                <span class="dashicons dashicons-megaphone"></span>
                                New CTA
                            </a>
                        </div>
                    </div>
                `;
                
                $('body').append(fabHtml);
                
                $('.mq-fab-button').on('click', function() {
                    $('.mq-fab').toggleClass('active');
                });
            }
        },
        
        /**
         * Initialize tab navigation
         */
        initTabNavigation: function() {
            $('.mq-nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var $tab = $(this);
                var target = $tab.data('target');
                
                // Update active tab
                $('.mq-nav-tab').removeClass('active');
                $tab.addClass('active');
                
                // Show/hide content
                $('.mq-tab-content').removeClass('active');
                $('#' + target).addClass('active');
                
                // Update URL without reload
                var newUrl = window.location.pathname + '?page=' + $tab.data('page') + '&tab=' + target;
                window.history.pushState({}, '', newUrl);
            });
        },
        
        /**
         * Track menu usage for smart suggestions
         */
        trackMenuUsage: function() {
            var page = this.getCurrentPage();
            if (!page) return;
            
            // Get usage data
            var usage = this.getUsageData();
            
            // Increment counter
            if (!usage[page]) {
                usage[page] = 0;
            }
            usage[page]++;
            
            // Save data
            this.saveUsageData(usage);
            
            // Update frequently used section
            this.updateFrequentlyUsed(usage);
        },
        
        /**
         * Get current page
         */
        getCurrentPage: function() {
            var urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('page');
        },
        
        /**
         * Get usage data
         */
        getUsageData: function() {
            var data = localStorage.getItem('mq_menu_usage');
            return data ? JSON.parse(data) : {};
        },
        
        /**
         * Save usage data
         */
        saveUsageData: function(data) {
            localStorage.setItem('mq_menu_usage', JSON.stringify(data));
        },
        
        /**
         * Update frequently used section
         */
        updateFrequentlyUsed: function(usage) {
            // Sort by usage
            var sorted = Object.entries(usage).sort((a, b) => b[1] - a[1]).slice(0, 5);
            
            if (sorted.length === 0) return;
            
            // Create frequently used widget
            var html = '<div class="mq-frequently-used mq-card">';
            html += '<h3 class="mq-card-title">Frequently Used</h3>';
            html += '<ul>';
            
            sorted.forEach(function(item) {
                var page = item[0];
                var title = MoneyQuizMenu.getPageTitle(page);
                if (title) {
                    html += `<li><a href="admin.php?page=${page}">${title}</a></li>`;
                }
            });
            
            html += '</ul></div>';
            
            // Add to dashboard
            if ($('.mq-dashboard-grid').length) {
                $('.mq-dashboard-grid').prepend(html);
            }
        },
        
        /**
         * Get page title from slug
         */
        getPageTitle: function(page) {
            var titles = {
                'money-quiz-dashboard-overview': 'Dashboard Overview',
                'money-quiz-quizzes-all': 'All Quizzes',
                'money-quiz-quizzes-add-new': 'Add New Quiz',
                'money-quiz-audience-results': 'Results & Analytics',
                'money-quiz-audience-prospects': 'Prospects/Leads',
                'money-quiz-settings-general': 'General Settings'
            };
            
            return titles[page] || null;
        },
        
        /**
         * Show migration notice if redirected
         */
        showMigrationNotice: function() {
            if (window.mqShowMigrationNotice) {
                var notice = `
                    <div class="notice notice-info is-dismissible mq-migration-notice">
                        <p><strong>Menu Updated:</strong> The Money Quiz menu has been reorganized for better navigation. 
                        You've been redirected to the new location. 
                        <a href="#" class="mq-migration-help">Learn more about the new menu structure</a></p>
                    </div>
                `;
                
                $('.wrap > h1').after(notice);
                
                $('.mq-migration-help').on('click', function(e) {
                    e.preventDefault();
                    MoneyQuizMenu.showMenuGuide();
                });
            }
        },
        
        /**
         * Show menu guide
         */
        showMenuGuide: function() {
            // Implementation for showing menu guide modal
            alert('Menu guide would show here with visual overview of new structure');
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MoneyQuizMenu.init();
        
        // Initialize page-specific modules
        initPageModules();
    });
    
    /**
     * Initialize page-specific functionality
     */
    function initPageModules() {
        var page = MoneyQuizMenu.getCurrentPage();
        
        // Dashboard pages
        if (page && page.startsWith('money-quiz-dashboard')) {
            initDashboardFeatures();
        }
        
        // Quiz management
        else if (page && page.startsWith('money-quiz-quizzes')) {
            initQuizFeatures();
        }
        
        // Audience/Results
        else if (page && page.startsWith('money-quiz-audience')) {
            initAudienceFeatures();
        }
        
        // Marketing
        else if (page && page.startsWith('money-quiz-marketing')) {
            initMarketingFeatures();
        }
        
        // Settings
        else if (page && page.startsWith('money-quiz-settings')) {
            initSettingsFeatures();
        }
    }
    
    /**
     * Dashboard Features
     */
    function initDashboardFeatures() {
        // Auto-refresh stats
        if ($('.mq-stat-card').length) {
            setInterval(function() {
                refreshDashboardStats();
            }, 60000); // Every minute
        }
        
        // Activity timeline filters
        $('#activity-type-filter').on('change', function() {
            var type = $(this).val();
            $('.mq-timeline-item').each(function() {
                var $item = $(this);
                if (type === 'all' || $item.data('type') === type) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        });
        
        // Quick action buttons
        $('.mq-quick-action-btn').on('click', function(e) {
            var action = $(this).data('action');
            if (action === 'refresh-stats') {
                e.preventDefault();
                refreshDashboardStats();
            }
        });
    }
    
    function refreshDashboardStats() {
        $('.mq-stat-card').addClass('mq-loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mq_refresh_dashboard_stats',
                nonce: money_quiz_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatCards(response.data);
                }
            },
            complete: function() {
                $('.mq-stat-card').removeClass('mq-loading');
            }
        });
    }
    
    function updateStatCards(data) {
        $.each(data, function(stat, value) {
            var $card = $('.mq-stat-card[data-stat="' + stat + '"]');
            if ($card.length) {
                $card.find('.mq-stat-value').text(value.formatted);
                if (value.change) {
                    var changeClass = value.change > 0 ? 'positive' : 'negative';
                    $card.find('.mq-stat-change')
                        .removeClass('positive negative')
                        .addClass(changeClass)
                        .text((value.change > 0 ? '↑' : '↓') + ' ' + Math.abs(value.change) + '%');
                }
            }
        });
    }
    
    /**
     * Quiz Features
     */
    function initQuizFeatures() {
        // Template selection
        $('.mq-template-card').on('click', function() {
            var template = $(this).data('template');
            loadQuizTemplate(template);
        });
        
        // Question management
        $('#add-question').on('click', addNewQuestion);
        $(document).on('click', '.delete-question', deleteQuestion);
        
        // Preview quiz
        $('#preview-quiz').on('click', function(e) {
            e.preventDefault();
            var quizId = $('#quiz_id').val();
            window.open(money_quiz_admin.preview_url + '?quiz_id=' + quizId, 'quiz_preview');
        });
    }
    
    function loadQuizTemplate(templateId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mq_load_quiz_template',
                template: templateId,
                nonce: money_quiz_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateQuizForm(response.data);
                }
            }
        });
    }
    
    function addNewQuestion(e) {
        e.preventDefault();
        var questionNum = $('.mq-question').length + 1;
        var template = $('#question-template').html().replace(/{{num}}/g, questionNum);
        $('#questions-list').append(template);
    }
    
    function deleteQuestion(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this question?')) {
            $(this).closest('.mq-question').remove();
            renumberQuestions();
        }
    }
    
    /**
     * Audience/Results Features
     */
    function initAudienceFeatures() {
        // Date range picker
        $('#date-range').on('change', function() {
            filterResults();
        });
        
        // Export functionality
        $('.export-btn').on('click', function(e) {
            e.preventDefault();
            var format = $(this).data('format');
            exportResults(format);
        });
        
        // Lead details modal
        $('.view-lead').on('click', function(e) {
            e.preventDefault();
            var leadId = $(this).data('lead-id');
            showLeadDetails(leadId);
        });
    }
    
    function filterResults() {
        var filters = {
            date_range: $('#date-range').val(),
            quiz_id: $('#filter-quiz').val(),
            archetype: $('#filter-archetype').val()
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mq_filter_results',
                filters: filters,
                nonce: money_quiz_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateResultsTable(response.data);
                }
            }
        });
    }
    
    /**
     * Marketing Features
     */
    function initMarketingFeatures() {
        // CTA preview
        $('#button_text, #button_color').on('input', updateCTAPreview);
        
        // Popup trigger settings
        $('#trigger_type').on('change', updateTriggerOptions);
        
        // Template usage
        $('.mq-template-item').on('click', function() {
            var template = $(this).data('template');
            useMarketingTemplate(template);
        });
    }
    
    function updateCTAPreview() {
        var text = $('#button_text').val() || 'Button Text';
        var color = $('#button_color').val() || '#0073aa';
        
        $('#cta-preview').html(
            '<button style="background:' + color + ';color:#fff;padding:10px 20px;border:none;border-radius:4px;">' + 
            text + '</button>'
        );
    }
    
    /**
     * Settings Features
     */
    function initSettingsFeatures() {
        // Provider toggles
        $('#email_provider').on('change', toggleEmailProvider);
        $('#email_marketing_provider').on('change', toggleMarketingProvider);
        
        // Test buttons
        $('.test-connection').on('click', function(e) {
            e.preventDefault();
            var provider = $(this).data('provider');
            testConnection(provider);
        });
        
        // GDPR toggle
        $('#gdpr_enabled').on('change', function() {
            $('#gdpr-text-wrapper').toggle(this.checked);
        });
    }
    
    function toggleEmailProvider() {
        var provider = $('#email_provider').val();
        $('.provider-settings').hide();
        $('#' + provider + '-settings').show();
    }
    
    function testConnection(provider) {
        var $btn = $('.test-connection[data-provider="' + provider + '"]');
        $btn.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mq_test_connection',
                provider: provider,
                nonce: money_quiz_admin.nonce
            },
            success: function(response) {
                var message = response.success ? 
                    'Connection successful!' : 
                    'Connection failed: ' + response.data;
                alert(message);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Test Connection');
            }
        });
    }
    
})(jQuery);