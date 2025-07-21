<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
	
 ?>

<div class=" mq-container">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<?php 
		// fetch data for email signature email
		$sql = "SELECT * FROM ".$table_prefix.EMAIL_SIGNATURE."" ;
		$rows = $wpdb->get_results($sql);
		$signature_email = array();
		foreach($rows as $row){
			$signature_email[$row->id] = stripslashes($row->value);
		}
	?>
	<h3>Money Coach Personal Details</h3>
	<?php echo $save_msg ?>
	<form method="post" action="" novalidate="novalidate">
		 
		<input name="action" value="update" type="hidden">
		<input name="email_signature_update" type="hidden" value="1">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="mq_first_name">First Name</label></th>
					<td><input name="post_data[2]" id="mq_first_name" value="<?php echo $post_data[2]?>" class="regular-text" type="text"></td>
				</tr>	
				<tr>
					<th scope="row"><label for="Surname">Surname</label></th>
					<td><input name="post_data[3]" id="Surname" value="<?php echo $post_data[3]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Title">Title</label></th>
					<td><input name="post_data[4]" id="Title" value="<?php echo $post_data[4]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Professional_Title">Professional Title</label></th>
					<td><input name="post_data[5]" id="Professional_Title" value="<?php echo $post_data[5]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Company">Company</label></th>
					<td><input name="post_data[6]" id="Company" value="<?php echo $post_data[6]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Address">Address</label></th>
					<td><input name="post_data[7]" id="Address" value="<?php echo $post_data[7]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Telephone">Telephone</label></th>
					<td><input name="post_data[8]" id="Telephone" value="<?php echo $post_data[8]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Email">Email</label></th>
					<td><input name="post_data[9]" id="Email" value="<?php echo $post_data[9]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Website">Website</label></th>
					<td><input name="post_data[10]" id="Website" value="<?php echo $post_data[10]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Online_Calendar_Link">Online Calendar Link</label></th>
					<td><input name="post_data[11]" id="Online_Calendar_Link" value="<?php echo $post_data[11]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<td colspan="2"><h3>Email signature Template Setting<h3><hr></td>
				</tr>
					<tr>
					<th scope="row"><label for="Website">Heading Text</label></th>
					<td><input name="signature_email[1]" id="Website" value="<?php echo $signature_email[1]?>" class="regular-text" type="text"></td>
					</tr>
					<tr>
					<th scope="row"><label for="Company_Logo">Author Image</label></th>
					<td> 
						<?php if($signature_email[2] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $signature_email[2]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="signature_email[2]" id="image_url" value="<?php echo $signature_email[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="signature_email[2]" id="image_url" value="<?php echo $signature_email[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>	
				<tr>
					<th scope="row"><label for="Website">Facebook Link </label></th>
					<td><input name="signature_email[3]" id="facebook_link" value="<?php echo $signature_email[3]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Website">Linkin Link </label></th>
					<td><input name="signature_email[4]" id="Linkin_link" value="<?php echo $signature_email[4]?>" class="regular-text" type="text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="Website">Instagram Link </label></th>
					<td><input name="signature_email[5]" id="instagram_link" value="<?php echo $signature_email[5]?>" class="regular-text" type="text"></td>
				</tr>
				
				<tr>
					<th scope="row"><label for="Company_Logo">Company Logo</label></th>
					<td> 
						<?php if($post_data[12] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[12]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[12]" id="image_url" value="<?php echo $post_data[12]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[12]" id="image_url" value="<?php echo $post_data[12]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
							
				<tr>
					<th scope="row">&nbsp;</th>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p></td>
				</tr>					
				
			</tbody>
		</table>
		
	</form>

</div>
<!-- .wrap -->