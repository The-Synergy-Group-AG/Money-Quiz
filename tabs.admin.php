<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
  
   $admin_page_url = admin_url()."admin.php?page=";
   
?>
 
<h1>The MoneyQuiz</h1>
<ul class="mq-admin-tabs noprint" >  
	<li class="<?php echo $current_tab_welcome?>"><a href="<?php echo $admin_page_url?>mq_welcome"> Welcome </a></li>
	<li class="<?php echo $current_tab_moneycoach?>"><a href="<?php echo $admin_page_url?>mq_moneycoach"> MoneyCoach </a></li>
	<li class="<?php echo $current_tab_archetypes?>"><a  href="<?php echo $admin_page_url?>mq_archetypes"> Archetypes </a></li>
	<li class="<?php echo $current_tab_questions?>"><a  href="<?php echo $admin_page_url?>mq_questions"> Questions </a></li>
	<li class="<?php echo $current_tab_mq_question_screen?>"><a  href="<?php echo $admin_page_url?>page_question_screen"> Sections </a></li>
	<li class="<?php echo $current_tab_quiz?>"><a  href="<?php echo $admin_page_url?>mq_quiz"> Layout  </a></li>
	<li class="<?php echo $current_tab_mq_money_quiz_layout?>"><a  href="<?php echo $admin_page_url?>mq_money_quiz_layout"> Start </a></li>
	<li class="<?php echo $current_tab_prospects?>"><a  href="<?php echo $admin_page_url?>mq_prospects"> Request </a></li>
	<li class="<?php echo $current_tab_quiz_result?>"><a  href="<?php echo $admin_page_url?>quiz_result">Display</a></li>
	<li class="<?php echo $current_tab_email_setting?>"><a  href="<?php echo $admin_page_url?>email_setting"> Email </a></li>
	<li class="<?php echo $current_tab_cta?>"><a  href="<?php echo $admin_page_url?>mq_cta"> CTA </a></li>
    <li class="<?php echo $current_tab_reports?>"><a  href="<?php echo $admin_page_url?>mq_reports"> Report </a></li>
	<li class="<?php echo $current_tab_stats?>"><a  href="<?php echo $admin_page_url?>mq_stats"> Stats </a></li>
	<li class="<?php echo $current_tab_integration?>"><a  href="<?php echo $admin_page_url?>mq_integration"> Integration </a></li>
	<li class="<?php echo $current_tab_popup?>"><a  href="<?php echo $admin_page_url?>mq_popup"> Pop-up </a></li>
	<li class="<?php echo $current_tab_credits?>"><a  href="<?php echo $admin_page_url?>mq_credit"> Credits </a></li>
	<li class="<?php echo $current_recaptcha_active?>"><a  href="<?php echo $admin_page_url?>recaptcha"> Recaptcha </a></li>
</ul>
<div class="clear"></div>  

  