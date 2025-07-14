<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
  	// API query parameters
	$api_params = array(
		'slm_action' => 'slm_check',
		'secret_key' => MONEYQUIZ_SPECIAL_SECRET_KEY,
		'license_key' => $post_data[35],
		'registered_domain' => $_SERVER['SERVER_NAME'],
		'item_reference' => urlencode(MONEYQUIZ_ITEM_REFERENCE),
	);

	// Send query to the license manager server
	$query = esc_url_raw(add_query_arg($api_params, MONEYQUIZ_LICENSE_SERVER_URL));
	$response = wp_remote_get($query, array('timeout' => 20, 'sslverify' => false));

	// Check for error in the response
	if (is_wp_error($response)){
		echo "Unexpected Error! The query returned with an error.";
	}

	//var_dump($response);//uncomment it if you want to look at the full response
	
	// License data.
	$license_data_checked = json_decode(wp_remote_retrieve_body($response));
	
	// echo '<pre>';
	// print_r($license_data_checked);
	// echo '</pre>';
	
	//	if key is nearing renew date then send email to Money coach and business insights 
 
	 
 ?>
  
<div class=" mq-container">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<div class="mq-intro">
		<?php echo $save_msg ?>
	</div>	
	<div class="clear"></div>
		<table class="form-table mq-form-table mq-welcome-table">
			<tbody>
				<tr>
					<td scope="row">Thank you for downloading the <b>MoneyQuiz</b>, a powerful interpretative tool to help you interact with your prospects.</td>
				</tr>	
				<tr>
					<td scope="row">Before you start using this plugin, please customise the settings under each of the Tabs.<br></td>
				</tr>
				<?php if(empty($post_data[35])) { ?>
				<form method="post" action="" novalidate="novalidate">
					<input name="action" value="activate" type="hidden">
					<?php wp_nonce_field( );?>
				<tr>
					<td scope="row" ><h3>To activate this plugin you require a valid License Key.</h3> </td>
				</tr>	
				<tr>
					<td scope="row" ><div class="clear"></div><input type="text" name="plugin_license_key"  value="" class="regular-text" />  </td>
				</tr>	
				<tr>
					<td><input name="submit" id="submit" class="button button-primary" value="Activate Plugin" type="submit"></td>
				</tr>	
				</form>
				<?php }elseif(isset($license_data_checked->result) && $license_data_checked->result == "success" && time() < strtotime($license_data_checked->date_expiry)  && count($license_data_checked->registered_domains) > 0){ ?>
				<tr>
					<td scope="row" ><h3>Your Licence is valid until <?php echo $license_data_checked->date_expiry;?> </h3><br> 
						<form method="post" action="" novalidate="novalidate">
							<input name="action" value="renew_plugin" type="hidden">
							<?php wp_nonce_field( );?>
							<button name="renew_plugin" id="submit" class="button button-primary renew_plugin" value="Save Changes" type="submit">Renew Now</button>
						</form>
					</td>
				</tr>	
				<?php }else{ // license key expired ?>
				<tr>
					<td scope="row" ><br>
						<form method="post" action="" novalidate="novalidate">
							<input name="action" value="renew_plugin" type="hidden">
							<?php wp_nonce_field( );?>
							<button name="renew_plugin" id="submit" class="button button-primary renew_plugin" value="Save Changes" type="submit">Renew your Licence</button>
						</form>
					</td>
				</tr>	
				<?php } ?>
				<tr>
					<td class="mq-footer-copyright" ><br><br><br>All Rights reserved. Business Insights Group AG, Zurich Switzerland</td>
				</tr>
				
			</tbody>
		</table>
		 
	
<br> 
</div>
<!-- .wrap -->