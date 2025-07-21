/**
 * Money Quiz Analytics Dashboard JavaScript
 * 
 * Handles interactive charts, data updates, and user interactions
 * for the analytics dashboard.
 */

(function($) {
    'use strict';

    const MoneyQuizAnalytics = {
        charts: {},
        currentPeriod: '30days',
        
        /**
         * Initialize analytics dashboard
         */
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.initDateRangePicker();
            this.loadTabContent();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Date range selector
            $('#quick-date-range').on('change', this.handleDateRangeChange.bind(this));
            
            // Refresh button
            $('#refresh-analytics').on('click', this.refreshDashboard.bind(this));
            
            // Export button
            $('#export-analytics').on('click', this.showExportModal.bind(this));
            
            // Export modal
            $('#confirm-export').on('click', this.exportData.bind(this));
            $('#cancel-export').on('click', this.hideExportModal.bind(this));
            
            // Tabs
            $('.analytics-tabs .tab-nav a').on('click', this.handleTabClick.bind(this));
            
            // Window resize
            $(window).on('resize', this.handleResize.bind(this));
        },
        
        /**
         * Initialize all charts
         */
        initCharts: function() {
            // Completions chart
            this.initCompletionsChart();
            
            // Archetype distribution chart
            this.initArchetypeChart();
            
            // Response patterns chart
            this.initResponsePatternsChart();
        },
        
        /**
         * Initialize completions line chart
         */
        initCompletionsChart: function() {
            const ctx = document.getElementById('completions-chart');
            if (!ctx) return;
            
            const data = window.moneyQuizAnalyticsData.trends.completions;
            
            this.charts.completions = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.period),
                    datasets: [{
                        label: 'Quiz Completions',
                        data: data.map(d => d.count),
                        borderColor: moneyQuizAnalytics.chartColors.primary,
                        backgroundColor: moneyQuizAnalytics.chartColors.primary + '20',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize archetype distribution chart
         */
        initArchetypeChart: function() {
            const ctx = document.getElementById('archetype-chart');
            if (!ctx) return;
            
            const data = window.moneyQuizAnalyticsData.archetype_distribution;
            
            this.charts.archetype = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.name),
                    datasets: [{
                        data: data.map(d => d.count),
                        backgroundColor: data.map(d => d.color || this.getRandomColor()),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    return data.labels.map((label, i) => ({
                                        text: label + ' (' + data.datasets[0].data[i] + ')',
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        hidden: false,
                                        index: i
                                    }));
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const percentage = window.moneyQuizAnalyticsData
                                        .archetype_distribution[context.dataIndex].percentage;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize response patterns chart
         */
        initResponsePatternsChart: function() {
            const ctx = document.getElementById('response-patterns-chart');
            if (!ctx) return;
            
            const data = window.moneyQuizAnalyticsData.engagement_metrics.response_patterns;
            
            this.charts.responsePatterns = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: data.map(d => d.category),
                    datasets: [{
                        label: 'Average Response',
                        data: data.map(d => d.avg_response),
                        borderColor: moneyQuizAnalytics.chartColors.info,
                        backgroundColor: moneyQuizAnalytics.chartColors.info + '20',
                        pointBackgroundColor: moneyQuizAnalytics.chartColors.info,
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: moneyQuizAnalytics.chartColors.info
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 8,
                            ticks: {
                                stepSize: 2
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize date range picker
         */
        initDateRangePicker: function() {
            $('#custom-date-range').daterangepicker({
                opens: 'left',
                locale: {
                    format: 'YYYY-MM-DD'
                },
                maxDate: moment(),
                autoUpdateInput: false
            });
            
            $('#custom-date-range').on('apply.daterangepicker', (ev, picker) => {
                const startDate = picker.startDate.format('YYYY-MM-DD');
                const endDate = picker.endDate.format('YYYY-MM-DD');
                $(ev.target).val(startDate + ' - ' + endDate);
                this.loadAnalytics('custom', { start_date: startDate, end_date: endDate });
            });
        },
        
        /**
         * Handle date range change
         */
        handleDateRangeChange: function(e) {
            const period = $(e.target).val();
            
            if (period === 'custom') {
                $('#custom-date-range').show().click();
            } else {
                $('#custom-date-range').hide();
                this.currentPeriod = period;
                this.loadAnalytics(period);
            }
        },
        
        /**
         * Load analytics data
         */
        loadAnalytics: function(period, customDates = {}) {
            const $dashboardContent = $('.money-quiz-analytics');
            $dashboardContent.addClass('loading');
            
            $.ajax({
                url: moneyQuizAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'money_quiz_get_analytics',
                    nonce: moneyQuizAnalytics.nonce,
                    type: 'overview',
                    period: period,
                    ...customDates
                },
                success: (response) => {
                    if (response.success) {
                        window.moneyQuizAnalyticsData = response.data;
                        this.updateDashboard(response.data);
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError(moneyQuizAnalytics.i18n.error);
                },
                complete: () => {
                    $dashboardContent.removeClass('loading');
                }
            });
        },
        
        /**
         * Update dashboard with new data
         */
        updateDashboard: function(data) {
            // Update summary cards
            this.updateSummaryCards(data.summary);
            
            // Update charts
            this.updateCharts(data);
            
            // Update funnel
            this.updateConversionFunnel(data.conversion_funnel);
            
            // Update activity timeline
            this.updateActivityTimeline(data.recent_activity);
        },
        
        /**
         * Update summary cards
         */
        updateSummaryCards: function(summary) {
            $('.summary-card').each(function(index) {
                const $card = $(this);
                const cardData = Object.values(summary)[index];
                
                if (cardData) {
                    $card.find('.card-value').text(cardData.value || cardData);
                    
                    // Update change indicator
                    const change = summary.growth ? summary.growth[Object.keys(summary.growth)[index]] : 0;
                    const $change = $card.find('.card-change');
                    
                    $change.removeClass('positive negative neutral');
                    if (change > 0) {
                        $change.addClass('positive')
                            .html('<span class="dashicons dashicons-arrow-up-alt"></span>' + Math.abs(change) + '%');
                    } else if (change < 0) {
                        $change.addClass('negative')
                            .html('<span class="dashicons dashicons-arrow-down-alt"></span>' + Math.abs(change) + '%');
                    } else {
                        $change.addClass('neutral')
                            .html('<span class="dashicons dashicons-minus"></span>0%');
                    }
                }
            });
        },
        
        /**
         * Update charts with new data
         */
        updateCharts: function(data) {
            // Update completions chart
            if (this.charts.completions && data.trends.completions) {
                this.charts.completions.data.labels = data.trends.completions.map(d => d.period);
                this.charts.completions.data.datasets[0].data = data.trends.completions.map(d => d.count);
                this.charts.completions.update();
            }
            
            // Update archetype chart
            if (this.charts.archetype && data.archetype_distribution) {
                this.charts.archetype.data.labels = data.archetype_distribution.map(d => d.name);
                this.charts.archetype.data.datasets[0].data = data.archetype_distribution.map(d => d.count);
                this.charts.archetype.data.datasets[0].backgroundColor = data.archetype_distribution.map(d => d.color || this.getRandomColor());
                this.charts.archetype.update();
            }
            
            // Update response patterns
            if (this.charts.responsePatterns && data.engagement_metrics.response_patterns) {
                this.charts.responsePatterns.data.labels = data.engagement_metrics.response_patterns.map(d => d.category);
                this.charts.responsePatterns.data.datasets[0].data = data.engagement_metrics.response_patterns.map(d => d.avg_response);
                this.charts.responsePatterns.update();
            }
        },
        
        /**
         * Update conversion funnel
         */
        updateConversionFunnel: function(funnel) {
            $('.funnel-stage').each(function(index) {
                if (funnel[index]) {
                    const stage = funnel[index];
                    $(this).find('.stage-count').text(stage.count.toLocaleString());
                    $(this).find('.stage-fill').css('width', stage.rate + '%');
                    $(this).find('.stage-rate').text(stage.rate + '%');
                }
            });
        },
        
        /**
         * Update activity timeline
         */
        updateActivityTimeline: function(activities) {
            const $timeline = $('.activity-timeline .timeline');
            $timeline.empty();
            
            activities.forEach(activity => {
                const $item = $('<div class="timeline-item">')
                    .append('<div class="timeline-marker"></div>')
                    .append(
                        $('<div class="timeline-content">')
                            .append(`<div class="activity-description">${activity.description}</div>`)
                            .append(`<div class="activity-time">${activity.time}</div>`)
                    );
                
                $timeline.append($item);
            });
        },
        
        /**
         * Handle tab clicks
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $link = $(e.target);
            const tabId = $link.data('tab');
            
            // Update active states
            $('.tab-nav li').removeClass('active');
            $link.parent().addClass('active');
            
            $('.tab-pane').removeClass('active');
            $('#' + tabId).addClass('active');
            
            // Load tab content if not already loaded
            this.loadTabContent(tabId);
        },
        
        /**
         * Load tab content
         */
        loadTabContent: function(tabId = null) {
            const tabs = tabId ? [tabId] : ['demographics'];
            
            tabs.forEach(tab => {
                const $pane = $('#' + tab);
                if ($pane.find('.loading-spinner').length) {
                    $.ajax({
                        url: moneyQuizAnalytics.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'money_quiz_get_analytics',
                            nonce: moneyQuizAnalytics.nonce,
                            type: 'custom',
                            period: this.currentPeriod,
                            sections: [tab]
                        },
                        success: (response) => {
                            if (response.success) {
                                this.renderTabContent(tab, response.data.data[tab]);
                            }
                        }
                    });
                }
            });
        },
        
        /**
         * Render tab content
         */
        renderTabContent: function(tab, data) {
            const $pane = $('#' + tab);
            let content = '';
            
            switch(tab) {
                case 'demographics':
                    content = this.renderDemographicsContent(data);
                    break;
                case 'behavior':
                    content = this.renderBehaviorContent(data);
                    break;
                case 'performance':
                    content = this.renderPerformanceContent(data);
                    break;
                case 'questions':
                    content = this.renderQuestionsContent(data);
                    break;
            }
            
            $pane.html(content);
        },
        
        /**
         * Show export modal
         */
        showExportModal: function() {
            $('#export-modal').fadeIn();
        },
        
        /**
         * Hide export modal
         */
        hideExportModal: function() {
            $('#export-modal').fadeOut();
        },
        
        /**
         * Export data
         */
        exportData: function() {
            const format = $('input[name="export_format"]:checked').val();
            const sections = $('input[name="export_section[]"]:checked').map(function() {
                return $(this).val();
            }).get();
            
            $.ajax({
                url: moneyQuizAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'money_quiz_export_analytics',
                    nonce: moneyQuizAnalytics.nonce,
                    format: format,
                    sections: sections,
                    period: this.currentPeriod
                },
                success: (response) => {
                    if (response.success) {
                        window.location.href = response.data.url;
                        this.showSuccess(moneyQuizAnalytics.i18n.exportSuccess);
                        this.hideExportModal();
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError(moneyQuizAnalytics.i18n.exportError);
                }
            });
        },
        
        /**
         * Refresh dashboard
         */
        refreshDashboard: function() {
            this.loadAnalytics(this.currentPeriod);
        },
        
        /**
         * Handle window resize
         */
        handleResize: function() {
            // Update charts on resize
            Object.values(this.charts).forEach(chart => {
                if (chart) {
                    chart.resize();
                }
            });
        },
        
        /**
         * Get random color
         */
        getRandomColor: function() {
            const colors = Object.values(moneyQuizAnalytics.chartColors);
            return colors[Math.floor(Math.random() * colors.length)];
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            // Implementation depends on notification system
            alert(message);
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message) {
            // Implementation depends on notification system
            alert(message);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        MoneyQuizAnalytics.init();
    });
    
})(jQuery);