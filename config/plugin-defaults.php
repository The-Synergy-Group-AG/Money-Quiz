<?php
/**
 * Plugin Default Configuration
 * 
 * Central location for all plugin defaults including author information
 * 
 * @package MoneyQuiz
 * @subpackage Config
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Author and Company Information
define('MONEY_QUIZ_AUTHOR', 'The Synergy Group AG');
define('MONEY_QUIZ_AUTHOR_URI', 'https://thesynergygroup.ch');
define('MONEY_QUIZ_AUTHOR_EMAIL', 'Andre@thesynergygroup.ch');
define('MONEY_QUIZ_SUPPORT_EMAIL', 'Andre@thesynergygroup.ch');

// Override default admin email for plugin notifications
add_filter('mq_admin_email', function($email) {
    // Use plugin author email for isolated environments
    if (defined('MONEY_QUIZ_ISOLATED_ENV') && MONEY_QUIZ_ISOLATED_ENV) {
        return MONEY_QUIZ_AUTHOR_EMAIL;
    }
    return $email;
});

// Default notification settings
add_filter('mq_default_notification_emails', function($emails) {
    // Add author email to notification list
    if (!in_array(MONEY_QUIZ_AUTHOR_EMAIL, $emails)) {
        $emails[] = MONEY_QUIZ_AUTHOR_EMAIL;
    }
    return $emails;
});

// Plugin information for admin displays
add_filter('mq_plugin_info', function($info) {
    return array_merge($info, [
        'author' => MONEY_QUIZ_AUTHOR,
        'author_uri' => MONEY_QUIZ_AUTHOR_URI,
        'support_email' => MONEY_QUIZ_SUPPORT_EMAIL,
        'version' => MONEY_QUIZ_VERSION,
        'environment' => defined('MONEY_QUIZ_ISOLATED_ENV') ? 'isolated' : 'standard'
    ]);
});

// Email sender information
add_filter('wp_mail_from_name', function($from_name) {
    if (strpos($from_name, 'Money Quiz') !== false || strpos($from_name, 'WordPress') !== false) {
        return get_bloginfo('name') . ' - Money Quiz';
    }
    return $from_name;
});

add_filter('wp_mail_from', function($from_email) {
    // Only override if it's the default WordPress email
    if ($from_email === 'wordpress@' . parse_url(home_url(), PHP_URL_HOST)) {
        $domain = parse_url(MONEY_QUIZ_AUTHOR_URI, PHP_URL_HOST);
        return 'noreply@' . $domain;
    }
    return $from_email;
});