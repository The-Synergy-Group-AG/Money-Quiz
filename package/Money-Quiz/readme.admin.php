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
		.mq-readme-container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 20px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.mq-readme-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 40px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 30px;
		}
		.mq-readme-header h1 {
			font-size: 2.5em;
			margin: 0 0 10px 0;
			font-weight: 700;
		}
		.mq-readme-header p {
			font-size: 1.2em;
			opacity: 0.9;
			margin: 0;
		}
		.mq-readme-section {
			background: white;
			border-radius: 10px;
			padding: 30px;
			margin-bottom: 25px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
		}
		.mq-readme-section h2 {
			color: #333;
			font-size: 1.8em;
			margin: 0 0 20px 0;
			padding-bottom: 10px;
			border-bottom: 3px solid #667eea;
		}
		.mq-readme-section h3 {
			color: #555;
			font-size: 1.4em;
			margin: 25px 0 15px 0;
			font-weight: 700 !important;
			line-height: 1.5 !important;
			display: block !important;
			position: relative !important;
			z-index: 10 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		.mq-readme-section h4 {
			color: #555;
			font-size: 1.2em;
			margin: 20px 0 10px 0;
			font-weight: 700 !important;
			line-height: 1.5 !important;
			display: block !important;
			position: relative !important;
			z-index: 10 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		.mq-readme-section p {
			color: #666;
			line-height: 1.7;
			margin-bottom: 15px;
		}
		.mq-readme-section ul, .mq-readme-section ol {
			color: #666;
			line-height: 1.7;
			margin-bottom: 15px;
			padding-left: 25px;
		}
		.mq-readme-section li {
			margin-bottom: 8px;
			line-height: 1.7 !important;
			display: block !important;
			position: relative !important;
			z-index: 5 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		.mq-feature-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin: 20px 0;
		}
		.mq-feature-card {
			background: #f8f9fa !important;
			padding: 30px !important;
			border-radius: 8px !important;
			border-left: 4px solid #667eea !important;
			display: flex !important;
			flex-direction: column !important;
			min-height: 200px !important;
			position: relative !important;
			box-sizing: border-box !important;
		}
		.mq-feature-card h4 {
			color: #333 !important;
			margin: 0 0 25px 0 !important;
			padding: 0 !important;
			font-size: 1.4em !important;
			font-weight: 700 !important;
			line-height: 1.5 !important;
			display: block !important;
			position: relative !important;
			z-index: 10 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
		}
		.mq-feature-card p {
			margin: 0 !important;
			padding: 0 !important;
			font-size: 1.05em !important;
			line-height: 1.7 !important;
			flex-grow: 1 !important;
			display: block !important;
			position: relative !important;
			z-index: 5 !important;
			color: #555 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
		}
		.mq-step-number {
			background: #667eea;
			color: white;
			width: 30px;
			height: 30px;
			border-radius: 50%;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			font-weight: bold;
			margin-right: 10px;
		}
		.mq-note {
			background: #fff3cd;
			border: 1px solid #ffeaa7;
			border-radius: 8px;
			padding: 15px;
			margin: 15px 0;
		}
		.mq-note strong {
			color: #856404;
		}
		.mq-tip {
			background: #d1ecf1;
			border: 1px solid #bee5eb;
			border-radius: 8px;
			padding: 15px;
			margin: 15px 0;
		}
		.mq-tip strong {
			color: #0c5460;
		}
		.mq-menu-structure {
			background: #f8f9fa;
			border-radius: 8px;
			padding: 20px;
			margin: 15px 0;
		}
		.mq-menu-category {
			margin-bottom: 20px;
		}
		.mq-menu-category h4 {
			color: #667eea;
			margin: 0 0 10px 0;
			font-size: 1.1em;
			font-weight: 700 !important;
			line-height: 1.5 !important;
			display: block !important;
			position: relative !important;
			z-index: 10 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		.mq-menu-items {
			list-style: none;
			padding-left: 20px;
		}
		.mq-menu-items li {
			margin-bottom: 5px;
			color: #666;
			line-height: 1.7 !important;
			display: block !important;
			position: relative !important;
			z-index: 5 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		.mq-menu-items li:before {
			content: "•";
			color: #667eea;
			font-weight: bold;
			margin-right: 8px;
		}
		/* Troubleshooting section fixes */
		.mq-readme-section h3 {
			color: #555;
			font-size: 1.4em;
			margin: 25px 0 15px 0;
			font-weight: 700 !important;
			line-height: 1.5 !important;
			display: block !important;
			position: relative !important;
			z-index: 10 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		.mq-readme-section h4 {
			color: #555;
			font-size: 1.2em;
			margin: 20px 0 10px 0;
			font-weight: 700 !important;
			line-height: 1.5 !important;
			display: block !important;
			position: relative !important;
			z-index: 10 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		.mq-readme-section ul, .mq-readme-section ol {
			color: #666;
			line-height: 1.7;
			margin-bottom: 15px;
			padding-left: 25px;
		}
		.mq-readme-section li {
			margin-bottom: 8px;
			line-height: 1.7 !important;
			display: block !important;
			position: relative !important;
			z-index: 5 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		/* Menu Structure specific fixes */
		#menu-structure .mq-readme-section h2,
		#troubleshooting .mq-readme-section h2 {
			color: #333 !important;
			font-size: 1.8em !important;
			margin: 0 0 20px 0 !important;
			padding-bottom: 10px !important;
			border-bottom: 3px solid #667eea !important;
			font-weight: 700 !important;
			line-height: 1.5 !important;
			display: block !important;
			position: relative !important;
			z-index: 15 !important;
			background: transparent !important;
			border-top: none !important;
			border-left: none !important;
			border-right: none !important;
			text-align: left !important;
		}
		#menu-structure .mq-menu-category h4,
		#troubleshooting .mq-readme-section h3,
		#troubleshooting .mq-readme-section h4 {
			color: #667eea !important;
			margin: 0 0 10px 0 !important;
			font-size: 1.1em !important;
			font-weight: 700 !important;
			line-height: 1.5 !important;
			display: block !important;
			position: relative !important;
			z-index: 10 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
		#menu-structure .mq-menu-items li,
		#troubleshooting .mq-readme-section ul li {
			margin-bottom: 5px !important;
			color: #666 !important;
			line-height: 1.7 !important;
			display: block !important;
			position: relative !important;
			z-index: 5 !important;
			background: transparent !important;
			border: none !important;
			text-align: left !important;
			padding: 0 !important;
		}
	</style>

	<div class="mq-readme-container">
		<div class="mq-readme-header">
			<h1>🎯 Money Quiz Plugin</h1>
			<p>Complete User Guide & Documentation</p>
		</div>

		<div class="mq-readme-section">
			<h2>📋 Table of Contents</h2>
			<ol>
				<li><a href="#overview">Plugin Overview</a></li>
				<li><a href="#features">Key Features</a></li>
				<li><a href="#installation">Installation & Setup</a></li>
				<li><a href="#configuration">Configuration Guide</a></li>
				<li><a href="#menu-structure">Menu Structure</a></li>
				<li><a href="#usage">Usage Instructions</a></li>
				<li><a href="#troubleshooting">Troubleshooting</a></li>
				<li><a href="#support">Support & Contact</a></li>
			</ol>
		</div>

		<div class="mq-readme-section" id="overview">
			<h2>🔍 Plugin Overview</h2>
			<p>The Money Quiz Plugin is a comprehensive financial assessment tool designed to help users understand their money mindset and financial archetype. Built by <strong>The Synergy Group AG</strong>, this plugin transforms your WordPress site into a powerful financial education platform.</p>
			
			<p>The plugin creates interactive quizzes that assess users' financial behaviors, attitudes, and beliefs, providing personalized insights and recommendations based on their responses. It's perfect for financial coaches, consultants, and organizations looking to engage their audience with meaningful financial content.</p>
		</div>

		<div class="mq-readme-section" id="features">
			<h2>✨ Key Features</h2>
			<div class="mq-feature-grid">
				<div class="mq-feature-card">
					<h4>🎨 Customizable Quiz Design</h4>
					<p>Fully customizable quiz layout, colors, and branding to match your website's design.</p>
				</div>
				<div class="mq-feature-card">
					<h4>📊 Advanced Analytics</h4>
					<p>Comprehensive reporting and statistics to track user engagement and quiz performance.</p>
				</div>
				<div class="mq-feature-card">
					<h4>📧 Email Integration</h4>
					<p>Automated email notifications and integration with popular email marketing platforms.</p>
				</div>
				<div class="mq-feature-card">
					<h4>🔒 Security Features</h4>
					<p>Built-in reCAPTCHA protection and secure data handling for user privacy.</p>
				</div>
				<div class="mq-feature-card">
					<h4>📱 Mobile Responsive</h4>
					<p>Optimized for all devices, ensuring a great user experience on desktop, tablet, and mobile.</p>
				</div>
				<div class="mq-feature-card">
					<h4>🔄 Lead Generation</h4>
					<p>Capture and manage prospects with detailed contact information and quiz results.</p>
				</div>
			</div>
		</div>

		<div class="mq-readme-section" id="installation">
			<h2>🚀 Installation & Setup</h2>
			
			<h3>Prerequisites</h3>
			<ul>
				<li>WordPress 5.0 or higher</li>
				<li>PHP 7.4 or higher</li>
				<li>MySQL 5.6 or higher</li>
				<li>Administrator access to your WordPress site</li>
			</ul>

			<h3>Installation Steps</h3>
			<p><span class="mq-step-number">1</span><strong>Upload the Plugin:</strong> Upload the plugin files to the `/wp-content/plugins/money-quiz/` directory, or install through WordPress admin panel using the plugin uploader.</p>
			
			<p><span class="mq-step-number">2</span><strong>Activate the Plugin:</strong> Go to Plugins > Installed Plugins and click "Activate" next to "Money Quiz".</p>
			
			<p><span class="mq-step-number">3</span><strong>Database Setup:</strong> The plugin will automatically create the necessary database tables upon activation.</p>
			
			<p><span class="mq-step-number">4</span><strong>Access the Menu:</strong> Navigate to "Money Quiz" in your WordPress admin menu to begin configuration.</p>

			<div class="mq-note">
				<strong>Note:</strong> Make sure your server has sufficient memory and execution time limits for optimal performance.
			</div>
		</div>

		<div class="mq-readme-section" id="configuration">
			<h2>⚙️ Configuration Guide</h2>
			
			<h3>Initial Setup</h3>
			<ol>
				<li><strong>Quiz Layout:</strong> Configure the main quiz appearance and structure</li>
				<li><strong>Questions:</strong> Add and organize your quiz questions</li>
				<li><strong>Archetypes:</strong> Set up different personality/archetype categories</li>
				<li><strong>CTA Settings:</strong> Configure call-to-action elements</li>
			</ol>

			<h3>Advanced Configuration</h3>
			<ol>
				<li><strong>Start Screen:</strong> Customize the quiz introduction page</li>
				<li><strong>Question Sections:</strong> Organize questions into logical sections</li>
				<li><strong>Display Settings:</strong> Configure how results are presented</li>
				<li><strong>Email Settings:</strong> Set up automated email communications</li>
			</ol>

			<div class="mq-tip">
				<strong>Pro Tip:</strong> Start with the basic configuration and gradually add advanced features as you become familiar with the plugin.
			</div>
		</div>

		<div class="mq-readme-section" id="menu-structure">
			<h2>📱 Menu Structure</h2>
			<p>The Money Quiz admin menu is organized into logical categories for easy navigation:</p>
			
			<div class="mq-menu-structure">
				<div class="mq-menu-category">
					<h4>🎯 Quiz Setup & Configuration</h4>
					<ul class="mq-menu-items">
						<li>Quiz Layout - Main quiz appearance and structure</li>
						<li>Questions - Add and manage quiz questions</li>
						<li>Archetypes - Define personality categories</li>
						<li>CTA Settings - Call-to-action configuration</li>
					</ul>
				</div>
				
				<div class="mq-menu-category">
					<h4>🎨 Quiz Experience</h4>
					<ul class="mq-menu-items">
						<li>Start Screen - Quiz introduction customization</li>
						<li>Question Sections - Organize questions by sections</li>
						<li>Display Settings - Result presentation options</li>
					</ul>
				</div>
				
				<div class="mq-menu-category">
					<h4>📧 Communication & Marketing</h4>
					<ul class="mq-menu-items">
						<li>Email Settings - Automated email configuration</li>
						<li>Pop-up Settings - Modal and popup options</li>
						<li>Integration - Third-party service connections</li>
					</ul>
				</div>
				
				<div class="mq-menu-category">
					<h4>📊 Data & Analytics</h4>
					<ul class="mq-menu-items">
						<li>Prospects - Lead management and contact details</li>
						<li>Reports - Detailed quiz performance reports</li>
						<li>Statistics - Usage analytics and insights</li>
					</ul>
				</div>
				
				<div class="mq-menu-category">
					<h4>🔧 Additional Features</h4>
					<ul class="mq-menu-items">
						<li>MoneyCoach - Integration with MoneyCoach platform</li>
						<li>Recaptcha - Security and spam protection</li>
						<li>ReadMe - This documentation page</li>
						<li>Credits - Plugin information and credits</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="mq-readme-section" id="usage">
			<h2>📖 Usage Instructions</h2>
			
			<h3>Creating Your First Quiz</h3>
			<ol>
				<li>Go to <strong>Money Quiz > Quiz Layout</strong> and configure the basic appearance</li>
				<li>Navigate to <strong>Questions</strong> and add your quiz questions</li>
				<li>Set up <strong>Archetypes</strong> to define different personality types</li>
				<li>Configure <strong>CTA Settings</strong> for lead generation</li>
				<li>Customize the <strong>Start Screen</strong> and <strong>Display Settings</strong></li>
				<li>Set up <strong>Email Settings</strong> for automated follow-ups</li>
			</ol>

			<h3>Managing Prospects</h3>
			<p>All quiz participants are automatically saved as prospects. You can:</p>
			<ul>
				<li>View all prospects in the <strong>Prospects</strong> section</li>
				<li>Export prospect data for use in other systems</li>
				<li>Set up email automation for follow-up communications</li>
				<li>Integrate with email marketing platforms</li>
			</ul>

			<h3>Analyzing Performance</h3>
			<p>Use the <strong>Reports</strong> and <strong>Statistics</strong> sections to:</p>
			<ul>
				<li>Track quiz completion rates</li>
				<li>Analyze user engagement patterns</li>
				<li>Monitor lead generation effectiveness</li>
				<li>Identify popular archetypes and responses</li>
			</ul>
		</div>

		<div class="mq-readme-section" id="troubleshooting">
			<h2>🔧 Troubleshooting</h2>
			
			<h3>Common Issues</h3>
			
			<h4>Plugin Not Activating</h4>
			<ul>
				<li>Check PHP version compatibility (requires 7.4+)</li>
				<li>Ensure sufficient server memory (recommended: 256MB+)</li>
				<li>Verify WordPress version (requires 5.0+)</li>
			</ul>

			<h4>Database Errors</h4>
			<ul>
				<li>Ensure your database user has CREATE TABLE permissions</li>
				<li>Check for sufficient database space</li>
				<li>Verify MySQL version compatibility</li>
			</ul>

			<h4>Email Not Sending</h4>
			<ul>
				<li>Check your server's email configuration</li>
				<li>Verify SMTP settings if using custom email service</li>
				<li>Check spam folder for test emails</li>
			</ul>

			<h4>Styling Issues</h4>
			<ul>
				<li>Clear browser cache and WordPress cache</li>
				<li>Check for theme conflicts</li>
				<li>Verify CSS is loading properly</li>
			</ul>

			<div class="mq-tip">
				<strong>Need Help?</strong> If you're experiencing issues not covered here, please contact our support team with detailed information about your setup and the specific problem you're encountering.
			</div>
		</div>

		<div class="mq-readme-section" id="support">
			<h2>📞 Support & Contact</h2>
			
			<p>We're here to help you get the most out of your Money Quiz Plugin!</p>
			
			<h3>Support Options</h3>
			<ul>
				<li><strong>Documentation:</strong> This comprehensive guide covers all major features</li>
				<li><strong>Integration Support:</strong> For custom integrations, visit our <a href="https://forms.gle/vWSkXS14oZ9FZ5i97" target="_blank">integration request form</a></li>
				<li><strong>Technical Support:</strong> Contact us at <a href="mailto:andre@theSynergyGroup.ch">andre@theSynergyGroup.ch</a></li>
			</ul>

			<h3>About The Synergy Group AG</h3>
			<p>We specialize in creating powerful business tools and solutions that help organizations transform their financial mindset and achieve sustainable growth. Our MoneyQuiz plugin represents our commitment to excellence in financial education and business development.</p>
			
			<p><strong>Website:</strong> <a href="https://www.thesynergygroup.ch" target="_blank">www.thesynergygroup.ch</a></p>
			<p><strong>Location:</strong> Zurich, Switzerland</p>

			<div class="mq-note">
				<strong>Thank you for choosing The Synergy Group AG!</strong> We appreciate your business and are committed to providing you with the best possible experience with our Money Quiz Plugin.
			</div>
		</div>
	</div>
</div>
<!-- .wrap --> 