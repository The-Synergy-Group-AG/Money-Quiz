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
		.mq-features-container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 20px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.mq-features-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 40px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 30px;
		}
		.mq-features-header h1 {
			margin: 0;
			font-size: 2.5em;
			font-weight: 300;
		}
		.mq-features-header p {
			margin: 10px 0 0 0;
			font-size: 1.2em;
			opacity: 0.9;
		}
		.mq-features-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-top: 30px;
		}
		.mq-features-card {
			background: white;
			border-radius: 10px;
			padding: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			text-align: center;
			transition: transform 0.3s ease;
		}
		.mq-features-card:hover {
			transform: translateY(-5px);
		}
		.mq-features-card h3 {
			color: #667eea;
			margin: 0 0 15px 0;
			font-size: 1.3em;
		}
		.mq-features-card p {
			color: #666;
			margin: 0 0 20px 0;
			line-height: 1.6;
		}
		.mq-features-card .mq-btn {
			background: #667eea;
			color: white;
			padding: 12px 24px;
			border-radius: 25px;
			text-decoration: none;
			display: inline-block;
			transition: background 0.3s ease;
		}
		.mq-features-card .mq-btn:hover {
			background: #5a6fd8;
		}
	</style>

	<div class="mq-features-container">
		<div class="mq-features-header">
			<h1>🔧 Additional Features</h1>
			<p>Access additional tools, security features, and documentation</p>
		</div>

		<div class="mq-features-grid">
			<div class="mq-features-card">
				<h3>💰 MoneyCoach</h3>
				<p>Configure MoneyCoach integration and coaching features for enhanced user experience.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_moneycoach'); ?>" class="mq-btn">Configure MoneyCoach</a>
			</div>

			<div class="mq-features-card">
				<h3>🔒 Recaptcha</h3>
				<p>Set up Google reCAPTCHA to protect your quiz from spam and automated submissions.</p>
				<a href="<?php echo admin_url('admin.php?page=recaptcha'); ?>" class="mq-btn">Configure Recaptcha</a>
			</div>

			<div class="mq-features-card">
				<h3>📖 ReadMe</h3>
				<p>Access comprehensive documentation and user guides for the Money Quiz plugin.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_readme'); ?>" class="mq-btn">View Documentation</a>
			</div>

			<div class="mq-features-card">
				<h3>📋 Change Log</h3>
				<p>View the complete version history and track all changes made to the plugin.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_changelog'); ?>" class="mq-btn">View Change Log</a>
			</div>

			<div class="mq-features-card">
				<h3>👨‍💼 Credits</h3>
				<p>Learn about the development team and company information.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_credit'); ?>" class="mq-btn">View Credits</a>
			</div>
		</div>
	</div>
</div> 