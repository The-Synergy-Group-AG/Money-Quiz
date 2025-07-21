<?php
/**
 * @package The Synergy Group AG
 */
/*
Plugin Name: Money Quiz
Plugin URI: https://www.101businessinsights.com/
Description: The Synergy Group AG - Advanced Money Quiz Plugin with Critical Failure Prevention System
Version: 3.22.6
Author: The Synergy Group AG
Author URI: https://www.101businessinsights.com/
License: Premium 
Text Domain: moneyquiz
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Network: false
*/
 
// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'direct access is not allowed.';
	exit;
}

	
$mq_plugin_url = str_replace('index.php','',plugins_url( 'index.php', __FILE__ ));
if(strpos($mq_plugin_url, 'http') === false) {
	$site_url = get_site_url();
	$mq_plugin_url = (substr($site_url, -1) === '/') ? substr($site_url, 0, -1). $mq_plugin_url : $site_url. $mq_plugin_url;
}

define( 'MONEYQUIZ_VERSION', '3.22.6' );
define( 'MONEYQUIZ__MINIMUM_WP_VERSION', '2' );
define( 'MONEYQUIZ__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MONEYQUIZ_DELETE_LIMIT', 100000 );
define( 'MONEYQUIZ_PLUGIN_URL', $mq_plugin_url);

// PLUGIN IDENTIFICATION SYSTEM - v3.22
// Ensure WordPress recognizes this as the same plugin across updates
define( 'MONEYQUIZ_PLUGIN_SLUG', 'money-quiz' );
define( 'MONEYQUIZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MONEYQUIZ_PLUGIN_FILE', __FILE__ );

define( 'MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL', 'andre@theSynergyGroup.ch' ); // live email

// This is the secret key for API authentication. You configured it in the settings menu of the license manager plugin.
// SECURITY FIX: Use WordPress options instead of hardcoded secrets
if (!defined('MONEYQUIZ_SPECIAL_SECRET_KEY')) {
    $secret_key = get_option('moneyquiz_special_secret_key');
    if (empty($secret_key)) {
        $secret_key = wp_generate_password(32, false);
        update_option('moneyquiz_special_secret_key', $secret_key);
    }
    define('MONEYQUIZ_SPECIAL_SECRET_KEY', $secret_key);
}

// This is the URL where API query request will be sent to. This should be the URL of the site where you have installed the main license manager plugin. Get this value from the integration help page.
define('MONEYQUIZ_LICENSE_SERVER_URL', 'https://www.101businessinsights.com'); // live server url
#define('MONEYQUIZ_LICENSE_SERVER_URL', 'http://money.reverinfotech.com'); // local server url

// This is a value that will be recorded in the license manager data so you can identify licenses for this item/product.
define('MONEYQUIZ_ITEM_REFERENCE', 'MoneyQuiz Plugin Key'); //Rename this constant name so it is specific to your plugin or theme.

	
// DB table names without prefix 
const TABLE_MQ_MASTER 	 = "mq_master";
const TABLE_MQ_PROSPECTS = "mq_prospects";
const TABLE_MQ_TAKEN 	 = "mq_taken";
const TABLE_MQ_RESULTS   = "mq_results";
const TABLE_MQ_COACH     = "mq_coach";
const TABLE_MQ_ARCHETYPES= "mq_archetypes";
const TABLE_MQ_CTA		 = "mq_cta";
const TABLE_MQ_MONEY_LAYOUT		 = "mq_template_layout";	
const TABLE_MQ_QUESTION_SCREEN = "mq_question_screen_setting";
const TABLE_EMAIL_SETTING = "mq_email_content_setting";
const ANSWER_TABLE = "mq_answer_label";
const REGISTER_RESULT_PAGE = "mq_register_result_setting";
const EMAIL_SIGNATURE = "mq_email_signature";
const QUIZ_RESULT = "mq_quiz_result";
const RECAPTCHA = "mq_recaptcha_setting";
const ARCHIVE_TYPE_TAG_LINE = "mq_quiz_archive_tag_line";
// wordpress active/deactive plugins hooks
register_activation_hook( __FILE__, array( 'Moneyquiz', 'mq_plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'Moneyquiz', 'mq_plugin_deactivation' ) );
register_uninstall_hook(__FILE__, 'mq_plugin_uninstall');

// PLUGIN UPDATE DETECTION SYSTEM - v3.22
// Ensure WordPress recognizes this as an update, not a new installation
add_action('plugins_loaded', 'mq_check_plugin_update');
function mq_check_plugin_update() {
    $current_version = get_option('mq_plugin_version', '0');
    $new_version = MONEYQUIZ_VERSION;
    
    if (version_compare($current_version, $new_version, '<')) {
        // This is an update
        update_option('mq_plugin_version', $new_version);
        update_option('mq_plugin_last_updated', current_time('mysql'));
        
        // Log the update for debugging
        error_log("Money Quiz Plugin updated from v{$current_version} to v{$new_version}");
        
        // Trigger update-specific actions
do_action('mq_plugin_updated', $current_version, $new_version);

// Show update notification to admin
if (is_admin() && current_user_can('manage_options')) {
    add_action('admin_notices', function() use ($current_version, $new_version) {
        if ($current_version !== '0') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Money Quiz Plugin Updated!</strong> Successfully updated from version ' . esc_html($current_version) . ' to version ' . esc_html($new_version) . '. The Critical Failure Prevention System is now active.</p>';
            echo '</div>';
        }
    });
}
}
}

/**
 * Un-install plugin and delete Db tables 
 * and all data
*/
function mq_plugin_uninstall( ) {
	global $wpdb;
	$table_prefix = $wpdb->prefix;
	// delete all data and database created by plugin
	$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_PROSPECTS );
	$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_COACH );
	$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_MASTER );
	$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_RESULTS );
	$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_TAKEN );
	$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_ARCHETYPES );
	$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_CTA );
	$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_MONEY_LAYOUT );

	// delete option so next time data tables queries can run on activate time
	delete_option('mq_money_coach_status');
	delete_option('mq_money_coach_plugin_version');
}	
	
// include functionality files 
require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
require_once( MONEYQUIZ__PLUGIN_DIR . 'class.moneyquiz.php');
require_once( MONEYQUIZ__PLUGIN_DIR . 'version-tracker.php');

// Load Composer autoloader
if ( file_exists( MONEYQUIZ__PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once( MONEYQUIZ__PLUGIN_DIR . 'vendor/autoload.php' );
}

// CRITICAL FAILURE PREVENTION SYSTEM - v3.22.6
// Load only essential components to prevent errors
if ( file_exists( MONEYQUIZ__PLUGIN_DIR . 'includes/class-money-quiz-dependency-checker.php' ) ) {
    require_once( MONEYQUIZ__PLUGIN_DIR . 'includes/class-money-quiz-dependency-checker.php' );
}

// Load enhanced features integration (STRATEGIC FIX - WordPress readiness check)
if ( file_exists( MONEYQUIZ__PLUGIN_DIR . 'includes/class-money-quiz-integration-loader.php' ) ) {
    require_once( MONEYQUIZ__PLUGIN_DIR . 'includes/class-money-quiz-integration-loader.php' );
}
    
    // Initialize essential components after plugins loaded
add_action( 'plugins_loaded', function() {
    try {
        // Initialize dependency checker if available
        if ( class_exists( 'Money_Quiz_Dependency_Checker' ) ) {
            Money_Quiz_Dependency_Checker::init();
        }
        
        // Load enhanced features if available (STRATEGIC FIX - WordPress readiness check)
        if ( class_exists( 'Money_Quiz_Integration_Loader' ) ) {
            Money_Quiz_Integration_Loader::load_features();
        }
    } catch (Exception $e) {
        // Log error but don't crash the plugin
        error_log('MoneyQuiz Initialization Error: ' . $e->getMessage());
    }
}, 5 ); 


// add admin menu and sub menus
add_action('admin_menu', 'moneyquiz_plugin_setting_menu');
function moneyquiz_plugin_setting_menu(){
		// Main Money Quiz menu
		add_menu_page( 'Money Quiz', 'Money Quiz', 'manage_options', 'mq_welcome', 'moneyquiz_plugin_setting_page', plugins_url('assets/images/menuicon.jpeg',__FILE__) );
		
		// Welcome page (main menu item)
		add_submenu_page( 'mq_welcome', 'Welcome', 'Welcome', 'manage_options', 'mq_welcome', 'moneyquiz_plugin_setting_page' );
		
		// 🎯 Quiz Setup & Configuration
		add_submenu_page( 'mq_welcome', 'Quiz Setup & Configuration', '🎯 Quiz Setup & Configuration', 'manage_options', 'mq_quiz_setup', 'moneyquiz_plugin_setting_page' );
		
		// Quiz Setup sub-items (direct under main menu)
		add_submenu_page( 'mq_welcome', 'Quiz Layout', '— Quiz Layout', 'manage_options', 'mq_quiz', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Questions', '— Questions', 'manage_options', 'mq_questions', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Archetypes', '— Archetypes', 'manage_options', 'mq_archetypes', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'CTA Settings', '— CTA Settings', 'manage_options', 'mq_cta', 'moneyquiz_plugin_setting_page' );
		
		// 🎯 Quiz Experience
		add_submenu_page( 'mq_welcome', 'Quiz Experience', '🎯 Quiz Experience', 'manage_options', 'mq_quiz_experience', 'moneyquiz_plugin_setting_page' );
		
		// Quiz Experience sub-items
		add_submenu_page( 'mq_welcome', 'Start Screen', '— Start Screen', 'manage_options', 'mq_money_quiz_layout', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Question Sections', '— Question Sections', 'manage_options', 'page_question_screen', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Display Settings', '— Display Settings', 'manage_options', 'quiz_result', 'moneyquiz_plugin_setting_page' );
		
		// 📧 Communication & Marketing
		add_submenu_page( 'mq_welcome', 'Communication & Marketing', '📧 Communication & Marketing', 'manage_options', 'mq_communication', 'moneyquiz_plugin_setting_page' );
		
		// Communication sub-items
		add_submenu_page( 'mq_welcome', 'Email Settings', '— Email Settings', 'manage_options', 'email_setting', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Pop-up Settings', '— Pop-up Settings', 'manage_options', 'mq_popup', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Integration', '— Integration', 'manage_options', 'mq_integration', 'moneyquiz_plugin_setting_page' );
		
		// 📊 Data & Analytics
		add_submenu_page( 'mq_welcome', 'Data & Analytics', '📊 Data & Analytics', 'manage_options', 'mq_analytics', 'moneyquiz_plugin_setting_page' );
		
		// Analytics sub-items
		add_submenu_page( 'mq_welcome', 'Prospects', '— Prospects', 'manage_options', 'mq_prospects', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Reports', '— Reports', 'manage_options', 'mq_reports', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Statistics', '— Statistics', 'manage_options', 'mq_stats', 'moneyquiz_plugin_setting_page' );
		
		// 🔧 Additional Features
		add_submenu_page( 'mq_welcome', 'Additional Features', '🔧 Additional Features', 'manage_options', 'mq_features', 'moneyquiz_plugin_setting_page' );
		
		// Features sub-items
		add_submenu_page( 'mq_welcome', 'MoneyCoach', '— MoneyCoach', 'manage_options', 'mq_moneycoach', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Recaptcha', '— Recaptcha', 'manage_options', 'recaptcha', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'ReadMe', '— ReadMe', 'manage_options', 'mq_readme', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Change Log', '— Change Log', 'manage_options', 'mq_changelog', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Credits', '— Credits', 'manage_options', 'mq_credit', 'moneyquiz_plugin_setting_page' );
		
		// 🤖 AI & Advanced Features
		add_submenu_page( 'mq_welcome', 'AI Dashboard', '🤖 AI Dashboard', 'manage_options', 'mq_ai_dashboard', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Performance Dashboard', '⚡ Performance Dashboard', 'manage_options', 'mq_performance_dashboard', 'moneyquiz_plugin_setting_page' );
		add_submenu_page( 'mq_welcome', 'Security Dashboard', '🛡️ Security Dashboard', 'manage_options', 'mq_security_dashboard', 'moneyquiz_plugin_setting_page' );
}
	
/*
 * Admin all dashboard pages 
 * of MoneyQuiz settings 
*/ 
function moneyquiz_plugin_setting_page(){
	global $wpdb;
	$table_prefix = $wpdb->prefix;
	
	// include styles and javascript 
	if ( ! did_action( 'wp_enqueue_media' ) ) {
		wp_enqueue_media();
	}
	
	echo '<style>.mq-container { display: none; }</style>';
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker');
	wp_enqueue_script( 'moneyquizuploadscript', plugins_url('assets/js/admin_js.js', __FILE__), array(), '2.4.9', false );
	wp_enqueue_style( 'admin_css', plugins_url('assets/css/admin_styles.css', __FILE__), false, '2.4.9');
	/** editor js enque */
	wp_enqueue_script( 'moneyquizeditor', plugins_url('assets/js/ckeditor.js', __FILE__), array(), '2.4.9', false );
	//update db queries for version above 1.2
	$mq_money_coach_plugin_version = get_option('mq_money_coach_plugin_version');
	/** Quiz Result */
		/** Answer table */
    /** Insert Record into screen setting */
	/** New create if not exiting  */
	$plugin_activated = 1;
	/** Create table if not exiting recaptcha */
	$table_question_list = $table_prefix.RECAPTCHA;
	$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_question_list'") == $table_question_list;
			if(!$table_exists){
			$sql = "CREATE TABLE  ".$table_prefix.RECAPTCHA." (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`field` varchar(1000),
				`value` varchar(12000),
				PRIMARY KEY (`id`)
			) ".$charset_collate." ;";
			dbDelta($sql);
				$money_quiz_recaptcha_data = array();
				$money_quiz_recaptcha_data[] =	array('field' => "enable_recaptcha", 'value' => "0");
				$money_quiz_recaptcha_data[] =	array('field' => "recaptcha_version", 'value' => "v2");
				$money_quiz_recaptcha_data[] =	array('field' => "site_key", 'value' => "0");
				$money_quiz_recaptcha_data[] =	array('field' => "secret_key", 'value' => "0");
				// insert default data into setting list

				foreach($money_quiz_recaptcha_data as $data){
					$field = $data['field'];
					$value = $data['value'];
					$wpdb->insert( 
						$table_prefix.RECAPTCHA,
						array(
							"field" => $field,
							"value" => $value
							
						)
					);
				} 
				
			}	

	/** End */
	$table_question_list = $table_prefix.TABLE_MQ_QUESTION_SCREEN;
	$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_question_list'") == $table_question_list;
			if(!$table_exists){
			$sql = "CREATE TABLE  ".$table_prefix.TABLE_MQ_QUESTION_SCREEN." (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`field` varchar(1000),
				`value` varchar(12000),
				PRIMARY KEY (`id`)
			) ".$charset_collate." ;";
			dbDelta($sql);
			$money_quiz_question_screen_data = array();
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_1_intro_content",  
				'value' => "Welcome to the first step of your Money Quiz journey! In this section, we'll explore how you perceive your current state of being. It's a chance to reflect on your present habits, emotions, and attitudes towards life and money. Remember, there are no right or wrong answers—just honest reflections of where you are today.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_1_closing_content",  
				'value' => " Great job on completing this section! Understanding your current state is the foundation for transforming your relationship with money. Let's move on to the next step in our journey.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_2_intro_content",  
				'value' => "Now, let's delve into your worldview. How you see the world around you greatly influences your financial mindset and decisions. Approach these questions with an open heart and mind, and let's uncover the lenses through which you view your reality.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_2_closing_content",  
				'value' => "You've done wonderfully in reflecting on your worldview. By understanding how you see the world, we can start to reshape your perceptions towards a more empowering financial future. Let's continue to the next section.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_3_intro_content",  
				'value' => "Our default actions often reveal underlying patterns and beliefs. In this section, we'll look at your typical behaviors, especially when it comes to handling money. Be as truthful as you can—self-awareness is the key to change.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_3_closing_content",  
				'value' => "Well done! Recognizing your default actions is a powerful step towards creating healthier financial habits. Let's keep this momentum going as we move forward.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_4_intro_content",  
				'value' => " Our relationships and interactions with others can significantly impact our financial lives. In this section, we will explore how you relate and engage with those around you. Reflecting on these aspects will help you understand the social dynamics of your money mindset.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_4_closing_content",  
				'value' => "Great work on reflecting how you interact with others! These insights will help us build stronger, more supportive relationships that enhance our financial well-being. Onward to the next section!", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_5_intro_content",  
				'value' => "Emotions play a crucial role in our financial decisions. This section is all about understanding the emotions you experience most frequently. Acknowledging these feelings will pave the way for emotional healing and financial empowerment.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_5_closing_content",  
				'value' => "You've done an amazing job identifying your emotions. Embracing these feelings is essential to fostering a healthier relationship with money. Let's continue our journey with the next section.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_6_intro_content",  
				'value' => "Self-perception is at the heart of our money mindset. In this section, you'll reflect on how you view yourself. Your self-image influences your financial behaviors and decisions, so let's take a closer look.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_6_closing_content",  
				'value' => "Wonderful! Gaining clarity on how you see yourself will empower you to make positive changes. Let's move on to the final section of our quiz.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_7_intro_content",  
				'value' => " This final section focuses on the truths you hold about yourself. These beliefs form the core of your identity and directly impact your money mindset. Answer honestly to reveal the deepest layers of your financial self.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "screen_7_closing_content",  
				'value' => "Congratulations on completing the Money Quiz! You've taken a significant step towards understanding and transforming your relationship with money. Remember, this is just the beginning of your journey to financial empowerment. Keep these insights close as you move forward.", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "reusult_page_content",  
				'value' => "Section 1: How would you describe your current state?", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "title2",  
				'value' => "Section 2: How do you see the world?", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "title3",  
				'value' => "Section 3: How do you tend to act by default?", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "title4",  
				'value' => "Section 4: How do you relate and engage with others?", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "title5",  
				'value' => "Section 5: Which emotions do you feel most often? ", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "title6",  
				'value' => "Section 6: How do you see yourself? ", 
			);
			$money_quiz_question_screen_data[] =	array( 
				'field' => "title7",  
				'value' => "Section 7: Things you know to be true about yourself? ", 
			);
			// insert default data into question list
			foreach($money_quiz_question_screen_data as $data){
				$field = $data['field'];
				$value = $data['value'];
				$wpdb->insert( 
					$table_prefix.TABLE_MQ_QUESTION_SCREEN,
					array(
						"field" => $field,
						"value" => $value
						
					)
				);
			} 
		}
		/** email daata */
		$table_question_list = $table_prefix.TABLE_EMAIL_SETTING;
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_question_list'") == $table_question_list;
					if(!$table_exists){
					$sql = "CREATE TABLE  ".$table_prefix.TABLE_EMAIL_SETTING." (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`field` varchar(1000),
						`value` varchar(12000),
						PRIMARY KEY (`id`)
					) ".$charset_collate." ;";
					dbDelta($sql);
					$money_email_setting_data = array();
					$money_email_setting_data[] =	array( 
						'field' => "email_thank_content",  
						'value' => "Thank you for taking the time to complete the Money Quiz! Your results are in, and they offer valuable insights into your current money mindset. Below, you'll find a summary of your scores for each of the 8 Money Archetypes.", 
					);
				   
					$money_email_setting_data[] =	array( 
						'field' => "email_bottom_content",  
						'value' => "Understanding Your Results:
						Each archetype represents a different aspect of your relationship with money. These scores are not a reflection of your personality but rather a snapshot of how these archetypes are currently showing up in your life. Here's a brief overview of what each archetype signifies:
	
	
	
						1. Hero (Warrior): Conquers the money world with determination and focus.
						2. Creator/Artist: Balances creative and spiritual pursuits with financial stability.
						3. Ruler: Exerts control and power in financial matters.
						4. Innocent: Avoids money matters, often relying on others for financial decisions.
						5. Maverick: Takes risks and sees money as a game.
						6. Victim: Feels helpless or blames external factors for financial difficulties.
						7. Magician: Transforms and manifests financial reality with a balanced approach.
						8. Martyr: Puts others' needs before their own, sometimes at the expense of their financial well-being.
	
	
	
						Next Steps:
						To gain a deeper understanding of your results and learn practical steps to improve your relationship with money, I invite you to book a complimentary one-on-one call with me. During this personalized session, we will:
						• Delve deeper into your Money Archetype scores
						• Discuss practical steps to enhance your financial mindset
						• Work on clearing any money blocks you may have
						This is your opportunity to gain personalized insights and start your journey towards financial empowerment and abundance.
	
	
	
						Book your complimentary call now and take the first step towards transforming your money mindset. Click here to schedule your call. I look forward to connecting with you and helping you unlock your financial potential!
						Warm regards,",
					);
					$money_email_setting_data[] =	array( 
						'field' => "review_button_text",  
						'value' => "Review", 
					);
					$money_email_setting_data[] =	array( 
						'field' => "action_button_link",  
						'value' => "Book Your Free Interpretation Session Now! Click here to access my online calendar ", 
					);
					$money_email_setting_data[] =	array( 
						'field' => "email_subject",  
						'value' => "Your Money Quiz Results & Next Steps ", 
					);
					$money_email_setting_data[] =	array( 
						'field' => "greeting",  
						'value' => "Dear", 
					);
					$money_email_setting_data[] =	array( 
						'field' => "email_closing_content",  
						'value' => '<strong>Next Steps:</strong>

						To gain a deeper understanding of your results and learn practical steps to improve your relationship with money, I invite you to book a complimentary one-on-one call with me. During this personalized session, we will:
						<ul style="padding-left: 16px;">
							<li style="padding-top: 10px;">Delve deeper into your Money Archetype scores</li>
							<li style="padding-top: 10px;">Discuss practical steps to enhance your financial mindset</li>
							<li style="padding-top: 10px;">Work on clearing any money blocks you may have</li>
						</ul>
						Additionally, if you’d like a <strong>free 30-minute personal interpretation of your Money Archetype scores</strong> after completing the Money Quiz, I encourage you to book a call in my calendar.Let’s dive deeper into your results and explore how you can leverage your unique traits for a more prosperous future. <span style="color: #339966;"><a style="color: #008000;" href="https://calendly.com/moneymagic/discovery-call-for-quiz-results" target="_blank" rel="noopener">Click here to schedule your call.</a> </span>
						<div class="mindfull-money-addtional-button" style="display: flex;">

						<a style="text-decoration: none; color: #fff; background-color: #008000; border: none; display: inline-block; padding: 15px; line-height: 22px; text-align: center;" href="https://www.google.com/search?hl=en-CH&amp;gl=ch&amp;q=Mindful+Money+Coaching,+Fadacher+4,+8126+Zumikon&amp;ludocid=14950036098757038132&amp;lsig=AB86z5XxweQNpfKpmKifjp0s4XMi#" target="_blank" rel="noopener">See our 5 Star Google Reviews</a><a style="text-decoration: none; margin-left: 20px; color: #fff; background-color: #008000; border: none; display: inline-block; padding: 15px; line-height: 22px; text-align: center;" href="https://calendly.com/moneymagic/discovery-call-for-quiz-results" target="_blank" rel="noopener">Schedule a Call with Me</a>

						</div>
						Together, we can unlock your financial potential and create the abundance you deserve!', 
					);
					$money_email_setting_data[] =	array( 
						'field' => "table_result_image",  
						'value' => plugins_url('assets/images/result-table.png', __FILE__), 
					);

					// insert default data into question list

					foreach($money_email_setting_data as $data){
						$field = $data['field'];
						$value = $data['value'];
						$wpdb->insert( 
							$table_prefix.TABLE_EMAIL_SETTING,
							array(
								"field" => $field,
								"value" => $value
								
							)
						);
					} 
				}
		/** End */

	/** End */
		$table_question_answer_label = $table_prefix.QUIZ_RESULT;
		$table_question_answer_label_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_question_answer_label'") == $table_question_answer_label;
	
		if(!$table_question_answer_label_exists){
			$sql = "CREATE TABLE  ".$table_prefix.QUIZ_RESULT." (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`field` varchar(1000),
			`value` varchar(12000),
			PRIMARY KEY (`id`)
			) ".$charset_collate." ;";
			dbDelta($sql);
				$quiz_result_setting_data = array();
				$quiz_result_setting_data[] =	array( 
				'field' => "intro_content",  
				'value' => "<h1><strong>Thank you for taking the Money Quiz!</strong></h1>
I'm so grateful that you took the time to explore your relationship with money through this quiz. It's a wonderful step towards understanding and improving how you interact with wealth and abundance.

Remember, these results are not a reflection of your personality but rather a snapshot of what’s currently influencing your money mindset.
<h1 style='margin-bottom: 30px; margin-top: 30px;'><strong>Here are your results:</strong></h1>", 
				);
				
				$quiz_result_setting_data[] =	array( 
					'field' => "result_table_image",  
					'value' => plugins_url('assets/images/result-table.png', __FILE__),
					);
				$quiz_result_setting_data[] =	array( 
					'field' => "result_chart_image",  
					'value' => plugins_url('assets/images/chart-result.png', __FILE__), 
					);
				$quiz_result_setting_data[] =	array( 
					'field' => "about_result_content",  
					'value' => '<h4>Understanding Your Results:</h4>
Each archetype represents a different aspect of your relationship with money. These scores are not a reflection of your personality but rather a snapshot of how these archetypes are currently showing up in your life. Heres a brief overview of what each archetype signifies:

If youre interested in exploring your Money Archetypes, feel free to click on the links below to read more about them:
<ul style="padding-left: 16px;">
 	<li style="padding-top: 10px;"><strong><span style="text-decoration: none; color: rgba(0, 128, 0, 1);"><a style="color: rgba(0, 128, 0, 1); text-decoration: none;" href="https://mindfulmoneycoaching.com/hero-money-archetype/" target="_blank" rel="noopener">Hero</a></span>:</strong> Discover how your courage and strategic decisions can propel you to greater heights.</li>
 	<li style="padding-top: 10px;"><strong><span style="color: #008000;"><a style="color: #008000; text-decoration: none;" href="https://mindfulmoneycoaching.com/creative-artist-money-archetype/" target="_blank" rel="noopener">Artist</a></span>:</strong> Learn how to harmonize your creative spirit with practical financial habits</li>
 	<li style="padding-top: 10px;"><strong><a style="color: #008000; text-decoration: none;" href="https://mindfulmoneycoaching.com/ruler-money-archetype/" target="_blank" rel="noopener"><span style="color: #008000; text-decoration: none;">Ruler</span></a>:</strong> Uncover the secrets to balancing control and emotional fulfillment.</li>
 	<li style="padding-top: 10px;"><strong><a style="color: #008000; text-decoration: none;" href="https://mindfulmoneycoaching.com/innocent-money-archetype/" target="_blank" rel="noopener"><span style="color: #008000; text-decoration: none;">Innocent</span></a>:</strong> Find out how to achieve peace of mind and confidence in your financial journey.</li>
 	<li style="padding-top: 10px;"><strong><span style="color: #008000; text-decoration: none;"><a style="color: #008000; text-decoration: none;" href="https://mindfulmoneycoaching.com/maverick-money-archetype/" target="_blank" rel="noopener">Maverick</a>:</span></strong> Channel your adventurous spirit into responsible and rewarding financial behaviors.</li>
 	<li style="padding-top: 10px;"><a style="color: #008000; text-decoration: none;" href="https://mindfulmoneycoaching.com/victim-money-archetype/" target="_blank" rel="noopener"><strong><span style="color: #008000; text-decoration: none;">Victim</span>:</strong></a> Empower yourself to transform challenges into opportunities for stability and success.</li>
 	<li style="padding-top: 10px;"><strong><span style="color: #008000;"><a style="color: #008000; text-decoration: none;" href="https://mindfulmoneycoaching.com/magician-money-archetype/" target="_blank" rel="noopener">Magician</a>:</span></strong> Tap into your creativity and resourcefulness to manifest abundance.</li>
 	<li style="padding-top: 10px;"><strong><span style="color: #008000;"><a style="color: #008000; text-decoration: none;" href="https://mindfulmoneycoaching.com/martyr-money-archetype/" target="_blank" rel="noopener">Martyr</a></span>:</strong> Balance your selflessness with strategies that ensure your own financial well-being.</li>
</ul>
<strong>Next Steps:</strong>

To gain a deeper understanding of your results and learn practical steps to improve your relationship with money, I invite you to book a complimentary one-on-one call with me. During this personalized session, we will:
<ul style="padding-left: 16px;">
 	<li style="padding-top: 10px;">Delve deeper into your Money Archetype scores</li>
 	<li style="padding-top: 10px;">Discuss practical steps to enhance your financial mindset</li>
 	<li style="padding-top: 10px;">Work on clearing any money blocks you may have</li>
</ul>
Additionally, if you’d like a <strong>free 30-minute personal interpretation of your Money Archetype scores</strong> after completing the Money Quiz, I encourage you to book a call in my calendar.Let’s dive deeper into your results and explore how you can leverage your unique traits for a more prosperous future. <span style="color: #339966;"><a style="color: #008000;" class="is-not-book-to-call"  href="https://calendly.com/moneymagic/discovery-call-for-quiz-results" target="_blank" rel="noopener">Click here to schedule your call.</a> </span>
<div class="mindfull-money-addtional-button" style="display: flex;">

<a style="text-decoration: none; color: #fff; background-color: #008000; border: none; display: inline-block; padding: 15px; line-height: 22px; text-align: center;" href="https://www.google.com/search?hl=en-CH&amp;gl=ch&amp;q=Mindful+Money+Coaching,+Fadacher+4,+8126+Zumikon&amp;ludocid=14950036098757038132&amp;lsig=AB86z5XxweQNpfKpmKifjp0s4XMi#" target="_blank" rel="noopener">See our 5 Star Google Reviews</a><a style="text-decoration: none; margin-left: 20px; color: #fff; background-color: #008000; border: none; display: inline-block; padding: 15px; line-height: 22px; text-align: center;" href="https://calendly.com/moneymagic/discovery-call-for-quiz-results" target="_blank" rel="noopener" class="is-not-book-to-call">Schedule a Call with Me</a>

</div>
Together, we can unlock your financial potential and create the abundance you deserve!', 
					);	
					$quiz_result_setting_data[] =	array( 
						'field' => "result_footer_content",  
						'value' => "I’ve also sent this report to you by email so you can easily refer back to it whenever you need.", 
						);
			foreach($quiz_result_setting_data as $data){
				$field = $data['field'];
				$value = $data['value'];
				$wpdb->insert( 
					$table_prefix.QUIZ_RESULT,
					array(
						"field" => $field,
						"value" => $value
						
					)
				);
			} 
		}
	

	/** end  */
	/** Answer table */

	$table_question_answer_label = $table_prefix.ANSWER_TABLE;
	$table_question_answer_label_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_question_answer_label'") == $table_question_answer_label;

	if(!$table_question_answer_label_exists){
		$sql = "CREATE TABLE  ".$table_prefix.ANSWER_TABLE." (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`field` varchar(1000),
		`value` varchar(12000),
		PRIMARY KEY (`id`)
		) ".$charset_collate." ;";
		dbDelta($sql);
			$money_email_setting_data = array();
			$money_email_setting_data[] =	array( 
			'field' => "answered_label",  
			'value' => "Answered", 
			);
			$money_email_setting_data[] =	array( 
			'field' => "answered_error",  
			'value' => "Please answer all the question to proceed to the next section", 
			);

		foreach($money_email_setting_data as $data){
			$field = $data['field'];
			$value = $data['value'];
			$wpdb->insert( 
				$table_prefix.ANSWER_TABLE,
				array(
					"field" => $field,
					"value" => $value
					
				)
			);
		} 
	}

	/** email signature template design template */

	/** Archive tagline */

	$table_question_answer_label = $table_prefix.ARCHIVE_TYPE_TAG_LINE;
	$table_question_answer_label_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_question_answer_label'") == $table_question_answer_label;

	if(!$table_question_answer_label_exists){
		$sql = "CREATE TABLE  ".$table_prefix.ARCHIVE_TYPE_TAG_LINE." (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`field` varchar(1000),
		`value` varchar(12000),
		PRIMARY KEY (`id`)
		) ".$charset_collate." ;";
		dbDelta($sql);
			$archive_tag_line = array();
			$archive_tag_line[] =	array( 
			'field' => "tagline_1",  
			'value' => "Discover how your courage and strategic decisions can propel you to greater heights", 
			);
			$archive_tag_line[] =	array( 
			'field' => "tagline_2",  
			'value' => "Learn how to harmonize your creative spirit with practical financial habits", 
			);
			$archive_tag_line[] =	array( 
				'field' => "tagline_3",  
				'value' => "Uncover the secrets to balancing control and emotional fulfillment.", 
				);
			$archive_tag_line[] =	array( 
				'field' => "tagline_4",  
				'value' => " Find out how to achieve peace of mind and confidence in your financial journey.", 
				);
			$archive_tag_line[] =	array( 
				'field' => "tagline_5",  
				'value' => "Channel your adventurous spirit into responsible and rewarding financial behaviors.", 
				);	
			$archive_tag_line[] =	array( 
					'field' => "tagline_6",  
					'value' => "Empower yourself to transform challenges into opportunities for stability and success.", 
					);
			$archive_tag_line[] =	array( 
				'field' => "tagline_7",  
				'value' => "Tap into your creativity and resourcefulness to manifest abundance.", 
				);
			$archive_tag_line[] =	array( 
				'field' => "tagline_8",  
				'value' => "Balance your selflessness with strategies that ensure your own financial well-being.", 
				);	
							
		foreach($archive_tag_line as $data){
			$field = $data['field'];
			$value = $data['value'];
			$wpdb->insert( 
				$table_prefix.ARCHIVE_TYPE_TAG_LINE,
				array(
					"field" => $field,
					"value" => $value
					
				)
			);
		} 
	}

	/** end */

	$table_question_answer_label = $table_prefix.EMAIL_SIGNATURE;
	$table_question_answer_label_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_question_answer_label'") == $table_question_answer_label;

	if(!$table_question_answer_label_exists){
		$sql = "CREATE TABLE  ".$table_prefix.EMAIL_SIGNATURE." (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`field` varchar(1000),
		`value` varchar(12000),
		PRIMARY KEY (`id`)
		) ".$charset_collate." ;";
		dbDelta($sql);
			$money_email_setting_data = array();
			$money_email_setting_data[] =	array( 
			'field' => "sub_heading",  
			'value' => "The Money Coach | Mindful Money Coaching", 
			);
			$money_email_setting_data[] =	array( 
				'field' => "author_image",  
				'value' => plugins_url('assets/images/illiana.jpg', __FILE__), 
				);
			$money_email_setting_data[] =	array( 
			'field' => "facebook_link",  
			'value' => "#", 
			);
			$money_email_setting_data[] =	array( 
				'field' => "linkin_link",  
				'value' => "#", 
				);
			$money_email_setting_data[] =	array( 
				'field' => "instagram_link",  
				'value' => "#", 
				);
			$money_email_setting_data[] =	array( 
				'field' => "footer_banner_image",  
				'value' =>  plugins_url('assets/images/Take-the-Money-Quiz.webp', __FILE__), 
				);
			$money_email_setting_data[] =	array( 
				'field' => "is_display_banner",  
				'value' =>  "Yes", 
				);		
		foreach($money_email_setting_data as $data){
			$field = $data['field'];
			$value = $data['value'];
			$wpdb->insert( 
				$table_prefix.EMAIL_SIGNATURE,
				array(
					"field" => $field,
					"value" => $value
					
				)
			);
		} 
	}


	/** end */
	/** Register table */

	$table_question_answer_label = $table_prefix.REGISTER_RESULT_PAGE;
	$table_question_answer_label_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_question_answer_label'") == $table_question_answer_label;

	if(!$table_question_answer_label_exists){
		$sql = "CREATE TABLE  ".$table_prefix.REGISTER_RESULT_PAGE." (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`field` varchar(1000),
		`value` varchar(12000),
		PRIMARY KEY (`id`)
		) ".$charset_collate." ;";
		dbDelta($sql);
			$money_email_setting_data = array();
			$money_email_setting_data[] =	array( 
			'field' => "title",  
			'value' => "Money Quiz Results", 
			);
			$money_email_setting_data[] =	array( 
			'field' => "register_banner",  
			'value' => plugins_url('assets/images/mind-full-money-banner-image.jpg', __FILE__), 
			);
			$money_email_setting_data[] =	array( 
				'field' => "register_intro_content",  
				'value' => "<strong>Unlock Your Money Archetype Results!</strong>

You're just one step away from discovering your unique Money Archetype!

To see your personalized results, simply provide your name and email address.

You'll also have the option to receive our newsletter filled with insightful tips and exclusive offers, as well as a complimentary interpretation of your scores.

This is your chance to gain deeper insights into your financial mindset and start your journey towards financial empowerment.", 
				);
		foreach($money_email_setting_data as $data){
			$field = $data['field'];
			$value = $data['value'];
			$wpdb->insert( 
				$table_prefix.REGISTER_RESULT_PAGE,
				array(
					"field" => $field,
					"value" => $value
					
				)
			);
		} 
	}


	/** end */
	if(empty($mq_money_coach_plugin_version)){
		// insert new data into  table mq_coach
		$data_insert_1[] =	array( 
			'Field' => "Number of Answers Options", 
			'Value' => "Five", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Never", 
			'Value' => "Yes", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Seldom", 
			'Value' => "Yes", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Sometimes", 
			'Value' => "Yes", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Mostly", 
			'Value' => "Yes", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Always", 
			'Value' => "Yes", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Closing Message1", 
			'Value' => "I've also sent this report to you by email.", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Before Quiz Results", 
			'Value' => "Yes", 
		);
		$data_insert_1[] =	array( 
			'Field' => "CTA Message", 
			'Value' => "Receive a copy of your results via email", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Closing Message2", 
			'Value' => "Your report has been sent to you by email.", 
		);

		$data_insert_1[] =	array( 
			'Field' => "Show Progress Bar", 
			'Value' => "Yes", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Progress Bar Background Colour", 
			'Value' => "#f1f1f1", 
		);
		$data_insert_1[] =	array( 
			'Field' => "Progress Bar Main Colour", 
			'Value' => "#9e9e9e", 
		);	
		$data_insert_1[] =	array( 
			'Field' => "Progress Bar Text Colour", 
			'Value' => "#fff", 
		);

		$data_insert_1[] =	array( 
			'Field' => "never_label", 
			'Value' => "Never", 
		);
		$data_insert_1[] =	array( 
			'Field' => "seldom_label", 
			'Value' => "Seldom", 
		);
		$data_insert_1[] =	array( 
			'Field' => "sometimes_label", 
			'Value' => "Sometimes", 
		);
		$data_insert_1[] =	array( 
			'Field' => "mostly_label", 
			'Value' => "Mostly", 
		);
		$data_insert_1[] =	array( 
			'Field' => "always_label", 
			'Value' => "Always", 
		);
		
		foreach($data_insert_1 as $data){
			$wpdb->insert( 
				$table_prefix.TABLE_MQ_COACH,
				$data
			);
		}
		
		// add plugin version into options so no double insertion 
		add_option('mq_money_coach_plugin_version', '1.3' );
		
	} // end update database 
	
	$save_msg = "";

	/** email signature update */
	// Email Signature
	if(isset($_POST['is_display_email_signature_banner_image'])){
		$is_display_banner_image = $_POST['is_display_email_signature_banner_image'];
		$wpdb->update( 
				$table_prefix.EMAIL_SIGNATURE, 
				array( 
					'value' => $is_display_banner_image
				), 
				array( 'id' =>'7' )
			);
		}
	if(isset($_POST['email_signature_update'])){
		foreach($_POST['signature_email'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			$wpdb->update( 
				$table_prefix.EMAIL_SIGNATURE, 
				array( 
					'value' => $new_val1
				), 
				array( 'id' => $key_id )
			);
		}
	}
	/** end */
	/** update Answred */
    if(isset($_POST['answered_label']) || isset($_POST['answred_error_message'])){
		$answered_label = $_POST['answered_label'];
		$answred_error_message = $_POST['answred_error_message'];
		$wpdb->update( 
				$table_prefix.ANSWER_TABLE, 
				array( 
					'value' => $answered_label
				), 
				array( 'id' => '1' )
		);
		$wpdb->update( 
			$table_prefix.ANSWER_TABLE, 
			array( 
				'value' => $answred_error_message
			), 
			array( 'id' => '2' )
	);
	}
	if(isset($_POST['below_result_content'])){
		$below_result_content = $_POST['below_result_content'];
		
		$wpdb->update( 
				$table_prefix.TABLE_MQ_QUESTION_SCREEN, 
				array( 
					'value' => $below_result_content
				), 
				array( 'id' => '16' )
		);
		
	}

	/** Update register page */

	if(isset($_POST['update_register'])){
		foreach($_POST['register_page_seeting'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			$wpdb->update( 
				$table_prefix.REGISTER_RESULT_PAGE, 
				array( 
					'Value' => $new_val1
				), 
				array( 'ID' => $key_id )
			);
		}
		
	}
	/** end */

	/** End */
	// to process all admin forms at one place
	if(isset($_POST['action']) && $_POST['action'] == "update"){
		
		foreach($_POST['post_data'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			$wpdb->update( 
				$table_prefix.TABLE_MQ_COACH, 
				array( 
					'Value' => $new_val1
				), 
				array( 'ID' => $key_id )
			);
		}
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}
	/** Quiz result  */
	if(isset($_POST['action']) && $_POST['action'] == "quiz_result_setting"){
		
		foreach($_POST['quiz_result_setting'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			$wpdb->update( 
				$table_prefix.QUIZ_RESULT, 
				array( 
					'value' => $new_val1
				), 
				array( 'id' => $key_id )
			);
		}
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}
	/** end */
	// to process money quiz template layout setting
	if(isset($_POST['action']) && $_POST['action'] == "page_template_layout"){
		
		foreach($_POST['post_data'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			
			//$new_val1 = htmlspecialchars($new_val, ENT_QUOTES, 'UTF-8');
			$wpdb->update( 
				$table_prefix.TABLE_MQ_MONEY_LAYOUT, 
				array( 
					'value' => $new_val1
				), 
				array( 'Moneytemplate_ID' => $key_id )
			);
		}
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}

	
	// To Process change Screen 1 to 5
	if(isset($_POST['action']) && $_POST['action'] == "page_question_screen"){
		
		foreach($_POST['post_data'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			
			//$new_val1 = htmlspecialchars($new_val, ENT_QUOTES, 'UTF-8');
			$wpdb->update( 
				$table_prefix.TABLE_MQ_QUESTION_SCREEN, 
				array( 
					'value' => $new_val1
				), 
				array( 'id' => $key_id )
			);
		}
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}
	//end
	// To Process change Screen 1 to 5
	if(isset($_POST['action']) && $_POST['action'] == "email_setting"){
		
		foreach($_POST['post_data'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			
			//$new_val1 = htmlspecialchars($new_val, ENT_QUOTES, 'UTF-8');
			$wpdb->update( 
				$table_prefix.TABLE_EMAIL_SETTING, 
				array( 
					'value' => $new_val1
				), 
				array( 'id' => $key_id )
			);
		}
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}
	//end
	// to process Archetype form 
 	if(isset($_POST['action']) && $_POST['action'] == "archetype"){
		foreach($_POST['post_data'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			$wpdb->update( 
				$table_prefix.TABLE_MQ_ARCHETYPES, 
				array( 
					'Value' => $new_val1
				), 
				array( 'ID' => $key_id )
			);
		}
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}
	
	// to process Archetype form 
 	if(isset($_POST['action']) && $_POST['action'] == "integration"){
		foreach($_POST['post_data'] as $key_id=>$new_val){
			$new_val1 = sanitize_text_field( $new_val, true );
			$wpdb->update( 
				$table_prefix.TABLE_MQ_COACH, 
				array( 
					'Value' => $new_val1
				), 
				array( 'ID' => $key_id )
			);
		}
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}
	//Update Recaptcha Setting

		// to process CTA form 
		if(isset($_POST['action']) && $_POST['action'] == "recaptcha_setting"){
			
			foreach($_POST['recaptcha_setting'] as $key_id=>$new_val){
				$new_val1 = $new_val;
				$wpdb->update( 
					$table_prefix.RECAPTCHA, 
					array( 
						'value' => $new_val1
					), 
					array( 'id' => $key_id )
				);
			}
			$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
		}	

	//end
	// to process CTA form 
 	if(isset($_POST['action']) && $_POST['action'] == "cta_update"){
		foreach($_POST['post_data'] as $key_id=>$new_val){
			$new_val1 = $new_val;
			$wpdb->update( 
				$table_prefix.TABLE_MQ_CTA, 
				array( 
					'Value' => $new_val1
				), 
				array( 'ID' => $key_id )
			);
		}
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}	
	
	// to process Questions edit form 
 	if(isset($_POST['update_question']) && $_POST['update_question'] == "1"){
			
			$Question = sanitize_text_field( $_POST['Question'], true );
			$Definition = sanitize_text_field( $_POST['Definition'], true );
			$Example = sanitize_text_field( $_POST['Example'], true );
			 
			$wpdb->update( 
				$table_prefix.TABLE_MQ_MASTER, 
				array( 
					'Question' => $Question,
					'Definition' => $Definition,
					'Example' => $Example
				), 
				array( 'Master_ID' => $_POST['questionid'] )
			);
		 
		$save_msg = "<div class='data_saved'>Changes saved successfully.</div>";
	}

	// to check plugin activated 
 	 

	if(isset($_POST['action']) && $_POST['action'] == "activate"){	
	 
		$license_key = $_REQUEST['plugin_license_key'];

		// API query parameters
		$api_params = array(
			'slm_action' => 'slm_activate',
			'secret_key' => MONEYQUIZ_SPECIAL_SECRET_KEY,
			'license_key' => $license_key,
			'registered_domain' => $_SERVER['SERVER_NAME'],
			'item_reference' => urlencode(MONEYQUIZ_ITEM_REFERENCE),
		);

		// Send query to the license manager server
		$query = esc_url_raw(add_query_arg($api_params, MONEYQUIZ_LICENSE_SERVER_URL));
		$response = wp_remote_get($query, array('timeout' => 20, 'sslverify' => false));

		// Check for error in the response
		if (is_wp_error($response)){
			echo "Unexpected Error! The query returned with an error.";
		}

		//var_dump($response);//uncomment it if you want to look at the full response
		
		// License data.
		$license_data = json_decode(wp_remote_retrieve_body($response));
		
		if($license_data->result == 'success'){ //Success was returned for the license activation
		   
			$wpdb->update( 
				$table_prefix.TABLE_MQ_COACH, 
				array( 
					'Value' => $license_key
				), 
				array( 'ID' => 35 )
			);
			if(isset($license_data->date_created)){
				$wpdb->update( 
					$table_prefix.TABLE_MQ_COACH, 
					array( 
						'Value' => $license_data->date_created
					), 
					array( 'ID' => 36 )
				);
			}
			if(isset($license_data->date_renewed)){
				$wpdb->update( 
					$table_prefix.TABLE_MQ_COACH, 
					array( 
						'Value' => $license_data->date_renewed
					), 
					array( 'ID' => 37 )
				);
			}
			$post_data[35] = $license_data; 
			$save_msg = "<div class='data_saved'>Plugin activated successfully.</div>";
			
			//update_option('moneyquiz_license_key', $license_key); 
		}
		else{
			//Show error to the user. Probably entered incorrect license key.
			
			//Uncomment the followng line to see the message that returned from the license server
			$save_msg = '<div class="data_saved error">Error: '.$license_key.' -- '.$license_data->message.'</div>';
		}

	}

	// fetch data at one place for all forms
	$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_COACH."" ;
	$rows = $wpdb->get_results($sql);
	$post_data= array();
	foreach($rows as $row){
		$post_data[$row->ID] = stripslashes($row->Value);
	}
		
	if(isset($_POST['action']) && $_POST['action'] == "renew_plugin"){
		if($post_data[9] != ""){
			// API query parameters
			$api_params = array(
				'slm_action' => 'slm_check',
				'secret_key' => MONEYQUIZ_SPECIAL_SECRET_KEY,
				'license_key' => $post_data[35],
				'registered_domain' => $_SERVER['SERVER_NAME'],
				'item_reference' => urlencode(MONEYQUIZ_ITEM_REFERENCE),
			);

			// Send query to the license manager server
			$query = esc_url_raw(add_query_arg($api_params, MONEYQUIZ_LICENSE_SERVER_URL));
			$response = wp_remote_get($query, array('timeout' => 20, 'sslverify' => false));

			// Check for error in the response
			if (is_wp_error($response)){
				echo "Unexpected Error! The query returned with an error.";
			}

			//var_dump($response);//uncomment it if you want to look at the full response
			
			// License data.
			$license_data_checked = json_decode(wp_remote_retrieve_body($response));
			
			// email code for Business Insights Group
			$body = "Dear Andre";
			$body .= "<p><b>MoneyQuiz</b> License is expiring soon for: </p>";
			
			$body .="<p>Money Coach : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[2]." ".$post_data[3]." </span></p>";
			$body .="<p>Website : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[10]."</span></p>";
			$body .="<p>Current License Key : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[35]."</span></p>";
			$body .="<p>Valid from : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$license_data_checked->date_created."</span></p>";
			$body .="<p>Expires on : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$license_data_checked->date_expiry."</span></p>";
			$body .="<p>Email : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[9]."</span></p>";
			$body .="<p>Group wide Stats : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[33]."</span></p>";
			$body .="<p>Please send renewal invoice to ".$post_data[9]."</span></p>";
		
			//$body .= '<p>Automated Email from the <b>MoneyQuiz Plugin</b></p>';  // for Money Coach logo
			//$body .= '<p>Developer note, website address incase Moneycoach added different in tab but plugin installed on different website:'.get_site_url().'</p>';  // for Money Coach logo
			if($post_data[33] == 'Yes'){
				$sql_email = "SELECT * from ".$table_prefix.TABLE_MQ_RESULTS."";
				$results_email = $wpdb->get_results($sql_email, OBJECT);
				if($results_email){	
					$table_str = "<br><h2> Results Table Data  </h2><table cellpadding='0' cellspacing='0' border='1'> <tr><th width='100'> Results_ID</th><th width='100'>Prospect_ID </th><th width='100'>Taken_ID </th><th width='100'> Master_ID</th><th width='100'>Score </th> </tr>";
					foreach($results_email as $row){	
						$table_str .= "<tr><td align='center'>".$row->Results_ID."</td><td align='center'>".$row->Prospect_ID."</td><td align='center'>".$row->Taken_ID."</td><td align='center'>".$row->Master_ID."</td><td align='center'>".$row->Score."</td></tr>";
					}
					$table_str .= "</table> ";
				}
				$body .= $table_str;
			}
			
			$headers = array('Content-Type: text/html; charset=UTF-8');
			$headers[] = 'From: Money Quiz <no-reply@101businessinsights.com>';  
			wp_mail(MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL , 'MoneyQuiz License Renew Request - Send PayPal Invoice', $body, $headers );	
			$save_msg = '<div class="data_saved ">Your request to renew your license has been sent to Business Insights Group for processing.</div>';
		}else{
			$save_msg = '<div class="data_saved error">Money Coach email not found. Please check MoneyCoach tab.</div>';
		}
	
	}
	//$save_msg = '<div class="data_saved ">Your request to renew your license has been sent to Business Insights Group for processing.</div>';

	$plugin_activated = 0; // default not activated so check for every page.  
//	$plugin_activated = 1; // for local_devlopment
	
	if(isset($_REQUEST['page']) && $_REQUEST['page'] != "mq_welcome" && $plugin_activated == 0){
		// API query parameters
        $api_params = array(
            'slm_action' => 'slm_check',
            'secret_key' => MONEYQUIZ_SPECIAL_SECRET_KEY,
            'license_key' => $post_data[35],           
            'registered_domain' => $_SERVER['SERVER_NAME'],
            'item_reference' => urlencode(MONEYQUIZ_ITEM_REFERENCE),
        );
		
        // Send query to the license manager server
        $query = esc_url_raw(add_query_arg($api_params, MONEYQUIZ_LICENSE_SERVER_URL));
        $response = wp_remote_get($query, array('timeout' => 20, 'sslverify' => false));
		
        // Check for error in the response
        if (is_wp_error($response)){
            echo "Unexpected Error! The query returned with an error.";
        }

        //var_dump($response);//uncomment it if you want to look at the full response
        
        // License data.
        $license_data_checked = json_decode(wp_remote_retrieve_body($response));
		 /*elseif(!isset($license_data_checked->result) || time() > strtotime($license_data_checked->date_renewed)){	
			$save_msg = '<div class="data_saved error">Error: Renew Plugin </div>';
		}*/
		
		
		if(!isset($license_data_checked->result) || $license_data_checked->status != "active"){
			$save_msg = '<div class="data_saved error"><b>Wrong Key:</b> Your License Key is invalid, please contact Business Insights Group for validation. </div>';
		}elseif(!isset($license_data_checked->result) || $license_data_checked->result == "error"){
			$save_msg = '<div class="data_saved error"><b>Wrong Key:</b> Your License Key is invalid, please contact Business Insights Group for validation.  </div>';
		}elseif(!isset($license_data_checked->result) || time() > strtotime($license_data_checked->date_expiry) ){
			$save_msg = '<div class="data_saved error"><b>Expired Key:</b> Your License Key has expired, please contact Business Insights Group to renew your License.</div>';
		}elseif(!isset($license_data_checked->result) || count($license_data_checked->registered_domains) ==0 ){
			$save_msg = '<div class="data_saved error"><b>Wrong Key:</b> Your License Key is invalid, please contact Business Insights Group for validation.</div>';
		}else{
			$plugin_activated = 1;
		}
		
		if($plugin_activated == 1){ // check for renew date and send email 
			
			$date1=date_create(date('Y-m-d'));
			$date2=date_create($license_data_checked->date_expiry);
			$diff=date_diff($date1,$date2);
		 	
			$mq_money_coach_email_sent = get_option('mq_money_coach_email_sent');
			if($diff->days <= 30 && $mq_money_coach_email_sent != "YES"){
				
				// code to send email to money coach  
				$no_email_msg = '';
				if($post_data[9] != ""){
					
					update_option('mq_money_coach_email_sent', 'YES' );
					$body = "Dear ".$post_data[2];
					$body .= "<p>Your <b>MoneyQuiz</b> License wil expire on ".$license_data_checked->date_expiry." </p>";
					
					$body .= ' <p>In order to keep benefiting from the use of the <b>MoneyQuiz</b>, please renew your license. In the next few days you will receive a <b>PayPal Invoice</b> from Business Insights Group AG.<br>';
					$body .= 'Simply click on the Pay Now button to pay via PayPal or Credit card.</p>';
					$body .= '<p>If you have any questions or need further support, please drop me an email.<br> Kind regards, Andre</p><br>';
					$body .= '<img src="'.plugins_url('assets/images/money_coach_signature.png', __FILE__).'" > <br/>';  // for Money Coach logo
					
					$headers = array('Content-Type: text/html; charset=UTF-8');
					$headers[] = 'From: Andre <'.MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL.'>';  
					wp_mail( $post_data[9], 'Your MoneyQuiz License is Expiring soon', $body, $headers );
					
				}else{
					$no_email_msg = "<p><b>Money Coach does not added email or removed email from Moneycoach tab of plugin.<br>Website: ".get_site_url()." </b></p>";
				}
				
				// email code for Business Insights Group
				$body = "Dear Andre";
				$body .= "<p><b>MoneyQuiz</b> License is expiring soon for: </p>";
				
				$body .="<p>Money Coach : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[2]." ".$post_data[3]." </span></p>";
				$body .="<p>Website : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[10]."</span></p>";
				$body .="<p>Current License Key : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[35]."</span></p>";
				$body .="<p>Valid from : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$license_data_checked->date_created."</span></p>";
				$body .="<p>Expires on : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$license_data_checked->date_renewed."</span></p>";
				$body .="<p>Email : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[9]."</span></p>";
				$body .="<p>Group wide Stats : <span style='padding: 5px; border:0px solid #d4d4d4; '>".$post_data[33]."</span></p>";
				$body .="<p>Please send renewal invoice to ".$post_data[9]."</span></p>";
			
				if($post_data[33] == 'Yes'){
					$sql_email = "SELECT * from ".$table_prefix.TABLE_MQ_RESULTS."";
					$results_email = $wpdb->get_results($sql_email, OBJECT);
					if($results_email){	
						$table_str = "<br><h2> Results Table Data  </h2><table cellpadding='0' cellspacing='0' border='1'> <tr><th width='100'> Results_ID</th><th width='100'>Prospect_ID </th><th width='100'>Taken_ID </th><th width='100'> Master_ID</th><th width='100'>Score </th> </tr>";
						foreach($results_email as $row){	
							$table_str .= "<tr><td align='center'>".$row->Results_ID."</td><td align='center'>".$row->Prospect_ID."</td><td align='center'>".$row->Taken_ID."</td><td align='center'>".$row->Master_ID."</td><td align='center'>".$row->Score."</td></tr>";
						}
						$table_str .= "</table> ";
					}
					$body .= $table_str;
				}
			
				$body .= '<p>Automated Email from the <b>MoneyQuiz Plugin</b></p>';  // for Money Coach logo
				//$body .= '<p>Developer note, website address incase Moneycoach added different in tab but plugin installed on different website:'.get_site_url().'</p>';  // for Money Coach logo
				$body .= $no_email_msg;
				
				$headers = array('Content-Type: text/html; charset=UTF-8');
				$headers[] = 'From: Money Quiz <no-reply@101businessinsights.com>';  
				wp_mail(MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL , 'MoneyQuiz License Expiring - Send PayPal Invoice', $body, $headers );				
			}
			if($diff->days > 30){
				update_option('mq_money_coach_email_sent', 'NO' );
			}
		}
	}
	
	
	$current_tab_archetypes = $current_tab_credits = $current_tab_popup = $current_tab_integration = $current_tab_stats = $current_tab_reports = $current_tab_welcome = $current_tab_moneycoach = $current_tab_prospects = $current_tab_questions = $current_tab_quiz = $current_tab_cta = $current_tab_readme = $current_tab_changelog = $current_tab_quiz_setup = $current_tab_quiz_experience = $current_tab_communication = $current_tab_analytics = $current_tab_features = "";
	if(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_prospects" && $plugin_activated == 1){
		$current_tab_prospects ="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'prospects.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_moneycoach"  && $plugin_activated == 1){
		$current_tab_moneycoach="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'moneycoach.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_quiz"  && $plugin_activated == 1){
		$current_tab_quiz="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'quiz.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_questions"  && $plugin_activated == 1){
		$current_tab_questions="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'questions.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_cta"  && $plugin_activated == 1){
		// fetch data for CTA
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_CTA."" ;
		$rows = $wpdb->get_results($sql);
		$post_data= array();
		foreach($rows as $row){
			$post_data[$row->ID] = stripslashes($row->Value);
		}
		$current_tab_cta="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'cta.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_archetypes"  && $plugin_activated == 1){
		// fetch data for archetypes
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_ARCHETYPES."" ;
		$rows = $wpdb->get_results($sql);
		$post_data= array();
		foreach($rows as $row){
			$post_data[$row->ID] = stripslashes($row->Value);
		}

		// Archive Tag Line

		$sql = "SELECT * FROM ".$table_prefix.ARCHIVE_TYPE_TAG_LINE."" ;
		$rows = $wpdb->get_results($sql);
		$arch_tag_line = array();
		foreach($rows as $row){
			$arch_tag_line[$row->id] = stripslashes($row->value);
		}

		// End
		
		$current_tab_archetypes="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'archetypes.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_reports"  && $plugin_activated == 1){
		$all_values = $post_data; // coach table data
		$current_tab_reports="Active";
		if(isset($_REQUEST['Taken_ID'])){
			// fetch data for archetypes
			$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_ARCHETYPES."" ;
			$rows = $wpdb->get_results($sql);
			$post_data= array();
			foreach($rows as $row){
				$post_data[$row->ID] = stripslashes($row->Value);
			}
			require_once( MONEYQUIZ__PLUGIN_DIR . 'reports.details.admin.php'); 
		}elseif(isset($_REQUEST['prospect'])){
			require_once( MONEYQUIZ__PLUGIN_DIR . 'reports.page.admin.php'); 
		}else{
			require_once( MONEYQUIZ__PLUGIN_DIR . 'reports.admin.php'); 
		}
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_integration"  && $plugin_activated == 1){
		$current_tab_integration="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'integration.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_popup"  && $plugin_activated == 1){
		$current_tab_popup="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'popup.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_credit"  && $plugin_activated == 1){
		$current_tab_credits="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'credit.admin.php'); 
	}
	elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_money_quiz_layout"  && $plugin_activated == 1){
			
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_MONEY_LAYOUT."" ;
		$rows = $wpdb->get_results($sql);
		
		$template_layout_data= array();
		foreach($rows as $row){
			$template_layout_data[$row->Moneytemplate_ID] = stripslashes($row->value);
		}
		
		$current_tab_mq_money_quiz_layout="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'money-quiz-template-layout.admin.php'); 
		

	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "page_question_screen" && $plugin_activated == 1){
		

		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_QUESTION_SCREEN."" ;
		$rows = $wpdb->get_results($sql);
		
		$template_layout_data= array();
		foreach($rows as $row){
			$page_question_screen[$row->id] = stripslashes($row->value);
		}
		
		$current_tab_mq_question_screen = "Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'question-screen-setting.admin.php'); 
		

	}
	elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "quiz_result"  && $plugin_activated == 1){
			
		$sql = "SELECT * FROM ".$table_prefix.QUIZ_RESULT."" ;
		$rows = $wpdb->get_results($sql);
		
		$quiz_result_setting = array();
		foreach($rows as $row){
			$quiz_result_setting[$row->id] = stripslashes($row->value);
		}
		
		$current_tab_quiz_result = "Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'quiz-result.admin.php'); 
	}
	elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "recaptcha"  && $plugin_activated == 1){
			
		$sql = "SELECT * FROM ".$table_prefix.RECAPTCHA."" ;
		$rows = $wpdb->get_results($sql);
		
		$recaptcha_setting = array();
		foreach($rows as $row){
			$recaptcha_setting[$row->id] = stripslashes($row->value);
		}
		
		$current_recaptcha_active = "Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'recaptcha.admin.php'); 
	}
	elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "email_setting"  && $plugin_activated == 1){
			/** New create if not exiting  */
			//error_reporting(E_ALL);
			//ini_set('display_errors', 1);
			
	
			$sql = "SELECT * FROM ".$table_prefix.TABLE_EMAIL_SETTING."" ;
			$rows = $wpdb->get_results($sql);
			
			$template_layout_data= array();
			foreach($rows as $row){
				$email_setting[$row->id] = stripslashes($row->value);
			}
			
			$current_tab_email_setting = "Active";
			require_once( MONEYQUIZ__PLUGIN_DIR . 'email-setting.admin.php'); 
			
	
	}
	elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_stats"  && $plugin_activated == 1){
		// fetch data for archetypes
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_ARCHETYPES."" ;
		$rows = $wpdb->get_results($sql);
		$archetype_data= array();
		foreach($rows as $row){
			$archetype_data[$row->ID] = stripslashes($row->Value);
		}
		$current_tab_stats="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'stats.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_readme"  && $plugin_activated == 1){
		$current_tab_readme="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'readme.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_changelog"  && $plugin_activated == 1){
		$current_tab_changelog="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'changelog.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_quiz_setup"  && $plugin_activated == 1){
		$current_tab_quiz_setup="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'quiz-setup.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_quiz_experience"  && $plugin_activated == 1){
		$current_tab_quiz_experience="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'quiz-experience.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_communication"  && $plugin_activated == 1){
		$current_tab_communication="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'communication.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_analytics"  && $plugin_activated == 1){
		$current_tab_analytics="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'analytics.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_features"  && $plugin_activated == 1){
		$current_tab_features="Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'features.admin.php'); 
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_ai_dashboard"  && $plugin_activated == 1){
		$current_tab_ai_dashboard="Active";
		// AI Dashboard content
		echo '<div class="wrap">';
		echo '<h1>🤖 AI Dashboard</h1>';
		echo '<div class="notice notice-info">';
		echo '<p><strong>AI Features Status:</strong> Enhanced AI features are being loaded...</p>';
		echo '<p>This dashboard provides access to AI-powered analytics, pattern recognition, and intelligent recommendations.</p>';
		echo '</div>';
		echo '<div class="card">';
		echo '<h2>AI Capabilities</h2>';
		echo '<ul>';
		echo '<li>📊 Intelligent Analytics</li>';
		echo '<li>🎯 Pattern Recognition</li>';
		echo '<li>💡 Smart Recommendations</li>';
		echo '<li>🔮 Predictive Insights</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_performance_dashboard"  && $plugin_activated == 1){
		$current_tab_performance_dashboard="Active";
		// Performance Dashboard content
		echo '<div class="wrap">';
		echo '<h1>⚡ Performance Dashboard</h1>';
		echo '<div class="notice notice-info">';
		echo '<p><strong>Performance Features Status:</strong> Performance optimization features are being loaded...</p>';
		echo '<p>This dashboard provides insights into plugin performance, caching, and optimization metrics.</p>';
		echo '</div>';
		echo '<div class="card">';
		echo '<h2>Performance Metrics</h2>';
		echo '<ul>';
		echo '<li>🚀 Cache Performance</li>';
		echo '<li>⚡ Query Optimization</li>';
		echo '<li>📈 Response Times</li>';
		echo '<li>🔧 System Resources</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}elseif(isset($_REQUEST['page']) && $_REQUEST['page'] == "mq_security_dashboard"  && $plugin_activated == 1){
		$current_tab_security_dashboard="Active";
		// Security Dashboard content
		echo '<div class="wrap">';
		echo '<h1>🛡️ Security Dashboard</h1>';
		echo '<div class="notice notice-info">';
		echo '<p><strong>Security Features Status:</strong> Security monitoring features are being loaded...</p>';
		echo '<p>This dashboard provides comprehensive security monitoring, threat detection, and access control.</p>';
		echo '</div>';
		echo '<div class="card">';
		echo '<h2>Security Features</h2>';
		echo '<ul>';
		echo '<li>🔒 Access Control</li>';
		echo '<li>🛡️ Threat Detection</li>';
		echo '<li>📊 Security Analytics</li>';
		echo '<li>🚨 Incident Monitoring</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}else{
		$current_tab_welcome = "Active";
		require_once( MONEYQUIZ__PLUGIN_DIR . 'welcome.admin.php'); 
	}

}

function get_percentage($Initiator_question,$score_total_value){
	$ques_total_value = ($Initiator_question * 8);
	//$score_total_value = ($Initiator_question * 10);
	return $cal_percentage = ($score_total_value/$ques_total_value*100);
}


/*
 * front end use functions 
 * short code for page
*/ 

	if ( ! wp_script_is( 'jquery', 'enqueued' )) {
		//Enqueue
        wp_enqueue_script( 'jquery' );
    }
	
add_shortcode( "mq_questions" , "mq_questions_func");

require_once( MONEYQUIZ__PLUGIN_DIR . 'quiz.moneycoach.php');

// Export Summary Results
add_action( 'admin_init', 'export_summary_result' );
function export_summary_result() {
	
	if( isset($_POST['reports-range-submit']) ) {
		ob_start();
		$start_date = strtotime( $_POST[ 'range-start-date' ] );
		// $start_date = strtotime( '2022-06-24' );
		$end_date = strtotime( $_POST[ 'range-end-date' ] );
		$reportname = 'summary_results_'.date( 'd/m/y', $start_date ).'-'.date( 'd/m/y', $end_date ).'.xls';
		header("Content-type: application/vnd.ms-excel");
		// header("Content-disposition: attachment; filename=spreadsheet.xls");
		header("Content-disposition: attachment; filename=".$reportname);
		global $wpdb;
		$MQ_TAKEN = "SELECT * FROM ".$wpdb->prefix.$table_prefix.TABLE_MQ_TAKEN;
		// $sql1 = "SELECT * FROM ".$wpdb->prefix.$table_prefix.TABLE_MQ_TAKEN." where Date_Taken BETWEEN 2019-07-21 AND 2022-07-21";

		$MQ_TAKEN_ROWS = $wpdb->get_results($MQ_TAKEN, OBJECT);
		$t_date = array();
		$sql_qry = 1;
		$n_n_arr = array();
		foreach( $MQ_TAKEN_ROWS as $ROW ) {
			if($ROW->Quiz_Length == "full") {
				$abc= 'a';
			}
			if($ROW->Quiz_Length == "classic") {
				$abc= 'b';
			}
			if($ROW->Quiz_Length == "short") {
				$abc= 'c';
			}
			if($ROW->Quiz_Length == "blitz") {
				$abc= 'd';
			}
			$Date_Taken = strtotime($ROW->Date_Taken);
			if ( ( $Date_Taken >= $start_date ) && ( $Date_Taken <= $end_date ) ){
				$arr_val = $abc.'__'.$ROW->Date_Taken.'~'.ucfirst($ROW->Quiz_Length).'__'.$ROW->Taken_ID.'__'.$ROW->Prospect_ID;
				array_push( $n_n_arr, $arr_val );
			}
		}
		asort( $n_n_arr );
		
		foreach($n_n_arr as $t_id){

			$n_tid = explode('__',$t_id);
			$t_date[$n_tid[2].'__'.$n_tid[3]] = $n_tid[1];
			$t_arr[] = $n_tid[2];

			$sql_rs_.$sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question,  mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$wpdb->prefix.$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$wpdb->prefix.$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE  mq_r.Prospect_ID=".$n_tid[3]." and mq_r.Taken_ID IN($n_tid[2]) ORDER BY mq_r.Taken_ID ASC ";

			$sql_rows[$n_tid[2]] = $wpdb->get_results($sql_rs_.$sql_qry, OBJECT);

			$sql_qry++;
		} 
		$Alchemist_score = $Alchemist_question = $Victim_score = $Victim_question = $Maverick_score = $Maverick_question = $Apprentice_score = $Apprentice_question = $Nurturer_score = $Nurturer_question = $Ruler_score = $Ruler_question = $Warrior_score = $Warrior_question = $Initiator_score = $Initiator_question = 0;

		$Alchemist_return =$Victim_return =$Maverick_return =$Apprentice_return =$Ruler_return =$Nurturer_return =$Initiator_return =$Warrior_return = 0;	

		$detailed_summary_rows = "";
		$new_arr = 1;
		$new_tr = 0;
		$table_row = '';

		$Alchemist_return_arr = $Victim_return_arr = $Maverick_return_arr = $Apprentice_return_arr = $Ruler_return_arr = $Warrior_return_arr = $Nurturer_return_arr = $Iniatiator_return_arr = array();

		$n_t_id=0;

		if($sql_rows){
			foreach($sql_rows as $n_row){
				foreach($n_row as $n_r){
					$new_result_sets[$t_arr[$n_t_id]][$n_r->Master_ID] = $n_r;
				}
				$n_t_id++;
			}
			foreach($new_result_sets as $nn_row){
				foreach($nn_row as $row ){
					$str = '<tr><td>'.$row->ID_Unique.'</td><td>'.$row->Question.'</td><td>'.$post_data[$row->Archetype].'</td>';
					foreach($t_arr as $tidn){
						$str .= '<td>'.$new_result_sets[$tidn][$row->Master_ID]->Score.'</td>';
						if($new_result_sets[$tidn][$row->Master_ID]->Score == ''){
							continue;
						}
						if($row->Archetype == 1){ //Warrior
							$Warrior_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
							$Warrior_question_arr[$tidn] +=1 ;  
						}
						if($row->Archetype == 5){ // Iniatiator
							$Iniatiator_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
							$Initiator_question_arr[$tidn] +=1;
						}
						if($row->Archetype == 9){ // Ruler
							$Ruler_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
							$Ruler_question_arr[$tidn] +=1;
						}
						if($row->Archetype == 13){ // Apprentice
							$Apprentice_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
							$Apprentice_question_arr[$tidn] +=1;
						}
						if($row->Archetype == 17){ // Maverick
							$Maverick_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
							$Maverick_question_arr[$tidn] +=1;
						}
						if($row->Archetype == 21){ //Victim
							$Victim_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
							$Victim_question_arr[$tidn] +=1;
						}
						if($row->Archetype == 25){ //Alchemist
							$Alchemist_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
							$Alchemist_question_arr[$tidn] +=1;
						}
						if($row->Archetype == 29){ // Nurturer
							$Nurturer_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
							$Nurturer_question_arr[$tidn] +=1;
						}
					}
					$str .= '</tr>';
					$table_row .= $str;
					$new_tr++;
				}
				break;
			}
			$new_arr++;
		}
		// fetch data for archetypes
		$sql = "SELECT * FROM ".$wpdb->prefix.$table_prefix.TABLE_MQ_ARCHETYPES."" ;
		$rows = $wpdb->get_results($sql);
		$post_data= array();
		foreach($rows as $row){
			$post_data[$row->ID] = stripslashes($row->Value);
		}
		$i = 1;
		?>
		<!DOCTYPE html>
		<html>
			<body style="border: 0.1pt solid #ccc"> 
				<table border='1'>
					<tbody>
						<tr>
							<td colspan="13" style="text-align: center;font-size: 24px;"><?php echo 'Quiz results from '.date( 'd-M-Y', $start_date ).' to '.date( 'd-M-Y', $end_date ); ?></td>
						</tr>
						<tr>
							<th><?php echo 'No'; ?></th>
							<th><?php echo 'First Name'; ?></th>
							<th><?php echo 'Surname'; ?></th>
							<th><?php echo 'Email'; ?></th>
							<th><?php echo 'Date'; ?></th>
							<th><?php echo $post_data[1];?></th>
							<th><?php echo $post_data[5];?></th>
							<th><?php echo $post_data[9];?></th>
							<th><?php echo $post_data[13];?></th>
							<th><?php echo $post_data[17];?></th>
							<th><?php echo $post_data[21];?></th>
							<th><?php echo $post_data[25];?></th>
							<th><?php echo $post_data[29];?></th>
						</tr>
						<?php
						if($t_date) {
							foreach($t_date as $prospect => $taken_date) { 
								$ids = explode('__',$prospect);
								$sql = "SELECT * FROM ".$wpdb->prefix.$table_prefix.TABLE_MQ_PROSPECTS." where Prospect_ID=".$ids[1];
								$row = $wpdb->get_row($sql, OBJECT);
								$email = $row->Email;
								$surname = $row->Surname;
								$first_name = $row->Name;
								?>
									<tr>
										<td style="text-align: center;"><?php echo $i; ?></td>
										<td><?php echo $first_name; ?></td>
										<td><?php echo $surname; ?></td>
										<td><?php echo $email; ?></td>
										<td><?php echo  str_replace('~',' (',$taken_date) ?>)</td>
										<?php
										$Warrior_return = get_percentage($Warrior_question_arr[$ids[0]],$Warrior_return_arr[$ids[0]]);
										echo '<td style="text-align: center;">'. round($Warrior_return).'%</td>';	?>
										<?php
										$Initiator_return = get_percentage($Initiator_question_arr[$ids[0]],$Iniatiator_return_arr[$ids[0]]);
										echo '<td style="text-align: center;">'. round($Initiator_return).'%</td>';  ?>
										<?php
										$Ruler_return = get_percentage($Ruler_question_arr[$ids[0]],$Ruler_return_arr[$ids[0]]);
										echo '<td style="text-align: center;">'. round($Ruler_return).'%</td>';  ?>
										<?php
										$Apprentice_return = get_percentage($Apprentice_question_arr[$ids[0]],$Apprentice_return_arr[$ids[0]]);
										echo '<td style="text-align: center;">'. round($Apprentice_return).'%</td>';  ?>
										<?php
										$Maverick_return = get_percentage($Maverick_question_arr[$ids[0]],$Maverick_return_arr[$ids[0]]);
										echo '<td style="text-align: center;">'. round($Maverick_return).'%</td>';  ?>
										<?php
										$Victim_return = get_percentage($Victim_question_arr[$ids[0]],$Victim_return_arr[$ids[0]]);
										echo '<td style="text-align: center;">'. round($Victim_return).'%</td>'; ?>
										<?php
										$Alchemist_return = get_percentage($Alchemist_question_arr[$ids[0]],$Alchemist_return_arr[$ids[0]]);
										echo '<td style="text-align: center;">'. round($Alchemist_return).'%</td>'; ?>
										<?php
										$Nurturer_return = get_percentage($Nurturer_question_arr[$ids[0]],$Nurturer_return_arr[$ids[0]]);
										echo '<td style="text-align: center;">'. round($Nurturer_return).'%</td>';  ?>
									</tr>
							<?php 
								$i++;
							}
						} else {
							?>
							<tr>
								<td colspan="13" style="text-align: center;"><?php echo 'No results are available for the selected dates.'; ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</body>
		</html>
		<?php
		exit;
	}
}
// Export Summary Results