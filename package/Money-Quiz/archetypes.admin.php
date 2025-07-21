<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
	
 ?>

<div class=" mq-container">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<h3>MoneyQuiz Archetype Details</h3>
	<?php echo $save_msg ?>
	<form method="post" action="" novalidate="novalidate">
		 
		<input name="action" value="archetype" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="1">Archetype1 Name</label></th>
					<td><input name="post_data[1]" id="1" value="<?php echo $post_data[1]?>" class="regular-text" type="text"></td>
					<th scope="row"><label for="tag_1">Tag Line:</label></th>
					<td><textarea  name="arch_tag_line[1]" ><?php echo $arch_tag_line[1]?></textarea></td>
				</tr>	
				<tr>
					<th scope="row"><label for="33">Archetype1 Ideal Score</label></th>
					<td><input name="post_data[33]" id="33" value="<?php echo $post_data[33]?>" class="regular-text" type="text"></td>
				</tr>	
				
				<tr>
					<th scope="row"><label for="">Archetype1 Image</label></th>
					<td><?php if($post_data[2] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[2]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[2]" id="image_url" value="<?php echo $post_data[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[2]" id="image_url" value="<?php echo $post_data[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="3">Archetype1 Short Description</label></th>
					<td>
						<!-- <textarea rows="3" name="post_data[3]" id="3" style="width:100%;"><?php // echo $post_data[3]?></textarea> !-->
					<?php

						$Archetype1_Short_Description = $post_data[3]; 
						$id = 'Archetype1_Short_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[3]', 
						
						);
						wp_editor( $Archetype1_Short_Description, $id,$arg );

						?>
					</td>
					<th scope="row"><label for="4">Archetype1 Long Description</label></th>
					<td>
					<!--<textarea rows="5"  name="post_data[4]" id="4" style="width:100%;" ><?php // echo $post_data[4]?></textarea>!-->
					<?php

						$Archetype1_Long_Description = $post_data[4]; 
						$id = 'Archetype1_Long_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[4]', 

						);
						wp_editor( $Archetype1_Long_Description, $id,$arg );

					?>
					</td>
				</tr>
				<tr><td colspan="2"><hr /></td></tr>
				
				<tr>
					<th scope="row"><label for="5">Archetype2 Name</label></th>
					<td><input name="post_data[5]" id="5" value="<?php echo $post_data[5]?>" class="regular-text" type="text"></td>
					<th scope="row"><label for="tag_2">Tag Line:</label></th>
					<td><textarea  name="arch_tag_line[2]" ><?php echo $arch_tag_line[2]?></textarea></td>
				</tr>	
				<tr>
					<th scope="row"><label for="34">Archetype2 Ideal Score</label></th>
					<td><input name="post_data[34]" id="34" value="<?php echo $post_data[34]?>" class="regular-text" type="text"></td>
				</tr>	
				<tr>
					<th scope="row"><label for="">Archetype2 Image</label></th>
					<td><?php if($post_data[6] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[6]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[6]" id="image_url" value="<?php echo $post_data[6]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[6]" id="image_url" value="<?php echo $post_data[6]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="7">Archetype2 Short Description</label></th>
					<td>
					<!--	<textarea rows="3"  name="post_data[7]" id="7" style="width:100%;"><?php //echo $post_data[7]?></textarea> !-->
					<?php
						$Archetype2_Short_Description = $post_data[7]; 
						$id = 'Archetype2_Short_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[7]', 

						);
						wp_editor( $Archetype2_Short_Description, $id,$arg );
					?>
					</td>
					<th scope="row"><label for="8">Archetype2 Long Description</label></th>
					<td>
					<!--		<textarea rows="5"  name="post_data[8]" id="8" style="width:100%;"><?php //echo $post_data[8]?></textarea>!-->
					<?php
						$Archetype2_Long_Description = $post_data[8]; 
						$id = 'Archetype2_Long_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[8]', 

						);
						wp_editor( $Archetype2_Long_Description, $id,$arg );
					?>
					</td>	
				</tr>
				
				<tr><td colspan="2"><hr /></td></tr>
				
				<tr>
					<th scope="row"><label for="mq_first_name">Archetype3 Name</label></th>
					<td><input name="post_data[9]" id="mq_first_name" value="<?php echo $post_data[9]?>" class="regular-text" type="text"></td>
					<th scope="row"><label for="tag_3">Tag Line:</label></th>
					<td><textarea  name="arch_tag_line[3]" ><?php echo $arch_tag_line[3]?></textarea></td>
				</tr>	
				<tr>
					<th scope="row"><label for="35">Archetype3 Ideal Score</label></th>
					<td><input name="post_data[35]" id="35" value="<?php echo $post_data[35]?>" class="regular-text" type="text"></td>
				</tr>	
				<tr>
					<th scope="row"><label for="">Archetype3 Image</label></th>
					<td><?php if($post_data[10] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[10]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[10]" id="image_url" value="<?php echo $post_data[10]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[10]" id="image_url" value="<?php echo $post_data[10]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="11">Archetype3 Short Description</label></th>
					<td>
						<!--<textarea rows="3"  name="post_data[11]" id="11" style="width:100%;"><?php //echo $post_data[11]?></textarea>!-->
					<?php
						$Archetype3_Short_Description = $post_data[11]; 
						$id = 'Archetype3_Short_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[11]', 

						);
						wp_editor( $Archetype3_Short_Description, $id,$arg );
					?>	
					</td>
					<th scope="row"><label for="12">Archetype3 Long Description</label></th>
					<td>
					<!--	<textarea rows="5"  name="post_data[12]" id="12" style="width:100%;"><?php //echo $post_data[12]?></textarea>!-->
					<?php
						$Archetype3_Long_Description = $post_data[12]; 
						$id = 'Archetype3_Long_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[12]', 

						);
						wp_editor( $Archetype3_Long_Description, $id,$arg );
					?>	
				
					</td>
				</tr>
				
					<tr><td colspan="2"><hr /></td></tr>
			
				<tr>
					<th scope="row"><label for="13">Archetype4 Name</label></th>
					<td><input name="post_data[13]" id="13" value="<?php echo $post_data[13]?>" class="regular-text" type="text"></td>

					<th scope="row"><label for="tag_4">Tag Line:</label></th>
					<td><textarea  name="arch_tag_line[4]" ><?php echo $arch_tag_line[4]?></textarea></td>

				</tr>	
				<tr>
					<th scope="row"><label for="36">Archetype4 Ideal Score</label></th>
					<td><input name="post_data[36]" id="36" value="<?php echo $post_data[36]?>" class="regular-text" type="text"></td>
				</tr>	
				<tr>
					<th scope="row"><label for="">Archetype4 Image</label></th>
					<td><?php if($post_data[14] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[14]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[14]" id="image_url" value="<?php echo $post_data[14]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[14]" id="image_url" value="<?php echo $post_data[14]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="15">Archetype4 Short Description</label></th>
					<td>
						<!--<textarea rows="3"  name="post_data[15]" id="15" style="width:100%;"><?php //echo $post_data[15]?></textarea>!-->
					<?php
						$Archetype4_Short_Description = $post_data[15]; 
						$id = 'Archetype4_Short_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[15]', 

						);
						wp_editor( $Archetype4_Short_Description, $id,$arg );
					?>		
							
					</td>
					<th scope="row"><label for="16">Archetype4 Long Description</label></th>
					<td>
					<!--	<textarea rows="5"  name="post_data[16]" id="16" style="width:100%;"><?php echo $post_data[16]?></textarea>!-->
					<?php

						$Archetype4_Long_Description = $post_data[16]; 
						$id = 'Archetype4_Long_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[16]', 

						);
						wp_editor( $Archetype4_Long_Description, $id,$arg );
					?>		
					</td>
				</tr>
				
				<tr><td colspan="2"><hr /></td></tr>
				
				<tr>
					<th scope="row"><label for="17">Archetype5 Name</label></th>
					<td><input name="post_data[17]" id="17" value="<?php echo $post_data[17]?>" class="regular-text" type="text"></td>

					<th scope="row"><label for="tag_5">Tag Line:</label></th>
					<td><textarea  name="arch_tag_line[5]" ><?php echo $arch_tag_line[5]?></textarea></td>

				</tr>	
				<tr>
					<th scope="row"><label for="37">Archetype5 Ideal Score</label></th>
					<td><input name="post_data[37]" id="37" value="<?php echo $post_data[37]?>" class="regular-text" type="text"></td>
				</tr>	
				<tr>
					<th scope="row"><label for="Surname">Archetype5 Image</label></th>
					<td><?php if($post_data[18] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[18]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[18]" id="image_url" value="<?php echo $post_data[18]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[18]" id="image_url" value="<?php echo $post_data[18]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="19">Archetype5 Short Description</label></th>
					<td>
						<!--<textarea rows="3"  name="post_data[19]" id="19" style="width:100%;"><?php //echo $post_data[19]?></textarea>!-->
						<?php

						$Archetype5_Short_Description = $post_data[19]; 
						$id = 'Archetype5_Short_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[19]', 

						);
						wp_editor( $Archetype5_Short_Description, $id,$arg );

						?>	
				
					</td>
					<th scope="row"><label for="20">Archetype5 Long Description</label></th>
					<td>
						<!-- <textarea rows="5"  name="post_data[20]" id="20" style="width:100%;"><?php// echo $post_data[20]?></textarea>!-->
						<?php
							$Archetype5_Long_Description = $post_data[20]; 
							$id = 'Archetype5_Long_Description'; 
							$arg = array( 
							'textarea_name' => 'post_data[20]', 

							);
							wp_editor( $Archetype5_Long_Description, $id,$arg );

						?>
					</td>
				</tr>
				
				<tr><td colspan="2"><hr /></td></tr>
				
				<tr>
					<th scope="row"><label for="21">Archetype6 Name</label></th>
					<td><input name="post_data[21]" id="21" value="<?php echo $post_data[21]?>" class="regular-text" type="text"></td>

					<th scope="row"><label for="tag_6">Tag Line:</label></th>
					<td><textarea  name="arch_tag_line[6]" ><?php echo $arch_tag_line[6]?></textarea></td>

				</tr>	
				<tr>
					<th scope="row"><label for="38">Archetype6 Ideal Score</label></th>
					<td><input name="post_data[38]" id="38" value="<?php echo $post_data[38]?>" class="regular-text" type="text"></td>
				</tr>	
				<tr>
					<th scope="row"><label for="">Archetype6 Image</label></th>
					<td><?php if($post_data[22] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[22]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[22]" id="image_url" value="<?php echo $post_data[22]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[22]" id="image_url" value="<?php echo $post_data[22]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="23">Archetype6 Short Description</label></th>
					<td>
					<!--	<textarea rows="3"  name="post_data[23]" id="23" style="width:100%;"><?php //echo $post_data[23]?></textarea>!-->
					<?php
							$Archetype6_Short_Description = $post_data[23]; 
							$id = 'Archetype6_Short_Description'; 
							$arg = array( 
							'textarea_name' => 'post_data[23]', 

							);
							wp_editor( $Archetype6_Short_Description, $id,$arg );

						?>		
					</td>
					<th scope="row"><label for="24">Archetype6 Long Description</label></th>
					<td>
					<!--	<textarea rows="5"  name="post_data[24]" id="24" style="width:100%;"><?php //echo $post_data[24]?></textarea>!-->
					<?php

						$Archetype6_Long_Description = $post_data[24]; 
						$id = 'Archetype6_Long_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[24]', 

						);
						wp_editor( $Archetype6_Long_Description, $id,$arg );
					?>		
					</td>
				</tr>
				
				<tr><td colspan="2"><hr /></td></tr>
				
				<tr>
					<th scope="row"><label for="25">Archetype7 Name</label></th>
					<td><input name="post_data[25]" id="25" value="<?php echo $post_data[25]?>" class="regular-text" type="text"></td>
					
					<th scope="row"><label for="tag_7">Tag Line:</label></th>
					<td><textarea  name="arch_tag_line[7]" ><?php echo $arch_tag_line[7]?></textarea></td>

				</tr>	
				<tr>
					<th scope="row"><label for="39">Archetype7 Ideal Score</label></th>
					<td><input name="post_data[39]" id="39" value="<?php echo $post_data[39]?>" class="regular-text" type="text"></td>
				</tr>	
				<tr>
					<th scope="row"><label for="">Archetype7 Image</label></th>
					<td><?php if($post_data[26] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[26]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[26]" id="image_url" value="<?php echo $post_data[26]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[26]" id="image_url" value="<?php echo $post_data[26]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="27">Archetype7 Short Description</label></th>
					<td>
						<!-- <textarea rows="3"  name="post_data[27]" id="27" style="width:100%;"><?php// echo $post_data[27]?></textarea>!-->
					<?php
					
						$Archetype7_Short_Description = $post_data[27]; 
						$id = 'Archetype7_Short_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[27]', 

						);
						wp_editor( $Archetype7_Short_Description, $id,$arg );
					?>	
				
					</td>
					<th scope="row"><label for="28">Archetype7 Long Description</label></th>
					<td>
						<!--<textarea rows="5"  name="post_data[28]" id="28" style="width:100%;"><?php //echo $post_data[28]?></textarea>!-->
					<?php
					
						$Archetype7_Long_Description = $post_data[28]; 
						$id = 'Archetype7_Long_Description'; 
						$arg = array( 
						'textarea_name' => 'post_data[28]', 

						);
						wp_editor( $Archetype7_Long_Description, $id,$arg );
				    ?>	
				
					</td>
				</tr>
				
				<tr><td colspan="2"><hr /></td></tr>
				
				<tr>
					<th scope="row"><label for="29">Archetype8 Name</label></th>
					<td><input name="post_data[29]" id="29" value="<?php echo $post_data[29]?>" class="regular-text" type="text"></td>

					<th scope="row"><label for="tag_8">Tag Line:</label></th>
					<td><textarea  name="arch_tag_line[8]" ><?php echo $arch_tag_line[8]?></textarea></td>

				</tr>	
				<tr>
					<th scope="row"><label for="40">Archetype8 Ideal Score</label></th>
					<td><input name="post_data[40]" id="40" value="<?php echo $post_data[40]?>" class="regular-text" type="text"></td>
				</tr>	
				<tr>
					<th scope="row"><label for="">Archetype8 Image</label></th>
					<td><?php if($post_data[30] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[30]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[30]" id="image_url" value="<?php echo $post_data[30]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[30]" id="image_url" value="<?php echo $post_data[30]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="31">Archetype8 Short Description</label></th>
					<td>
					<!--	<textarea rows="3"  name="post_data[31]" id="31" style="width:100%;"><?php //echo $post_data[31]?></textarea>!-->
					<?php
					
					$Archetype8_Short_Description = $post_data[31]; 
					$id = 'Archetype8_Short_Description'; 
					$arg = array( 
					'textarea_name' => 'post_data[31]', 

					);
					wp_editor( $Archetype8_Short_Description, $id,$arg );
				?>
				
					</td>
					<th scope="row"><label for="32">Archetype8 Long Description</label></th>
					<td>
					<!--	<textarea rows="5"  name="post_data[32]" id="32" style="width:100%;"><?php //echo $post_data[32]?></textarea>!-->
					<?php
					
					$Archetype8_Long_Description = $post_data[32]; 
					$id = 'Archetype8_Long_Description'; 
					$arg = array( 
					'textarea_name' => 'post_data[32]', 

					);
					wp_editor( $Archetype8_Long_Description, $id,$arg );
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