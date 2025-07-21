<?php
/**
 * @package The Synergy Group AG
 * @Author: The Synergy Group AG
 * @Author URI: https://www.thesynergygroup.ch/
*/
  
   $admin_page_url = admin_url()."admin.php?page=";
   
   // Determine current category and sub-category based on page
   $current_page = $_GET['page'] ?? '';
   $current_category = '';
   $current_sub_category = '';
   
   // Map pages to categories and sub-categories
   $category_map = [
       'mq_welcome' => ['welcome', ''],
       'mq_quiz_setup' => ['quiz_setup', ''],
       'mq_quiz' => ['quiz_setup', 'quiz_layout'],
       'mq_questions' => ['quiz_setup', 'questions'], 
       'mq_archetypes' => ['quiz_setup', 'archetypes'],
       'mq_cta' => ['quiz_setup', 'cta'],
       'mq_quiz_experience' => ['quiz_experience', ''],
       'mq_money_quiz_layout' => ['quiz_experience', 'start_screen'],
       'page_question_screen' => ['quiz_experience', 'question_sections'],
       'quiz_result' => ['quiz_experience', 'display_settings'],
       'mq_communication' => ['communication', ''],
       'email_setting' => ['communication', 'email'],
       'mq_popup' => ['communication', 'popup'],
       'mq_integration' => ['communication', 'integration'],
       'mq_analytics' => ['analytics', ''],
       'mq_prospects' => ['analytics', 'prospects'],
       'mq_reports' => ['analytics', 'reports'],
       'mq_stats' => ['analytics', 'statistics'],
       'mq_features' => ['features', ''],
       'mq_moneycoach' => ['features', 'moneycoach'],
       'recaptcha' => ['features', 'recaptcha'],
       'mq_readme' => ['features', 'readme'],
       'mq_changelog' => ['features', 'changelog'],
       'mq_credit' => ['features', 'credits']
   ];
   
   $current_category = $category_map[$current_page][0] ?? 'welcome';
   $current_sub_category = $category_map[$current_page][1] ?? '';
   
   // Get category title and icon
   $category_info = [
       'welcome' => ['Welcome', '🏠'],
       'quiz_setup' => ['Quiz Setup & Configuration', '🎯'],
       'quiz_experience' => ['Quiz Experience', '🎯'],
       'communication' => ['Communication & Marketing', '📧'],
       'analytics' => ['Data & Analytics', '📊'],
       'features' => ['Additional Features', '🔧']
   ];
   
   $current_category_title = $category_info[$current_category][0] ?? 'Welcome';
   $current_category_icon = $category_info[$current_category][1] ?? '🏠';
   
?>
 
<div class="mq-main-title">
    <div class="mq-title-container">
        <span class="mq-title-icon">🎯</span>
        <h1 class="mq-title-text">The MoneyQuiz</h1>
        <span class="mq-title-subtitle">Financial Assessment Platform</span>
    </div>
</div>

<!-- High-Level Menu Navigation - Across Top -->
<div class="mq-top-level-menu">
    <div class="mq-top-menu-item <?php echo $current_category === 'welcome' ? 'active' : ''; ?>">
        <a href="<?php echo $admin_page_url?>mq_welcome">
            <span class="mq-top-icon">🏠</span>
            <span class="mq-top-title">Welcome</span>
        </a>
    </div>
    
    <div class="mq-top-menu-item <?php echo $current_category === 'quiz_setup' ? 'active' : ''; ?>">
        <a href="<?php echo $admin_page_url?>mq_quiz_setup">
            <span class="mq-top-icon">🎯</span>
            <span class="mq-top-title">Quiz Setup & Configuration</span>
        </a>
    </div>
    
    <div class="mq-top-menu-item <?php echo $current_category === 'quiz_experience' ? 'active' : ''; ?>">
        <a href="<?php echo $admin_page_url?>mq_quiz_experience">
            <span class="mq-top-icon">🎯</span>
            <span class="mq-top-title">Quiz Experience</span>
        </a>
    </div>
    
    <div class="mq-top-menu-item <?php echo $current_category === 'communication' ? 'active' : ''; ?>">
        <a href="<?php echo $admin_page_url?>mq_communication">
            <span class="mq-top-icon">📧</span>
            <span class="mq-top-title">Communication & Marketing</span>
        </a>
    </div>
    
    <div class="mq-top-menu-item <?php echo $current_category === 'analytics' ? 'active' : ''; ?>">
        <a href="<?php echo $admin_page_url?>mq_analytics">
            <span class="mq-top-icon">📊</span>
            <span class="mq-top-title">Data & Analytics</span>
        </a>
    </div>
    
    <div class="mq-top-menu-item <?php echo $current_category === 'features' ? 'active' : ''; ?>">
        <a href="<?php echo $admin_page_url?>mq_features">
            <span class="mq-top-icon">🔧</span>
            <span class="mq-top-title">Additional Features</span>
        </a>
    </div>
</div>

<!-- Sub-Menu Banner - Show for all categories including welcome -->
<div class="mq-submenu-banner">
    <div class="mq-submenu-banner-content">
        <span class="mq-submenu-icon"><?php echo $current_category_icon; ?></span>
        <span class="mq-submenu-title"><?php echo $current_category_title; ?></span>
        <span class="mq-submenu-subtitle">
            <?php
            $subtitle_map = [
                'welcome' => 'Welcome to The MoneyQuiz - Your Financial Assessment Platform',
                'quiz_setup' => 'Configure the core settings and structure of your Money Quiz',
                'quiz_experience' => 'Customize the user experience and quiz flow',
                'communication' => 'Manage email settings, pop-ups, and integrations',
                'analytics' => 'View prospects, reports, and statistics',
                'features' => 'Access additional tools and features'
            ];
            echo $subtitle_map[$current_category] ?? '';
            ?>
        </span>
    </div>
</div>

<!-- Sub-Sub Menu Items - Horizontal Line (only show if not welcome) -->
<?php if ($current_category !== 'welcome'): ?>
<div class="mq-sub-submenu">
    <?php if ($current_category === 'quiz_setup'): ?>
        <a href="<?php echo $admin_page_url?>mq_quiz" class="<?php echo $current_sub_category === 'quiz_layout' ? 'active' : ''; ?>">Quiz Layout</a>
        <a href="<?php echo $admin_page_url?>mq_questions" class="<?php echo $current_sub_category === 'questions' ? 'active' : ''; ?>">Questions</a>
        <a href="<?php echo $admin_page_url?>mq_archetypes" class="<?php echo $current_sub_category === 'archetypes' ? 'active' : ''; ?>">Archetypes</a>
        <a href="<?php echo $admin_page_url?>mq_cta" class="<?php echo $current_sub_category === 'cta' ? 'active' : ''; ?>">CTA Settings</a>
    <?php elseif ($current_category === 'quiz_experience'): ?>
        <a href="<?php echo $admin_page_url?>mq_money_quiz_layout" class="<?php echo $current_sub_category === 'start_screen' ? 'active' : ''; ?>">Start Screen</a>
        <a href="<?php echo $admin_page_url?>page_question_screen" class="<?php echo $current_sub_category === 'question_sections' ? 'active' : ''; ?>">Question Sections</a>
        <a href="<?php echo $admin_page_url?>quiz_result" class="<?php echo $current_sub_category === 'display_settings' ? 'active' : ''; ?>">Display Settings</a>
    <?php elseif ($current_category === 'communication'): ?>
        <a href="<?php echo $admin_page_url?>email_setting" class="<?php echo $current_sub_category === 'email' ? 'active' : ''; ?>">Email Settings</a>
        <a href="<?php echo $admin_page_url?>mq_popup" class="<?php echo $current_sub_category === 'popup' ? 'active' : ''; ?>">Pop-up Settings</a>
        <a href="<?php echo $admin_page_url?>mq_integration" class="<?php echo $current_sub_category === 'integration' ? 'active' : ''; ?>">Integration</a>
    <?php elseif ($current_category === 'analytics'): ?>
        <a href="<?php echo $admin_page_url?>mq_prospects" class="<?php echo $current_sub_category === 'prospects' ? 'active' : ''; ?>">Prospects</a>
        <a href="<?php echo $admin_page_url?>mq_reports" class="<?php echo $current_sub_category === 'reports' ? 'active' : ''; ?>">Reports</a>
        <a href="<?php echo $admin_page_url?>mq_stats" class="<?php echo $current_sub_category === 'statistics' ? 'active' : ''; ?>">Statistics</a>
    <?php elseif ($current_category === 'features'): ?>
        <a href="<?php echo $admin_page_url?>mq_moneycoach" class="<?php echo $current_sub_category === 'moneycoach' ? 'active' : ''; ?>">MoneyCoach</a>
        <a href="<?php echo $admin_page_url?>recaptcha" class="<?php echo $current_sub_category === 'recaptcha' ? 'active' : ''; ?>">Recaptcha</a>
        <a href="<?php echo $admin_page_url?>mq_readme" class="<?php echo $current_sub_category === 'readme' ? 'active' : ''; ?>">ReadMe</a>
        <a href="<?php echo $admin_page_url?>mq_changelog" class="<?php echo $current_sub_category === 'changelog' ? 'active' : ''; ?>">Change Log</a>
        <a href="<?php echo $admin_page_url?>mq_credit" class="<?php echo $current_sub_category === 'credits' ? 'active' : ''; ?>">Credits</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
/* CSS Reset for Money Quiz */
.mq-sub-submenu a,
.mq-feature-card h4,
.mq-feature-card p {
    all: unset;
    box-sizing: border-box;
}

/* Main Title Design */
.mq-main-title {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.mq-title-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.mq-title-icon {
    font-size: 2.5em;
    margin-bottom: 5px;
}

.mq-title-text {
    color: white;
    font-size: 2.2em;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.mq-title-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 1.1em;
    font-weight: 300;
    margin: 0;
}

/* High-Level Menu - Across Top */
.mq-top-level-menu {
    display: flex;
    background: #fff;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.mq-top-menu-item {
    flex: 1;
    border-right: 1px solid #e9ecef;
}

.mq-top-menu-item:last-child {
    border-right: none;
}

.mq-top-menu-item a {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px 10px;
    text-decoration: none;
    color: #666;
    font-weight: 500;
    transition: all 0.3s ease;
    text-align: center;
}

.mq-top-menu-item a:hover {
    background: #f8f9fa;
    color: #667eea;
}

.mq-top-menu-item.active a {
    background: #667eea;
    color: white;
}

.mq-top-icon {
    font-size: 1.5em;
    margin-bottom: 5px;
}

.mq-top-title {
    font-size: 0.9em;
    line-height: 1.2;
}

/* Sub-Menu Banner */
.mq-submenu-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 20px;
    color: white;
}

.mq-submenu-banner-content {
    display: flex;
    align-items: center;
}

.mq-submenu-icon {
    font-size: 2em;
    margin-right: 15px;
}

.mq-submenu-title {
    font-size: 1.8em;
    font-weight: 700 !important;
    margin-right: 15px;
    color: white !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.mq-submenu-subtitle {
    font-size: 1em;
    opacity: 0.9;
    font-weight: 300;
}

/* Sub-Sub Menu - Horizontal Line */
.mq-sub-submenu {
    display: flex;
    background: #fff;
    border-radius: 8px;
    padding: 0;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.mq-sub-submenu a {
    flex: 1 !important;
    padding: 15px 20px !important;
    text-decoration: none !important;
    color: #666 !important;
    font-weight: 700 !important;
    font-size: 1.05em !important;
    text-align: center !important;
    border-right: 1px solid #e9ecef !important;
    transition: all 0.3s ease !important;
    background: transparent !important;
    border-top: none !important;
    border-bottom: none !important;
    border-left: none !important;
    margin: 0 !important;
    line-height: 1.4 !important;
}

.mq-sub-submenu a:last-child {
    border-right: none;
}

.mq-sub-submenu a:hover {
    background: #f8f9fa;
    color: #667eea;
}

.mq-sub-submenu a.active {
    background: #667eea;
    color: white;
    font-weight: 600;
}

.clear {
    clear: both;
}
</style>

<div class="clear"></div>  

  