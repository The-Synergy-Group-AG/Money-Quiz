<?php
/**
 * Menu Redesign Implementation
 * 
 * Implements the workflow-centric menu design
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

namespace MoneyQuiz\Admin;

class Menu_Redesign {
    
    /**
     * @var array Menu structure definition
     */
    private $menu_structure = [
        'dashboard' => [
            'title' => 'Dashboard',
            'menu_title' => 'Dashboard',
            'capability' => 'read',
            'icon' => 'dashicons-dashboard',
            'position' => 10,
            'color' => '#0073aa',
            'submenu' => [
                'overview' => [
                    'title' => 'Overview',
                    'capability' => 'read',
                    'callback' => 'render_dashboard_overview'
                ],
                'activity' => [
                    'title' => 'Recent Activity',
                    'capability' => 'read',
                    'callback' => 'render_recent_activity'
                ],
                'stats' => [
                    'title' => 'Quick Stats',
                    'capability' => 'read',
                    'callback' => 'render_quick_stats'
                ],
                'health' => [
                    'title' => 'System Health',
                    'capability' => 'manage_options',
                    'callback' => 'render_system_health'
                ]
            ]
        ],
        'quizzes' => [
            'title' => 'Quizzes',
            'menu_title' => 'Quizzes',
            'capability' => 'edit_posts',
            'icon' => 'dashicons-forms',
            'position' => 20,
            'color' => '#46b450',
            'submenu' => [
                'all' => [
                    'title' => 'All Quizzes',
                    'capability' => 'edit_posts',
                    'callback' => 'render_all_quizzes'
                ],
                'add-new' => [
                    'title' => 'Add New Quiz',
                    'capability' => 'edit_posts',
                    'callback' => 'render_add_quiz'
                ],
                'questions' => [
                    'title' => 'Questions Bank',
                    'capability' => 'edit_posts',
                    'callback' => 'render_questions_bank'
                ],
                'archetypes' => [
                    'title' => 'Archetypes',
                    'capability' => 'edit_posts',
                    'callback' => 'render_archetypes'
                ],
            ]
        ],
        'audience' => [
            'title' => 'Audience',
            'menu_title' => 'Audience',
            'capability' => 'edit_posts',
            'icon' => 'dashicons-groups',
            'position' => 30,
            'color' => '#826eb4',
            'submenu' => [
                'results' => [
                    'title' => 'Results & Analytics',
                    'capability' => 'edit_posts',
                    'callback' => 'render_results_analytics'
                ],
                'prospects' => [
                    'title' => 'Prospects/Leads',
                    'capability' => 'edit_posts',
                    'callback' => 'render_prospects'
                ],
                'campaigns' => [
                    'title' => 'Email Campaigns',
                    'capability' => 'edit_posts',
                    'callback' => 'render_email_campaigns'
                ],
                'export' => [
                    'title' => 'Export/Import',
                    'capability' => 'export',
                    'callback' => 'render_export_import'
                ]
            ]
        ],
        'marketing' => [
            'title' => 'Marketing',
            'menu_title' => 'Marketing',
            'capability' => 'edit_posts',
            'icon' => 'dashicons-megaphone',
            'position' => 40,
            'color' => '#ff6900',
            'submenu' => [
                'cta' => [
                    'title' => 'Call-to-Actions',
                    'capability' => 'edit_posts',
                    'callback' => 'render_cta'
                ],
                'popups' => [
                    'title' => 'Pop-ups',
                    'capability' => 'edit_posts',
                    'callback' => 'render_popups'
]
            ]
        ],
        'settings' => [
            'title' => 'Settings',
            'menu_title' => 'Settings',
            'capability' => 'manage_options',
            'icon' => 'dashicons-admin-generic',
            'position' => 50,
            'color' => '#555d66',
            'submenu' => [
                'general' => [
                    'title' => 'General Settings',
                    'capability' => 'manage_options',
                    'callback' => 'render_general_settings'
                ],
                'email' => [
                    'title' => 'Email Configuration',
                    'capability' => 'manage_options',
                    'callback' => 'render_email_settings'
                ],
                'integrations' => [
                    'title' => 'Integrations',
                    'capability' => 'manage_options',
                    'callback' => 'render_integrations'
                ],
                'security' => [
                    'title' => 'Security & Privacy',
                    'capability' => 'manage_options',
                    'callback' => 'render_security_settings'
                ],
                'advanced' => [
                    'title' => 'Advanced Options',
                    'capability' => 'manage_options',
                    'callback' => 'render_advanced_settings'
                ]
            ]
        ]
    ];
    
    /**
     * @var array Legacy menu mapping - maps old menu slugs to new structure
     */
    private $legacy_mapping = [
        // Main page
        'mq_welcome' => 'dashboard/overview',
        'moneyquiz' => 'dashboard/overview',
        
        // Quiz related
        'mq_quiz' => 'quizzes/all',
        'mq_questions' => 'quizzes/questions',
        'mq_archetypes' => 'quizzes/archetypes',
        'mq_money_quiz_layout' => 'quizzes/templates',
        'page_question_screen' => 'quizzes/templates',
        'quiz_result' => 'quizzes/templates',
        
        // Audience/User related
        'mq_prospects' => 'audience/prospects',
        'mq_reports' => 'audience/results',
        'mq_stats' => 'audience/results',
        'mq_moneycoach' => 'audience/prospects',
        
        // Marketing related
        'mq_cta' => 'marketing/cta',
        'mq_popup' => 'marketing/popups',
        
        // Settings related
        'mq_integration' => 'settings/integrations',
        'email_setting' => 'settings/email',
        'recaptcha' => 'settings/security',
        'mq_credit' => 'settings/advanced'
    ];
    
    /**
     * Initialize the menu system
     */
    public function init() {
        // Load compatibility layer
        require_once plugin_dir_path( __FILE__ ) . 'menu-redesign/class-compatibility-layer.php';
        
        // Load migration notice system
        require_once plugin_dir_path( __FILE__ ) . 'menu-redesign/class-migration-notice.php';
        
        // Load navigation test suite (only in admin)
        if ( is_admin() && ( current_user_can( 'manage_options' ) || defined( 'MONEY_QUIZ_TESTING' ) ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'menu-redesign/test-menu-navigation.php';
        }
        
        // Track first enable time
        if ( get_option( 'money_quiz_menu_redesign_enabled' ) && ! get_option( 'money_quiz_menu_first_enabled_time' ) ) {
            update_option( 'money_quiz_menu_first_enabled_time', time() );
        }
        
        // Check if safe to proceed
        if ( ! apply_filters( 'money_quiz_menu_enabled', get_option( 'money_quiz_menu_redesign_enabled', false ) ) ) {
            return;
        }
        
        add_action( 'admin_menu', [ $this, 'register_menus' ], 5 );
        add_action( 'admin_menu', [ $this, 'remove_legacy_menus' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_menu_styles' ] );
        add_action( 'admin_init', [ $this, 'handle_legacy_redirects' ] );
        add_filter( 'admin_body_class', [ $this, 'add_menu_body_class' ] );
        add_action( 'admin_bar_menu', [ $this, 'add_quick_access_menu' ], 100 );
    }
    
    /**
     * Register new menu structure
     */
    public function register_menus() {
        // Add main Money Quiz menu
        add_menu_page(
            __( 'Money Quiz', 'money-quiz' ),
            __( 'Money Quiz', 'money-quiz' ),
            'read',
            'money-quiz',
            [ $this, 'render_dashboard_overview' ],
            'dashicons-chart-pie',
            25
        );
        
        // Add each section
        foreach ( $this->menu_structure as $section_key => $section ) {
            $parent_slug = 'money-quiz-' . $section_key;
            
            // Add section menu
            add_menu_page(
                $section['title'],
                $section['menu_title'],
                $section['capability'],
                $parent_slug,
                [ $this, 'render_' . $section_key . '_default' ],
                $section['icon'],
                25 + $section['position']
            );
            
            // Add submenu items
            foreach ( $section['submenu'] as $submenu_key => $submenu ) {
                add_submenu_page(
                    $parent_slug,
                    $submenu['title'],
                    $submenu['title'],
                    $submenu['capability'],
                    $parent_slug . '-' . $submenu_key,
                    [ $this, $submenu['callback'] ]
                );
            }
            
            // Remove duplicate first submenu item
            remove_submenu_page( $parent_slug, $parent_slug );
            
            // Add default redirect
            add_submenu_page(
                $parent_slug,
                $section['title'],
                $section['title'],
                $section['capability'],
                $parent_slug,
                [ $this, 'redirect_to_first_submenu' ]
            );
        }
    }
    
    /**
     * Remove legacy menu items
     */
    public function remove_legacy_menus() {
        // Only remove if migration is enabled
        if ( ! get_option( 'money_quiz_new_menu_enabled', false ) ) {
            return;
        }
        
        // Remove legacy menu items
        remove_menu_page( 'mq_welcome' );
        remove_menu_page( 'moneyquiz' );
    }
    
    /**
     * Handle legacy URL redirects
     */
    public function handle_legacy_redirects() {
        if ( ! isset( $_GET['page'] ) ) {
            return;
        }
        
        $page = sanitize_text_field( $_GET['page'] );
        
        if ( isset( $this->legacy_mapping[ $page ] ) ) {
            $new_location = $this->legacy_mapping[ $page ];
            list( $section, $subsection ) = explode( '/', $new_location );
            
            $new_url = admin_url( 'admin.php?page=money-quiz-' . $section . '-' . $subsection );
            
            // Add migration notice
            set_transient( 'mq_menu_migration_notice', true, 30 );
            
            wp_safe_redirect( $new_url );
            exit;
        }
    }
    
    /**
     * Enqueue menu styles
     */
    public function enqueue_menu_styles( $hook ) {
        // Only load on Money Quiz pages
        if ( strpos( $hook, 'money-quiz' ) === false ) {
            return;
        }
        
        wp_enqueue_style(
            'money-quiz-menu-redesign',
            MONEY_QUIZ_PLUGIN_URL . 'assets/css/menu-redesign.css',
            [],
            MONEY_QUIZ_VERSION
        );
        
        wp_enqueue_script(
            'money-quiz-menu-redesign',
            MONEY_QUIZ_PLUGIN_URL . 'assets/js/menu-redesign.js',
            [ 'jquery' ],
            MONEY_QUIZ_VERSION,
            true
        );
        
        // Add color coding CSS
        $this->add_dynamic_menu_colors();
    }
    
    /**
     * Add dynamic menu colors
     */
    private function add_dynamic_menu_colors() {
        $css = '<style id="mq-menu-colors">';
        
        foreach ( $this->menu_structure as $section_key => $section ) {
            $color = $section['color'];
            $css .= "
                #adminmenu .menu-icon-money-quiz-{$section_key} .wp-menu-image:before { color: {$color} !important; }
                #adminmenu .menu-icon-money-quiz-{$section_key}.current .wp-menu-image:before,
                #adminmenu .menu-icon-money-quiz-{$section_key}.wp-has-current-submenu .wp-menu-image:before { color: #fff !important; }
                .mq-section-{$section_key} .mq-page-header { border-left-color: {$color}; }
                .mq-section-{$section_key} .button-primary { background: {$color}; border-color: {$color}; }
            ";
        }
        
        $css .= '</style>';
        echo $css;
    }
    
    /**
     * Add body class for current section
     */
    public function add_menu_body_class( $classes ) {
        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'money-quiz-' ) === 0 ) {
            $parts = explode( '-', $_GET['page'] );
            if ( isset( $parts[2] ) ) {
                $section = $parts[2];
                $classes .= ' mq-section-' . $section;
            }
        }
        
        return $classes;
    }
    
    /**
     * Add quick access admin bar menu
     */
    public function add_quick_access_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        
        // Add main node
        $wp_admin_bar->add_node( [
            'id' => 'money-quiz-quick',
            'title' => '💰 Money Quiz',
            'href' => admin_url( 'admin.php?page=money-quiz' ),
            'meta' => [
                'title' => __( 'Money Quiz Quick Access', 'money-quiz' )
            ]
        ] );
        
        // Add quick actions
        $quick_actions = [
            'new-quiz' => [
                'title' => '➕ ' . __( 'New Quiz', 'money-quiz' ),
                'href' => admin_url( 'admin.php?page=money-quiz-quizzes-add-new' )
            ],
            'view-results' => [
                'title' => '📊 ' . __( 'View Results', 'money-quiz' ),
                'href' => admin_url( 'admin.php?page=money-quiz-audience-results' )
            ],
            'prospects' => [
                'title' => '👥 ' . __( 'Prospects', 'money-quiz' ),
                'href' => admin_url( 'admin.php?page=money-quiz-audience-prospects' )
            ]
        ];
        
        foreach ( $quick_actions as $id => $action ) {
            $wp_admin_bar->add_node( [
                'id' => 'money-quiz-quick-' . $id,
                'parent' => 'money-quiz-quick',
                'title' => $action['title'],
                'href' => $action['href']
            ] );
        }
    }
    
    /**
     * Render breadcrumbs
     */
    private function render_breadcrumbs( $section, $subsection ) {
        $breadcrumbs = [
            [
                'title' => __( 'Money Quiz', 'money-quiz' ),
                'url' => admin_url( 'admin.php?page=money-quiz' )
            ],
            [
                'title' => $this->menu_structure[ $section ]['title'],
                'url' => admin_url( 'admin.php?page=money-quiz-' . $section )
            ],
            [
                'title' => $this->menu_structure[ $section ]['submenu'][ $subsection ]['title'],
                'url' => ''
            ]
        ];
        
        echo '<div class="mq-breadcrumb">';
        foreach ( $breadcrumbs as $index => $crumb ) {
            if ( $crumb['url'] ) {
                echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['title'] ) . '</a>';
            } else {
                echo '<span>' . esc_html( $crumb['title'] ) . '</span>';
            }
            
            if ( $index < count( $breadcrumbs ) - 1 ) {
                echo ' › ';
            }
        }
        echo '</div>';
    }
    
    /**
     * Render page header
     */
    private function render_page_header( $title, $section, $subsection, $actions = [] ) {
        ?>
        <div class="mq-page-header">
            <?php $this->render_breadcrumbs( $section, $subsection ); ?>
            <div class="mq-header-content">
                <h1><?php echo esc_html( $title ); ?></h1>
                <?php if ( ! empty( $actions ) ) : ?>
                    <div class="mq-header-actions">
                        <?php foreach ( $actions as $action ) : ?>
                            <a href="<?php echo esc_url( $action['url'] ); ?>" 
                               class="button <?php echo esc_attr( $action['class'] ?? '' ); ?>">
                                <?php echo esc_html( $action['label'] ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Redirect to first submenu item
     */
    public function redirect_to_first_submenu() {
        $page = $_GET['page'] ?? '';
        $section = str_replace( 'money-quiz-', '', $page );
        
        if ( isset( $this->menu_structure[ $section ] ) ) {
            $first_submenu = array_key_first( $this->menu_structure[ $section ]['submenu'] );
            $redirect_url = admin_url( 'admin.php?page=' . $page . '-' . $first_submenu );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }
    
    // Default render methods for each section
    public function render_dashboard_default() {
        $this->redirect_to_first_submenu();
    }
    
    public function render_dashboard_overview() {
        $this->render_page_header( 
            __( 'Dashboard Overview', 'money-quiz' ),
            'dashboard',
            'overview',
            [
                [
                    'label' => __( 'View Reports', 'money-quiz' ),
                    'url' => admin_url( 'admin.php?page=money-quiz-audience-results' ),
                    'class' => 'button-primary'
                ]
            ]
        );
        
        // Include the template
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/dashboard-overview.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<div class="wrap"><p>' . __( 'Template not found.', 'money-quiz' ) . '</p></div>';
        }
    }
    
    public function render_recent_activity() {
        $this->render_page_header( 
            __( 'Recent Activity', 'money-quiz' ),
            'dashboard',
            'activity'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/dashboard-activity.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<div class="wrap"><p>' . __( 'Template not found.', 'money-quiz' ) . '</p></div>';
        }
    }
    
    public function render_quick_stats() {
        $this->render_page_header( 
            __( 'Quick Stats', 'money-quiz' ),
            'dashboard',
            'stats',
            [
                [
                    'label' => __( 'Export Report', 'money-quiz' ),
                    'url' => admin_url( 'admin.php?page=money-quiz-audience-export' ),
                    'class' => 'button'
                ]
            ]
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/dashboard-stats.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<div class="wrap"><p>' . __( 'Template not found.', 'money-quiz' ) . '</p></div>';
        }
    }
    
    public function render_system_health() {
        $this->render_page_header( 
            __( 'System Health', 'money-quiz' ),
            'dashboard',
            'health'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/dashboard-health.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<div class="wrap"><p>' . __( 'Template not found.', 'money-quiz' ) . '</p></div>';
        }
    }
    
    // Placeholder methods for other sections - to be implemented
    public function render_quizzes_default() {
        $this->redirect_to_first_submenu();
    }
    
    public function render_all_quizzes() {
        $this->render_page_header( 
            __( 'All Quizzes', 'money-quiz' ),
            'quizzes',
            'all',
            [
                [
                    'label' => __( 'Add New', 'money-quiz' ),
                    'url' => admin_url( 'admin.php?page=money-quiz-quizzes-add-new' ),
                    'class' => 'button-primary'
                ]
            ]
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/quizzes-all.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_quiz' );
        }
    }
    
    public function render_add_quiz() {
        $this->render_page_header( 
            __( 'Add New Quiz', 'money-quiz' ),
            'quizzes',
            'add-new'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/quizzes-add-new.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            echo '<div class="wrap"><p>' . __( 'Add new quiz form will be displayed here.', 'money-quiz' ) . '</p></div>';
        }
    }
    
    public function render_questions_bank() {
        $this->render_page_header( 
            __( 'Questions Bank', 'money-quiz' ),
            'quizzes',
            'questions'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/quizzes-questions.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_questions' );
        }
    }
    
    public function render_archetypes() {
        $this->render_page_header( 
            __( 'Archetypes', 'money-quiz' ),
            'quizzes',
            'archetypes'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/quizzes-archetypes.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_archetypes' );
        }
    }
    
    
    /**
     * Render legacy fallback
     */
    private function render_legacy_fallback( $legacy_page ) {
        // Check if legacy function exists
        if ( function_exists( 'moneyquiz_plugin_setting_page' ) ) {
            // Temporarily set the page parameter
            $_GET['page'] = $legacy_page;
            
            // Call legacy function
            moneyquiz_plugin_setting_page();
            
            // Restore page parameter
            $_GET['page'] = isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : '';
            
            // Add notice about using legacy mode
            ?>
            <div class="notice notice-info">
                <p><?php _e( 'This page is using legacy mode. Some features may look different.', 'money-quiz' ); ?></p>
            </div>
            <?php
        } else {
            echo '<div class="wrap"><p>' . __( 'Legacy functionality not available.', 'money-quiz' ) . '</p></div>';
        }
    }
    
    // Audience section methods
    public function render_audience_default() {
        $this->redirect_to_first_submenu();
    }
    
    public function render_results_analytics() {
        $this->render_page_header( 
            __( 'Results & Analytics', 'money-quiz' ),
            'audience',
            'results',
            [
                [
                    'label' => __( 'Export Results', 'money-quiz' ),
                    'url' => admin_url( 'admin.php?page=money-quiz-audience-export' ),
                    'class' => 'button'
                ]
            ]
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/audience-results.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_reports' );
        }
    }
    
    public function render_prospects() {
        $this->render_page_header( 
            __( 'Prospects/Leads', 'money-quiz' ),
            'audience',
            'prospects',
            [
                [
                    'label' => __( 'Export Prospects', 'money-quiz' ),
                    'url' => admin_url( 'admin.php?page=money-quiz-audience-export&type=prospects' ),
                    'class' => 'button'
                ]
            ]
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/audience-prospects.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_prospects' );
        }
    }
    
    public function render_email_campaigns() {
        $this->render_page_header( 
            __( 'Email Campaigns', 'money-quiz' ),
            'audience',
            'campaigns',
            [
                [
                    'label' => __( 'New Campaign', 'money-quiz' ),
                    'url' => '#new-campaign',
                    'class' => 'button-primary'
                ]
            ]
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/audience-campaigns.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy email functionality
            $this->render_legacy_fallback( 'email_setting' );
        }
    }
    
    public function render_export_import() {
        $this->render_page_header( 
            __( 'Export/Import', 'money-quiz' ),
            'audience',
            'export'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/audience-export.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Create basic export functionality
            ?>
            <div class="wrap">
                <p><?php _e( 'Export and import functionality for Money Quiz data.', 'money-quiz' ); ?></p>
                <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-audience-export&export=all' ), 'export-all' ); ?>" class="button button-primary">
                    <?php _e( 'Export All Data', 'money-quiz' ); ?>
                </a>
            </div>
            <?php
        }
    }
    
    // Marketing section methods
    public function render_marketing_default() {
        $this->redirect_to_first_submenu();
    }
    
    public function render_cta() {
        $this->render_page_header( 
            __( 'Call-to-Actions', 'money-quiz' ),
            'marketing',
            'cta'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/marketing-cta.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_cta' );
        }
    }
    
    public function render_popups() {
        $this->render_page_header( 
            __( 'Pop-ups', 'money-quiz' ),
            'marketing',
            'popups'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/marketing-popups.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_popup' );
        }
    }
    
    
    // Settings section methods
    public function render_settings_default() {
        $this->redirect_to_first_submenu();
    }
    
    public function render_general_settings() {
        $this->render_page_header( 
            __( 'General Settings', 'money-quiz' ),
            'settings',
            'general'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/settings-general.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy settings
            $this->render_legacy_fallback( 'mq_welcome' );
        }
    }
    
    public function render_email_settings() {
        $this->render_page_header( 
            __( 'Email Configuration', 'money-quiz' ),
            'settings',
            'email'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/settings-email.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'email_setting' );
        }
    }
    
    public function render_integrations() {
        $this->render_page_header( 
            __( 'Integrations', 'money-quiz' ),
            'settings',
            'integrations'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/settings-integrations.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_integration' );
        }
    }
    
    public function render_security_settings() {
        $this->render_page_header( 
            __( 'Security & Privacy', 'money-quiz' ),
            'settings',
            'security'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/settings-security.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'recaptcha' );
        }
    }
    
    public function render_advanced_settings() {
        $this->render_page_header( 
            __( 'Advanced Options', 'money-quiz' ),
            'settings',
            'advanced'
        );
        
        $template_path = plugin_dir_path( __FILE__ ) . 'menu-redesign/templates/settings-advanced.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to legacy functionality
            $this->render_legacy_fallback( 'mq_credit' );
        }
    }
}

// Initialize if enabled
add_action( 'init', function() {
    if ( get_option( 'money_quiz_enable_menu_redesign', false ) ) {
        $menu_redesign = new Menu_Redesign();
        $menu_redesign->init();
    }
} );