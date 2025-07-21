<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
	
 ?>

<div class=" mq-container money-quiz-template-form">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<h3>Money Quiz Page Template Layout Setting</h3>
	<?php echo $save_msg ?>
	<?php
		
	?>
	<form method="post" action="" novalidate="novalidate">
		 
		<input name="action" value="page_template_layout" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
					
			 <?php /*	echo '<tr>
					<th scope="row"><label for="Company_Logo">Money Quiz Page Banner Image</label></th>
					<td> 
						<?php if($template_layout_data[1] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $template_layout_data[1];?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[1]" id="image_url" value="<?php echo $template_layout_data[1]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[1]" id="image_url" value="<?php echo $template_layout_data[1]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
					
				</tr>';*/ ?>
                <tr>
					<th><label>Banner Title </label></th>		
					<td>
						<input id="banner_heading" type="text" name="post_data[5]" value="<?php echo $template_layout_data[5];?>"></td>
				</tr>
				<tr>			
					<th><label>Banner Content Text </label></th>		
					<td><textarea name="post_data[6]"><?php echo $template_layout_data[6];?></textarea>
					<?php
						/*
						$banner_heading_content_text = $template_layout_data[6]; 
						$id = 'banner_heading_content_text'; 
						$arg = array( 
						'textarea_name' => 'post_data[6]', 

						);
						wp_editor( $banner_heading_content_text, $id,$arg );
						*/	
					?>
				 	</td>
				</tr>
				<tr><td colspan="2"><hr></td></tr>
				<tr>
					<th><label>Column Section Heading Text</label></th>
					<td><input type="text" name="post_data[7]" value="<?php echo $template_layout_data[7]?>"></td>
				</tr>
                <tr>
					<th scope="row"><label for="Company_Logo">Money Quiz Column Image</label></th>
					<td> 
						<?php if($template_layout_data[2] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $template_layout_data[2]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[2]" id="image_url" value="<?php echo $template_layout_data[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[2]" id="image_url" value="<?php echo $template_layout_data[1]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th><label>Money Quiz Column Content</label></th>		
					<td colspan="4">
					<?php
					
					$money_quiz_column_content =  $template_layout_data[17]; 
					$id = 'money_quiz_column_content_editor'; 
					$arg = array( 
					'textarea_name' => 'post_data[17]', 
					
					);
					wp_editor( $money_quiz_column_content, $id, $arg );
					
					?>
					
				</tr>
				<tr>
					<th>Money Quiz Buttom Text</th>
					<td><input type="text" name="post_data[16]" value="<?php echo $template_layout_data[16]?>"></td>
					</tr>
                <tr><td ><hr></td></tr>
				<tr>
				<th><label>Gift Section Heading</label></th>
					<td colspan="4">
					
					<?php	
						$gift_section_heading_editor = $template_layout_data[8]; 
						$id = 'gift_section_heading_editor'; 
						$arg = array( 
						'textarea_name' => 'post_data[8]', 

						);
						wp_editor( $gift_section_heading_editor, $id,$arg );

					?>
				
					</td>
				</tr>
				<tr><td colspan="2"><hr></td></tr>
				<tr>
					<th>Quiz Gift1 Display:</th> <td>
						<input type="radio" <?php if($template_layout_data[14]=="Yes") { ?> checked="checked" <?php  } ?>  name="post_data[14]" value="Yes">Yes 
						<input type="radio" <?php if($template_layout_data[14]=="No") { ?> checked="checked" <?php  } ?> name="post_data[14]" value="No">No </span></td>
					<th><lable>Gift 1 Heading Text</label></th>
					<td><input type="text" name="post_data[9]" value="<?php echo $template_layout_data[9]?>"></td>	
					
					
					
							
				</tr>
				<tr>
				<th scope="row"><label for="Company_Logo">Money Quiz Gift Image 1 </label></th>
					<td> 
						<?php if($template_layout_data[3] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $template_layout_data[3]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[3]" id="image_url" value="<?php echo $template_layout_data[3]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[3]" id="image_url" value="<?php echo $template_layout_data[3]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
					<th>Gift 1 Content Text</th>
					<td>
					<?php
					
					$gift_1_content =  $template_layout_data[11]; 
					$id = 'gift_1_section_content'; 
					$arg = array( 
					'textarea_name' => 'post_data[11]', 
					
					);
					wp_editor( $gift_1_content, $id, $arg );
					
					?>	
				  </td>
				</tr>
				<tr><td colspan="2"><hr></td></tr>
				<tr>
				<th>Quiz Gift2 Display:</th><td> 
						<input type="radio" <?php if($template_layout_data[15]=="Yes") { ?> checked="checked" <?php  } ?>  name="post_data[15]" value="Yes">Yes
						 <input type="radio" <?php if($template_layout_data[15]=="No") { ?> checked="checked" <?php  } ?> name="post_data[15]" value="No">No </span></td>
				<th><lable>Gift 2 Heading Text</label></th>
				<td><input type="text" name="post_data[10]" value="<?php echo $template_layout_data[10];?>"></td>
					
				</tr>
				<tr>
				<th scope="row"><label for="Company_Logo">Money Quiz Gift Image 2 </label></th>
					<td> 
						<?php if($template_layout_data[4] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $template_layout_data[4]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[4]" id="image_url" value="<?php echo $template_layout_data[4]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[4]" id="image_url" value="<?php echo $template_layout_data[4]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>	
					<th><label>Gift 2 Content Text</label></th>
					<td>
					<?php
					
					$gift_2_content =  $template_layout_data[12]; 
					$id = 'gift_2section_content'; 
					$arg = array( 
					'textarea_name' => 'post_data[12]', 
					
					);
					wp_editor( $gift_2_content, $id, $arg );
					
					?>
				
				  </td>
				  <th><label>Bottom Section Content</label></th>
					<td>
					<?php
					
					$bottom_section_content =  $template_layout_data[13]; 
					$id = 'bottom_section_content'; 
					$arg = array( 
					'textarea_name' => 'post_data[13]', 
					
					);
					wp_editor( $bottom_section_content, $id, $arg );
					
					?>
							
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