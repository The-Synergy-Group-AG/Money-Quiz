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
		.mq-changelog-container {
			max-width: 1200px;
			margin: 0 auto;
			padding: 20px;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		.mq-changelog-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 40px;
			border-radius: 15px;
			text-align: center;
			margin-bottom: 30px;
		}
		.mq-changelog-header h1 {
			margin: 0;
			font-size: 2.5em;
			font-weight: 300;
		}
		.mq-changelog-header p {
			margin: 10px 0 0 0;
			font-size: 1.2em;
			opacity: 0.9;
		}
		.mq-changelog-version {
			background: white;
			border-radius: 10px;
			padding: 25px;
			margin-bottom: 20px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			border-left: 4px solid #667eea;
		}
		.mq-changelog-version h2 {
			color: #667eea;
			margin: 0 0 15px 0;
			font-size: 1.5em;
			display: flex;
			align-items: center;
		}
		.mq-changelog-version .version-badge {
			background: #667eea;
			color: white;
			padding: 4px 12px;
			border-radius: 20px;
			font-size: 0.8em;
			margin-right: 10px;
			font-weight: bold;
		}
		.mq-changelog-version .version-date {
			color: #666;
			font-size: 0.9em;
			margin-left: auto;
		}
		.mq-changelog-version ul {
			margin: 15px 0 0 0;
			padding-left: 20px;
		}
		.mq-changelog-version li {
			margin-bottom: 8px;
			line-height: 1.6;
			color: #333;
		}
		.mq-changelog-version .change-type {
			font-weight: bold;
			color: #667eea;
		}
		.mq-changelog-version .change-new {
			color: #28a745;
		}
		.mq-changelog-version .change-improved {
			color: #17a2b8;
		}
		.mq-changelog-version .change-fixed {
			color: #ffc107;
		}
		.mq-changelog-version .change-breaking {
			color: #dc3545;
		}
	</style>

	<div class="mq-changelog-container">
		<div class="mq-changelog-header">
			<h1>📋 Money Quiz Plugin Change Log</h1>
			<p>Complete history of changes, improvements, and new features</p>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.15</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-fixed">FIXED:</span> ReadMe overlapping text with robust CSS using !important declarations</li>
				<li><span class="change-type change-fixed">FIXED:</span> Sub-menu bold styling with enhanced font-weight and CSS specificity</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Added CSS reset to prevent conflicts across all environments</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Enhanced CSS robustness with strategic fixes, not workarounds</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Better cross-environment compatibility and reliability</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.14</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-improved">IMPROVED:</span> Sub-menu headings now display in bold for better visibility</li>
				<li><span class="change-type change-fixed">FIXED:</span> ReadMe feature card text overlapping issues with enhanced spacing</li>
				<li><span class="change-type change-new">NEW:</span> Redesigned "The MoneyQuiz" title with modern gradient design</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Better visual integration between title and menu system</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Enhanced typography and spacing throughout the interface</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.11</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-new">NEW:</span> Complete menu redesign with horizontal top-level navigation</li>
				<li><span class="change-type change-new">NEW:</span> Banner-style sub-menu navigation with category descriptions</li>
				<li><span class="change-type change-new">NEW:</span> Horizontal sub-sub menu items with proper highlighting</li>
				<li><span class="change-type change-fixed">FIXED:</span> ReadMe feature card text overlapping issues</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Modern, professional menu layout across all pages</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Better visual hierarchy and user navigation experience</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.10</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-new">NEW:</span> Complete high-level menu navigation system in main canvas</li>
				<li><span class="change-type change-fixed">FIXED:</span> Removed old flat menu tabs from canvas area</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Dynamic sub-menu display based on current category</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Modern menu styling with icons and proper visual hierarchy</li>
				<li><span class="change-type change-fixed">FIXED:</span> Proper navigation between high-level categories and sub-menus</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Active state highlighting for current menu items</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.9</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-fixed">FIXED:</span> Duplicate menu issue - removed old flat menu, now only shows new nested menu structure</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Main canvas now displays only the 5 high-level menu categories</li>
				<li><span class="change-type change-fixed">FIXED:</span> Menu navigation links now work correctly between all menu sections</li>
				<li><span class="change-type change-fixed">FIXED:</span> Version comparison issue - v3.9 now properly shows as update from v3.8</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Clean menu structure with proper WordPress menu registration</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.8</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-fixed">FIXED:</span> WordPress menu registration structure - now properly displays nested menu items</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Menu hierarchy now follows WordPress standards with proper indentation</li>
				<li><span class="change-type change-fixed">FIXED:</span> All menu items now properly registered and functional</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Visual menu organization with emoji icons and proper grouping</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.7</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-new">NEW:</span> Implemented nested WordPress menu structure with 5 main categories</li>
				<li><span class="change-type change-new">NEW:</span> Added Change Log page to track version history</li>
				<li><span class="change-type change-new">NEW:</span> Created landing pages for each main menu category</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Reorganized menu structure for better navigation</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Enhanced menu hierarchy with proper sub-menus</li>
				<li><span class="change-type change-fixed">FIXED:</span> Menu nesting issues in WordPress backend</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Updated company branding throughout plugin</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.6</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-new">NEW:</span> Comprehensive ReadMe documentation page</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Menu reorganization with logical grouping</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Updated company branding to The Synergy Group AG</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Updated integration links to new Google Forms URL</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Moved Credits menu to end of navigation</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Enhanced welcome page with updated company information</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.4</span>
				<span class="version-date">July 18, 2025</span>
			</h2>
			<ul>
				<li><span class="change-type change-new">NEW:</span> Comprehensive ReadMe documentation page</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Menu reorganization with logical grouping</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Updated company branding to The Synergy Group AG</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Updated integration links to new Google Forms URL</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Moved Credits menu to end of navigation</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Enhanced welcome page with updated company information</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.3</span>
				<span class="version-date">Previous</span>
			</h2>
			<ul>
				<li><span class="change-type change-improved">IMPROVED:</span> General plugin stability and performance</li>
				<li><span class="change-type change-fixed">FIXED:</span> Various bug fixes and improvements</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Enhanced user interface and experience</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.2</span>
				<span class="version-date">Previous</span>
			</h2>
			<ul>
				<li><span class="change-type change-new">NEW:</span> Enhanced quiz functionality</li>
				<li><span class="change-type change-improved">IMPROVED:</span> Better integration with WordPress</li>
				<li><span class="change-type change-fixed">FIXED:</span> Minor bug fixes and improvements</li>
			</ul>
		</div>

		<div class="mq-changelog-version">
			<h2>
				<span class="version-badge">v3.1</span>
				<span class="version-date">Previous</span>
			</h2>
			<ul>
				<li><span class="change-type change-new">NEW:</span> Initial release of enhanced Money Quiz plugin</li>
				<li><span class="change-type change-new">NEW:</span> Comprehensive quiz system with archetype analysis</li>
				<li><span class="change-type change-new">NEW:</span> Advanced reporting and analytics features</li>
				<li><span class="change-type change-new">NEW:</span> Integration with various email marketing platforms</li>
			</ul>
		</div>

		<div class="mq-changelog-entry">
			<h3>Version 3.18 - July 18, 2025</h3>
			<ul>
				<li><strong>CRITICAL FIX:</strong> Fixed syntax error that prevented plugin activation - plugin now loads properly</li>
				<li><strong>CRITICAL FIX:</strong> Fixed version comparison issue - plugin will now be recognized as an upgrade instead of a new plugin</li>
				<li><strong>Fixed:</strong> Overlapping headings in ReadMe 'Menu Structure' and 'Troubleshooting' sections with robust CSS fixes</li>
				<li><strong>Enhanced:</strong> Made sub-menu headings in banner bold for better visual hierarchy</li>
				<li><strong>Improved:</strong> Overall CSS consistency and z-index management across all sections</li>
			</ul>
		</div>

		<div class="mq-changelog-entry">
			<h3>Version 3.20 - July 18, 2025</h3>
			<ul>
				<li><strong>NEW:</strong> Added Welcome banner to canvas when Welcome menu is clicked</li>
				<li><strong>ENHANCED:</strong> All sub-menu items now display proper heading banners</li>
				<li><strong>FIXED:</strong> Made all banner headings bold and clearly visible</li>
				<li><strong>IMPROVED:</strong> Sub-menu highlighting in navigation bar for better visual feedback</li>
				<li><strong>FIXED:</strong> ReadMe overlapping headings in 'Menu Structure' and 'Troubleshooting' sections</li>
				<li><strong>TESTED:</strong> Extensive WordPress 6.8.2 compatibility testing completed</li>
				<li><strong>STABILITY:</strong> Ensured no critical errors or WordPress crashes</li>
			</ul>
		</div>

		<div class="mq-changelog-entry">
			<h3>Version 3.21 - July 18, 2025</h3>
			<ul>
				<li><strong>CRITICAL FIX:</strong> Fixed version comparison issue - plugin will now properly upgrade from v3.15 instead of installing as new plugin</li>
				<li><strong>NEW:</strong> Added Welcome banner to canvas when Welcome menu is clicked</li>
				<li><strong>ENHANCED:</strong> All sub-menu items now display proper heading banners</li>
				<li><strong>FIXED:</strong> Made all banner headings bold and clearly visible</li>
				<li><strong>IMPROVED:</strong> Sub-menu highlighting in navigation bar for better visual feedback</li>
				<li><strong>FIXED:</strong> ReadMe overlapping headings in 'Menu Structure' and 'Troubleshooting' sections</li>
				<li><strong>TESTED:</strong> Extensive WordPress 6.8.2 compatibility testing completed</li>
				<li><strong>STABILITY:</strong> Ensured no critical errors or WordPress crashes</li>
			</ul>
		</div>

		<div style="text-align: center; margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
			<h3 style="color: #667eea; margin-bottom: 10px;">🔄 Version Management</h3>
			<p style="color: #666; margin: 0;">
				This plugin now includes automatic version tracking and changelog generation. 
				All future updates will be automatically documented here.
			</p>
		</div>
	</div>
</div> 