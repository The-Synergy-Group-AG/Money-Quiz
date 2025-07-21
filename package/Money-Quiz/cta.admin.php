<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/

// fetch data for archetypes for email
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_ARCHETYPES."" ;
		$rows = $wpdb->get_results($sql);
		$archetypes_data= array();
		foreach($rows as $row){
			$archetypes_data[$row->ID] = stripslashes($row->Value);
		}
		
 ?>

<div class=" mq-container cta-container">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	 
	<?php echo $save_msg ?>
	

	<form method="post" action="" novalidate="novalidate">
		<input name="action" value="cta_update" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr><th colspan="2" style="text-align: center;" ><h3 class="cta-heading"><a href="javascript:;" onclick="show_2(2);">2. Quiz Results: Benefits</a></h3> </th></tr>
			</tbody>
		</table>
		
		<table class="form-table mq-form-table hide_all_tables show_2 mq-hide">
			<tbody>
				<!--<tr><th colspan="2" style="text-align: center;" ><h3 class="cta-heading">2. Quiz Results: Benefits</h3> </th></tr>-->
				<tr>
					<th scope="row"><label for="7">Enable</label></th>
					<td><input type="radio" <?php echo ($post_data[7]== "Yes"? 'checked="checked"': '') ?> name="post_data[7]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[7]" value="No" <?php echo ($post_data[7]== "No"? 'checked="checked"': '') ?> >No </td>
				</tr>	
				<tr>
					<th scope="row"><label for="8">Title</label></th>
					<td><input name="post_data[8]" id="8" value="<?php echo $post_data[8]?>" class="regular-text" type="text"></td>
				</tr>	
				
				<tr>
					<th scope="row"><label for="">Image</label></th>
					<td><?php if($post_data[9] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[9]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[9]" id="image_url" value="<?php echo $post_data[9]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[9]" id="image_url" value="<?php echo $post_data[9]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="10">Intro</label></th>
					<td>
						
						<?php
						
						$id = 'intor_10'; 
						$arg = array( 
						'textarea_name' => 'post_data[10]', 

						);
						wp_editor( $post_data[10], $id,$arg );

						?>
				 	</td>
				</tr>
				
				<tr><td colspan="2">
					<fieldset>
						<legend>Benefit 1:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="11">Title</label></th>
							<td><input name="post_data[11]" id="11" value="<?php echo $post_data[11]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="12">Description </label></th>
							<td>
								<!--<textarea rows="3"  name="post_data[12]" id="12" style="width:100%;" ><?php echo $post_data[12]?></textarea>!-->
						<?php
							
							$id = 'intor_12'; 
							$arg = array( 
							'textarea_name' => 'post_data[12]', 

							);
							wp_editor( $post_data[12], $id,$arg );

						?>
						</td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Benefit 2:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="13">Title</label></th>
							<td><input name="post_data[13]" id="13" value="<?php echo $post_data[13]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="14">Description </label></th>
							<td><!--<textarea rows="3"  name="post_data[14]" id="14" style="width:100%;" ><?php echo $post_data[14]?></textarea>!-->
							<?php
							
							$id = 'intor_14'; 
							$arg = array( 
							'textarea_name' => 'post_data[14]', 

							);
							wp_editor( $post_data[14], $id,$arg );

						?>
							</td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Benefit 3:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="15">Title</label></th>
							<td><input name="post_data[15]" id="15" value="<?php echo $post_data[15]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="16">Description </label></th>
							<td><!--<textarea rows="3"  name="post_data[16]" id="16" style="width:100%;" ><?php echo $post_data[16]?></textarea>!-->
						
							<?php
							
							$id = 'intor_16'; 
							$arg = array( 
							'textarea_name' => 'post_data[16]', 

							);
							wp_editor( $post_data[16], $id,$arg );

						  ?>
						</td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Benefit 4:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="17">Title</label></th>
							<td><input name="post_data[17]" id="17" value="<?php echo $post_data[17]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="18">Description </label></th>
							<td><!--<textarea rows="3"  name="post_data[18]" id="18" style="width:100%;" ><?php echo $post_data[18]?></textarea>!-->
							<?php
							
							$id = 'intor_18'; 
							$arg = array( 
							'textarea_name' => 'post_data[18]', 

							);
							wp_editor( $post_data[18], $id,$arg );

						  ?>
						    </td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Benefit 5:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="19">Title</label></th>
							<td><input name="post_data[19]" id="19" value="<?php echo $post_data[19]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="20">Description </label></th>
							<td><!--<textarea rows="3"  name="post_data[20]" id="20" style="width:100%;" ><?php echo $post_data[20]?></textarea>!-->
							<?php
							
							$id = 'intor_20'; 
							$arg = array( 
							'textarea_name' => 'post_data[20]', 

							);
							wp_editor( $post_data[20], $id,$arg );

						  ?>
						
							</td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Benefit 6:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="21">Title</label></th>
							<td><input name="post_data[21]" id="21" value="<?php echo $post_data[21]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="22">Description </label></th>
							<td><!--<textarea rows="3"  name="post_data[22]" id="22" style="width:100%;" ><?php echo $post_data[22]?></textarea>!-->
							<?php
							
							$id = 'intor_22'; 
							$arg = array( 
							'textarea_name' => 'post_data[22]', 

							);
							wp_editor( $post_data[22], $id,$arg );

						  ?>
						
						
							</td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				
				<tr><td colspan="2">
					<fieldset>
						<legend>Closing:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="23">Closing</label></th>
							<td><!--<textarea rows="3"  name="post_data[23]" id="23" style="width:100%;" ><?php echo $post_data[23]?></textarea>!-->
							<?php
							
							$id = 'intor_23'; 
							$arg = array( 
							'textarea_name' => 'post_data[23]', 

							);
							wp_editor( $post_data[23], $id,$arg );

						  ?>
						
						
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="24">Button Text </label></th>
							<td><input name="post_data[24]" id="24" value="<?php echo $post_data[24]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="25">Hyperlink </label></th>
							<td><input name="post_data[25]" id="25" value="<?php echo $post_data[25]?>" class="regular-text" type="text"></td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				
				
				<tr>
					<th scope="row">&nbsp;</th>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p></td>
				</tr>			
				<tr><td colspan="2"><hr /></td></tr>
			</tbody>
		</table>
	</form>	
	
	<form method="post" action="" novalidate="novalidate">
		<input name="action" value="cta_update" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr><th colspan="2" style="text-align: center;" ><h3 class="cta-heading"><a href="javascript:;" onclick="show_2(3);">3. Quiz Results: Archetype Specific</a></h3> </th></tr>
			</tbody>
		</table>
		<table class="form-table mq-form-table hide_all_tables show_3 mq-hide">
			<tbody>
				 
				<tr>
					<th scope="row"><label for="26">Enable</label></th>
					<td><input type="radio" <?php echo ($post_data[26]== "Yes"? 'checked="checked"': '') ?> name="post_data[26]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[26]" value="No" <?php echo ($post_data[26]== "No"? 'checked="checked"': '') ?> >No </td>
				</tr>	
				<tr>
					<th scope="row"><label for="27">Title</label></th>
					<td><input name="post_data[27]" id="27" value="<?php echo $post_data[27]?>" class="regular-text" type="text"></td>
				</tr>	
				
				<tr>
					<th scope="row"><label for="">Image</label></th>
					<td><?php if($post_data[28] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[28]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[28]" id="image_url" value="<?php echo $post_data[28]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[28]" id="image_url" value="<?php echo $post_data[28]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="29">Intro</label></th>
					<td><!--<textarea rows="3" name="post_data[29]" id="29" style="width:100%;"><?php echo $post_data[29]?></textarea>!-->
					<?php
							
							$id = 'intor_29'; 
							$arg = array( 
							'textarea_name' => 'post_data[29]', 

							);
							wp_editor( $post_data[29], $id,$arg );

						  ?>
				
				
				</td>
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Archetype1:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="">Archetype </label></th>
							<td><?php echo $archetypes_data[1] ?> </td>
						</tr>
						<tr>
							<th scope="row"><label for="30">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[30]" id="30" style="width:100%;" ><?php echo $post_data[30]?></textarea>!-->
							<?php
							
							$id = 'intor_30'; 
							$arg = array( 
							'textarea_name' => 'post_data[30]', 

							);
							wp_editor( $post_data[30], $id,$arg );

						  ?>
						
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>	
				
				<tr><td colspan="2">
					<fieldset>
						<legend>Archetype2:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="">Archetype </label></th>
							<td><?php echo $archetypes_data[5] ?> </td>
						</tr>
						<tr>
							<th scope="row"><label for="31">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[31]" id="31" style="width:100%;" ><?php echo $post_data[31]?></textarea>!-->
							<?php
							
							$id = 'intor_31'; 
							$arg = array( 
							'textarea_name' => 'post_data[31]', 

							);
							wp_editor( $post_data[31], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Archetype3:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="">Archetype </label></th>
							<td><?php echo $archetypes_data[9] ?> </td>
						</tr>
						<tr>
							<th scope="row"><label for="32">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[32]" id="32" style="width:100%;" ><?php echo $post_data[32]?></textarea>!-->
							<?php
							
							$id = 'intor_32'; 
							$arg = array( 
							'textarea_name' => 'post_data[32]', 

							);
							wp_editor( $post_data[32], $id,$arg );

						  ?>
						
						 	</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Archetype4:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="">Archetype </label></th>
							<td> <?php echo $archetypes_data[13] ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="33">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[33]" id="33" style="width:100%;" ><?php echo $post_data[33]?></textarea>!-->
							<?php
							
							$id = 'intor_33'; 
							$arg = array( 
							'textarea_name' => 'post_data[33]', 

							);
							wp_editor( $post_data[33], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Archetype5:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="">Archetype </label></th>
							<td><?php echo $archetypes_data[17] ?> </td>
						</tr>
						<tr>
							<th scope="row"><label for="34">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[34]" id="34" style="width:100%;" ><?php echo $post_data[34]?></textarea>!-->
							<?php
							
							$id = 'intor_34'; 
							$arg = array( 
							'textarea_name' => 'post_data[34]', 

							);
							wp_editor( $post_data[34], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Archetype6:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="">Archetype </label></th>
							<td> <?php echo $archetypes_data[21] ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="35">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[35]" id="35" style="width:100%;" ><?php echo $post_data[35]?></textarea>!-->
							<?php
							
							$id = 'intor_35'; 
							$arg = array( 
							'textarea_name' => 'post_data[35]', 

							);
							wp_editor( $post_data[35], $id,$arg );

						  ?>
						
						
						</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Archetype7:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="">Archetype </label></th>
							<td> <?php echo $archetypes_data[25] ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="36">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[36]" id="36" style="width:100%;" ><?php echo $post_data[36]?></textarea>!-->
							<?php
							
							$id = 'intor_36'; 
							$arg = array( 
							'textarea_name' => 'post_data[36]', 

							);
							wp_editor( $post_data[36], $id,$arg );

						  ?>
						
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Archetype8:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="">Archetype </label></th>
							<td> <?php echo $archetypes_data[29] ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="31">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[37]" id="37" style="width:100%;" ><?php echo $post_data[37]?></textarea>!-->
							<?php
							
							$id = 'intor_37'; 
							$arg = array( 
							'textarea_name' => 'post_data[37]', 

							);
							wp_editor( $post_data[37], $id,$arg );

						  ?>
						
						
						</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				
				
				
				<tr><td colspan="2">
					<fieldset>
						<legend>Closing:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="38">Closing</label></th>
							<td><!--<textarea rows="3"  name="post_data[38]" id="38" style="width:100%;" ><?php echo $post_data[38]?></textarea>!-->
							<?php
							
							$id = 'intor_38'; 
							$arg = array( 
							'textarea_name' => 'post_data[38]', 

							);
							wp_editor( $post_data[38], $id,$arg );

						  ?>

							</td>
						</tr>
						<tr>
							<th scope="row"><label for="39">Button Text </label></th>
							<td><input name="post_data[39]" id="39" value="<?php echo $post_data[39]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="40">Hyperlink </label></th>
							<td><input name="post_data[40]" id="40" value="<?php echo $post_data[40]?>" class="regular-text" type="text"></td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				
				<tr>
					<th scope="row">&nbsp;</th>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p></td>
				</tr>			
				<tr><td colspan="2"><hr /></td></tr>
			</tbody>
		</table>
	</form>
	
	<form method="post" action="" novalidate="novalidate">
		<input name="action" value="cta_update" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr><th colspan="2" style="text-align: center;" ><h3 class="cta-heading"><a href="javascript:;" onclick="show_2(4);">4. Quiz Results: Promotion</a></h3> </th></tr>
			</tbody>
		</table>		
		<table class="form-table mq-form-table hide_all_tables show_4 mq-hide">
			<tbody>
			 
				<tr>
					<th scope="row"><label for="41">Enable</label></th>
					<td><input type="radio" <?php echo ($post_data[41]== "Yes"? 'checked="checked"': '') ?> name="post_data[41]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[41]" value="No" <?php echo ($post_data[41]== "No"? 'checked="checked"': '') ?> >No </td>
				</tr>	
				<tr>
					<th scope="row"><label for="42">Title</label></th>
					<td><input name="post_data[42]" id="42" value="<?php echo $post_data[42]?>" class="regular-text" type="text"></td>
				</tr>	
				
				<tr>
					<th scope="row"><label for="">Image</label></th>
					<td><?php if($post_data[43] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[43]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[43]" id="image_url" value="<?php echo $post_data[43]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[43]" id="image_url" value="<?php echo $post_data[43]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="44">Intro</label></th>
					<td><!--<textarea rows="3" name="post_data[44]" id="44" style="width:100%;"><?php echo $post_data[44]?></textarea>!-->
					<?php
							
							$id = 'intor_44'; 
							$arg = array( 
							'textarea_name' => 'post_data[44]', 

							);
							wp_editor( $post_data[44], $id,$arg );

						  ?>
				
					</td>
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 1:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="45">Title </label></th>
							<td><input name="post_data[45]" id="45" value="<?php echo $post_data[45]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[46] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[46]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[46]" id="image_url" value="<?php echo $post_data[46]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[46]" id="image_url" value="<?php echo $post_data[46]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="47">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[47]" id="47" style="width:100%;" ><?php echo $post_data[47]?></textarea>!-->
							<?php
							
							$id = 'intor_47'; 
							$arg = array( 
							'textarea_name' => 'post_data[47]', 

							);
							wp_editor( $post_data[47], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 2:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="48">Title </label></th>
							<td><input name="post_data[48]" id="48" value="<?php echo $post_data[48]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[49] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[49]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[49]" id="image_url" value="<?php echo $post_data[49]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[49]" id="image_url" value="<?php echo $post_data[49]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="50">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[50]" id="50" style="width:100%;" ><?php echo $post_data[50]?></textarea>!-->
							<?php
							
							$id = 'intor_50'; 
							$arg = array( 
							'textarea_name' => 'post_data[50]', 

							);
							wp_editor( $post_data[50], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 3:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="51">Title </label></th>
							<td><input name="post_data[51]" id="51" value="<?php echo $post_data[51]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[52] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[52]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[52]" id="image_url" value="<?php echo $post_data[52]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[52]" id="image_url" value="<?php echo $post_data[52]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="53">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[53]" id="53" style="width:100%;" ><?php echo $post_data[53]?></textarea>!-->
							<?php
							
							$id = 'intor_53'; 
							$arg = array( 
							'textarea_name' => 'post_data[53]', 

							);
							wp_editor( $post_data[53], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 4:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="54">Title </label></th>
							<td><input name="post_data[54]" id="54" value="<?php echo $post_data[54]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[55] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[55]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[55]" id="image_url" value="<?php echo $post_data[55]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[55]" id="image_url" value="<?php echo $post_data[55]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="56">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[56]" id="56" style="width:100%;" ><?php echo $post_data[56]?></textarea>!-->
							<?php
							
							$id = 'intor_56'; 
							$arg = array( 
							'textarea_name' => 'post_data[56]', 

							);
							wp_editor( $post_data[56], $id,$arg );

						  ?>
									
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 5:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="57">Title </label></th>
							<td><input name="post_data[57]" id="57" value="<?php echo $post_data[57]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[58] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[58]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[58]" id="image_url" value="<?php echo $post_data[58]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[58]" id="image_url" value="<?php echo $post_data[58]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="59">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[59]" id="59" style="width:100%;" ><?php echo $post_data[59]?></textarea>!-->
							<?php
							
							$id = 'intor_56'; 
							$arg = array( 
							'textarea_name' => 'post_data[56]', 

							);
							wp_editor( $post_data[56], $id,$arg );

						  ?>
						
						 	</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 6:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="60">Title </label></th>
							<td><input name="post_data[60]" id="60" value="<?php echo $post_data[60]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[61] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[61]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[61]" id="image_url" value="<?php echo $post_data[61]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[61]" id="image_url" value="<?php echo $post_data[61]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="62">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[62]" id="62" style="width:100%;" ><?php echo $post_data[62]?></textarea>!-->
							<?php
							
							$id = 'intor_62'; 
							$arg = array( 
							'textarea_name' => 'post_data[62]', 

							);
							wp_editor( $post_data[62], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 7:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="63">Title </label></th>
							<td><input name="post_data[63]" id="63" value="<?php echo $post_data[63]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[64] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[64]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[64]" id="image_url" value="<?php echo $post_data[64]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[64]" id="image_url" value="<?php echo $post_data[64]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="65">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[65]" id="65" style="width:100%;" ><?php echo $post_data[65]?></textarea>!-->
							<?php
							
							$id = 'intor_65'; 
							$arg = array( 
							'textarea_name' => 'post_data[65]', 

							);
							wp_editor( $post_data[65], $id,$arg );

						  ?>
									
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>

				
				<tr><td colspan="2">
					<fieldset>
						<legend>Closing:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="66">Closing</label></th>
							<td><!--<textarea rows="3"  name="post_data[66]" id="66" style="width:100%;" ><?php echo $post_data[66]?></textarea>!-->
							<?php
							
							$id = 'intor_66'; 
							$arg = array( 
							'textarea_name' => 'post_data[66]', 

							);
							wp_editor( $post_data[66], $id,$arg );

						  ?>
						
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="67">Button Text </label></th>
							<td><input name="post_data[67]" id="67" value="<?php echo $post_data[67]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="68">Hyperlink </label></th>
							<td><input name="post_data[68]" id="68" value="<?php echo $post_data[68]?>" class="regular-text" type="text"></td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				
				<tr>
					<th scope="row">&nbsp;</th>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p></td>
				</tr>			
				<tr><td colspan="2"><hr /></td></tr>
			</tbody>
		</table>
	</form>
	
	<form method="post" action="" novalidate="novalidate">
		<input name="action" value="cta_update" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr><th colspan="2" style="text-align: center;" ><h3 class="cta-heading"><a href="javascript:;" onclick="show_2(5);">5. Quiz Results: Testimonials</a></h3> </th></tr>
			</tbody>
		</table>		
		<table class="form-table mq-form-table hide_all_tables show_5 mq-hide">
			<tbody>
				 
				<tr>
					<th scope="row"><label for="69">Enable</label></th>
					<td><input type="radio" <?php echo ($post_data[69]== "Yes"? 'checked="checked"': '') ?> name="post_data[69]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[69]" value="No" <?php echo ($post_data[69]== "No"? 'checked="checked"': '') ?> >No </td>
				</tr>	
				<tr>
					<th scope="row"><label for="70">Title</label></th>
					<td><input name="post_data[70]" id="70" value="<?php echo $post_data[70]?>" class="regular-text" type="text"></td>
				</tr>	
				
				<tr>
					<th scope="row"><label for="">Image</label></th>
					<td><?php if($post_data[71] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[71]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[71]" id="image_url" value="<?php echo $post_data[71]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[71]" id="image_url" value="<?php echo $post_data[71]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="72">Intro</label></th>
					<td><!--<textarea rows="3" name="post_data[72]" id="72" style="width:100%;"><?php echo $post_data[72]?></textarea>!-->
					<?php
							
							$id = 'intor_72'; 
							$arg = array( 
							'textarea_name' => 'post_data[72]', 

							);
							wp_editor( $post_data[72], $id,$arg );

						  ?>
					</td>
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 1:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="73">Title </label></th>
							<td><input name="post_data[73]" id="73" value="<?php echo $post_data[73]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[74] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[74]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[74]" id="image_url" value="<?php echo $post_data[74]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[74]" id="image_url" value="<?php echo $post_data[74]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="75">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[75]" id="75" style="width:100%;" ><?php echo $post_data[75]?></textarea>!-->
							<?php
							
							$id = 'intor_75'; 
							$arg = array( 
							'textarea_name' => 'post_data[75]', 

							);
							wp_editor( $post_data[75], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Item 2:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="76">Title </label></th>
							<td><input name="post_data[76]" id="76" value="<?php echo $post_data[76]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[77] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[77]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[77]" id="image_url" value="<?php echo $post_data[77]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[77]" id="image_url" value="<?php echo $post_data[77]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="78">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[78]" id="78" style="width:100%;" ><?php echo $post_data[78]?></textarea>!-->
							<?php
							
							$id = 'intor_78'; 
							$arg = array( 
							'textarea_name' => 'post_data[78]', 

							);
							wp_editor( $post_data[78], $id,$arg );

						  ?>
						
						</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Item 3:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="79">Title </label></th>
							<td><input name="post_data[79]" id="79" value="<?php echo $post_data[79]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="">Image</label></th>
							<td><?php if($post_data[80] != ""){ ?>
									<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[80]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
									<input  class="regular-text" type="hidden" name="post_data[80]" id="image_url" value="<?php echo $post_data[80]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
								<?php }else{ ?>
									<a href="#" class="mq_upload_image_button button">Upload image</a>
									<input  class="regular-text" type="hidden" name="post_data[80]" id="image_url" value="<?php echo $post_data[80]?>" readonly />
									<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
								<?php } ?>
								 
							</td>
						</tr>						
						<tr>
							<th scope="row"><label for="81">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[81]" id="81" style="width:100%;" ><?php echo $post_data[81]?></textarea>!-->
							<?php
							
							$id = 'intor_81'; 
							$arg = array( 
							'textarea_name' => 'post_data[81]', 

							);
							wp_editor( $post_data[81], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				

				
				<tr><td colspan="2">
					<fieldset>
						<legend>Closing:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="82">Closing</label></th>
							<td><!--<textarea rows="3"  name="post_data[82]" id="82" style="width:100%;" ><?php echo $post_data[82]?></textarea>!-->
							<?php
							
							$id = 'intor_82'; 
							$arg = array( 
							'textarea_name' => 'post_data[82]', 

							);
							wp_editor( $post_data[82], $id,$arg );

						  ?>
						
						
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="83">Button Text </label></th>
							<td><input name="post_data[83]" id="83" value="<?php echo $post_data[83]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="84">Hyperlink </label></th>
							<td><input name="post_data[84]" id="84" value="<?php echo $post_data[84]?>" class="regular-text" type="text"></td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				
				<tr>
					<th scope="row">&nbsp;</th>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p></td>
				</tr>			
				<tr><td colspan="2"><hr /></td></tr>
			</tbody>
		</table>
	</form>	

	<form method="post" action="" novalidate="novalidate">
		<input name="action" value="cta_update" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr><th colspan="2" style="text-align: center;" ><h3 class="cta-heading"><a href="javascript:;" onclick="show_2(6);">6. Quiz Results: Special Offer</a></h3> </th></tr>
			</tbody>
		</table>
		<table class="form-table mq-form-table hide_all_tables show_6 mq-hide">
			<tbody>
			 
				<tr>
					<th scope="row"><label for="85">Enable</label></th>
					<td><input type="radio" <?php echo ($post_data[85]== "Yes"? 'checked="checked"': '') ?> name="post_data[85]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[85]" value="No" <?php echo ($post_data[85]== "No"? 'checked="checked"': '') ?> >No </td>
				</tr>	
				<tr>
					<th scope="row"><label for="86">Title</label></th>
					<td><input name="post_data[86]" id="86" value="<?php echo $post_data[86]?>" class="regular-text" type="text"></td>
				</tr>	
				
				<tr>
					<th scope="row"><label for="">Image</label></th>
					<td><?php if($post_data[87] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[87]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[87]" id="image_url" value="<?php echo $post_data[87]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[87]" id="image_url" value="<?php echo $post_data[87]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="88">Intro</label></th>
					<td><!--<textarea rows="3" name="post_data[88]" id="88" style="width:100%;"><?php echo $post_data[88]?></textarea>!-->
					<?php
							
							$id = 'intor_88'; 
							$arg = array( 
							'textarea_name' => 'post_data[88]', 

							);
							wp_editor( $post_data[88], $id,$arg );

						  ?>
				
				
					</td>
				</tr>

				<tr><td colspan="2">
					<fieldset>
						<legend>Item 1:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="89">Title </label></th>
							<td><input name="post_data[89]" id="89" value="<?php echo $post_data[89]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="90">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[90]" id="90" style="width:100%;" ><?php echo $post_data[90]?></textarea>!-->
							<?php
							
							$id = 'intor_90'; 
							$arg = array( 
							'textarea_name' => 'post_data[90]', 

							);
							wp_editor( $post_data[90], $id,$arg );

						  ?>
						
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Item 2:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="91">Title </label></th>
							<td><input name="post_data[91]" id="91" value="<?php echo $post_data[91]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="92">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[92]" id="92" style="width:100%;" ><?php echo $post_data[92]?></textarea>!-->
							<?php
							
							$id = 'intor_92'; 
							$arg = array( 
							'textarea_name' => 'post_data[92]', 

							);
							wp_editor( $post_data[92], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Item 3:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="93">Title </label></th>
							<td><input name="post_data[93]" id="93" value="<?php echo $post_data[93]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="94">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[94]" id="94" style="width:100%;" ><?php echo $post_data[94]?></textarea>!-->
							<?php
							
							$id = 'intor_94'; 
							$arg = array( 
							'textarea_name' => 'post_data[94]', 

							);
							wp_editor( $post_data[94], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
							
				
				
				<tr><td colspan="2">
					<fieldset>
						<legend>Closing:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="95">Closing</label></th>
							<td><!--<textarea rows="3"  name="post_data[95]" id="95" style="width:100%;" ><?php echo $post_data[95]?></textarea>!-->
							<?php
							
							$id = 'intor_95'; 
							$arg = array( 
							'textarea_name' => 'post_data[95]', 

							);
							wp_editor( $post_data[95], $id,$arg );

						  ?>
						
						
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="96">Button Text </label></th>
							<td><input name="post_data[96]" id="96" value="<?php echo $post_data[96]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="97">Hyperlink </label></th>
							<td><input name="post_data[97]" id="97" value="<?php echo $post_data[97]?>" class="regular-text" type="text"></td>
						</tr>
						</table>
					</fieldset>
					</td> 
				</tr>
				
				<tr>
					<th scope="row">&nbsp;</th>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"></p></td>
				</tr>			
				<tr><td colspan="2"><hr /></td></tr>
			</tbody>
		</table>
	</form>	
	
	<form method="post" action="" novalidate="novalidate">
		<input name="action" value="cta_update" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr><th colspan="2" style="text-align: center;" ><h3 class="cta-heading"><a href="javascript:;" onclick="show_2(7);">7. Quiz Results: Bonus Offer</a></h3> </th></tr>
			</tbody>
		</table>
		<table class="form-table mq-form-table hide_all_tables show_7 mq-hide">
			<tbody>
			 	<tr>
					<th scope="row"><label for="98">Enable</label></th>
					<td><input type="radio" <?php echo ($post_data[98]== "Yes"? 'checked="checked"': '') ?> name="post_data[98]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[98]" value="No" <?php echo ($post_data[98]== "No"? 'checked="checked"': '') ?> >No </td>
				</tr>	
				<tr>
					<th scope="row"><label for="99">Title</label></th>
					<td><input name="post_data[99]" id="99" value="<?php echo $post_data[99]?>" class="regular-text" type="text"></td>
				</tr>	
				
				<tr>
					<th scope="row"><label for="">Image</label></th>
					<td><?php if($post_data[100] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $post_data[100]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[100]" id="image_url" value="<?php echo $post_data[100]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[100]" id="image_url" value="<?php echo $post_data[100]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
						 
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="101">Intro</label></th>
					<td><!--<textarea rows="3" name="post_data[101]" id="101" style="width:100%;"><?php echo $post_data[101]?></textarea>!-->
					<?php
							
							$id = 'intor_101'; 
							$arg = array( 
							'textarea_name' => 'post_data[101]', 

							);
							wp_editor( $post_data[101], $id,$arg );

						  ?>
				
					</td>
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Item 1:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="102">Title </label></th>
							<td><input name="post_data[102]" id="102" value="<?php echo $post_data[102]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="103">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[103]" id="103" style="width:100%;" ><?php echo $post_data[103]?></textarea>!-->
							<?php
							
							$id = 'intor_103'; 
							$arg = array( 
							'textarea_name' => 'post_data[103]', 

							);
							wp_editor( $post_data[103], $id,$arg );

						  ?>
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Item 2:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="104">Title </label></th>
							<td><input name="post_data[104]" id="104" value="<?php echo $post_data[104]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="105">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[105]" id="105" style="width:100%;" ><?php echo $post_data[105]?></textarea>!-->
							<?php
							
							$id = 'intor_105'; 
							$arg = array( 
							'textarea_name' => 'post_data[105]', 

							);
							wp_editor( $post_data[105], $id,$arg );

						  ?>
						
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				<tr><td colspan="2">
					<fieldset>
						<legend>Item 3:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="106">Title </label></th>
							<td><input name="post_data[106]" id="106" value="<?php echo $post_data[106]?>" class="regular-text" type="text"> </td>
						</tr>
						<tr>
							<th scope="row"><label for="107">Description</label></th>
							<td><!--<textarea rows="3"  name="post_data[107]" id="107" style="width:100%;" ><?php echo $post_data[107]?></textarea>!-->
							<?php
							
							$id = 'intor_107'; 
							$arg = array( 
							'textarea_name' => 'post_data[107]', 

							);
							wp_editor( $post_data[107], $id,$arg );

						  ?>
						
							</td>
						</tr>
						 
						</table>
					</fieldset>
					</td> 
				</tr>
				
				<tr><td colspan="2">
					<fieldset>
						<legend>Closing:</legend>
							
						<table style="width:100%;">
						<tr>
							<th scope="row"><label for="108">Closing</label></th>
							<td><!--<textarea rows="3"  name="post_data[108]" id="108" style="width:100%;" ><?php echo $post_data[108]?></textarea>!-->
							<?php
							
							$id = 'intor_108'; 
							$arg = array( 
							'textarea_name' => 'post_data[108]', 

							);
							wp_editor( $post_data[108], $id,$arg );

						  ?>
						
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="109">Button Text </label></th>
							<td><input name="post_data[109]" id="109" value="<?php echo $post_data[109]?>" class="regular-text" type="text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="110">Hyperlink </label></th>
							<td><input name="post_data[110]" id="110" value="<?php echo $post_data[110]?>" class="regular-text" type="text"></td>
						</tr>
						</table>
					</fieldset>
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