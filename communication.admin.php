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
		.mq-communication-container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 20px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.mq-communication-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 40px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 30px;
		}
		.mq-communication-header h1 {
			margin: 0;
			font-size: 2.5em;
			font-weight: 300;
		}
		.mq-communication-header p {
			margin: 10px 0 0 0;
			font-size: 1.2em;
			opacity: 0.9;
		}
		.mq-communication-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-top: 30px;
		}
		.mq-communication-card {
			background: white;
			border-radius: 10px;
			padding: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			text-align: center;
			transition: transform 0.3s ease;
		}
		.mq-communication-card:hover {
			transform: translateY(-5px);
		}
		.mq-communication-card h3 {
			color: #667eea;
			margin: 0 0 15px 0;
			font-size: 1.3em;
		}
		.mq-communication-card p {
			color: #666;
			margin: 0 0 20px 0;
			line-height: 1.6;
		}
		.mq-communication-card .mq-btn {
			background: #667eea;
			color: white;
			padding: 12px 24px;
			border-radius: 25px;
			text-decoration: none;
			display: inline-block;
			transition: background 0.3s ease;
		}
		.mq-communication-card .mq-btn:hover {
			background: #5a6fd8;
		}
	</style>

	<div class="mq-communication-container">
		<div class="mq-communication-header">
			<h1>📧 Communication & Marketing</h1>
			<p>Manage email communications, pop-ups, and marketing integrations</p>
		</div>

		<div class="mq-communication-grid">
			<div class="mq-communication-card">
				<h3>📧 Email Settings</h3>
				<p>Configure email templates, notifications, and automated follow-up sequences.</p>
				<a href="<?php echo admin_url('admin.php?page=email_setting'); ?>" class="mq-btn">Configure Emails</a>
			</div>

			<div class="mq-communication-card">
				<h3>💬 Pop-up Settings</h3>
				<p>Manage pop-up notifications, lead capture forms, and engagement tools.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_popup'); ?>" class="mq-btn">Configure Pop-ups</a>
			</div>

			<div class="mq-communication-card">
				<h3>🔗 Integration</h3>
				<p>Connect with email marketing platforms, CRM systems, and third-party services.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_integration'); ?>" class="mq-btn">Manage Integrations</a>
			</div>
		</div>
	</div>
</div> 