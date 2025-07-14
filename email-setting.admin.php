<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
	
 ?>

<div class=" mq-container money-quiz-template-form">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<h3>Email Content Setting</h3>
	<?php echo $save_msg ?>
	<?php
		
	?>
	<form method="post" action="" novalidate="novalidate" class="email-setting-option">
		 
		<input name="action" value="email_setting" type="hidden">
		<?php wp_nonce_field( );?>
        <?php
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $first_name = $current_user->user_firstname;
            //echo 'First Name: ' . esc_html( $first_name );
        }
        ?>
		<table class="form-table mq-form-table">
			<tbody>
            <tr>
                <th><label>Email Subject</th>		
                <td><textarea  id="book_button_text" name="post_data[5]"><?php echo $email_setting[5]?></textarea></td>
                
            </tr>
            <tr>
                <th><label>Greeting</label></th>
                <td><input type="text" name="post_data[6]" value="<?php echo $email_setting[6];?>"></td>
                <th><label>First Name</label></th>
                <td><input type="text" name="first_name_defult" value="<?php echo $first_name;?>"></td>
            </tr>
            <tr>
                <th><label>Email Header Content</label></th>		
                <td>
            
                <?php
                        $email_thank_msg = $email_setting[1]; 
                        $id = 'email_thank_msg'; 
                        $arg = array( 
                        'textarea_name' => 'post_data[1]', 
                       
                        );
                        wp_editor( stripslashes($email_thank_msg), $id, $arg );

                    ?>
            </td>
                
            </tr>
            <tr>
					<th scope="row"><label for="">Table Result Image</label></th>
					<td><?php if($email_setting[8] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image review-image-view" src="<?php echo $email_setting[8]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[8]" id="image_url" value="<?php echo $email_setting[8]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[8]" id="image_url" value="<?php echo $email_setting[8]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
           
            <tr>
                <th><label>Email Body</th>		
                <td>
                    <?php
                        $email_bottom_contnt = $email_setting[2]; 
                        $id = 'email_content'; 
                        $arg = array( 
                        'textarea_name' => 'post_data[2]', 
                       
                        );
                        wp_editor( stripslashes($email_bottom_contnt), $id, $arg );

                    ?>
                </td>
            </tr>
            <tr>
                <th><label>Closing</th>		
                <td>
                    <?php
                        $email_closing_content = $email_setting[7]; 
                        $id = 'email_closing_content'; 
                        $arg = array( 
                        'textarea_name' => 'post_data[7]', 
                       
                        );
                        wp_editor( stripslashes($email_closing_content), $id, $arg );
                    ?>
                </td>
            </tr>
          
            <th scope="row">&nbsp;</th>
                <td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p></td>
            </tr>					
        
			</tbody>
		</table>
		
	</form>

</div>
<!-- .wrap -->