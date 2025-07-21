<?php
/**
 * Worker 7: CSRF Protection - AJAX Endpoints and Admin Actions
 * CVSS: 8.8 (High)
 * Focus: Securing all AJAX calls and admin state changes
 */

// PATCH 1: Comprehensive AJAX Security Framework
class MoneyQuizAjaxSecurity {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register all AJAX endpoints with security
        $this->register_ajax_handlers();
    }
    
    /**
     * Register secure AJAX handlers
     */
    private function register_ajax_handlers() {
        // Admin AJAX actions
        add_action('wp_ajax_mq_save_question', array($this, 'ajax_save_question'));
        add_action('wp_ajax_mq_delete_question', array($this, 'ajax_delete_question'));
        add_action('wp_ajax_mq_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_mq_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_mq_import_data', array($this, 'ajax_import_data'));
        
        // Frontend AJAX actions (if needed)
        add_action('wp_ajax_mq_save_progress', array($this, 'ajax_save_progress'));
        add_action('wp_ajax_nopriv_mq_save_progress', array($this, 'ajax_save_progress'));
    }
    
    /**
     * Verify AJAX request security
     */
    private function verify_ajax_request($action, $capability = 'manage_options') {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mq_ajax_' . $action)) {
            wp_send_json_error(array(
                'message' => __('Security verification failed', 'money-quiz')
            ), 403);
        }
        
        // Check user capability
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'money-quiz')
            ), 403);
        }
        
        return true;
    }
    
    /**
     * AJAX: Save Question
     */
    public function ajax_save_question() {
        $this->verify_ajax_request('save_question');
        
        // Validate and sanitize input
        $question_data = array(
            'question' => sanitize_text_field($_POST['question'] ?? ''),
            'money_type' => absint($_POST['money_type'] ?? 0),
            'archetype' => absint($_POST['archetype'] ?? 0),
            'id' => absint($_POST['id'] ?? 0)
        );
        
        // Validate required fields
        if (empty($question_data['question'])) {
            wp_send_json_error(array(
                'message' => __('Question text is required', 'money-quiz')
            ));
        }
        
        global $wpdb, $table_prefix;
        
        if ($question_data['id'] > 0) {
            // Update existing
            $result = $wpdb->update(
                $table_prefix . TABLE_MQ_MASTER,
                array(
                    'Question' => $question_data['question'],
                    'Money_Type' => $question_data['money_type'],
                    'Archetype' => $question_data['archetype']
                ),
                array('Master_ID' => $question_data['id']),
                array('%s', '%d', '%d'),
                array('%d')
            );
        } else {
            // Insert new
            $result = $wpdb->insert(
                $table_prefix . TABLE_MQ_MASTER,
                array(
                    'Question' => $question_data['question'],
                    'Money_Type' => $question_data['money_type'],
                    'Archetype' => $question_data['archetype']
                ),
                array('%s', '%d', '%d')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Question saved successfully', 'money-quiz'),
                'id' => $question_data['id'] ?: $wpdb->insert_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save question', 'money-quiz')
            ));
        }
    }
    
    /**
     * AJAX: Delete Question
     */
    public function ajax_delete_question() {
        $this->verify_ajax_request('delete_question');
        
        $question_id = absint($_POST['id'] ?? 0);
        
        if ($question_id <= 0) {
            wp_send_json_error(array(
                'message' => __('Invalid question ID', 'money-quiz')
            ));
        }
        
        global $wpdb, $table_prefix;
        
        $result = $wpdb->delete(
            $table_prefix . TABLE_MQ_MASTER,
            array('Master_ID' => $question_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Question deleted successfully', 'money-quiz')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete question', 'money-quiz')
            ));
        }
    }
    
    /**
     * AJAX: Save Settings
     */
    public function ajax_save_settings() {
        $this->verify_ajax_request('save_settings');
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        $sanitized_settings = array();
        
        // Define setting validation rules
        $setting_rules = array(
            'email_from' => 'sanitize_email',
            'email_subject' => 'sanitize_text_field',
            'admin_email' => 'sanitize_email',
            'results_page' => 'absint',
            'questions_per_page' => 'absint',
            'enable_newsletter' => 'absint',
            'enable_consultation' => 'absint'
        );
        
        foreach ($setting_rules as $key => $sanitize_func) {
            if (isset($settings[$key])) {
                $sanitized_settings[$key] = call_user_func($sanitize_func, $settings[$key]);
            }
        }
        
        // Save to options
        update_option('money_quiz_settings', $sanitized_settings);
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully', 'money-quiz')
        ));
    }
    
    /**
     * AJAX: Export Data
     */
    public function ajax_export_data() {
        $this->verify_ajax_request('export_data', 'export');
        
        $export_type = sanitize_key($_POST['export_type'] ?? 'all');
        
        global $wpdb, $table_prefix;
        
        $data = array();
        
        switch ($export_type) {
            case 'questions':
                $data['questions'] = $wpdb->get_results(
                    "SELECT * FROM {$table_prefix}" . TABLE_MQ_MASTER
                );
                break;
                
            case 'results':
                $data['results'] = $wpdb->get_results(
                    "SELECT * FROM {$table_prefix}" . TABLE_MQ_RESULTS
                );
                break;
                
            case 'prospects':
                $data['prospects'] = $wpdb->get_results(
                    "SELECT * FROM {$table_prefix}" . TABLE_MQ_PROSPECTS
                );
                break;
                
            case 'all':
            default:
                $data['questions'] = $wpdb->get_results(
                    "SELECT * FROM {$table_prefix}" . TABLE_MQ_MASTER
                );
                $data['archetypes'] = $wpdb->get_results(
                    "SELECT * FROM {$table_prefix}" . TABLE_MQ_ARCHETYPES
                );
                $data['prospects'] = $wpdb->get_results(
                    "SELECT * FROM {$table_prefix}" . TABLE_MQ_PROSPECTS
                );
                break;
        }
        
        wp_send_json_success(array(
            'data' => $data,
            'filename' => 'money-quiz-export-' . date('Y-m-d') . '.json'
        ));
    }
    
    /**
     * AJAX: Save Progress (Frontend)
     */
    public function ajax_save_progress() {
        // For frontend, verify with a different nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mq_frontend_ajax')) {
            wp_send_json_error(array(
                'message' => __('Security verification failed', 'money-quiz')
            ), 403);
        }
        
        $quiz_id = absint($_POST['quiz_id'] ?? 0);
        $step = absint($_POST['step'] ?? 0);
        $answers = isset($_POST['answers']) ? $_POST['answers'] : array();
        
        // Validate session token
        $token = sanitize_text_field($_POST['token'] ?? '');
        if (!MoneyQuizSession::verify_quiz_token($quiz_id, $token)) {
            wp_send_json_error(array(
                'message' => __('Invalid session', 'money-quiz')
            ));
        }
        
        // Save progress to session
        MoneyQuizSession::init();
        $_SESSION['mq_progress_' . $quiz_id] = array(
            'step' => $step,
            'answers' => array_map('absint', $answers),
            'timestamp' => time()
        );
        
        wp_send_json_success(array(
            'message' => __('Progress saved', 'money-quiz')
        ));
    }
}

// Initialize AJAX Security
MoneyQuizAjaxSecurity::get_instance();

// PATCH 2: JavaScript AJAX Security Layer
?>
<script type="text/javascript">
(function($) {
    'use strict';
    
    // Money Quiz AJAX Security Manager
    window.MoneyQuizAjax = {
        
        // Configuration
        config: {
            ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonces: {
                save_question: '<?php echo wp_create_nonce('mq_ajax_save_question'); ?>',
                delete_question: '<?php echo wp_create_nonce('mq_ajax_delete_question'); ?>',
                save_settings: '<?php echo wp_create_nonce('mq_ajax_save_settings'); ?>',
                export_data: '<?php echo wp_create_nonce('mq_ajax_export_data'); ?>'
            }
        },
        
        // Send secure AJAX request
        request: function(action, data, callback) {
            // Add security data
            data.action = 'mq_' + action;
            data.nonce = this.config.nonces[action] || '';
            
            // Show loading state
            this.showLoading();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    MoneyQuizAjax.hideLoading();
                    
                    if (response.success) {
                        if (callback) callback(response.data);
                        MoneyQuizAjax.showNotice('success', response.data.message);
                    } else {
                        MoneyQuizAjax.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    MoneyQuizAjax.hideLoading();
                    MoneyQuizAjax.showNotice('error', 'Request failed: ' + error);
                }
            });
        },
        
        // Show loading indicator
        showLoading: function() {
            $('#mq-ajax-loading').show();
        },
        
        // Hide loading indicator
        hideLoading: function() {
            $('#mq-ajax-loading').hide();
        },
        
        // Show notice
        showNotice: function(type, message) {
            var notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                         '<p>' + message + '</p>' +
                         '<button type="button" class="notice-dismiss"></button>' +
                         '</div>');
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Example usage
    $(document).ready(function() {
        
        // Save question
        $('#save-question').on('click', function(e) {
            e.preventDefault();
            
            var data = {
                question: $('#question').val(),
                money_type: $('#money_type').val(),
                archetype: $('#archetype').val(),
                id: $('#question_id').val()
            };
            
            MoneyQuizAjax.request('save_question', data, function(response) {
                // Handle success
                if (response.id) {
                    $('#question_id').val(response.id);
                }
            });
        });
        
        // Delete question with confirmation
        $('.delete-question').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this question?', 'money-quiz')); ?>')) {
                return;
            }
            
            var questionId = $(this).data('id');
            
            MoneyQuizAjax.request('delete_question', {id: questionId}, function() {
                // Remove row from table
                $('#question-' + questionId).fadeOut(function() {
                    $(this).remove();
                });
            });
        });
        
        // Save settings
        $('#save-settings').on('click', function(e) {
            e.preventDefault();
            
            var settings = {};
            $('#settings-form input, #settings-form select').each(function() {
                settings[$(this).attr('name')] = $(this).val();
            });
            
            MoneyQuizAjax.request('save_settings', {settings: settings});
        });
    });
    
})(jQuery);
</script>
<?php

// PATCH 3: Admin action URL security
class MoneyQuizAdminUrls {
    
    /**
     * Generate secure admin action URL
     */
    public static function action_url($action, $params = array(), $nonce_action = null) {
        if (!$nonce_action) {
            $nonce_action = 'mq_action_' . $action;
        }
        
        $params['action'] = $action;
        $url = add_query_arg($params, admin_url('admin.php'));
        
        return wp_nonce_url($url, $nonce_action);
    }
    
    /**
     * Verify admin action
     */
    public static function verify_action($nonce_action = null) {
        if (!$nonce_action && isset($_GET['action'])) {
            $nonce_action = 'mq_action_' . $_GET['action'];
        }
        
        check_admin_referer($nonce_action);
    }
}

// PATCH 4: Rate limiting for AJAX calls
class MoneyQuizRateLimit {
    
    const OPTION_PREFIX = 'mq_rate_limit_';
    const MAX_REQUESTS = 60; // per minute
    const TIME_WINDOW = 60; // seconds
    
    /**
     * Check if request is allowed
     */
    public static function check($action, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $key = self::OPTION_PREFIX . $action . '_' . $user_id;
        $data = get_transient($key);
        
        if (!$data) {
            $data = array(
                'count' => 0,
                'window_start' => time()
            );
        }
        
        // Reset if window expired
        if (time() - $data['window_start'] > self::TIME_WINDOW) {
            $data = array(
                'count' => 0,
                'window_start' => time()
            );
        }
        
        // Check limit
        if ($data['count'] >= self::MAX_REQUESTS) {
            return false;
        }
        
        // Increment and save
        $data['count']++;
        set_transient($key, $data, self::TIME_WINDOW);
        
        return true;
    }
}

// PATCH 5: Add rate limiting to AJAX handlers
add_action('wp_ajax_mq_save_question', function() {
    if (!MoneyQuizRateLimit::check('save_question')) {
        wp_send_json_error(array(
            'message' => __('Too many requests. Please wait a moment and try again.', 'money-quiz')
        ), 429);
    }
    // Continue with normal handler...
}, 9); // Priority 9 to run before main handler