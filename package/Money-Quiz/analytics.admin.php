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
		.mq-analytics-container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 20px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.mq-analytics-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 40px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 30px;
		}
		.mq-analytics-header h1 {
			margin: 0;
			font-size: 2.5em;
			font-weight: 300;
		}
		.mq-analytics-header p {
			margin: 10px 0 0 0;
			font-size: 1.2em;
			opacity: 0.9;
		}
		.mq-analytics-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-top: 30px;
		}
		.mq-analytics-card {
			background: white;
			border-radius: 10px;
			padding: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			text-align: center;
			transition: transform 0.3s ease;
		}
		.mq-analytics-card:hover {
			transform: translateY(-5px);
		}
		.mq-analytics-card h3 {
			color: #667eea;
			margin: 0 0 15px 0;
			font-size: 1.3em;
		}
		.mq-analytics-card p {
			color: #666;
			margin: 0 0 20px 0;
			line-height: 1.6;
		}
		.mq-analytics-card .mq-btn {
			background: #667eea;
			color: white;
			padding: 12px 24px;
			border-radius: 25px;
			text-decoration: none;
			display: inline-block;
			transition: background 0.3s ease;
		}
		.mq-analytics-card .mq-btn:hover {
			background: #5a6fd8;
		}
	</style>

	<div class="mq-analytics-container">
		<div class="mq-analytics-header">
			<h1>📊 Data & Analytics</h1>
			<p>Track performance, analyze results, and generate comprehensive reports</p>
		</div>

		<div class="mq-analytics-grid">
			<div class="mq-analytics-card">
				<h3>👥 Prospects</h3>
				<p>View and manage all quiz participants, their responses, and contact information.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_prospects'); ?>" class="mq-btn">View Prospects</a>
			</div>

			<div class="mq-analytics-card">
				<h3>📈 Reports</h3>
				<p>Generate detailed reports on quiz performance, user engagement, and conversion metrics.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_reports'); ?>" class="mq-btn">Generate Reports</a>
			</div>

			<div class="mq-analytics-card">
				<h3>📊 Statistics</h3>
				<p>View comprehensive statistics and analytics about quiz usage and performance.</p>
				<a href="<?php echo admin_url('admin.php?page=mq_stats'); ?>" class="mq-btn">View Statistics</a>
			</div>
		</div>
	</div>
</div> 