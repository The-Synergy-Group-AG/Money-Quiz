<?php
/**
 * React WordPress Integration
 * 
 * @package MoneyQuiz\React
 * @version 1.0.0
 */

namespace MoneyQuiz\React;

/**
 * WordPress Integration
 */
class Integration {
    
    /**
     * Generate integration code
     */
    public static function generateIntegration() {
        return <<<'JS'
// WordPress Integration Layer
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

// Configure WordPress API
apiFetch.use(apiFetch.createNonceMiddleware(window.moneyQuizAdmin.nonce));
apiFetch.use(apiFetch.createRootURLMiddleware(window.moneyQuizAdmin.apiUrl));

// WordPress Integration Service
class WPIntegration {
    constructor() {
        this.setupMediaUploader();
        this.setupNotices();
        this.setupShortcodes();
    }

    // Media uploader integration
    setupMediaUploader() {
        window.moneyQuizSelectMedia = (callback) => {
            const frame = wp.media({
                title: 'Select or Upload Media',
                button: { text: 'Use this media' },
                multiple: false
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                callback(attachment);
            });

            frame.open();
        };
    }

    // WordPress admin notices
    setupNotices() {
        this.notices = {
            success: (message) => this.showNotice(message, 'success'),
            error: (message) => this.showNotice(message, 'error'),
            warning: (message) => this.showNotice(message, 'warning'),
            info: (message) => this.showNotice(message, 'info')
        };
    }

    showNotice(message, type = 'info') {
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `<p>${message}</p>`;
        
        const container = document.querySelector('.wrap');
        if (container) {
            container.insertBefore(notice, container.firstChild);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => notice.remove(), 5000);
        }
    }

    // Shortcode generator
    generateShortcode(type, attributes = {}) {
        const attrs = Object.entries(attributes)
            .map(([key, value]) => `${key}="${value}"`)
            .join(' ');
        
        return `[money_quiz_${type} ${attrs}]`;
    }

    // Copy to clipboard
    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
            this.notices.success('Copied to clipboard!');
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            this.notices.success('Copied to clipboard!');
        }
    }

    // Open WordPress link
    openWPAdmin(path) {
        window.open(window.moneyQuizAdmin.adminUrl + path, '_blank');
    }

    // Get user capabilities
    getUserCapabilities() {
        return window.moneyQuizAdmin.userCapabilities || {};
    }

    // Check capability
    canUser(capability) {
        const caps = this.getUserCapabilities();
        return caps[capability] || false;
    }
}

// Export singleton
export default new WPIntegration();

// WordPress Hooks Integration
export const wpHooks = {
    addAction: (hook, callback, priority = 10) => {
        if (window.wp && window.wp.hooks) {
            window.wp.hooks.addAction(hook, 'money-quiz', callback, priority);
        }
    },
    
    addFilter: (hook, callback, priority = 10) => {
        if (window.wp && window.wp.hooks) {
            window.wp.hooks.addFilter(hook, 'money-quiz', callback, priority);
        }
    },
    
    doAction: (hook, ...args) => {
        if (window.wp && window.wp.hooks) {
            window.wp.hooks.doAction(hook, ...args);
        }
    },
    
    applyFilters: (hook, value, ...args) => {
        if (window.wp && window.wp.hooks) {
            return window.wp.hooks.applyFilters(hook, value, ...args);
        }
        return value;
    }
};

// WordPress Components Wrapper
export function WPButton({ variant = 'primary', ...props }) {
    const className = `button button-${variant} ${props.className || ''}`;
    return <button {...props} className={className} />;
}

export function WPSpinner() {
    return <span className="spinner is-active" />;
}

export function WPNotice({ type = 'info', isDismissible = true, children }) {
    const className = `notice notice-${type} ${isDismissible ? 'is-dismissible' : ''}`;
    return <div className={className}><p>{children}</p></div>;
}
JS;
    }
    
    /**
     * Initialize integration
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'checkDependencies']);
        add_action('admin_notices', [__CLASS__, 'showDependencyNotices']);
        add_filter('admin_body_class', [__CLASS__, 'addBodyClasses']);
    }
    
    /**
     * Check dependencies
     */
    public static function checkDependencies() {
        $missing = [];
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            $missing[] = 'WordPress 5.8 or higher';
        }
        
        // Check Node/npm (for development)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $node_version = shell_exec('node -v 2>&1');
            if (!$node_version || version_compare($node_version, 'v14.0.0', '<')) {
                $missing[] = 'Node.js 14.0 or higher';
            }
        }
        
        if (!empty($missing)) {
            set_transient('money_quiz_react_missing_deps', $missing, HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Show dependency notices
     */
    public static function showDependencyNotices() {
        $missing = get_transient('money_quiz_react_missing_deps');
        
        if ($missing && !empty($missing)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Money Quiz React:</strong> Missing dependencies:</p>';
            echo '<ul>';
            foreach ($missing as $dep) {
                echo '<li>' . esc_html($dep) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }
    
    /**
     * Add body classes
     */
    public static function addBodyClasses($classes) {
        if (isset($_GET['page']) && strpos($_GET['page'], 'money-quiz') !== false) {
            $classes .= ' money-quiz-react-admin';
        }
        return $classes;
    }
}