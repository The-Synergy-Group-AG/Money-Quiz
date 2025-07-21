<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
	
 ?>

<div class=" mq-container money-quiz-template-form">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<h3>Quiz Screens for Sections 1 to 7:</h3>
	<?php echo $save_msg ?>
	<?php
		
	?>
	<form method="post" action="" novalidate="novalidate">
		 
		<input name="action" value="page_question_screen" type="hidden">
		<?php wp_nonce_field( );?>
		<table class="form-table mq-form-table">
			<tbody>
                <tr>
                <th><label>SECTION 1:Title</label></th>
                <td><input type="text" name="post_data[15]" value="<?php echo $page_question_screen[15];?>"></td>	
                </tr>
            <tr>

                <th><label>SECTION 1: Introduction Content</label></th>	

                <td>
                <?php
                    $quiz_list_section_1_intro = $page_question_screen[1]; 
                    $id = 'quiz_list_section_1_intro'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[1]', 
                    
                    );
                    wp_editor( $quiz_list_section_1_intro, $id,$arg );

                ?>
                </td>
                <th><label>SECTION 1: Closing Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_1_closing = $page_question_screen[2]; 
                    $id = 'quiz_list_section_1_closing'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[2]', 
                   
                    );
                    wp_editor( $quiz_list_section_1_closing, $id, $arg );

                ?>
                </td>
            </tr>
            <tr>
                <th><label>SECTION 2:Title</label></th>
                <td><input type="text" name="post_data[16]" value="<?php echo $page_question_screen[16];?>"></td>	
                </tr>
            <tr>
                <th><label>SECTION 2: Introduction Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_2_intro = $page_question_screen[3]; 
                    $id = 'quiz_list_section_2_intro'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[3]', 
                   
                    );
                    wp_editor( $quiz_list_section_2_intro, $id, $arg );

                ?>
                </td>
                <th><label>SECTION 2: Closing Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_2_closing = $page_question_screen[4]; 
                    $id = 'quiz_list_section_2_closing'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[4]', 
                    
                    );
                    wp_editor( $quiz_list_section_2_closing, $id, $arg );

                ?>
                    
              </td>
            </tr>
            <tr>
                <th><label>SECTION 3:Title</label></th>
                <td><input type="text" name="post_data[17]" value="<?php echo $page_question_screen[17];?>"></td>	
                </tr>
            <tr>
                <th><label>SECTION 3: Introduction Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_3_intro = $page_question_screen[5]; 
                    $id = 'quiz_list_section_3_intro'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[5]', 
                    
                    );
                    wp_editor( $quiz_list_section_3_intro, $id, $arg );

                ?>
               </td>
                <th><label>SECTION 3: Closing Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_3_closing = $page_question_screen[6]; 
                    $id = 'quiz_list_section_3_closing'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[6]', 
                    );
                    wp_editor( $quiz_list_section_3_closing, $id, $arg );

                ?>
                </td>
            </tr>
            <tr>
                <th><label>SECTION 4:Title</label></th>
                <td><input type="text" name="post_data[18]" value="<?php echo $page_question_screen[18];?>"></td>	
                </tr>
            <tr>
                <th><label>SECTION 4: Introduction Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_4_intro = $page_question_screen[7]; 
                    $id = 'quiz_list_section_4_intro'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[7]', 
                    );
                    wp_editor( $quiz_list_section_4_intro, $id,$arg );

                ?>
            
               </td>
                <th><label>SECTION 4: Closing Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_4_closing = $page_question_screen[8]; 
                    $id = 'quiz_list_section_4_closing'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[8]', 
                    );
                    wp_editor( $quiz_list_section_4_closing, $id,$arg );

                ?>
               </td>
            </tr>
            <tr>
                <th><label>SECTION 5:Title</label></th>
                <td><input type="text" name="post_data[19]" value="<?php echo $page_question_screen[19];?>"></td>	
                </tr>
            <tr>
                <th><label>SECTION 5: Introduction Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_5_intro = $page_question_screen[9]; 
                    $id = 'quiz_list_section_5_intro'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[9]', 
                    );
                    wp_editor( $quiz_list_section_5_intro, $id,$arg);

                ?>
                </td>
                <th><label>SECTION 5: Closing Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_5_closing = $page_question_screen[10]; 
                    $id = 'quiz_list_section_5_closing'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[10]', 
                    );
                    wp_editor( $quiz_list_section_5_closing, $id,$arg);

                ?>
               </td>
            </tr>
            <tr>
                <th><label>SECTION 6:Title</label></th>
                <td><input type="text" name="post_data[20]" value="<?php echo $page_question_screen[20];?>"></td>	
                </tr>
            <tr>
                <th><label>SECTION 6: Introduction Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_6_intro = $page_question_screen[11]; 
                    $id = 'quiz_list_section_6_intro'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[11]', 
                    );
                    wp_editor( $quiz_list_section_6_intro, $id,$arg);

                ?>
               </td>
                <th><label>SECTION 6: Closing Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_6_closing = $page_question_screen[12]; 
                    $id = 'quiz_list_section_6_closing'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[12]', 
                    );
                    wp_editor( $quiz_list_section_6_closing, $id,$arg);

                ?>
               </td>
            </tr>
            <tr>
                <th><label>SECTION 7:Title</label></th>
                <td><input type="text" name="post_data[21]" value="<?php echo $page_question_screen[21];?>"></td>	
                </tr>
            <tr>
                <th><label>SECTION 7: Introduction Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_7_intro = $page_question_screen[13]; 
                    $id = 'quiz_list_section_7_intro'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[13]', 
                    );
                    wp_editor( $quiz_list_section_7_intro, $id,$arg);

                ?>
            </td>
                <th><label>SECTION 7: Closing Content</label></th>		
                <td>
                <?php
                    $quiz_list_section_7_closing = $page_question_screen[14]; 
                    $id = 'quiz_list_section_7_closing'; 
                    $arg = array( 
                    'textarea_name' => 'post_data[14]', 
                    );
                    wp_editor( $quiz_list_section_7_closing, $id,$arg);

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