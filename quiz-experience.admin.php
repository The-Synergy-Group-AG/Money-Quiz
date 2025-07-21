<?php
/**
 * @package The Synergy Group AG
 * @Author: The Synergy Group AG
 * @Author URI: https://www.thesynergygroup.ch/
*/

?>

<div class=" mq-container">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<div class="mq-intro">
		<?php echo $save_msg ?>
	</div>	
	<div class="clear"></div>
	
	<style>
		.mq-experience-container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 20px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.mq-experience-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 40px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 30px;
		}
		.mq-experience-header h1 {
			margin: 0;
			font-size: 2.5em;
			font-weight: 300;
		}
		.mq-experience-header p {
			margin: 10px 0 0 0;
			font-size: 1.2em;
			opacity: 0.9;
		}
		.mq-experience-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-top: 30px;
		}
		.mq-experience-card {
			background: white;
			border-radius: 10px;
			padding: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			text-align: center;
			transition: transform 0.3s ease;
		}
		.mq-experience-card:hover {
			transform: translateY(-5px);
		}
		.mq-experience-card h3 {
			color: #667eea;
			margin: 0 0 15px 0;
			font-size: 1.3em;
		}
		.mq-experience-card p {
			color: #666;
			margin: 0 0 20px 0;
			line-height: 1.6;
		}
		.mq-experience-card .mq-btn {
			background: #667eea;
			color: white;
			padding: 12px 24px;
			border-radius: 25px;
			text-decoration: none;
			display: inline-block;
			transition: background 0.3s ease;
		}
		.mq-experience-card .mq-btn:hover {
			background: #5a6fd8;
		}
	</style>

	<div class="mq-experience-container">
		<div class="mq-experience-header">
			<h1>🎯 Quiz Experience</h1>
			<p>Customize the user experience and flow of your Money Quiz</p>
		</div>

		<div class="mq-experience-grid">
			<div class="mq-experience-card">
				<h3>🚀 Start Screen</h3>
				<p>Configure the welcome screen and introduction that users see when starting the quiz.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_money_quiz_layout'); ?>" class="mq-btn">Configure Start Screen</a>
			</div>

			<div class="mq-experience-card">
				<h3>📝 Question Sections</h3>
				<p>Manage the different sections and flow of questions throughout the quiz experience.</p>
				<a href="<?php echo admin_url('admin.php?page=page_question_screen'); ?>" class="mq-btn">Manage Sections</a>
			</div>

			<div class="mq-experience-card">
				<h3>🎨 Display Settings</h3>
				<p>Customize how results are displayed and presented to users after completing the quiz.</p>
				<a href="<?php echo admin_url('admin.php?page=quiz_result'); ?>" class="mq-btn">Configure Display</a>
			</div>
		</div>
	</div>
</div> 