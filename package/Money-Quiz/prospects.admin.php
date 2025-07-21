<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
  
 ?>
 
 <div class="mq-container">
    <?php
   // fetch data at one place for all forms
	$sql = "SELECT * FROM ".$table_prefix.REGISTER_RESULT_PAGE."" ;
	$rows = $wpdb->get_results($sql);
	$register_page_seeting= array();
	foreach($rows as $row){
		$register_page_seeting[$row->id] = stripslashes($row->value);
	}
    ?>
    <?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
    <h3>Prospect's Personal Details for display on the MoneyQuiz form</h3>
    <?php echo $save_msg ?>
    <form method="post" action="" novalidate="novalidate">
        <input name="action" value="update" type="hidden" />
        <input name="update_register" value="update_register" type="hidden" />
        <?php wp_nonce_field( );?>
        <table class="form-table mq-form-table">
            <tbody>
            <tr>
            <tr>
                    <th scope="row"><label for="Prospect_First_Name">Page Title</label></th>
                    <td>
                        <input name="register_page_seeting[1]" id="page_title" value="<?php echo $register_page_seeting[1]?>" class="regular-text" type="text" />
                    </td>
                </tr>
					<th scope="row"><label for="Header_Image">Banner Image</label></th>
					<td> 
						<?php if($register_page_seeting[2] != ""){ ?>
							<a href="#" class="mq_upload_image_button "><img class="true_pre_image" src="<?php echo $register_page_seeting[2]?>" style="max-width:240px;display:block;margin-bottom: 10px;"></a>
							<input  class="regular-text" type="hidden" name="register_page_seeting[2]" id="image_url" value="<?php echo $register_page_seeting[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:block;color:red;">Remove image</a>
						<?php }else{ ?>
							<a href="#" class="mq_upload_image_button button">Upload image</a>
							<input  class="regular-text" type="hidden" name="register_page_seeting[2]" id="image_url" value="<?php echo $register_page_seeting[2]?>" readonly />
							<a href="#" class="mq_remove_image_button" style="display:inline-block;display:none;color:red;">Remove image</a>
						<?php } ?>
					</td>
				</tr>
                <tr>
					<th scope="row"><label for="Prospect_Surname">Display Banner</label></th>
					<td> Display <input type="radio" <?php echo ($post_data[29]== "Yes"? 'checked="checked"': '') ?> name="post_data[29]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[29]" value="No" <?php echo ($post_data[29]== "No"? 'checked="checked"': '') ?> >No </span></td>
				</tr>	
                <tr>
                    <th scope="row"><label for="Closing_Message1">Register Intro Content</label></th>
                    <td>
                        
                        <?php

						$register_intor_content  = $register_page_seeting[3]; 
						$id = 'register_intor_content'; 

						$arg = array( 
						'textarea_name' =>'register_page_seeting[3]'
                         );
                         wp_editor( $register_intor_content, $id,$arg ); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="Prospect_First_Name">Prospect's First Name</label></th>
                    <td>
                        <input name="post_data[13]" id="Prospect_First_Name" value="<?php echo $post_data[13]?>" class="regular-text" type="text" />
                        <span>
                            Display <input type="radio"
                            <?php echo ($post_data[14]== "Yes"? 'checked="checked"': '') ?>
                            name="post_data[14]" value="Yes">Yes &nbsp;&nbsp; <input type="radio" name="post_data[14]" value="No"
                            <?php echo ($post_data[14]== "No"? 'checked="checked"': '') ?>
                            >No
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="Prospect_Surname">Prospect's Surname</label></th>
                    <td>
                        <input name="post_data[15]" id="Prospect_Surname" value="<?php echo $post_data[15]?>" class="regular-text" type="text" />
                        <span>
                            Display <input type="radio"
                            <?php echo ($post_data[16]== "Yes"? 'checked="checked"': '') ?>
                            name="post_data[16]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[16]" value="No"
                            <?php echo ($post_data[16]== "No"? 'checked="checked"': '') ?>>No
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="Prospect_Email">Prospect's Email</label></th>
                    <td>
                        <input name="post_data[17]" id="Prospect_Email" value="<?php echo $post_data[17]?>" class="regular-text" type="text" />
                        <span>
                            Display <input type="radio"
                            <?php echo ($post_data[18]== "Yes"? 'checked="checked"': '') ?>
                            name="post_data[18]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[18]" value="No"
                            <?php echo ($post_data[18]== "No"? 'checked="checked"': '') ?>>No
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="Prospect_Tel">Prospect's Tel</label></th>
                    <td>
                        <input name="post_data[19]" id="Prospect_Tel" value="<?php echo $post_data[19]?>" class="regular-text" type="text" />
                        <span>
                            Display <input type="radio"
                            <?php echo ($post_data[20]== "Yes"? 'checked="checked"': '') ?>
                            name="post_data[20]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[20]" value="No"
                            <?php echo ($post_data[20]== "No"? 'checked="checked"': '') ?>>No
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td></td>
                </tr>
                <tr>
                    <th scope="row"><label for="Newsletter">Newsletter Option</label></th>
                    <td>
                        <input name="post_data[21]" id="Newsletter" value="<?php echo $post_data[21]?>" class="regular-text" type="text" />
                        <span>
                            Display <input type="radio"
                            <?php echo ($post_data[22]== "Yes"? 'checked="checked"': '') ?>
                            name="post_data[22]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[22]" value="No"
                            <?php echo ($post_data[22]== "No"? 'checked="checked"': '') ?>>No
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="Consultation">Consultation Option</label></th>
                    <td>
                        <input name="post_data[23]" id="Consultation" value="<?php echo $post_data[23]?>" class="regular-text" type="text" />
                        <span>
                            Display <input type="radio"
                            <?php echo ($post_data[24]== "Yes"? 'checked="checked"': '') ?>
                            name="post_data[24]" value="Yes">Yes &nbsp;&nbsp;<input type="radio" name="post_data[24]" value="No"
                            <?php echo ($post_data[24]== "No"? 'checked="checked"': '') ?>>No
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="Submit_Button">Submit Button</label></th>
                    <td><input name="post_data[25]" id="Submit_Button" value="<?php echo $post_data[25]?>" class="regular-text" type="text" /></td>
                </tr>
              <!--  <tr>
                    <th scope="row"><label for="Closing_Message1">Closing Message1</label></th>
                    <td>
                      
                        <?php

						/*$closingMessage_1  = $post_data[82]; 
						$id = 'closingMessage_1'; 
						$arg = array( 
						'textarea_name' =>
                        'post_data[82]', ); wp_editor( $closingMessage_1, $id,$arg );*/ ?>
                    </td>
                </tr>
                !--->          
                <tr>
                    <td colspan="2"><h2>Capture Prospect's Personal Details(as indicated above)</h2></td>
                </tr>

                <tr>
                    <th scope="row"><label for="83">Before Quiz Results?</label></th>
                    <td>
                        <input type="radio"
                        <?php echo ($post_data[83]== "Yes"? 'checked="checked"': '') ?>
                        name="post_data[83]" value="Yes">Yes &nbsp;&nbsp;<input type="radio"
                        <?php echo ($post_data[83]== "No"? 'checked="checked"': '') ?>
                        name="post_data[83]" value="No">No <br />
                        <span>If No, The prospect capture form will be displayed after showing results. </span>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="84">CTA Message</label></th>
                    <td>
                       
                        <?php
						$cta_messege  = $post_data[84]; 
						$id = 'cta_messege'; 
						$arg = array( 
						'textarea_name' =>
                        'post_data[84]', ); wp_editor( $cta_messege, $id,$arg ); ?>
                    </td>
                     <!--  <th scope="row"><label for="85">Closing Message2</label></th>
                 <td>
                       
                        <?php
                       /* $closingMessage_2  = $post_data[85]; 
                        $id = 'closingMessage_2'; 
                        $arg = array( 
                            'textarea_name' =>
                        'post_data[85]', ); wp_editor( $closingMessage_2, $id,$arg ); 
                        */
                        ?>
                    </td>
                    !-->
                </tr>
                        
                <tr>
                    <th scope="row">&nbsp;</th>
                    <td>
                        <p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit" /></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</div>

<!-- .wrap -->