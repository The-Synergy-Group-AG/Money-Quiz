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
		.mq-setup-container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 20px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.mq-setup-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 40px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 30px;
		}
		.mq-setup-header h1 {
			margin: 0;
			font-size: 2.5em;
			font-weight: 300;
		}
		.mq-setup-header p {
			margin: 10px 0 0 0;
			font-size: 1.2em;
			opacity: 0.9;
		}
		.mq-setup-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-top: 30px;
		}
		.mq-setup-card {
			background: white;
			border-radius: 10px;
			padding: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			text-align: center;
			transition: transform 0.3s ease;
		}
		.mq-setup-card:hover {
			transform: translateY(-5px);
		}
		.mq-setup-card h3 {
			color: #667eea;
			margin: 0 0 15px 0;
			font-size: 1.3em;
		}
		.mq-setup-card p {
			color: #666;
			margin: 0 0 20px 0;
			line-height: 1.6;
		}
		.mq-setup-card .mq-btn {
			background: #667eea;
			color: white;
			padding: 12px 24px;
			border-radius: 25px;
			text-decoration: none;
			display: inline-block;
			transition: background 0.3s ease;
		}
		.mq-setup-card .mq-btn:hover {
			background: #5a6fd8;
		}
	</style>

	<div class="mq-setup-container">
		<div class="mq-setup-header">
			<h1>🎯 Quiz Setup & Configuration</h1>
			<p>Configure the core settings and structure of your Money Quiz</p>
		</div>

		<div class="mq-setup-grid">
			<div class="mq-setup-card">
				<h3>📐 Quiz Layout</h3>
				<p>Configure the overall layout, styling, and appearance of your quiz interface.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_quiz'); ?>" class="mq-btn">Configure Layout</a>
			</div>

			<div class="mq-setup-card">
				<h3>❓ Questions</h3>
				<p>Manage and customize the quiz questions, their order, and scoring system.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_questions'); ?>" class="mq-btn">Manage Questions</a>
			</div>

			<div class="mq-setup-card">
				<h3>🎭 Archetypes</h3>
				<p>Configure the personality archetypes and their characteristics for quiz results.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_archetypes'); ?>" class="mq-btn">Setup Archetypes</a>
			</div>

			<div class="mq-setup-card">
				<h3>📞 CTA Settings</h3>
				<p>Configure call-to-action buttons, forms, and conversion elements.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_cta'); ?>" class="mq-btn">Configure CTA</a>
			</div>
		</div>
	</div>
</div> 