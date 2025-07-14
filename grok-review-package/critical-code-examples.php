<?php
// CRITICAL CODE EXAMPLES FROM MONEY QUIZ PLUGIN

// 1. SQL INJECTION VULNERABILITIES
// From quiz.moneycoach.php line 303
$results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );

// From questions.admin.php line 30
if(isset($_REQUEST['questionid']) && $_REQUEST['questionid'] > 0 ){
    $where = " where Master_ID = ".$_REQUEST['questionid']; 
}

// 2. XSS VULNERABILITIES
// Direct output without escaping
echo $row->Question;
echo $_REQUEST['Question'];
echo '<div class="result">' . $user_data['name'] . '</div>';

// 3. MISSING CSRF PROTECTION
// From moneyquiz.php line 854
if(isset($_POST['action']) && $_POST['action'] == "update"){
    // No nonce verification
    $wpdb->update($table, $data);
}

// 4. DIVISION BY ZERO BUG
// From moneyquiz.php line 1446
function get_percentage($Initiator_question,$score_total_value){
    $ques_total_value = ($Initiator_question * 8);
    return $cal_percentage = ($score_total_value/$ques_total_value*100); // Crashes if $Initiator_question is 0
}

// 5. HARDCODED CREDENTIALS
// From moneyquiz.php lines 35-38
define('MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL', 'andre@101businessinsights.info');
define('MONEYQUIZ_SPECIAL_SECRET_KEY', '5bcd52f5276855.46942741');
define('MONEYQUIZ_LICENSE_SERVER_URL', 'https://www.101businessinsights.com');

// 6. NO ERROR HANDLING
$wpdb->insert( 
    $table_prefix.TABLE_MQ_PROSPECTS,
    $data_insert
);
$prospect_id = $wpdb->insert_id; // No check if insert succeeded

// 7. WEAK ACCESS CONTROL
if ( !function_exists( 'add_action' ) ) {
    echo 'direct access is not allowed.';
    exit;
}
// Should use: defined('ABSPATH') or die();

// 8. UNREACHABLE CODE
// From quiz.moneycoach.php line 290
exit;
$prospect_data = $_POST['prospect_data']; // This line never executes

// 9. EXTERNAL DEPENDENCY
// From quiz.moneycoach.php line 285
<img src='https://mindfulmoneycoaching.online/wp-content/plugins/moneyquiz/assets/images/mind-full-preloader.webp'>
?>
