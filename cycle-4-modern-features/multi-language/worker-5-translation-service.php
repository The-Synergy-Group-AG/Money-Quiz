<?php
/**
 * Money Quiz Plugin - Translation Service
 * Worker 5: Multi-language Support
 * 
 * Provides comprehensive internationalization and localization capabilities
 * for the Money Quiz plugin, supporting multiple languages and regions.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use MoneyQuiz\Models\Settings;
use MoneyQuiz\Utilities\CacheUtil;
use MoneyQuiz\Utilities\ArrayUtil;
use MoneyQuiz\Utilities\StringUtil;

/**
 * Translation Service Class
 * 
 * Handles all translation and localization functionality
 */
class TranslationService {
    
    /**
     * Available languages
     * 
     * @var array
     */
    protected $languages = array();
    
    /**
     * Current language
     * 
     * @var string
     */
    protected $current_language;
    
    /**
     * Translation strings
     * 
     * @var array
     */
    protected $translations = array();
    
    /**
     * Text domain
     * 
     * @var string
     */
    const TEXT_DOMAIN = 'money-quiz';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_languages();
        $this->set_current_language();
        $this->load_translations();
        
        // Register hooks
        add_action( 'init', array( $this, 'init_textdomain' ) );
        add_filter( 'locale', array( $this, 'filter_locale' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_language_assets' ) );
        add_filter( 'money_quiz_translatable_strings', array( $this, 'register_strings' ) );
    }
    
    /**
     * Initialize available languages
     */
    protected function init_languages() {
        $this->languages = array(
            'en_US' => array(
                'name' => 'English (US)',
                'native' => 'English',
                'flag' => '🇺🇸',
                'rtl' => false
            ),
            'es_ES' => array(
                'name' => 'Spanish (Spain)',
                'native' => 'Español',
                'flag' => '🇪🇸',
                'rtl' => false
            ),
            'fr_FR' => array(
                'name' => 'French (France)',
                'native' => 'Français',
                'flag' => '🇫🇷',
                'rtl' => false
            ),
            'de_DE' => array(
                'name' => 'German (Germany)',
                'native' => 'Deutsch',
                'flag' => '🇩🇪',
                'rtl' => false
            ),
            'it_IT' => array(
                'name' => 'Italian (Italy)',
                'native' => 'Italiano',
                'flag' => '🇮🇹',
                'rtl' => false
            ),
            'pt_BR' => array(
                'name' => 'Portuguese (Brazil)',
                'native' => 'Português',
                'flag' => '🇧🇷',
                'rtl' => false
            ),
            'nl_NL' => array(
                'name' => 'Dutch (Netherlands)',
                'native' => 'Nederlands',
                'flag' => '🇳🇱',
                'rtl' => false
            ),
            'pl_PL' => array(
                'name' => 'Polish (Poland)',
                'native' => 'Polski',
                'flag' => '🇵🇱',
                'rtl' => false
            ),
            'ru_RU' => array(
                'name' => 'Russian (Russia)',
                'native' => 'Русский',
                'flag' => '🇷🇺',
                'rtl' => false
            ),
            'ja' => array(
                'name' => 'Japanese',
                'native' => '日本語',
                'flag' => '🇯🇵',
                'rtl' => false
            ),
            'zh_CN' => array(
                'name' => 'Chinese (Simplified)',
                'native' => '简体中文',
                'flag' => '🇨🇳',
                'rtl' => false
            ),
            'ar' => array(
                'name' => 'Arabic',
                'native' => 'العربية',
                'flag' => '🇸🇦',
                'rtl' => true
            ),
            'he_IL' => array(
                'name' => 'Hebrew (Israel)',
                'native' => 'עברית',
                'flag' => '🇮🇱',
                'rtl' => true
            ),
            'hi_IN' => array(
                'name' => 'Hindi (India)',
                'native' => 'हिन्दी',
                'flag' => '🇮🇳',
                'rtl' => false
            ),
            'ko_KR' => array(
                'name' => 'Korean (South Korea)',
                'native' => '한국어',
                'flag' => '🇰🇷',
                'rtl' => false
            )
        );
        
        // Allow filtering of available languages
        $this->languages = apply_filters( 'money_quiz_languages', $this->languages );
    }
    
    /**
     * Set current language
     */
    protected function set_current_language() {
        // Check URL parameter
        if ( isset( $_GET['lang'] ) && $this->is_valid_language( $_GET['lang'] ) ) {
            $this->current_language = sanitize_text_field( $_GET['lang'] );
            $this->save_language_preference( $this->current_language );
        }
        // Check user preference
        elseif ( $saved_lang = $this->get_saved_language() ) {
            $this->current_language = $saved_lang;
        }
        // Check browser language
        elseif ( $browser_lang = $this->detect_browser_language() ) {
            $this->current_language = $browser_lang;
        }
        // Default to site locale
        else {
            $this->current_language = get_locale();
        }
        
        // Ensure language is available
        if ( ! isset( $this->languages[ $this->current_language ] ) ) {
            $this->current_language = 'en_US';
        }
    }
    
    /**
     * Initialize text domain
     */
    public function init_textdomain() {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) ) . '/languages/'
        );
    }
    
    /**
     * Filter locale for Money Quiz
     * 
     * @param string $locale Current locale
     * @return string Modified locale
     */
    public function filter_locale( $locale ) {
        if ( $this->is_money_quiz_context() ) {
            return $this->current_language;
        }
        
        return $locale;
    }
    
    /**
     * Load translations for current language
     */
    protected function load_translations() {
        $cache_key = 'translations_' . $this->current_language;
        
        $this->translations = CacheUtil::remember( $cache_key, function() {
            return $this->fetch_translations( $this->current_language );
        }, DAY_IN_SECONDS );
    }
    
    /**
     * Fetch translations from file or database
     * 
     * @param string $language Language code
     * @return array Translations
     */
    protected function fetch_translations( $language ) {
        $translations = array();
        
        // Load from MO file
        $mo_file = MONEY_QUIZ_PLUGIN_DIR . 'languages/' . self::TEXT_DOMAIN . '-' . $language . '.mo';
        if ( file_exists( $mo_file ) ) {
            $mo = new \MO();
            if ( $mo->import_from_file( $mo_file ) ) {
                foreach ( $mo->entries as $entry ) {
                    $translations[ $entry->singular ] = $entry->translations[0];
                }
            }
        }
        
        // Load custom translations from database
        $custom = $this->get_custom_translations( $language );
        $translations = array_merge( $translations, $custom );
        
        // Load JavaScript translations
        $translations['js'] = $this->get_js_translations( $language );
        
        return $translations;
    }
    
    /**
     * Get translated string
     * 
     * @param string $string String to translate
     * @param string $context Optional context
     * @return string Translated string
     */
    public function translate( $string, $context = '' ) {
        // Check custom translations first
        if ( isset( $this->translations[ $string ] ) ) {
            return $this->translations[ $string ];
        }
        
        // Fall back to WordPress translation
        if ( $context ) {
            return _x( $string, $context, self::TEXT_DOMAIN );
        }
        
        return __( $string, self::TEXT_DOMAIN );
    }
    
    /**
     * Get plural translation
     * 
     * @param string $singular Singular form
     * @param string $plural Plural form
     * @param int    $number Number
     * @return string Translated string
     */
    public function translate_plural( $singular, $plural, $number ) {
        return _n( $singular, $plural, $number, self::TEXT_DOMAIN );
    }
    
    /**
     * Translate quiz content
     * 
     * @param array $content Quiz content
     * @return array Translated content
     */
    public function translate_quiz_content( array $content ) {
        $translated = $content;
        
        // Translate questions
        if ( isset( $content['questions'] ) ) {
            foreach ( $content['questions'] as &$question ) {
                $question['text'] = $this->translate_dynamic( 
                    $question['text'], 
                    'question_' . $question['id'] 
                );
                
                // Translate answer scale
                if ( isset( $question['answers'] ) && is_array( $question['answers'] ) ) {
                    foreach ( $question['answers'] as $value => &$label ) {
                        $label = $this->translate( $label, 'answer_scale' );
                    }
                }
            }
        }
        
        // Translate archetypes
        if ( isset( $content['archetypes'] ) ) {
            foreach ( $content['archetypes'] as &$archetype ) {
                $archetype['name'] = $this->translate_dynamic(
                    $archetype['name'],
                    'archetype_' . $archetype['id'] . '_name'
                );
                
                $archetype['description'] = $this->translate_dynamic(
                    $archetype['description'],
                    'archetype_' . $archetype['id'] . '_description'
                );
                
                if ( isset( $archetype['recommendations'] ) ) {
                    $archetype['recommendations'] = $this->translate_dynamic(
                        $archetype['recommendations'],
                        'archetype_' . $archetype['id'] . '_recommendations'
                    );
                }
            }
        }
        
        // Translate UI elements
        if ( isset( $content['ui'] ) ) {
            foreach ( $content['ui'] as $key => &$value ) {
                $value = $this->translate( $value, 'ui' );
            }
        }
        
        return $translated;
    }
    
    /**
     * Translate dynamic content
     * 
     * @param string $text Text to translate
     * @param string $key Unique key
     * @return string Translated text
     */
    public function translate_dynamic( $text, $key ) {
        // Check if translation exists in database
        $translation = $this->get_dynamic_translation( $key, $this->current_language );
        
        if ( $translation ) {
            return $translation;
        }
        
        // Register for translation if not exists
        $this->register_dynamic_string( $key, $text );
        
        // Return original text
        return $text;
    }
    
    /**
     * Get dynamic translation from database
     * 
     * @param string $key Translation key
     * @param string $language Language code
     * @return string|null Translated text
     */
    protected function get_dynamic_translation( $key, $language ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mq_translations';
        
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT translated_text 
             FROM {$table} 
             WHERE translation_key = %s 
             AND language = %s 
             AND status = 'active'",
            $key,
            $language
        ));
    }
    
    /**
     * Register dynamic string for translation
     * 
     * @param string $key Translation key
     * @param string $text Original text
     */
    protected function register_dynamic_string( $key, $text ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mq_translations';
        
        // Check if already registered
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT translation_id FROM {$table} WHERE translation_key = %s",
            $key
        ));
        
        if ( ! $exists ) {
            $wpdb->insert( $table, array(
                'translation_key' => $key,
                'original_text' => $text,
                'context' => $this->extract_context( $key ),
                'status' => 'pending'
            ));
        }
    }
    
    /**
     * Get language switcher HTML
     * 
     * @param array $args Display arguments
     * @return string HTML output
     */
    public function get_language_switcher( array $args = array() ) {
        $defaults = array(
            'show_flags' => true,
            'show_names' => true,
            'dropdown' => false,
            'class' => 'money-quiz-language-switcher'
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr( $args['class'] ); ?>">
            <?php if ( $args['dropdown'] ) : ?>
                <select class="language-selector" onchange="window.location.href=this.value;">
                    <?php foreach ( $this->languages as $code => $language ) : ?>
                        <option value="<?php echo esc_url( $this->get_language_url( $code ) ); ?>" 
                                <?php selected( $code, $this->current_language ); ?>>
                            <?php if ( $args['show_flags'] ) : ?>
                                <?php echo $language['flag']; ?>
                            <?php endif; ?>
                            <?php echo esc_html( $args['show_names'] ? $language['name'] : $language['native'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else : ?>
                <ul class="language-list">
                    <?php foreach ( $this->languages as $code => $language ) : ?>
                        <li class="<?php echo $code === $this->current_language ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url( $this->get_language_url( $code ) ); ?>" 
                               title="<?php echo esc_attr( $language['name'] ); ?>">
                                <?php if ( $args['show_flags'] ) : ?>
                                    <span class="flag"><?php echo $language['flag']; ?></span>
                                <?php endif; ?>
                                <?php if ( $args['show_names'] ) : ?>
                                    <span class="name"><?php echo esc_html( $language['native'] ); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get URL for specific language
     * 
     * @param string $language Language code
     * @param string $url Base URL (current if empty)
     * @return string URL with language parameter
     */
    public function get_language_url( $language, $url = '' ) {
        if ( empty( $url ) ) {
            $url = $_SERVER['REQUEST_URI'];
        }
        
        return add_query_arg( 'lang', $language, $url );
    }
    
    /**
     * Enqueue language-specific assets
     */
    public function enqueue_language_assets() {
        // Add RTL stylesheet if needed
        if ( $this->is_rtl() ) {
            wp_enqueue_style(
                'money-quiz-rtl',
                MONEY_QUIZ_PLUGIN_URL . 'assets/css/rtl.css',
                array( 'money-quiz-style' ),
                MONEY_QUIZ_VERSION
            );
        }
        
        // Add language-specific JavaScript strings
        wp_localize_script( 'money-quiz-script', 'moneyQuizI18n', array(
            'lang' => $this->current_language,
            'rtl' => $this->is_rtl(),
            'strings' => $this->get_js_translations( $this->current_language )
        ));
    }
    
    /**
     * Get JavaScript translations
     * 
     * @param string $language Language code
     * @return array Translations
     */
    protected function get_js_translations( $language ) {
        $strings = array(
            'loading' => $this->translate( 'Loading...', 'js' ),
            'error' => $this->translate( 'An error occurred', 'js' ),
            'success' => $this->translate( 'Success!', 'js' ),
            'confirm' => $this->translate( 'Are you sure?', 'js' ),
            'cancel' => $this->translate( 'Cancel', 'js' ),
            'save' => $this->translate( 'Save', 'js' ),
            'next' => $this->translate( 'Next', 'js' ),
            'previous' => $this->translate( 'Previous', 'js' ),
            'complete' => $this->translate( 'Complete', 'js' ),
            'required' => $this->translate( 'This field is required', 'js' ),
            'emailInvalid' => $this->translate( 'Please enter a valid email address', 'js' ),
            'phoneInvalid' => $this->translate( 'Please enter a valid phone number', 'js' ),
            'quizProgress' => $this->translate( 'Question %1$d of %2$d', 'js' ),
            'timeRemaining' => $this->translate( '%d minutes remaining', 'js' ),
            'connectionError' => $this->translate( 'Connection error. Please try again.', 'js' )
        );
        
        return apply_filters( 'money_quiz_js_translations', $strings, $language );
    }
    
    /**
     * Import translations from file
     * 
     * @param string $file File path
     * @param string $language Target language
     * @return array Import results
     */
    public function import_translations( $file, $language ) {
        $results = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        // Determine file type and parse
        $extension = pathinfo( $file, PATHINFO_EXTENSION );
        
        switch ( $extension ) {
            case 'po':
                $translations = $this->parse_po_file( $file );
                break;
                
            case 'csv':
                $translations = $this->parse_csv_file( $file );
                break;
                
            case 'json':
                $translations = $this->parse_json_file( $file );
                break;
                
            default:
                $results['errors'][] = __( 'Unsupported file format', 'money-quiz' );
                return $results;
        }
        
        // Import translations
        foreach ( $translations as $key => $translation ) {
            $result = $this->import_single_translation( $key, $translation, $language );
            
            if ( $result === 'imported' ) {
                $results['imported']++;
            } elseif ( $result === 'updated' ) {
                $results['updated']++;
            } elseif ( $result === 'skipped' ) {
                $results['skipped']++;
            } else {
                $results['errors'][] = $result;
            }
        }
        
        // Clear translation cache
        CacheUtil::delete( 'translations_' . $language );
        
        return $results;
    }
    
    /**
     * Export translations
     * 
     * @param string $language Language to export
     * @param string $format Export format
     * @return string File path or content
     */
    public function export_translations( $language, $format = 'po' ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mq_translations';
        
        // Get all translations for language
        $translations = $wpdb->get_results( $wpdb->prepare(
            "SELECT translation_key, original_text, translated_text, context 
             FROM {$table} 
             WHERE language = %s 
             AND status = 'active'
             ORDER BY context, translation_key",
            $language
        ), ARRAY_A );
        
        switch ( $format ) {
            case 'po':
                return $this->export_as_po( $translations, $language );
                
            case 'csv':
                return $this->export_as_csv( $translations, $language );
                
            case 'json':
                return $this->export_as_json( $translations, $language );
                
            default:
                return '';
        }
    }
    
    /**
     * Get available languages
     * 
     * @return array Languages
     */
    public function get_languages() {
        return $this->languages;
    }
    
    /**
     * Get current language
     * 
     * @return string Language code
     */
    public function get_current_language() {
        return $this->current_language;
    }
    
    /**
     * Check if current language is RTL
     * 
     * @return bool
     */
    public function is_rtl() {
        return isset( $this->languages[ $this->current_language ]['rtl'] ) 
            && $this->languages[ $this->current_language ]['rtl'];
    }
    
    /**
     * Check if language is valid
     * 
     * @param string $language Language code
     * @return bool
     */
    protected function is_valid_language( $language ) {
        return isset( $this->languages[ $language ] );
    }
    
    /**
     * Detect browser language
     * 
     * @return string|null Language code
     */
    protected function detect_browser_language() {
        if ( ! isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            return null;
        }
        
        $browser_langs = explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
        
        foreach ( $browser_langs as $lang ) {
            $lang = strtolower( trim( explode( ';', $lang )[0] ) );
            
            // Try exact match
            if ( isset( $this->languages[ $lang ] ) ) {
                return $lang;
            }
            
            // Try language without region
            $lang_parts = explode( '-', $lang );
            if ( count( $lang_parts ) > 1 ) {
                foreach ( $this->languages as $code => $info ) {
                    if ( strpos( $code, $lang_parts[0] ) === 0 ) {
                        return $code;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Save language preference
     * 
     * @param string $language Language code
     */
    protected function save_language_preference( $language ) {
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), 'money_quiz_language', $language );
        } else {
            setcookie( 'money_quiz_language', $language, time() + YEAR_IN_SECONDS, '/' );
        }
    }
    
    /**
     * Get saved language preference
     * 
     * @return string|null Language code
     */
    protected function get_saved_language() {
        if ( is_user_logged_in() ) {
            return get_user_meta( get_current_user_id(), 'money_quiz_language', true );
        } elseif ( isset( $_COOKIE['money_quiz_language'] ) ) {
            return sanitize_text_field( $_COOKIE['money_quiz_language'] );
        }
        
        return null;
    }
    
    /**
     * Check if in Money Quiz context
     * 
     * @return bool
     */
    protected function is_money_quiz_context() {
        // Check if on Money Quiz pages
        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'money-quiz' ) === 0 ) {
            return true;
        }
        
        // Check if displaying quiz shortcode
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'money_quiz' ) ) {
            return true;
        }
        
        // Check AJAX requests
        if ( wp_doing_ajax() && isset( $_POST['action'] ) && strpos( $_POST['action'], 'money_quiz' ) === 0 ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Extract context from translation key
     * 
     * @param string $key Translation key
     * @return string Context
     */
    protected function extract_context( $key ) {
        $parts = explode( '_', $key );
        return $parts[0] ?? 'general';
    }
}

/**
 * Translation Manager Class
 * 
 * Manages translation interface for administrators
 */
class TranslationManager {
    
    /**
     * Translation service
     * 
     * @var TranslationService
     */
    protected $translation_service;
    
    /**
     * Constructor
     * 
     * @param TranslationService $translation_service
     */
    public function __construct( TranslationService $translation_service ) {
        $this->translation_service = $translation_service;
        
        // Register admin hooks
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_money_quiz_save_translation', array( $this, 'ajax_save_translation' ) );
        add_action( 'wp_ajax_money_quiz_get_translations', array( $this, 'ajax_get_translations' ) );
        add_action( 'wp_ajax_money_quiz_import_translations', array( $this, 'ajax_import_translations' ) );
        add_action( 'wp_ajax_money_quiz_export_translations', array( $this, 'ajax_export_translations' ) );
        add_action( 'wp_ajax_money_quiz_auto_translate', array( $this, 'ajax_auto_translate' ) );
    }
    
    /**
     * Add translations menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'money-quiz',
            __( 'Translations', 'money-quiz' ),
            __( 'Translations', 'money-quiz' ),
            'manage_options',
            'money-quiz-translations',
            array( $this, 'render_page' )
        );
    }
    
    /**
     * Render translations page
     */
    public function render_page() {
        $languages = $this->translation_service->get_languages();
        $current_language = $_GET['language'] ?? 'es_ES';
        
        ?>
        <div class="wrap money-quiz-translations">
            <h1><?php _e( 'Money Quiz Translations', 'money-quiz' ); ?></h1>
            
            <div class="translation-toolbar">
                <div class="language-selector">
                    <label><?php _e( 'Language:', 'money-quiz' ); ?></label>
                    <select id="translation-language">
                        <?php foreach ( $languages as $code => $language ) : ?>
                            <?php if ( $code === 'en_US' ) continue; // Skip source language ?>
                            <option value="<?php echo esc_attr( $code ); ?>" 
                                    <?php selected( $code, $current_language ); ?>>
                                <?php echo esc_html( $language['name'] ); ?> 
                                (<?php echo esc_html( $language['native'] ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="translation-actions">
                    <button class="button" id="import-translations">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e( 'Import', 'money-quiz' ); ?>
                    </button>
                    <button class="button" id="export-translations">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e( 'Export', 'money-quiz' ); ?>
                    </button>
                    <button class="button button-primary" id="auto-translate">
                        <span class="dashicons dashicons-translation"></span>
                        <?php _e( 'Auto-Translate', 'money-quiz' ); ?>
                    </button>
                </div>
            </div>
            
            <div class="translation-filters">
                <input type="text" id="translation-search" placeholder="<?php esc_attr_e( 'Search translations...', 'money-quiz' ); ?>">
                
                <select id="translation-context">
                    <option value=""><?php _e( 'All Contexts', 'money-quiz' ); ?></option>
                    <option value="general"><?php _e( 'General', 'money-quiz' ); ?></option>
                    <option value="question"><?php _e( 'Questions', 'money-quiz' ); ?></option>
                    <option value="archetype"><?php _e( 'Archetypes', 'money-quiz' ); ?></option>
                    <option value="ui"><?php _e( 'User Interface', 'money-quiz' ); ?></option>
                    <option value="email"><?php _e( 'Emails', 'money-quiz' ); ?></option>
                </select>
                
                <select id="translation-status">
                    <option value=""><?php _e( 'All Status', 'money-quiz' ); ?></option>
                    <option value="pending"><?php _e( 'Pending', 'money-quiz' ); ?></option>
                    <option value="active"><?php _e( 'Translated', 'money-quiz' ); ?></option>
                    <option value="review"><?php _e( 'Needs Review', 'money-quiz' ); ?></option>
                </select>
            </div>
            
            <div class="translation-editor">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-original"><?php _e( 'Original (English)', 'money-quiz' ); ?></th>
                            <th class="column-translation"><?php _e( 'Translation', 'money-quiz' ); ?></th>
                            <th class="column-context"><?php _e( 'Context', 'money-quiz' ); ?></th>
                            <th class="column-status"><?php _e( 'Status', 'money-quiz' ); ?></th>
                            <th class="column-actions"><?php _e( 'Actions', 'money-quiz' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="translation-list">
                        <!-- Translations loaded via AJAX -->
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="pagination-links">
                            <a class="prev-page button" href="#" id="prev-page">
                                <span class="screen-reader-text"><?php _e( 'Previous page', 'money-quiz' ); ?></span>
                                <span aria-hidden="true">‹</span>
                            </a>
                            <span class="paging-input">
                                <span class="current-page">1</span> <?php _e( 'of', 'money-quiz' ); ?> 
                                <span class="total-pages">1</span>
                            </span>
                            <a class="next-page button" href="#" id="next-page">
                                <span class="screen-reader-text"><?php _e( 'Next page', 'money-quiz' ); ?></span>
                                <span aria-hidden="true">›</span>
                            </a>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Import Modal -->
            <div id="import-modal" class="translation-modal" style="display:none;">
                <div class="modal-content">
                    <h3><?php _e( 'Import Translations', 'money-quiz' ); ?></h3>
                    
                    <form id="import-form" enctype="multipart/form-data">
                        <p>
                            <label><?php _e( 'Select file to import:', 'money-quiz' ); ?></label>
                            <input type="file" name="translation_file" accept=".po,.csv,.json" required>
                        </p>
                        
                        <p class="description">
                            <?php _e( 'Supported formats: PO, CSV, JSON', 'money-quiz' ); ?>
                        </p>
                        
                        <div class="modal-actions">
                            <button type="submit" class="button button-primary">
                                <?php _e( 'Import', 'money-quiz' ); ?>
                            </button>
                            <button type="button" class="button cancel-import">
                                <?php _e( 'Cancel', 'money-quiz' ); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'money-quiz_page_money-quiz-translations' ) {
            return;
        }
        
        wp_enqueue_script(
            'money-quiz-translations',
            MONEY_QUIZ_PLUGIN_URL . 'admin/js/translations.js',
            array( 'jquery' ),
            MONEY_QUIZ_VERSION,
            true
        );
        
        wp_localize_script( 'money-quiz-translations', 'moneyQuizTranslations', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'money_quiz_translations' ),
            'i18n' => array(
                'saving' => __( 'Saving...', 'money-quiz' ),
                'saved' => __( 'Saved!', 'money-quiz' ),
                'error' => __( 'Error saving translation', 'money-quiz' ),
                'confirmAutoTranslate' => __( 'This will automatically translate all pending strings. Continue?', 'money-quiz' ),
                'autoTranslating' => __( 'Auto-translating...', 'money-quiz' ),
                'importSuccess' => __( 'Import completed successfully', 'money-quiz' ),
                'importError' => __( 'Import failed', 'money-quiz' )
            )
        ));
        
        wp_enqueue_style(
            'money-quiz-translations',
            MONEY_QUIZ_PLUGIN_URL . 'admin/css/translations.css',
            array(),
            MONEY_QUIZ_VERSION
        );
    }
}

/**
 * Helper function to get translation service instance
 * 
 * @return TranslationService
 */
function money_quiz_translation() {
    return money_quiz_service( 'translation' );
}

/**
 * Translate string helper
 * 
 * @param string $string String to translate
 * @param string $context Context
 * @return string Translated string
 */
function mq__( $string, $context = '' ) {
    return money_quiz_translation()->translate( $string, $context );
}

/**
 * Translate and echo string helper
 * 
 * @param string $string String to translate
 * @param string $context Context
 */
function mq_e( $string, $context = '' ) {
    echo mq__( $string, $context );
}

/**
 * Translate plural helper
 * 
 * @param string $singular Singular form
 * @param string $plural Plural form
 * @param int    $number Number
 * @return string Translated string
 */
function mq_n( $singular, $plural, $number ) {
    return money_quiz_translation()->translate_plural( $singular, $plural, $number );
}