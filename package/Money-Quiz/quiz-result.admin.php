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
		 
		<input name="action" value="quiz_result_setting" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
            <tr>
                <th><label>Intro Content</label></th>		
                <td>
                     <?php
                        $intro_content = $quiz_result_setting[1]; 
                        $id = 'intro_content'; 
                        $arg = array( 
                        'textarea_name' => 'quiz_result_setting[1]', 
                       
                        );
                        wp_editor( stripslashes($intro_content), $id, $arg );

                    ?>
            </td>
                
            </tr>
            <tr>
					<th scope="row"><label for="">Table Result Image</label></th>
					<td><?php if($quiz_result_setting[2] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image review-image-view" src="<?php echo $quiz_result_setting[2]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="quiz_result_setting[2]" id="image_url" value="<?php echo $quiz_result_setting[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="quiz_result_setting[2]" id="image_url" value="<?php echo $quiz_result_setting[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
                <tr>
					<th scope="row"><label for="">Chart Result Image</label></th>
					<td><?php if($quiz_result_setting[3] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image review-image-view" src="<?php echo $quiz_result_setting[3]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="quiz_result_setting[3]" id="image_url" value="<?php echo $quiz_result_setting[3]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="quiz_result_setting[3]" id="image_url" value="<?php echo $quiz_result_setting[3]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>  
            <tr>
                <th><label>Result Bottom Content</th>		
                <td>
                    <?php
                        $email_bottom_contnt = $quiz_result_setting[4]; 
                        $id = 'email_content'; 
                        $arg = array( 
                        'textarea_name' => 'quiz_result_setting[4]', 
                       
                        );
                        wp_editor( stripslashes($email_bottom_contnt), $id, $arg );

                    ?>
                </td>
            </tr>
            <tr>
                <th><label>Result Footer Content</th>		
                <td>
                    <?php
                        $email_bottom_contnt = $quiz_result_setting[5]; 
                        $id = 'result_footer_content'; 
                        $arg = array( 
                        'textarea_name' => 'quiz_result_setting[5]', 
                       
                        );
                        wp_editor( stripslashes($email_bottom_contnt), $id, $arg );

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