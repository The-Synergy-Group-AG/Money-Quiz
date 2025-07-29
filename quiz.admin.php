<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
  update_option('mq_money_coach_email_sent', 'NO' );
 ?>
 
<div class=" mq-container quiz_page">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<?php
		$sql = "SELECT * FROM ".$table_prefix.ANSWER_TABLE."" ;
		$rows_answered = $wpdb->get_results($sql);
		$answred_label = array();
		foreach($rows_answered as $row){
			$answred_label[$row->id] = stripslashes($row->value);
		}
		
		/** Get Email Signatre Settings */
		$sql = "SELECT * FROM ".$table_prefix.EMAIL_SIGNATURE."" ;
		$rows_email = $wpdb->get_results($sql);
		$email_seting = array();
		foreach($rows_email as $row){
			$email_seting[$row->id] = stripslashes($row->value);
		}
	
		/** End */
	?>
	<h3>Customizing the Quiz</h3>
	<?php echo esc_html($save_msg) ?>
	<form method="post" action="" novalidate="novalidate">
		<input name="action" value="update" type="hidden">
		<?php wp_nonce_field('mq_quiz_settings', 'mq_quiz_nonce');?>
		<table class="form-table mq-form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="Header_Image">Header Image</label></th>
					<td> 
						<?php if($post_data[34] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo esc_url($post_data[34])?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="post_data[34]" id="image_url" value="<?php echo esc_attr($post_data[34])?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="post_data[34]" id="image_url" value="<?php echo esc_attr($post_data[34])?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>	
				<tr>
					<th scope="row"><label for="Prospect_Surname">Display on <span style="font-weight:800;">Main</span> MoneyQuiz Page</label></th>
					<td> Display <input type="radio" <?php echo ($post_data[28]== "Yes"? 'checked="checked"': '') ?> name="post_data[28]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[28]" value="No" <?php echo ($post_data[28]== "No"? 'checked="checked"': '') ?> >No </span></td>
				</tr>
				
				<tr>
					<th scope="row"><label for="Prospect_Surname">Display on <span style="font-weight:800;">Results</span> Page</label></th>
					<td> Display <input type="radio" <?php echo ($post_data[30]== "Yes"? 'checked="checked"': '') ?> name="post_data[30]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[30]" value="No" <?php echo ($post_data[30]== "No"? 'checked="checked"': '') ?> >No </span></td>
				</tr>
				<tr>
					<th scope="row"><label for="Prospect_Surname">Display on <span style="font-weight:800;">Email</span> Confirmation</label></th>
					<td> Display <input type="radio" <?php echo ($post_data[31]== "Yes"? 'checked="checked"': '') ?> name="post_data[31]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[31]" value="No" <?php echo ($post_data[31]== "No"? 'checked="checked"': '') ?> >No </span></td>
				</tr>
				<tr>
					<th scope="row"><label for="Prospect_Surname">Display on <span style="font-weight:800;"> Signature</span> Email</label></th>
					<td> Display <input type="radio" <?php echo ($email_seting[7]== "Yes"? 'checked="checked"': '') ?> name="is_display_email_signature_banner_image" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="is_display_email_signature_banner_image" value="No" <?php echo ($email_seting[7]== "No"? 'checked="checked"': '') ?> >No </span></td>
				</tr>
				<tr><td colspan="2"></td></tr>
				<!---<tr>
					<th scope="row"><label for="48">Result Below Banner Content</label></th>
					<td>
						<?php
						/*
						$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_QUESTION_SCREEN."" ;
						$rows = $wpdb->get_results($sql);
						
						$below_result_content = array();
						foreach($rows as $row){
							$below_result_content[$row->id] = stripslashes($row->value);
						}
						$result_below_banner_content = $below_result_content[16]; 
						$id = 'result_below_banner_content'; 
						$arg = array( 
						'textarea_name' => 'below_result_content', 

						);
						wp_editor( $result_below_banner_content, $id,$arg );
							*/
						?>
					</td>
				</tr>
				!-->
				<tr>
					<th scope="row" ><label for="quiz_length">Quiz Length</label></th>
					<!--<td>
						<input type="radio" <?php //echo ($post_data[32]== "Short Only"? 'checked="checked"': '') ?> name="post_data[32]" value="Short Only">Short Only &nbsp;&nbsp;
						<input type="radio" <?php //echo ($post_data[32]== "Full Only"? 'checked="checked"': '') ?> name="post_data[32]" value="Full Only">Full Only &nbsp;&nbsp;
						<input type="radio" <?php //echo ($post_data[32]== "Short & Full"? 'checked="checked"': '') ?> name="post_data[32]" value="Short & Full">Short and Full
					</td> -->
				</tr>
				<tr>
					<th scope="row"> Blitz</th>
					<td><input name="post_data[41]" id="41" value="<?php echo esc_attr($post_data[41])?>"  type="text" class="regular-text" > 
						<span>Display <input type="radio" <?php echo ($post_data[38]== "Yes"? 'checked="checked"': '') ?> name="post_data[38]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[38]" value="No" <?php echo ($post_data[38]== "No"? 'checked="checked"': '') ?> >No </span>
					</td>
				</tr>						
				<tr>
					<th scope="row"> Short</th>
					<td><input name="post_data[42]" id="42" value="<?php echo esc_attr($post_data[42])?>"  type="text" class="regular-text" > 
						<span>Display <input type="radio" <?php echo ($post_data[39]== "Yes"? 'checked="checked"': '') ?> name="post_data[39]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[39]" value="No" <?php echo ($post_data[39]== "No"? 'checked="checked"': '') ?> >No </span>
					</td>
				</tr>						
				<tr>
					<th scope="row"> Full</th>
					<td><input name="post_data[43]" id="43" value="<?php echo esc_attr($post_data[43])?>"  type="text" class="regular-text" > 
						<span>Display <input type="radio" <?php echo ($post_data[40]== "Yes"? 'checked="checked"': '') ?> name="post_data[40]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[40]" value="No" <?php echo ($post_data[40]== "No"? 'checked="checked"': '') ?> >No </span>
					</td>
				</tr>
				<tr>
					<th scope="row"> Classic</th>
					<td><input name="post_data[53]" id="53" value="<?php echo esc_attr($post_data[53])?>"  type="text" class="regular-text" > 
						<span>Display <input type="radio" <?php echo ($post_data[52]== "Yes"? 'checked="checked"': '') ?> name="post_data[52]" value="Yes">Yes &nbsp;&nbsp;
						<input type="radio" name="post_data[52]" value="No" <?php echo ($post_data[52]== "No"? 'checked="checked"': '') ?> >No </span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="46">Blitz Instructions</label></th>
					<td>
					<!--	<textarea rows="5"  name="post_data[46]" id="46" cols="75"><?php //echo stripslashes($post_data[46])?></textarea> !-->
					<?php
						$Blitz_Instructions = $post_data[46]; 
						$id = 'Blitz_Instructions'; 
						$arg = array( 
						'textarea_name' => 'post_data[46]', 

						);
						wp_editor( $Blitz_Instructions, $id,$arg );

					?>
				    </td>
					<th scope="row"><label for="47">Short Instructions</label></th>
					<td>
					<!-- <textarea rows="5"  name="post_data[47]" id="47" cols="75"><?php //echo stripslashes($post_data[47])?></textarea>!-->
				    <?php
						$Short_Instructions = $post_data[47]; 
						$id = 'short_Instructions'; 
						$arg = array( 
						'textarea_name' => 'post_data[47]', 

						);
						wp_editor( $Short_Instructions, $id,$arg );

					?>
				    </td>	
				</tr>
				
				<tr>
					<th scope="row"><label for="48">Full Instructions</label></th>
					<td>
					<!--	<textarea rows="5"  name="post_data[48]" id="48" cols="75"><?php //echo stripslashes($post_data[48])?></textarea>!-->
					<?php
						$Full_Instructions = $post_data[48]; 
						$id = 'full_Instructions'; 
						$arg = array( 
						'textarea_name' => 'post_data[48]', 

						);
						wp_editor( $Full_Instructions, $id,$arg );

					?>
				    </td>
					<th scope="row"><label for="54">Classic Instructions</label></th>
					<td>	<!--	<textarea rows="5"  name="post_data[54]" id="54" cols="75"><?php //echo stripslashes($post_data[54])?></textarea>!-->
					<?php
						$Classic_Instructions = $post_data[54]; 
						$id = 'classic_Instructions'; 
						$arg = array( 
						'textarea_name' => 'post_data[54]', 

						);
						wp_editor( $Classic_Instructions, $id,$arg );

					?>
				    </td>
				</tr>				
				
				<tr><td colspan="2">&nbsp;</td></tr>	
				<tr>
					<th scope="row"><label for="76">Number of Answers Options</label></th>
					<td> <input onclick="update_values('Two');" type="radio" <?php echo ($post_data[76]== "Two"? 'checked="checked"': '') ?>  name="post_data[76]" value="Two">Two &nbsp;&nbsp;<input onclick="update_values('Three');"type="radio" <?php echo ($post_data[76]== "Three"? 'checked="checked"': '') ?>  name="post_data[76]" value="Three">Three &nbsp;&nbsp;<input onclick="update_values('Five');" type="radio" name="post_data[76]" value="Five" <?php echo ($post_data[76]== "Five"? 'checked="checked"': '') ?> >Five </td>
				</tr>
				<tr><th >Answers Options Text</th>
					<td><img id="image_to_show_Five" class="example_image" src="<?php echo esc_url(plugins_url('moneyquiz/assets/images/five_options.PNG'))?>">
						<img id="image_to_show_Three" class="example_image" src="<?php echo esc_url(plugins_url('moneyquiz/assets/images/three_options.PNG'))?>">
						<img id="image_to_show_Two" class="example_image" src="<?php echo esc_url(plugins_url('moneyquiz/assets/images/two_options.PNG'))?>">
					</td>		
				</tr>	
				<tr>
					<th scope="row"><label for="77">Never</label></th>
					<td>
					<?php if( !empty( $post_data[90] ) ){
						$never_value = $post_data[90];
						
					}else{
						$never_value = 'Never';
						
					}
					?>
					<input name="post_data[90]" id="90" value="<?php echo esc_attr($never_value) ?>"  type="text" class="regular-text" >					
					<input class="post_data_radio" type="radio" <?php echo ($post_data[77]== "Yes"? 'checked="checked"': '') ?>  name="post_data[77]" id="post_data_77" value="Yes">Yes &nbsp;&nbsp;<input  class="post_data_radio" type="radio" <?php echo ($post_data[77]== "No"? 'checked="checked"': '') ?>  name="post_data[77]" value="No">No  </td>
				</tr>
				<tr>
					<th scope="row"><label for="78">Seldom</label></th>
					<td>
					<?php if( !empty( $post_data[91] ) ){
						$seldom_value = $post_data[91];
					}else{
						$seldom_value = 'Seldom';
					}
					?>
					<input name="post_data[91]" id="91" value="<?php echo esc_attr($seldom_value) ?>"  type="text" class="regular-text">
					<input  class="post_data_radio"  type="radio" <?php echo ($post_data[78]== "Yes"? 'checked="checked"': '') ?>  name="post_data[78]" id="post_data_78" value="Yes">Yes &nbsp;&nbsp;<input  class="post_data_radio"  type="radio" <?php echo ($post_data[78]== "No"? 'checked="checked"': '') ?>  name="post_data[78]" value="No">No  </td>
				</tr>
				<tr>
					<th scope="row"><label for="79">Sometimes</label></th>
					<td>
					<?php if( !empty( $post_data[92] ) ){
						$sometimes_value = $post_data[92];
					}else{
						$sometimes_value = 'Sometimes';
					}
					?>
					<input name="post_data[92]" id="92" value="<?php echo esc_attr($sometimes_value) ?>"  type="text" class="regular-text">
					<input class="post_data_radio"  type="radio" <?php echo ($post_data[79]== "Yes"? 'checked="checked"': '') ?>  name="post_data[79]" id="post_data_79" value="Yes">Yes &nbsp;&nbsp;<input class="post_data_radio"  type="radio" <?php echo ($post_data[79]== "No"? 'checked="checked"': '') ?>  name="post_data[79]" value="No">No  </td>
				</tr>
				<tr>
					<th scope="row"><label for="80">Mostly</label></th>
					<td>
					<?php if( !empty( $post_data[93] ) ){
						$mostly_value = $post_data[93];
					}else{
						$mostly_value = 'Mostly';
					}
					?>
					<input name="post_data[93]" id="93" value="<?php echo esc_attr($mostly_value) ?>"  type="text" class="regular-text">
					<input class="post_data_radio"  type="radio" <?php echo ($post_data[80]== "Yes"? 'checked="checked"': '') ?>  name="post_data[80]"  id="post_data_80" value="Yes">Yes &nbsp;&nbsp;<input class="post_data_radio"  type="radio" <?php echo ($post_data[80]== "No"? 'checked="checked"': '') ?>  name="post_data[80]" value="No">No  </td>
				</tr>
				<tr>
					<th scope="row"><label for="81">Always</label></th>
					<td>
					<?php if( !empty( $post_data[94] ) ){
						$always_value = $post_data[94];
					}else{
						$always_value = 'Always';
					}
					?>
					<input name="post_data[94]" id="94" value="<?php echo esc_attr($always_value) ?>"  type="text" class="regular-text">
					<input class="post_data_radio"  type="radio" <?php echo ($post_data[81]== "Yes"? 'checked="checked"': '') ?>  name="post_data[81]" id="post_data_81" value="Yes">Yes &nbsp;&nbsp;<input class="post_data_radio"  type="radio" <?php echo ($post_data[81]== "No"? 'checked="checked"': '') ?>  name="post_data[81]" value="No">No  </td>
				</tr>
				<tr>
					<th>Answered</th>
					<td><input type="text" name="answered_label" id="answered_label" class="regular-text" value="<?php echo esc_attr($answred_label[1]);?>"></td>
				</tr>	
				<tr>
					<th>Answered Error Message</th>
					<td><input type="text" name="answred_error_message" id="answred_error_message" class="regular-text" value="<?php echo esc_attr($answred_label[2]);?>"></td>
				</tr>	
				
				
				<tr><td colspan="2">&nbsp;</td></tr>	
				<tr>
					<th scope="row"><label for="86">Show Progress Bar</label></th>
					<td> <input type="radio" <?php echo ($post_data[86]== "Yes"? 'checked="checked"': '') ?>  name="post_data[86]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[86]" value="No" <?php echo ($post_data[86]== "No"? 'checked="checked"': '') ?> >No </td>
				</tr>	
				<tr>
					<th scope="row"><label for="87">Progress Bar Background Colour</label></th>
					<td><input type="text" name="post_data[87]" id="87" value="<?php echo esc_attr($post_data[87]);?>" class="color-field-87 regular-text" data-default-color="<?php echo esc_attr($post_data[87]);?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="88">Progress Bar Main Colour</label></th>
					<td><input type="text" name="post_data[88]" id="88" value="<?php echo esc_attr($post_data[88]);?>" class="color-field-88 regular-text" data-default-color="<?php echo esc_attr($post_data[88]);?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="89">Progress Bar Text Colour</label></th>
					<td><input type="text" name="post_data[89]" id="89" value="<?php echo esc_attr($post_data[89]);?>" class="color-field-89 regular-text" data-default-color="<?php echo esc_attr($post_data[89]);?>" />
					</td>
				</tr>				
				

				<tr><td colspan="2">&nbsp;</td></tr>	
				<tr>
					<th scope="row"><label for="49">Show Ideal Score</label></th>
					<td> <input type="radio" <?php echo ($post_data[49]== "Yes"? 'checked="checked"': '') ?>  name="post_data[49]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[49]" value="No" <?php echo ($post_data[49]== "No"? 'checked="checked"': '') ?> >No </td>
				</tr>			
				<tr>
					<th scope="row"> <label for="50">Ideal Score Label</label></th>
					<td><input name="post_data[50]" id="50" value="<?php echo esc_attr($post_data[50])?>"  type="text" class="regular-text" > 
					</td>
				</tr>
				<tr>
					<th scope="row"> <label for="51">Prospects Score Label</label></th>
					<td><input name="post_data[51]" id="51" value="<?php echo esc_attr($post_data[51])?>"  type="text" class="regular-text" > 
					</td>
				</tr>
				<tr><td colspan="2">&nbsp;</td></tr>		
				
				<tr>
					<th scope="row"><label for="44">Submit/Next Button Colour</label></th>
					<td><input type="text" name="post_data[44]" id="44" value="<?php echo esc_attr($post_data[44]);?>" class="submit-color-field regular-text" data-default-color="<?php echo esc_attr($post_data[44]);?>" />
					</td>
				</tr>							<tr>					
					<th scope="row"><label for="95">Submit/Next Button Height</label></th>
				  <td><input name="post_data[95]" id="95" value="<?php echo esc_attr($post_data[95])?>"  type="text" class="regular-text" ></td>	</tr>					
				<tr>
					<th scope="row"><label for="45">Previous Button Colour</label></th>
					<td><input type="text" name="post_data[45]" id="45" value="<?php echo esc_attr($post_data[45]);?>" class="prev-color-field regular-text" data-default-color="<?php echo esc_attr($post_data[45]);?>" />
					</td>
				</tr>			
				<tr>
					<th scope="row"><label for="Prospect_Surname">Participate in group wide stats</label></th>
					<td> <input type="radio" <?php echo ($post_data[33]== "Yes"? 'checked="checked"': '') ?>  name="post_data[33]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[33]" value="No" <?php echo ($post_data[33]== "No"? 'checked="checked"': '') ?> >No </span></td>
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
<style>
.example_image{max-width:500px;}
</style>
<script>
<?php if(isset($post_data[76])){
	echo 'update_values("'.esc_js($post_data[76]).'");';
} ?>


function update_values(val){
	
	
	jQuery(".post_data_radio").prop('checked', false);
	jQuery(".example_image").hide();
	/* jQuery("#post_data[77]").attr("checked", false);
	
	jQuery("#post_data[78]").prop('checked', false);
	jQuery("#post_data[79]").prop('checked', false);
	jQuery("#post_data[80]").prop('checked', false);
	jQuery("#post_data[81]").prop('checked', false); */
	setTimeout(function(){
		console.log(val);
		if(val == "Two"){
			jQuery("#image_to_show_Two").show();
			jQuery("#post_data_77").prop('checked', true);
			jQuery("#post_data_81").prop('checked', true);
			
		}	
		if(val == "Three"){
			jQuery("#image_to_show_Three").show();
			jQuery("#post_data_77").prop('checked', true);
			jQuery("#post_data_79").prop('checked', true);
			jQuery("#post_data_81").prop('checked', true);
		}	
		if(val == "Five"){
			jQuery("#image_to_show_Five").show();
			jQuery("#post_data_77").prop('checked', true);
			jQuery("#post_data_78").prop('checked', true);
			jQuery("#post_data_79").prop('checked', true);
			jQuery("#post_data_80").prop('checked', true);
			jQuery("#post_data_81").prop('checked', true);
 
		}
	}, 300);
	
}



</script>
