<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/

function mq_questions_func($attr)
{
	?>
	<style>
	  /*--- Loader Text */
    #mfm-loading {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: #fbf5f5;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        font-family: Arial, sans-serif;
        color: #333;
        }
    
        /* Rotating image */
        .mfm-rotating-image {
        width: 60px;
        height: 60px;
        animation: spin 1s linear infinite;
        margin-bottom: 10px; /* Space between the image and text */
        }
    
        /* Spin animation */
        @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
        }
	</style>
	<?php
	global $wpdb;
	$table_prefix = $wpdb->prefix;
	$save_msg = "";

	/** Tagline */
		// Archive Tag Line

		$sql = "SELECT * FROM ".$table_prefix.ARCHIVE_TYPE_TAG_LINE."" ;
		$rows = $wpdb->get_results($sql);
		$arch_tag_line = array();
		foreach($rows as $row){
			$arch_tag_line[$row->id] = stripslashes($row->value);
		}

		// End

	/** end */


	/** Get Register  */
	// fetch data at one place for all forms
	 $sql = "SELECT * FROM ".$table_prefix.REGISTER_RESULT_PAGE."" ;
	 $rows = $wpdb->get_results($sql);
	 $register_page_seeting= array();
	 foreach($rows as $row){
		 $register_page_seeting[$row->id] = stripslashes($row->value);
	 }

	/** end */

	// fetch data for email signature email
	$sql = "SELECT * FROM ".$table_prefix.EMAIL_SIGNATURE."" ;
	$rows = $wpdb->get_results($sql);
	$signature_email = array();
	foreach($rows as $row){
		$signature_email[$row->id] = stripslashes($row->value);
	}
	//print_r($signature_email);

	/** Get Answered Label */

	$sql = "SELECT * FROM ".$table_prefix.ANSWER_TABLE."" ;
	$rows_answered = $wpdb->get_results($sql);
	$answred_label = array();
	foreach($rows_answered as $row){
		$answred_label[$row->id] = stripslashes($row->value);
	}

	/** Quiz Result */
		$sql = "SELECT * FROM ".$table_prefix.QUIZ_RESULT."" ;
		$rows = $wpdb->get_results($sql);
		
		$quiz_result_setting = array();
		foreach($rows as $row){
			$quiz_result_setting[$row->id] = stripslashes($row->value);
		}
	/** End */

	/** Fetch question list and Result page content */
	$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_QUESTION_SCREEN."" ;
		$rows = $wpdb->get_results($sql);
		
		$page_question_screen= array();
		foreach($rows as $row){
			$page_question_screen[$row->field] = stripslashes($row->value);
			$page_question_screen[$row->id] = stripslashes($row->value);
		}
		$sql = "SELECT * FROM ".$table_prefix.TABLE_EMAIL_SETTING."" ;
		$rows = $wpdb->get_results($sql);
		
		$email_setting= array();
		foreach($rows as $row){
			$email_setting[$row->field] = stripslashes($row->value);
			$email_setting[$row->id] = stripslashes($row->value);
		}
	/** end */
	/** get money quiz banner image and column image */
    

	/** Recaptcha  */
	$sql = "SELECT * FROM ".$table_prefix.RECAPTCHA."" ;
	$rows = $wpdb->get_results($sql);
	
	$recaptcha_setting = array();
	foreach($rows as $row){
		$recaptcha_setting[$row->id] = stripslashes($row->value);
	}
	?>
	<input type="hidden" id="is_recaptcha_enable" value="<?php echo $recaptcha_setting[1]; ?>" />
	<input type="hidden" id="recaptcha_type" value="<?php echo $recaptcha_setting[2]; ?>" />
	<input type="hidden" id="recaptcha_site_key" value="<?php echo $recaptcha_setting[3]; ?>" />
	<input type="hidden" id="recaptcha_secrete" value="<?php echo $recaptcha_setting[4]; ?>" />
	
	<?php
	/** End */
	

	$sql_image_setting = "SELECT * from ".$table_prefix.TABLE_MQ_MONEY_LAYOUT."  ";
	$results_image_setting_list = $wpdb->get_results($sql_image_setting, OBJECT);
	$banner_image = "";
	$column_image = "";
	if($results_image_setting_list){
		foreach($results_image_setting_list as $rs){
			if($rs->Moneytemplate_ID == 1 && !empty($rs->value)){
				$banner_image = $rs->value;
			}
			if($rs->Moneytemplate_ID == 2 && !empty($rs->value)){
				$column_image = $rs->value;
			}
			if($rs->Moneytemplate_ID == 3 && !empty($rs->value)){
				$gift_image1 = $rs->value;
			}
			if($rs->Moneytemplate_ID == 4 && !empty($rs->value)){
				$gift_image2 = $rs->value;
			}
			/**new field value */
			if($rs->Moneytemplate_ID == 5 && !empty($rs->value)){
				$banner_heading_text = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 6 && !empty($rs->value)){
				$banner_quiz_content = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 7 && !empty($rs->value)){
				$minfull_money_gift_two_column_heading = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 8 && !empty($rs->value)){
				$minfull_money_bottom = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 9 && !empty($rs->value)){
				$minfull_money_gift_one_headig = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 10 && !empty($rs->value)){
				$minfull_money_gift_two_heading = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 11 && !empty($rs->value)){
				$minfull_money_gift_one_content = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 12 && !empty($rs->value)){
				$minfull_money_gift_two_content = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 13 && !empty($rs->value)){
				$minfull_money_bottom_section_content = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 14 && !empty($rs->value)){
				$minfull_money_gift_one_display = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 15 && !empty($rs->value)){
				$minfull_money_gift_two_display = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 16 && !empty($rs->value)){
				$mindfull_money_button_text = stripslashes($rs->value);
			}
			if($rs->Moneytemplate_ID == 17 && !empty($rs->value)){
				$two_column_heading_content = stripslashes($rs->value);
			}
			/** end */
		}
	}
	// get money coach to show in quiz page and email 
	$sql = "SELECT * from ".$table_prefix.TABLE_MQ_COACH."  ";
	$results = $wpdb->get_results($sql, OBJECT);
	$show_header_image = false;
	$header_image = "";
	$all_values = array();
	if($results){
		foreach($results as $rs){			
			if($rs->ID == 28 && $rs->Value == "Yes"){ // if Display MQ Image on Main page == yes only then
				$show_header_image = true;
			}
			if($rs->ID == 34 ){
				$header_image = "<div class='mq-header-image-container' ><img src='".$rs->Value."' class='mq-header-image' /></div>";
			}
			if($rs->ID == 34 ){
				$header_image_email = "<div class='mq-header-image-container' ><img src='".$rs->Value."' class='mq-header-image' width='893' style='width:941px;'/></div>";

			}
			$all_values[$rs->ID] = stripslashes($rs->Value);			
		}
	}

	// fetch data for CTA
	$sql_cta = "SELECT * FROM ".$table_prefix.TABLE_MQ_CTA."" ;
	$rows_cta = $wpdb->get_results($sql_cta);
	$cta_data= array();
	$skpi_row = array(1,3,2,6,9,24,25,26,27,28,39,40,41,43,46,49,52,55,58,61,64,67,68,69,71,74,77,83,84,85,87,96,97,98,100,109,110);
	foreach($rows_cta as $row_cta){
		$temp_id_row = $row_cta->ID;
		if(in_array($temp_id_row,$skpi_row)){
			$cta_data[$row_cta->ID] = stripslashes($row_cta->Value);
		}else{
			$cta_data[$row_cta->ID] = stripslashes(wpautop($row_cta->Value));
		}
		
	}
	
	$plugin_activated = 0;	 
	// API query parameters
	$api_params = array(
		'slm_action' => 'slm_check',
		'secret_key' => MONEYQUIZ_SPECIAL_SECRET_KEY,
		'license_key' => $all_values[35],
		'registered_domain' => $_SERVER['SERVER_NAME'],
		'item_reference' => urlencode(MONEYQUIZ_ITEM_REFERENCE),
	);

	// Send query to the license manager server
	$query = esc_url_raw(add_query_arg($api_params, MONEYQUIZ_LICENSE_SERVER_URL));
	$response = wp_remote_get($query, array('timeout' => 20, 'sslverify' => false));

	// Check for error in the response
	if (is_wp_error($response)){
		$save_msg =  "Unexpected Error! The query returned with an error.";
	}

	//var_dump($response);//uncomment it if you want to look at the full response
	
	// License data.
	$license_data_checked = json_decode(wp_remote_retrieve_body($response));

	if(!isset($license_data_checked->result) || $license_data_checked->status != "active"){
		$save_msg = '<div class="data_saved error"><b>Wrong Key:</b> Your License Key is invalid, please contact Business Insights Group for validation. </div>';
	}elseif(!isset($license_data_checked->result) || $license_data_checked->result == "error"){
		$save_msg = '<div class="data_saved error"><b>Wrong Key:</b> Your License Key is invalid, please contact Business Insights Group for validation.  </div>';
	}elseif(!isset($license_data_checked->result) || time() > strtotime($license_data_checked->date_expiry) ){
		$save_msg = '<div class="data_saved error"><b>Expired Key:</b> Your License Key has expired, please contact Business Insights Group to renew your License.</div>';
	}elseif(!isset($license_data_checked->result) || count($license_data_checked->registered_domains) ==0 ){
		$save_msg = '<div class="data_saved error"><b>Wrong Key:</b> Your License Key is invalid, please contact Business Insights Group for validation.</div>';
	}else{
		$plugin_activated = 1;
	}
	
//	$plugin_activated = 1; 
	if($plugin_activated == 0){
		return $save_msg;
	}		
	
	$ideal_values = array(1=>'90%',5=>'80%',9=>'70%',13=>'60%',17=>'20%',21=>'10%',25=>'10%',29=>'10%',);	
	
	// to process all admin forms at one place
if(isset($_POST['prospect_action']) && $_POST['prospect_action'] == "submit_new"){ ?>
<div id='mfm-loading'>
<img src='https://mindfulmoneycoaching.online/wp-content/plugins/moneyquiz/assets/images/mind-full-preloader.webp' alt='Loading' class='mfm-rotating-image'>
<div id="mfm-loading-text"><h1> Thank you for your patience, we're Almost done. You're results will soon be displayed here.</hi></div>
</div>		

<?php
	exit;
		
		$prospect_data =  $_POST['prospect_data'];
		$Name = sanitize_text_field( $prospect_data['Name'], true );
		$Surname = sanitize_text_field( $prospect_data['Surname'], true );
		$Email = sanitize_text_field( $prospect_data['Email'], true );
		$Telephone = sanitize_text_field( $prospect_data['Telephone'], true );
		$Newsletter = sanitize_text_field( $prospect_data['Newsletter'], true );
		$Consultation = sanitize_text_field( $prospect_data['Consultation'], true );
		$prospect_Email = $Email;
		$newmal = sanitize_text_field( $prospect_data['Email'], true );
		
		// if email already exists 
		$results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );
		if($results){
			$prospect_id = $results->Prospect_ID;
			$wpdb->update( 
				$table_prefix.TABLE_MQ_PROSPECTS, 
				array( 
					'Newsletter' => $Newsletter,
					'Consultation' => $Consultation,
				), 
				array( 'Prospect_ID' => $prospect_id )
			);
		}else{
			$data_insert = array( 
				'Name' => $Name, 
				'Surname' => $Surname, 
				'Email' => $Email, 
				'Telephone' => $Telephone, 
				'Newsletter' => $Newsletter, 
				'Consultation' => $Consultation, 
			);		
			$wpdb->insert( 
					$table_prefix.TABLE_MQ_PROSPECTS,
					$data_insert
				);
			$prospect_id = $wpdb->insert_id; // prospect id
		}
		
		$wpdb->update( 
			$table_prefix.TABLE_MQ_TAKEN, 
			array( 
				'Prospect_ID' => $prospect_id
			), 
			array( 'Taken_ID' => $_GET['tid'] )
		);
		
		$wpdb->update( 
			$table_prefix.TABLE_MQ_RESULTS, 
			array( 
				'Prospect_ID' => $prospect_id 
				 
			), 
			array( 'Taken_ID' => $_GET['tid'] )
		);
		 
		
		
		
			// fetch data for archetypes for email
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_ARCHETYPES."" ;
		$rows = $wpdb->get_results($sql);
		$archetypes_data = array();
		$archive_type_include = array(3,4,7,8,11,12,15,16,19,20,23,24,27,28,31,32);
		foreach($rows as $row){
			$temp_archive_id = $row->ID; 
			if(in_array($temp_archive_id,$archive_type_include)){
				$archetypes_data[$row->ID] =  stripslashes(wpautop($row->Value));
			}else{
				$archetypes_data[$row->ID] = stripslashes($row->Value);
			}
			
		}
		$tid = $_GET['tid'];
		$prospect = $prospect_id;
		$sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question, mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE  mq_r.Prospect_ID=".$prospect." and mq_r.Taken_ID IN($tid) ORDER BY mq_r.Taken_ID ASC ";
		$sql_rows = $wpdb->get_results($sql_qry, OBJECT);
		 
		
		$Alchemist_score = $Alchemist_question = $Victim_score = $Victim_question = $Maverick_score = $Maverick_question = $Apprentice_score = $Apprentice_question = $Nurturer_score = $Nurturer_question = $Ruler_score = $Ruler_question = $Warrior_score = $Warrior_question = $Initiator_score = $Initiator_question = 0;
		$Alchemist_return =$Victim_return =$Maverick_return =$Apprentice_return =$Ruler_return =$Nurturer_return =$Initiator_return =$Warrior_return = 0;	
		$detailed_summary_rows = "";
		$new_arr = 1;
		$new_tr = 0;
		$table_row = '';
		$Alchemist_return_arr = $Victim_return_arr = $Maverick_return_arr = $Apprentice_return_arr = $Ruler_return_arr = $Warrior_return_arr = $Nurturer_return_arr = $Iniatiator_return_arr = array();	
		if($sql_rows){
			foreach($sql_rows as $row){
				$str = '<tr><td>'.$row->ID_Unique.'</td><td>'.$row->Question.'</td><td>'.$archetypes_data[$row->Archetype].'</td>';
				$str .= '<td>'.$row->Score.'</td>';
				if($row->Archetype == 1) //Warrior
					$Warrior_return_arr[$tid] += $row->Score;
				if($row->Archetype == 5) // Iniatiator
					$Iniatiator_return_arr[$tid] += $row->Score;
				if($row->Archetype == 9) // Ruler
					$Ruler_return_arr[$tid] += $row->Score;
				if($row->Archetype == 13) // Apprentice
					$Apprentice_return_arr[$tid] += $row->Score;
				if($row->Archetype == 17) // Maverick
					$Maverick_return_arr[$tid] += $row->Score;
				if($row->Archetype == 21) //Victim
					$Victim_return_arr[$tid] += $row->Score;
				if($row->Archetype == 25) //Alchemist
					$Alchemist_return_arr[$tid] += $row->Score;
				if($row->Archetype == 29) // Nurturer
					$Nurturer_return_arr[$tid] += $row->Score;
				$str .= '</tr>';
				$table_row .= $str;
				switch($row->Archetype){
					case 1:
						$Warrior_question++;  
					 break;
					case 5:
						$Initiator_question++;  
					 break;
					case 9:
						$Ruler_question++;  
					 break;
					case 13:
						$Apprentice_question++;  
					 break;
					case 17:
						$Maverick_question++;  
					 break;
					case 21:
						$Victim_question++;  
					 break;
					case 25:
						$Alchemist_question++;  
					 break;
					case 29:
						$Nurturer_question++;  
					 break;
				}
				$new_tr++;
			}
			$new_arr++;
		}
		
		// code to send email to prospect and money coach
		foreach($Warrior_return_arr as $Warrior_score){
			$Warrior_return = get_percentage($Warrior_question,$Warrior_score);
		}
		foreach($Iniatiator_return_arr as $Initiator_score){
			$Initiator_return = get_percentage($Initiator_question,$Initiator_score);
		}		
		foreach($Ruler_return_arr as $ques_score){
			$Ruler_return = get_percentage($Ruler_question,$ques_score);
		}	
		foreach($Apprentice_return_arr as $ques_score){
			$Apprentice_return = get_percentage($Apprentice_question,$ques_score);
		}
		foreach($Maverick_return_arr as $ques_score){
			$Maverick_return = get_percentage($Maverick_question,$ques_score);
		}
		foreach($Victim_return_arr as $ques_score){
			$Victim_return = get_percentage($Victim_question,$ques_score);
		}
		foreach($Alchemist_return_arr as $ques_score){
			$Alchemist_return = get_percentage($Alchemist_question,$ques_score);
		}		
		foreach($Nurturer_return_arr as $ques_score){
			$Nurturer_return = get_percentage($Nurturer_question,$ques_score);
		} 		
		
		$to = $Email; // prospect email's address 
		//$subject = 'MoneyQuiz Results for: '.$Name.' '.$Surname;
		$subject = $email_setting['email_subject'];
		$body = "Dear ".$Name.",<br>"; 
		//$body .= "<p>Thank you for taking the MoneyQuiz. Here are your results.</p>"; 
		
		if($all_values[31] == 'Yes'){
			$header_image_email = "<div class='mq-header-image-container' ><img src='".$rs->Value."' class='mq-header-image' width='893' style='width:941px;'/></div>";
		}
			$body .= $header_image_email;
		
		//$body .= '<p>'.stripslashes(wpautop($all_values[26])).'</p>';  // additional opening paragraph
		$body .= "<p>".wpautop($email_setting['email_thank_content'])."</p>"; 
		$body .=  '<div class="include_summary ">
			<table class="form-table mq-form-table-reports">
				<tbody>
					<tr>
						<th style="min-width:200px;text-align:left;" >Archetype</th>
						'.($all_values[49] == 'Yes' ? '<th style="min-width:200px;text-align:center;">'.$all_values[50].'</th>' : '').'
						<th style="min-width:200px;text-align:center;">'.$all_values[51].'</th>
					</tr>
					<tr>
						<td>'.$archetypes_data[1].'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[33].'</td>' : '').'
						<td align="center">'. round($Warrior_return).'%</td>
					</tr>
					<tr>
						<td>'.$archetypes_data[5].'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[34].'</td>' : '').'
						<td align="center">'. round($Initiator_return).'%</td>
					</tr>
					<tr>
						<td >'.$archetypes_data[9].'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[35].'</td>' : '').'
						<td align="center">'. round($Ruler_return).'%</td>
					</tr>
					<tr>
						<td>'.$archetypes_data[13].'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[36].'</td>' : '').'
						<td align="center">'. round($Apprentice_return).'%</td>
					</tr>
					<tr>
						<td>'.$archetypes_data[17].'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[37].'</td>' : '').'
						<td align="center">'. round($Maverick_return).'%</td>
					</tr>
					<tr>
						<td>'.$archetypes_data[21].'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[38].'</td>' : '').'
						<td align="center">'. round($Victim_return).'%</td>
					</tr>
					<tr>
						<td>'.$archetypes_data[25].'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[39].'</td>' : '').'
						<td align="center">'. round($Alchemist_return).'%</td>
					</tr>
					<tr>
						<td>'.$archetypes_data[29].'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[40].'</td>' : '').'
						<td align="center">'. round($Nurturer_return).'%</td>
					</tr>
				</tbody>
			</table>
		</div>';
		$body1 = "<br><br><p>First a little bit about each archetype.</p>" ;
		$body1 .='<div class="include_details">
			<div class="mq-archetypes-overview">
				<h3 class="clear">1. '.$archetypes_data[1].' : '.$all_values[51].' = '.round($Warrior_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[2].'" >
						<div class="clear"></div>
						'.$archetypes_data[4].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">2. '.$archetypes_data[5].' : '.$all_values[51].' = '.round($Initiator_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[6].'" >
						<div class="clear"></div>
						'.$archetypes_data[8].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">3. '.$archetypes_data[9].' : '.$all_values[51].' = '.round($Ruler_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[10].'" >
						<div class="clear"></div>
						'.$archetypes_data[12].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">4. '.$archetypes_data[13].' : '.$all_values[51].' = '.round($Apprentice_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[14].'" >
						<div class="clear"></div>
						'.$archetypes_data[16].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">5. '.$archetypes_data[17].' : '.$all_values[51].' = '.round($Maverick_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[18].'" >
						<div class="clear"></div>
						'.$archetypes_data[20].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">6. '.$archetypes_data[21].' : '.$all_values[51].' = '.round($Victim_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[22].'" >
						<div class="clear"></div>
						'.$archetypes_data[24].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">7. '.$archetypes_data[25].' : '.$all_values[51].' = '.round($Alchemist_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[26].'" >
						<div class="clear"></div>
						'.$archetypes_data[28].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">8. '.$archetypes_data[29].' : '.$all_values[51].' = '.round($Nurturer_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[30].'" >
						<div class="clear"></div>
						'.$archetypes_data[32].'
				</div>
			</div>
		</div>'; 
		
		$temp_new_txt = '<br><br>';
		if($cta_data[7] == "Yes") { 
			$temp_new_txt .= '<div class="arch_type_show benefits">
				<h4 style="margin-bottom:5px;">'.$cta_data[8].'</h4>
				<div class="mq-archetypes-overview">
					<div class="clear mq-archetypes-desc">';
					 if($cta_data[9] != ""){
						$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[9].'" style="max-width:500px;" > 
							<div class="clear"></div>';
						  } 
						$temp_new_txt .= '<p>'.$cta_data[10].'</p>
						 
						<div class="clear"></div>'; // intro 
					if($cta_data[11] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[11].'</b><br>'
						.$cta_data[12].'</p>
						<div class="clear"></div>';
					}
					if($cta_data[13] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[13].'</b><br>'
						.$cta_data[14].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[15] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[15].'</b><br>
						'.$cta_data[16].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[17] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[17].'</b><br>'
						.$cta_data[18].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[19] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[19].'</b><br>'
						.$cta_data[20].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[21] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[21].'</b><br>'
						.$cta_data[22].'</p>
						<div class="clear"></div>';
					} 
					$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[23].' </p>
						<div class="clear"></div>';
					if($cta_data[25] != ""){ 	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[25].'" ><button name="start" class="next_step lets_start" "style="background-color: rgba(0, 128, 0, 1); border: none; width: 100%; margin: 0 auto; display: flex; padding: 15px; line-height: 22px;">'.$cta_data[24].' </button></a>';
					} 	
			$temp_new_txt .= '</div>
				</div>
			</div>	';
		} 
		
		if($cta_data[26] == "Yes") { 
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show arch_type_specific">
				<h4 style="margin-bottom:5px;">'.$cta_data[27].'</h4>
				<div class="mq-archetypes-overview">
					<div class="clear mq-archetypes-desc">';		
					
						if($cta_data[28] != ""){ 
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[28].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= '<p>'.$cta_data[29].'</p>'; // intro 
							
							$temp_new_txt .= '<p class="benefits-area-p"><b>'.$archetypes_data[1].'</b><br>'.$cta_data[30].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[5].'</b><br>'.$cta_data[31].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[9].'</b><br>'.$cta_data[32].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[13].'</b><br>'.$cta_data[33].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[17].'</b><br>'.$cta_data[34].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[21].'</b><br>'.$cta_data[35].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[25].'</b><br> '.$cta_data[36].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[29].'</b><br>'.$cta_data[37].'</p>
							<div class="clear"></div>';
						 
						$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[38].' </p>
							<div class="clear"></div>';
						if($cta_data[40] != ""){ 	
							$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[40].'" ><button name="start" class="next_step lets_start" style="background-color: rgba(0, 128, 0, 1); border: none; width: 100%; margin: 0 auto; display: flex; padding: 15px; line-height: 22px;">'.$cta_data[39].' </button></a>';
						} 
				$temp_new_txt .= '</div>
				</div>
			</div>';	
		} 

		if($cta_data[41] == "Yes") { 
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show promotion">
				<h4 style="margin-bottom:5px;">'.$cta_data[42].'</h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">';
						 if($cta_data[43] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[43].'"  style="max-width:500px;"  > 
							<div class="clear"></div>';
						 } 
						$temp_new_txt .=  '<p>'.$cta_data[44].'</p>
						 
						<div class="clear"></div>'; // intro 
					if($cta_data[45] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[45].'</b><br>';
						if($cta_data[46] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[46].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[47].'</p>
						<div class="clear"></div>';
					  }  
					if($cta_data[48] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[48].'</b><br>';
						if($cta_data[49] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[49].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[50].'</p>
						<div class="clear"></div>';
					  }  
					
					if($cta_data[51] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[51].'</b><br>';
						if($cta_data[52] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[52].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[53].'</p>
						<div class="clear"></div>';
					  }  
					
 					if($cta_data[54] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[54].'</b><br>';
						if($cta_data[55] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[55].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[56].'</p>
						<div class="clear"></div>';
					  }  
					
 					if($cta_data[57] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[57].'</b><br>';
						if($cta_data[58] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[58].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[59].'</p>
						<div class="clear"></div>';
					  }  
 					if($cta_data[60] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[60].'</b><br>';
						if($cta_data[61] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[61].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[62].'</p>
						<div class="clear"></div>';
					  }  
					
 					if($cta_data[63] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[63].'</b><br>';
						if($cta_data[64] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[64].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[65].'</p>
						<div class="clear"></div>';
					  }  
					
					$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[66].' </p>
						<div class="clear"></div>';
					if($cta_data[68] != ""){  	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[68].'" ><button name="start" class="next_step lets_start" style="background-color: rgba(0, 128, 0, 1); border: none; width: 100%; margin: 0 auto; display: flex; padding: 15px; line-height: 22px;">'.$cta_data[67].' </button></a>';
					}	
				$temp_new_txt .= '</div>
				</div>
			</div>';
		}  

		if($cta_data[69] == "Yes") {
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show testimonials">
				<h4 style="margin-bottom:5px;">'.$cta_data[70].'</h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">';
					
					if($cta_data[71] != ""){
						$temp_new_txt .= '<div class="clear"></div>
						<img src="'.$cta_data[71].'"  style="max-width:500px;" > 
						<div class="clear"></div>';
					} 
					$temp_new_txt .= '<p>'.$cta_data[72].'</p>						
						<div class="clear"></div>'; // intro 

					if($cta_data[73] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[73].'</b><br>';
						if($cta_data[74] != ""){ 
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[74].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= ''.$cta_data[75].'</p>
						<div class="clear"></div>';
					 } 
					if($cta_data[76] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[76].'</b><br>';
						if($cta_data[77] != ""){ 
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[77].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= ''.$cta_data[78].'</p>
						<div class="clear"></div>';
					 } 
					if($cta_data[79] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[79].'</b><br>';
						if($cta_data[80] != ""){ 
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[80].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= ''.$cta_data[81].'</p>
						<div class="clear"></div>';
					} 
					
				  	$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[82].' </p>
						<div class="clear"></div>';
					if($cta_data[84] != ""){  	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[84].'" ><button name="start" class="next_step lets_start" style="background-color: rgba(0, 128, 0, 1); border: none; width: 100%; margin: 0 auto; display: flex; padding: 15px; line-height: 22px;">'.$cta_data[83].' </button></a>';
					} 	
			$temp_new_txt .= '</div>
				</div>
			</div>	';
		 } 

		if($cta_data[85] == "Yes") { 
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show special-offer">
				<h4 style="margin-bottom:5px;">'.$cta_data[86].'</h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">';
						if($cta_data[87] != ""){
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[87].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						 } 
							$temp_new_txt .= '<p>'.$cta_data[88].'</p>
						
						<div class="clear"></div>'; // intro 
						
					if($cta_data[89] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[89].'</b><br>'.$cta_data[90].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[91] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[91].'</b><br>'.$cta_data[92].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[93] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[93].'</b><br>'.$cta_data[94].'</p>
						<div class="clear"></div>';
					} 
					
		 			$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[95].' </p>
						<div class="clear"></div>';
					if($cta_data[97] != ""){ 	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[97].'" ><button name="start"  class="next_step lets_start" style="background-color: rgba(0, 128, 0, 1); border: none; width: 100%; margin: 0 auto; display: flex; padding: 15px; line-height: 22px;">'.$cta_data[96].' </button></a>';
					} 	
			$temp_new_txt .= '</div>
				</div>
			</div>';	
		} 

		if($cta_data[98] == "Yes") {
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show bonus-offer">
				<h4 style="margin-bottom:5px;">'.$cta_data[99].'</h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">';
					
						if($cta_data[100] != ""){
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[100].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= '<p>'.$cta_data[101].'</p> 
						
						<div class="clear"></div>';// intro 
						
					if($cta_data[102] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[102].'</b><br>'.$cta_data[103].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[104] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[104].'</b><br>'.$cta_data[105].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[106] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[106].'</b><br>'.$cta_data[107].'</p>
						<div class="clear"></div>';
					} 

   		
					$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[108].'</p>
						<div class="clear"></div>';
					if($cta_data[110] != ""){ 	
						$temp_new_txt .= '<a href="'.$cta_data[110].'" target="_blank" ><button name="start" class="next_step lets_start" >'.$cta_data[109].' </button></a>';
					}	
				$temp_new_txt .= '</div>
				</div>
			</div>';
		} 
		//$temp_new_txt .= '<a  target="_blank" href="https://search.google.com/local/writereview?placeid=ChIJV43gxZCmmkcRNBCuhZ8yec8" ><button name="start" class="next_step lets_start" "style="background-color: rgba(0, 128, 0, 1); border: none; width: 100%; margin: 0 auto; display: flex; padding: 15px; line-height: 22px;">Google Review</button></a>';
		//$temp_new_txt .= '<br><a  target="_blank" href="https://search.google.com/local/writereview?placeid=ChIJV43gxZCmmkcRNBCuhZ8yec8" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';"> Google Review  </button></a>';
		$temp_new_txt .= wpautop($email_setting['email_bottom_content']);
		$body .= $temp_new_txt;
		
		//$body .= '<br><p>'.stripslashes(wpautop($all_values[27])).'</p>';  // additional closing paragraph
		$body .= '<p>'.stripslashes($all_values[2]).' '.stripslashes($all_values[3]).'<br/>';  // Money Coach name and surname
		$body .= ''.stripslashes($all_values[7]).'<br/>';  // Money Coach address
		$body .= ''.stripslashes($all_values[9]).'<br/>';  // Money Coach email
		$body .= ''.stripslashes($all_values[8]).' ';  // Money Coach telephone
		$body .= ''.stripslashes($all_values[11]).'<br/>';  // Money Coach on-line calender
		if($all_values[12] != "")
			$body .= '<img src="'.$all_values[12].'" width="300" style="width:300px;height:auto;"> <br/>';  // Money Coach logo
		
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$headers[] = 'From: '.stripslashes($all_values[2]).' <'.stripslashes($all_values[9]).'>'; // money coach details 
		if($to != "notcaptured@nowhere.com"){
			wp_mail( $to, $subject, $body, $headers );
		}
	
				// email code for Money Coach 
		if($all_values[9] != ""){
			$to = $all_values[9]; // prospect email's address 
			$subject = 'MoneyQuiz Results for: '.$Name.' '.$Surname;
			$body = "Dear ".$all_values[2].",<br>"; 
			$body .= "<p>Here are the results from the MoneyQuiz taken by:</p>"; 
			$body .= '<div class="include_summary ">
					<table class="form-table mq-form-table-reports">
						<tbody>
							<tr>
								<th style="min-width:100px;text-align:left;" >Date</th>
								<th style="min-width:100px;text-align:left;">First</th>
								<th style="min-width:100px;text-align:left;">Surname</th>
								<th style="min-width:120px;text-align:left;">Email</th>
								<th style="min-width:100px;text-align:left;">Telephone</th>
								<th style="min-width:100px;text-align:left;">Newsletter</th>
								<th style="min-width:100px;text-align:left;">Consultation</th>
							</tr>
							<tr>
								<td>'.date('d-M-y').'</td>
								<td>'.$Name.'</td>
								<td>'.$Surname.'</td>
								<td>'.$Email.'</td>
								<td>'.$Telephone.'</td>
								<td>'.$Newsletter.'</td>
								<td>'.$Consultation.'</td>
							</tr>
						</tbody>
					</table>
				</div>';
			$body .=  '<div class="include_summary ">
			<h3 class="clear">Summary</h3> 
				<table class="form-table mq-form-table-reports">
					<tbody>
						<tr>
							<th style="min-width:200px;text-align:left;" >Archetype</th>
							<th style="min-width:200px;text-align:left;">'.date('d-M-y').'</th>
						</tr>
						<tr>
							<td>'.$archetypes_data[1].'</td>
							<td>'. round($Warrior_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[5].'</td>
							<td>'. round($Initiator_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[9].'</td>
							<td>'. round($Ruler_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[13].'</td>
							<td>'. round($Apprentice_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[17].'</td>
							<td>'. round($Maverick_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[21].'</td>
							<td>'. round($Victim_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[25].'</td>
							<td>'. round($Alchemist_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[29].'</td>
							<td>'. round($Nurturer_return).'%</td>
						</tr>
						
					</tbody>
				</table>
			</div>';
			$body .= '<div class="include_details">
				<h3>Detailed Scores</h3>
				<table class="form-table mq-form-table-reports ">
					<tbody>
						<tr>
							<th style="min-width:200px;text-align:left;">ID</th>
							<th style="min-width:200px;text-align:left;">Key Phrase</th>
							<th style="min-width:150px;text-align:left;">Archetype</th>
							<th style="min-width:150px;text-align:left;">'.date('d-M-y').'</th>
						</tr>	
						'.$table_row.'
					</tbody>
				</table>
			</div>';
		
			$body .= '<br><p>Thank you for using the MoneyQuiz.</p>';
			$body .= '<p>Powered by Business Insights Group AG<br> Zurich, Switzerland</p>';
			$body .= '<img src="'.plugins_url('assets/images/money_coach_signature.png', __FILE__).'" > <br/>';  // Money Coach logo
			
			$headers = array('Content-Type: text/html; charset=UTF-8');
			$headers[] = 'From: Money Quiz <no-reply@101businessinsights.com>';  
			
			wp_mail( $to, $subject, $body, $headers );
		}	

	// CODE FOR MAILERLITE API	
		$select_feilds_arr = array();
		try {
			$Newsletter_val = 0;
			if($all_values[75] == "Only if Newsletter Selected" && $Newsletter == "Yes"){
				$Newsletter_val = 1;
			}
			if($all_values[75] == "All Records"){
				$Newsletter_val = 1;
			}
			if($all_values[55] != "" && $all_values[57] != "" && $all_values[60] != "" && $Newsletter_val == 1){ 
				 
				require_once('vendor/autoload.php');
			 
				$mailerliteClient = new \MailerLiteApi\MailerLite($all_values[57]);
				$subscribersApi = $mailerliteClient->subscribers();
				$groupId = $all_values[60];

				$subscriber = $subscribersApi->find($newmal);
				 
	
				$fields = array();
				if($all_values[61] == "Yes")
					$fields[$all_values[62]] = $Name;
				if($all_values[63] == "Yes")
					$fields[$all_values[64]] = $Surname;
				if($all_values[65] == "Yes")
					$fields[$all_values[66]] = $newmal;
				if($all_values[67] == "Yes")
					$fields[$all_values[68]] = $Telephone;
				if($all_values[69] == "Yes")
					$fields[$all_values[70]] = $Newsletter;
				if($all_values[71] == "Yes")
					$fields[$all_values[72]] = $Consultation;
				if($all_values[73] == "Yes")
					$fields[$all_values[74]] = 'MoneyQuiz';
				
				if(isset($subscriber->error->code)){ // subscriber not found, so create new one 
					$subscriber_data = [
					'email' => $newmal,
					'name' => $Name,
					'fields'=> $fields
					];
					 
					$groupsApi = $mailerliteClient->groups();
					$addedSubscriber = $groupsApi->addSubscriber($groupId, $subscriber_data); // returns added subscriber
					 
				}else{ // subscriber found, so update new one 
					unset($fields[$all_values[66]]);
					$subscriber_data = [
					'name' => $Name,
					'fields'=> $fields
					];
					$addedSubscriber = $subscribersApi->update($newmal,$subscriber_data);
					
				} 
				
			}
		}
		catch(Exception $e) {
			echo 'Message: ' .$e->getMessage();
			/* echo '<br> ************ <br> ';
			print_r($e);
			die(); */
		}		
	// end code for mailerlite	
	//	die();
		ob_end_flush();
		$url = get_permalink()."?result=2&tid=".$taken_id."&prospect=".$prospect_id;
		return "<script>window.location='".$url."';</script>";
		exit;
		$save_msg = "<div class='data_saved'>Thank you for taking the MoneyQuiz. <a href='".get_permalink()."' > click here to go to quiz page</a></div>";	
	}
	if(isset($_POST['prospect_action']) && $_POST['prospect_action'] == "submit"){

		/*** Check Google Recaptcha  */
		$recaptcha_enable = $recaptcha_setting[1];   // 'on'
		$recaptcha_type   = $recaptcha_setting[2];   // 'v3'
		$siteKey          = $recaptcha_setting[3];
		$secretKey        = $recaptcha_setting[4];

		$responseToken = $_POST['g-recaptcha-response']; // Token sent from client
		$userIP = $_SERVER['REMOTE_ADDR']; // Optional: get user IP
	
		
		if ($recaptcha_enable == 'on' && $recaptcha_type == 'v3' && !empty($secretKey)) {
			
			// Prepare POST request to Google's API
			$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
			$data = [
				'secret' => $secretKey,
				'response' => $responseToken,
				'remoteip' => $userIP
			];
			
			$options = [
				'http' => [
					'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
					'method'  => 'POST',
					'content' => http_build_query($data),
				]
			];
			
			$context  = stream_context_create($options);
			$result = file_get_contents($verifyUrl, false, $context);
			$responseData = json_decode($result);
			
			if ($responseData->success) {
				// ✅ reCAPTCHA passed
				
				// Optional: additional checks for reCAPTCHA v3
				if (isset($responseData->score)) {
					if ($responseData->score >= 0.3) {
						// Considered human
						// Proceed with your logic
					} else {
						// Low score, possible bot
						echo $responseData->score;
						echo 'reCAPTCHA score too low.';
						exit;
					}
				} else {
					// It's v2 or no score provided
					// Proceed with form processing
				}
			
			} else {
				// ❌ reCAPTCHA failed
				echo 'reCAPTCHA verification failed.';
				exit;
			}
		}
		
		
		?>
		<style>
			 /*--- Loader Text */
			 #mfm-loading {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: #fbf5f5;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        font-family: Arial, sans-serif;
        color: #333;
        }
    
        /* Rotating image */
        .mfm-rotating-image {
        width: 60px;
        height: 60px;
        animation: spin 1s linear infinite;
        margin-bottom: 10px; /* Space between the image and text */
        }
    
        /* Spin animation */
        @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
        }
    /*------------End-----------*/
		</style>
	<div id='mfm-loading'>
	<img src='https://mindfulmoneycoaching.online/wp-content/plugins/moneyquiz/assets/images/mind-full-preloader.webp' alt='Loading' class='mfm-rotating-image'>
	<div id="mfm-loading-text"><h1> Thank you for your patience, we're Almost done. You're results will soon be displayed here.</hi></div>
  </div>		
  <?php
		
		if(isset($all_values[83]) && $all_values[83] == 'Yes'){ 
			// insert prospect details 
			$prospect_data =  $_POST['prospect_data'];
			$Name = sanitize_text_field( $prospect_data['Name'], true );
			$Surname = sanitize_text_field( $prospect_data['Surname'], true );
			$Email = sanitize_text_field( $prospect_data['Email'], true );
			$Telephone = sanitize_text_field( $prospect_data['Telephone'], true );
			$Newsletter = sanitize_text_field( $prospect_data['Newsletter'], true );
			$Consultation = sanitize_text_field( $prospect_data['Consultation'], true );
			$prospect_Email = $Email;
			$newmal = sanitize_text_field( $prospect_data['Email'], true );
		}else{
			$Name = "Not Captured";
			$Surname = "Not Captured";
			$Email = "notcaptured@nowhere.com";
			$Telephone = "";
			$Newsletter = "";
			$Consultation = "";
			$prospect_Email = "notcaptured@nowhere.com";
			$newmal = sanitize_text_field( $prospect_data['Email'], true );
		}
		
		// if email already exists 
		$results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );
		if($results){
			$prospect_id = $results->Prospect_ID;
		

			$wpdb->update( 
				$table_prefix.TABLE_MQ_PROSPECTS, 
				array( 
					'Newsletter' => $Newsletter,
					'Consultation' => $Consultation,
				), 
				array( 'Prospect_ID' => $prospect_id )
			);
		}else{
			$data_insert = array( 
				'Name' => $Name, 
				'Surname' => $Surname, 
				'Email' => $Email, 
				'Telephone' => $Telephone, 
				'Newsletter' => $Newsletter, 
				'Consultation' => $Consultation, 
			);		
			$wpdb->insert( 
					$table_prefix.TABLE_MQ_PROSPECTS,
					$data_insert
				);
			$prospect_id = $wpdb->insert_id; // prospect id
			if ($wpdb->last_error) {
				echo 'Database error: ' . $wpdb->last_error;
			}
		
		}
	 
		
		// insert data into quiz taken table
		$wpdb->insert( 
				$table_prefix.TABLE_MQ_TAKEN,
				array(
					'Prospect_ID'=>$prospect_id,
					'Date_Taken'=>date('d-M-y'),
					'Quiz_Length'=>$_POST['mq_version_selected'],
				)
			);
		$taken_id = $wpdb->insert_id; // result taken id
		// save taken id in option table to check how many answers options selected 
		if(isset($all_values[76])) {
			add_option('mq_money_coach_plugin_answer_options_selected_'.$taken_id, $all_values[76] );
		}else{
			add_option('mq_money_coach_plugin_answer_options_selected_'.$taken_id, 'Five' );	
		}
		
		// save results in table 
		foreach($_POST['question_data'] as $key_id=>$new_val){
			$new_val1 = sanitize_text_field( $new_val, true );
			$wpdb->insert( 
				$table_prefix.TABLE_MQ_RESULTS,
				array(
					'Prospect_ID'=>$prospect_id,
					'Taken_ID'=>$taken_id,
					'Master_ID'=>$key_id,
					'Score'=>$new_val1,
				)
			);
		}
		
		// fetch data for archetypes for email
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_ARCHETYPES."" ;
		$rows = $wpdb->get_results($sql);
		$archetypes_data= array();
		foreach($rows as $row){
			$archetypes_data[$row->ID] = stripslashes($row->Value);
		}
		$tid = $taken_id;
		$prospect = $prospect_id;
		$sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question, mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE  mq_r.Prospect_ID=".$prospect." and mq_r.Taken_ID IN($tid) ORDER BY mq_r.Taken_ID ASC ";
		$sql_rows = $wpdb->get_results($sql_qry, OBJECT);
		 
		
		$Alchemist_score = $Alchemist_question = $Victim_score = $Victim_question = $Maverick_score = $Maverick_question = $Apprentice_score = $Apprentice_question = $Nurturer_score = $Nurturer_question = $Ruler_score = $Ruler_question = $Warrior_score = $Warrior_question = $Initiator_score = $Initiator_question = 0;
		$Alchemist_return =$Victim_return =$Maverick_return =$Apprentice_return =$Ruler_return =$Nurturer_return =$Initiator_return =$Warrior_return = 0;	
		$detailed_summary_rows = "";
		$new_arr = 1;
		$new_tr = 0;
		$table_row = '';
		$Alchemist_return_arr = $Victim_return_arr = $Maverick_return_arr = $Apprentice_return_arr = $Ruler_return_arr = $Warrior_return_arr = $Nurturer_return_arr = $Iniatiator_return_arr = array();	
		if($sql_rows){
			foreach($sql_rows as $row){
				$str = '<tr><td>'.$row->ID_Unique.'</td><td>'.$row->Question.'</td><td>'.$archetypes_data[$row->Archetype].'</td>';
				$str .= '<td>'.$row->Score.'</td>';
				if($row->Archetype == 1) //Warrior
					$Warrior_return_arr[$tid] += $row->Score;
				if($row->Archetype == 5) // Iniatiator
					$Iniatiator_return_arr[$tid] += $row->Score;
				if($row->Archetype == 9) // Ruler
					$Ruler_return_arr[$tid] += $row->Score;
				if($row->Archetype == 13) // Apprentice
					$Apprentice_return_arr[$tid] += $row->Score;
				if($row->Archetype == 17) // Maverick
					$Maverick_return_arr[$tid] += $row->Score;
				if($row->Archetype == 21) //Victim
					$Victim_return_arr[$tid] += $row->Score;
				if($row->Archetype == 25) //Alchemist
					$Alchemist_return_arr[$tid] += $row->Score;
				if($row->Archetype == 29) // Nurturer
					$Nurturer_return_arr[$tid] += $row->Score;
				$str .= '</tr>';
				$table_row .= $str;
				switch($row->Archetype){
					case 1:
						$Warrior_question++;  
					 break;
					case 5:
						$Initiator_question++;  
					 break;
					case 9:
						$Ruler_question++;  
					 break;
					case 13:
						$Apprentice_question++;  
					 break;
					case 17:
						$Maverick_question++;  
					 break;
					case 21:
						$Victim_question++;  
					 break;
					case 25:
						$Alchemist_question++;  
					 break;
					case 29:
						$Nurturer_question++;  
					 break;
				}
				$new_tr++;
			}
			$new_arr++;
		}
		
		// code to send email to prospect and money coach
		foreach($Warrior_return_arr as $Warrior_score){
			$Warrior_return = get_percentage($Warrior_question,$Warrior_score);
		}
		foreach($Iniatiator_return_arr as $Initiator_score){
			$Initiator_return = get_percentage($Initiator_question,$Initiator_score);
		}		
		foreach($Ruler_return_arr as $ques_score){
			$Ruler_return = get_percentage($Ruler_question,$ques_score);
		}	
		foreach($Apprentice_return_arr as $ques_score){
			$Apprentice_return = get_percentage($Apprentice_question,$ques_score);
		}
		foreach($Maverick_return_arr as $ques_score){
			$Maverick_return = get_percentage($Maverick_question,$ques_score);
		}
		foreach($Victim_return_arr as $ques_score){
			$Victim_return = get_percentage($Victim_question,$ques_score);
		}
		foreach($Alchemist_return_arr as $ques_score){
			$Alchemist_return = get_percentage($Alchemist_question,$ques_score);
		}		
		foreach($Nurturer_return_arr as $ques_score){
			$Nurturer_return = get_percentage($Nurturer_question,$ques_score);
		} 		
		
		$to = $Email; // prospect email's address 
		//$subject = 'MoneyQuiz Results for: '.$Name.' '.$Surname;
		$subject = $email_setting['email_subject'];
		$body = $email_setting['greeting']." ".$Name.",<br>"; 
		//$body .= "<p>Thank you for taking the MoneyQuiz. Here are your results.</p>"; 
		$body .= "<p>".wpautop($email_setting['email_thank_content'])."</p>"; 
		if($all_values[31] == 'Yes') // if prospect email show header yes 
			//$body .= $header_image;
			if($all_values[31] == 'Yes'){
				//$header_image_email = "<div class='mq-header-image-container' ><img src='".$rs->Value."' class='mq-header-image' width='893' style='width:941px;'/></div>";
				$body .= $header_image_email;
			}	
			
		//$body .= '<p>'.stripslashes($all_values[26]).'</p>';  // additional opening paragraph
		$body .=  '<div class="include_summary ">
			<table class="form-table mq-form-table-reports" style=" border-collapse: separate; border-spacing: 0 1em;" cellpadding="10">
				<tbody>
					<tr>
						<th style="min-width:200px;text-align:left;" >Archetype</th>
						'.($all_values[49] == 'Yes' ? '<th style="min-width:200px;text-align:center;">'.$all_values[50].'</th>' : '').'
						<th style="min-width:200px;text-align:center;">'.$all_values[51].'</th>
					</tr>
					<tr style="background-color:#e5e5e5;">
						<td><strong style="font-size:16px">'.$archetypes_data[1].'</strong></strong><p style="font-size:13px;">'.$arch_tag_line[1].'</p>'.'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[33].'</td>' : '').'
						<td align="center">'. round($Warrior_return).'%</td>
					</tr>
					<tr style="background-color:#c9c8bc55;">
						<td><strong style="font-size:16px">'.$archetypes_data[5].'</strong><p style="font-size:13px;">'.$arch_tag_line[2].'</p>'.'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[34].'</td>' : '').'
						<td align="center">'. round($Initiator_return).'%</td>
					</tr>
					<tr style="background-color:#e5e5e5;"> 
						<td ><strong style="font-size:16px">'.$archetypes_data[9].'</strong><p style="font-size:13px;">'.$arch_tag_line[3].'</p>'.'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[35].'</td>' : '').'
						<td align="center">'. round($Ruler_return).'%</td>
					</tr>
					<tr style="background-color:#c9c8bc55;">
						<td><strong style="font-size:16px">'.$archetypes_data[13].'</strong><p style="font-size:13px;">'.$arch_tag_line[4].'</p>'.'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[36].'</td>' : '').'
						<td align="center">'. round($Apprentice_return).'%</td>
					</tr>
					<tr style="background-color:#e5e5e5;">
						<td><strong style="font-size:16px">'.$archetypes_data[17].'</strong><p style="font-size:13px;">'.$arch_tag_line[5].'</p>'.'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[37].'</td>' : '').'
						<td align="center">'. round($Maverick_return).'%</td>
					</tr>
					<tr style="background-color:#c9c8bc55;">
						<td><strong style="font-size:16px">'.$archetypes_data[21].'</strong><p style="font-size:13px;">'.$arch_tag_line[6].'</p>'.'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[38].'</td>' : '').'
						<td align="center">'. round($Victim_return).'%</td>
					</tr>
					<tr style="background-color:#e5e5e5;">
						<td><strong style="font-size:16px">'.$archetypes_data[25].'</strong><p style="font-size:13px;">'.$arch_tag_line[7].'</p>'.'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[39].'</td>' : '').'
						<td align="center">'. round($Alchemist_return).'%</td>
					</tr>
					<tr style="background-color:#c9c8bc55;">
						<td><strong style="font-size:16px">'.$archetypes_data[29].'</strong><p style="font-size:13px;">'.$arch_tag_line[8].'</p>'.'</td>
						'.($all_values[49] == 'Yes' ? '<td align="center">'.$archetypes_data[40].'</td>' : '').'
						<td align="center">'. round($Nurturer_return).'%</td>
					</tr>
				</tbody>
			</table>
		</div>';
		$body1 = "<br><br><p>First a little bit about each archetype.</p>" ;
		$body1 .='<div class="include_details">
			<div class="mq-archetypes-overview">
				<h3 class="clear">1. '.$archetypes_data[1].' : '.$all_values[51].' = '.round($Warrior_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[2].'" >
						<div class="clear"></div>
						'.$archetypes_data[4].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">2. '.$archetypes_data[5].' : '.$all_values[51].' = '.round($Initiator_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[6].'" >
						<div class="clear"></div>
						'.$archetypes_data[8].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">3. '.$archetypes_data[9].' : '.$all_values[51].' = '.round($Ruler_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[10].'" >
						<div class="clear"></div>
						'.$archetypes_data[12].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">4. '.$archetypes_data[13].' : '.$all_values[51].' = '.round($Apprentice_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[14].'" >
						<div class="clear"></div>
						'.$archetypes_data[16].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">5. '.$archetypes_data[17].' : '.$all_values[51].' = '.round($Maverick_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[18].'" >
						<div class="clear"></div>
						'.$archetypes_data[20].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">6. '.$archetypes_data[21].' : '.$all_values[51].' = '.round($Victim_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[22].'" >
						<div class="clear"></div>
						'.$archetypes_data[24].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">7. '.$archetypes_data[25].' : '.$all_values[51].' = '.round($Alchemist_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[26].'" >
						<div class="clear"></div>
						'.$archetypes_data[28].'
				</div>
			</div>
			<div class="mq-archetypes-overview">
				<h3 class="clear">8. '.$archetypes_data[29].' : '.$all_values[51].' = '.round($Nurturer_return).'% </h3> 
				<div class="clear mq-archetypes-desc">
					<img src="'.$archetypes_data[30].'" >
						<div class="clear"></div>
						'.$archetypes_data[32].'
				</div>
			</div>
		</div>'; 
		
		$temp_new_txt = '<br><br>';
		if($cta_data[7] == "Yes" && 6<5) { 
			$temp_new_txt .= '<div class="arch_type_show benefits">
				<h4 style="margin-bottom:5px;">'.$cta_data[8].'</h4>
				<div class="mq-archetypes-overview">
					<div class="clear mq-archetypes-desc">';
					 if($cta_data[9] != ""){
						$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[9].'" style="max-width:500px;" > 
							<div class="clear"></div>';
						  } 
						$temp_new_txt .= '<p>'.$cta_data[10].'</p>
						 
						<div class="clear"></div>'; // intro 
					if($cta_data[11] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[11].'</b><br>'
						.$cta_data[12].'</p>
						<div class="clear"></div>';
					}
					if($cta_data[13] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[13].'</b><br>'
						.$cta_data[14].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[15] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[15].'</b><br>
						'.$cta_data[16].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[17] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[17].'</b><br>'
						.$cta_data[18].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[19] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[19].'</b><br>'
						.$cta_data[20].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[21] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[21].'</b><br>'
						.$cta_data[22].'</p>
						<div class="clear"></div>';
					} 
					$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[23].' </p>
						<div class="clear"></div>';
					if($cta_data[25] != ""){ 	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[25].'" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$cta_data[24].' </button></a>';
					} 	
			$temp_new_txt .= '</div>
				</div>
			</div>	';
		} 
		
		if($cta_data[26] == "Yes"  && 6<5) { 
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show arch_type_specific">
				<h4 style="margin-bottom:5px;">'.$cta_data[27].'</h4>
				<div class="mq-archetypes-overview">
					<div class="clear mq-archetypes-desc">';		
					
						if($cta_data[28] != ""){ 
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[28].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= '<p>'.$cta_data[29].'</p>'; // intro 
							
							$temp_new_txt .= '<p class="benefits-area-p"><b>'.$archetypes_data[1].'</b><br>'.$cta_data[30].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[5].'</b><br>'.$cta_data[31].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[9].'</b><br>'.$cta_data[32].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[13].'</b><br>'.$cta_data[33].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[17].'</b><br>'.$cta_data[34].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[21].'</b><br>'.$cta_data[35].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[25].'</b><br> '.$cta_data[36].'</p>
							<div class="clear"></div>
						 
							<p class="benefits-area-p"><b>'.$archetypes_data[29].'</b><br>'.$cta_data[37].'</p>
							<div class="clear"></div>';
						 
						$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[38].' </p>
							<div class="clear"></div>';
						if($cta_data[40] != ""){ 	
							$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[40].'" ><button name="start" class="next_step lets_start" style=" color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$cta_data[39].' </button></a>';
						} 
				$temp_new_txt .= '</div>
				</div>
			</div>';	
		} 

		if($cta_data[41] == "Yes"  && 6<5) { 
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show promotion">
				<h4 style="margin-bottom:5px;">'.$cta_data[42].'</h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">';
						 if($cta_data[43] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[43].'"  style="max-width:500px;"  > 
							<div class="clear"></div>';
						 } 
						$temp_new_txt .=  '<p>'.$cta_data[44].'</p>
						 
						<div class="clear"></div>'; // intro 
					if($cta_data[45] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[45].'</b><br>';
						if($cta_data[46] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[46].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[47].'</p>
						<div class="clear"></div>';
					  }  
					if($cta_data[48] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[48].'</b><br>';
						if($cta_data[49] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[49].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[50].'</p>
						<div class="clear"></div>';
					  }  
					
					if($cta_data[51] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[51].'</b><br>';
						if($cta_data[52] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[52].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[53].'</p>
						<div class="clear"></div>';
					  }  
					
 					if($cta_data[54] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[54].'</b><br>';
						if($cta_data[55] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[55].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[56].'</p>
						<div class="clear"></div>';
					  }  
					
 					if($cta_data[57] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[57].'</b><br>';
						if($cta_data[58] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[58].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[59].'</p>
						<div class="clear"></div>';
					  }  
 					if($cta_data[60] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[60].'</b><br>';
						if($cta_data[61] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[61].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[62].'</p>
						<div class="clear"></div>';
					  }  
					
 					if($cta_data[63] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[63].'</b><br>';
						if($cta_data[64] != ""){  
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[64].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= $cta_data[65].'</p>
						<div class="clear"></div>';
					  }  
					
					$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[66].' </p>
						<div class="clear"></div>';
					if($cta_data[68] != ""){  	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[68].'" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$cta_data[67].' </button></a>';
					}	
				$temp_new_txt .= '</div>
				</div>
			</div>';
		}  

		if($cta_data[69] == "Yes" && 6<5) {
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show testimonials">
				<h4 style="margin-bottom:5px;">'.$cta_data[70].'</h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">';
					
					if($cta_data[71] != ""){
						$temp_new_txt .= '<div class="clear"></div>
						<img src="'.$cta_data[71].'"  style="max-width:500px;" > 
						<div class="clear"></div>';
					} 
					$temp_new_txt .= '<p>'.$cta_data[72].'</p>						
						<div class="clear"></div>'; // intro 

					if($cta_data[73] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[73].'</b><br>';
						if($cta_data[74] != ""){ 
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[74].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= ''.$cta_data[75].'</p>
						<div class="clear"></div>';
					 } 
					if($cta_data[76] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[76].'</b><br>';
						if($cta_data[77] != ""){ 
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[77].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= ''.$cta_data[78].'</p>
						<div class="clear"></div>';
					 } 
					if($cta_data[79] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[79].'</b><br>';
						if($cta_data[80] != ""){ 
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[80].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= ''.$cta_data[81].'</p>
						<div class="clear"></div>';
					} 
					
				  	$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[82].' </p>
						<div class="clear"></div>';
					if($cta_data[84] != ""){  	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[84].'" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$cta_data[83].' </button></a>';
					} 	
			$temp_new_txt .= '</div>
				</div>
			</div>	';
		 } 

		if($cta_data[85] == "Yes"  && 6<5) { 
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show special-offer">
				<h4 style="margin-bottom:5px;">'.$cta_data[86].'</h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">';
						if($cta_data[87] != ""){
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[87].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						 } 
							$temp_new_txt .= '<p>'.$cta_data[88].'</p>
						
						<div class="clear"></div>'; // intro 
						
					if($cta_data[89] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[89].'</b><br>'.$cta_data[90].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[91] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[91].'</b><br>'.$cta_data[92].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[93] != ""){
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[93].'</b><br>'.$cta_data[94].'</p>
						<div class="clear"></div>';
					} 
					
		 			$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[95].' </p>
						<div class="clear"></div>';
					if($cta_data[97] != ""){ 	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[97].'" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$cta_data[96].' </button></a>';
					} 	
			$temp_new_txt .= '</div>
				</div>
			</div>';	
		} 

		if($cta_data[98] == "Yes"  && 6<5) {
			$temp_new_txt .= '<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show bonus-offer">
				<h4 style="margin-bottom:5px;">'.$cta_data[99].'</h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">';
					
						if($cta_data[100] != ""){
							$temp_new_txt .= '<div class="clear"></div>
							<img src="'.$cta_data[100].'"  style="max-width:500px;" > 
							<div class="clear"></div>';
						} 
						$temp_new_txt .= '<p>'.$cta_data[101].'</p> 
						
						<div class="clear"></div>';// intro 
						
					if($cta_data[102] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[102].'</b><br>'.$cta_data[103].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[104] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[104].'</b><br>'.$cta_data[105].'</p>
						<div class="clear"></div>';
					} 
					if($cta_data[106] != ""){ 
						$temp_new_txt .= '<p class="benefits-area-p"><b>'.$cta_data[106].'</b><br>'.$cta_data[107].'</p>
						<div class="clear"></div>';
					} 

   		
					$temp_new_txt .= '<p class="benefits-area-closing">'.$cta_data[108].'</p>
						<div class="clear"></div>';
					if($cta_data[110] != ""){ 	
						$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[110].'"  target="_blank" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$cta_data[109].' </button></a>';
					}	
				$temp_new_txt .= '</div>
				</div>
			</div>';
		} 
		$temp_new_txt .= "<div style='max-width:891px'>".wpautop($email_setting['email_bottom_content'])."</div>";
		$temp_new_txt .= "<div style='max-width:891px'>".wpautop($email_setting['7'])."</div>";
		
		//$temp_new_txt .= '<a  style="color:#008000;border:none;" target="_blank" href="'.$cta_data[25].'"  target="_blank" >'.$email_setting['action_button_link'].'</a><br>';
		//$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[25].'"  target="_blank" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$email_setting['action_button_link'].' </button></a><br>';
		//$temp_new_txt .= "<br>".$email_setting['email_closing_content']."<br>";
		//$temp_new_txt .= '<a  target="_blank" href="'.$cta_data[25].'"  target="_blank" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$email_setting['action_button_link'].' </button></a><br>';
		//$temp_new_txt .= '<br><a  target="_blank" href="https://search.google.com/local/writereview?placeid=ChIJV43gxZCmmkcRNBCuhZ8yec8" ><button name="start" class="next_step lets_start" style="color:#fff; height:'.$all_values[95].';background: '.$all_values[44].';">'.$email_setting['review_button_text'].'</button></a>';
		//$temp_new_txt .= '<a  target="_blank" href="https://search.google.com/local/writereview?placeid=ChIJV43gxZCmmkcRNBCuhZ8yec8" ><button name="start" class="next_step lets_start" "style="background-color: rgba(0, 128, 0, 1); border: none; width: 100%; margin: 0 auto; display: flex; padding: 15px; line-height: 22px;">Google Review</button></a>';
		$body .= $temp_new_txt;
		//$body .= '<br><p>'.stripslashes($all_values[27]).'</p>';  // additional closing paragraph
		// Email Footer
		//$body .= '<p>'.stripslashes($all_values[2]).' '.stripslashes($all_values[3]).'<br/>';  // Money Coach name and surname
		//$body .= ''.stripslashes($all_values[7]).'<br/>';  // Money Coach address
		//$body .= ''.stripslashes($all_values[9]).'<br/>';  // Money Coach email
		//$body .= ''.stripslashes($all_values[8]).' ';  // Money Coach telephone
		//$body .= ''.stripslashes($all_values[11]).'<br/>';  // Money Coach on-line calender
		
		ob_start();
		?>
		<table>
  <tr>
    <td style="padding-right: 10px;">
      <img src="<?php echo $signature_email[2]; ?>" alt="Ilana Jankowitz" width="200" height="200" style="border-radius: 12px; display: block;">
      <div style="text-align: center; margin-top: 20px;">
        <table align="center" cellspacing="0" cellpadding="0" border="0" style="margin: auto;">
          <tr>
		  <?php
			if(!empty($signature_email[3])){
			?>
            <td>
              <a href="<?php echo $signature_email[3]; ?>" style="
					display: block;
					text-align: center;
					vertical-align: bottom;
					background-color: #1a78f2;
					padding: 6px;
					line-height: 12px;
					border-radius: 5px;
				">
                <img src="<?php echo plugins_url('assets/images/facebook_icon.png', __FILE__); ?>" style="height:20px;width:20px;">
              </a>
            </td>
			<?php
			}
			if(!empty($signature_email[4])){
		
			if(!empty($signature_email[3])){ ?>
			<td style="width: 20px;"></td>
			<?php } ?>
            <td>
              <a href="<?php echo $signature_email[4]; ?>" style="
					display: block;
					text-align: center;
					vertical-align: bottom;
					background-color: #2867b2;
					padding: 6px;
					line-height: 12px;
					border-radius: 5px;

				">
                <img src="<?php echo plugins_url('assets/images/linkin.png', __FILE__); ?>" style="height:20px;width:20px;">
              </a>
            </td>
			<?php
			 }
			?>
			<?php
			
			if(!empty($signature_email[5])){

			if(!empty($signature_email[4])){ ?>
            <td style="width: 20px;"></td>
			<?php } ?>
            <td>
              <a href="<?php echo $signature_email[5]; ?>" style="
				display: block;
				text-align: center;
				vertical-align: bottom;
				background-color: #f00073;
				padding: 6px;
				line-height: 12px;
				border-radius: 5px;
			">
                <img src="<?php echo plugins_url('assets/images/instagram.png', __FILE__); ?>" style="height:20px;width:20px;">
              </a>
            </td>

			<?php
			}
			?>
          </tr>
        </table>
		<a  href="<?php echo $all_values[11];?>" style="margin-top:15px;text-decoration: none; color: #fff; background-color: #008000; border: none; display: inline-block; padding: 6px; line-height: 22px; text-align: center;" target="_blank" rel="noopener">Book the Calendly call</a> 
     </div>
    </td>
    <td>
      <h2 style="font-weight: 700; font-size: 18px; line-height: 28px; margin-top: 0; margin-bottom: 0; color: #f00073;"><?php echo stripslashes($all_values[2]); ?> <?php echo stripslashes($all_values[3]); ?></h2>
      <hr>
      <p style="font-size: 16px; line-height: 26px; margin: 0;"><?php echo $signature_email[1]; ?></p>
      
        <table cellspacing="" cellpadding="0" border="0">
		<?php
			if(!empty($all_values[8])){ ?>
			<tr>
				<td style="
				background-color: #f00073;
				height: 30px;
				width: 30px;
				border-radius: 50%;
				text-align: center;
				display: inline-block;
				display: inline-block;
				line-height: 36px;
				margin-right:6px;
				">
				<img src="<?php echo plugins_url('assets/images/phone_icon.png', __FILE__); ?>" width="15" height="15">
				</td>
				<td>
				<p style="font-size: 14px; line-height: 25px; color: #000; margin: 0;">
					<a href="tel:<?php echo $all_values[8]; ?>" style="color: #000; text-decoration: none;"><?php echo $all_values[8]; ?></a>
				</p>
				</td>
          	</tr>
		  <?php 
		  	}
		  
		  if(!empty($all_values[9])){ ?>
			<tr>
				<td 
				style="
					background-color: #f00073;
					height: 30px;
					width: 30px;
					border-radius: 50%;
					text-align: center;
					display: inline-block;
					display: inline-block;
					line-height: 36px;
				">
				<img src="<?php echo plugins_url('assets/images/email_icon.png', __FILE__); ?>" width="15" height="15">
				</td>
				<td>
				<p style="font-size: 14px; line-height: 25px; color: #000; margin: 0;">
					<a href="mailto:<?php echo $all_values[9]; ?>" style="color: #000; text-decoration: none;"><?php echo $all_values[9]; ?></a>
				</p>
				</td>
			</tr>

		  <?php
			 } 
			 
		if(!empty($all_values[10])){ ?>	 
          <tr>
            <td 
			style="
			background-color: #f00073;
			height: 30px;
			width: 30px;
			border-radius: 50%;
			text-align: center;
			display: inline-block;
			display: inline-block;
			line-height: 36px;
						
			">
              <img src="<?php echo plugins_url('assets/images/website_icon.png', __FILE__); ?>" width="15" height="15">
            </td>
            <td>
              <p style="font-size: 14px; line-height: 25px; color: #000; margin: 0;">
                <a href="<?php echo $all_values[10]; ?>" style="color: #000; text-decoration: none;"><?php echo $all_values[10]; ?></a>
              </p>
            </td>
          </tr>
		 <?php } ?>	
        </table>
      <?php
	  if($signature_email[7]=="Yes" && !empty($all_values[34])){ ?>
      <div style="padding-top: 10px;">
        <img src="<?php echo $all_values[34]; ?>" alt="Take the Money Quiz" width="300" height="150" style="border-radius: 12px; display: block;">
      </div>
	<?php } ?>
    </td>
  </tr>
</table>
';
<?php

$bufferedContent = ob_get_clean();
$body .= $bufferedContent;
		if($all_values[12] != "")
			//$body .= '<img src="'.$all_values[12].'" width="130" style="width:130px;height:auto;margin-top:30px;"> <br/>';  // Money Coach logo
		
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$headers[] = 'From: '.stripslashes($all_values[2]).' <'.stripslashes($all_values[9]).'>'; // money coach details 
		if($to != "notcaptured@nowhere.com"){
			wp_mail( $to, $subject, $body, $headers );
		}
		 
	 
	// email code for Money Coach 
		if($all_values[9] != ""){
			$to = $all_values[9]; // prospect email's address 
			$subject = 'MoneyQuiz Results for: '.$Name.' '.$Surname;
			$body = "Dear ".$all_values[2].",<br>"; 
			$body .= "<p>Here are the results from the MoneyQuiz taken by:</p>"; 
			$body .= '<div class="include_summary ">
					<table class="form-table mq-form-table-reports">
						<tbody>
							<tr>
								<th style="min-width:100px;text-align:left;" >Date</th>
								<th style="min-width:100px;text-align:left;">First</th>
								<th style="min-width:100px;text-align:left;">Surname</th>
								<th style="min-width:120px;text-align:left;">Email</th>
								<th style="min-width:100px;text-align:left;">Telephone</th>
								<th style="min-width:100px;text-align:left;">Newsletter</th>
								<th style="min-width:100px;text-align:left;">Consultation</th>
							</tr>
							<tr>
								<td>'.date('d-M-y').'</td>
								<td>'.$Name.'</td>
								<td>'.$Surname.'</td>
								<td>'.$Email.'</td>
								<td>'.$Telephone.'</td>
								<td>'.$Newsletter.'</td>
								<td>'.$Consultation.'</td>
							</tr>
						</tbody>
					</table>
				</div>';
			$body .=  '<div class="include_summary ">
			<h3 class="clear">Summary</h3> 
				<table class="form-table mq-form-table-reports">
					<tbody>
						<tr>
							<th style="min-width:200px;text-align:left;" >Archetype</th>
							<th style="min-width:200px;text-align:left;">'.date('d-M-y').'</th>
						</tr>
						<tr>
							<td>'.$archetypes_data[1].'</td>
							<td>'. round($Warrior_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[5].'</td>
							<td>'. round($Initiator_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[9].'</td>
							<td>'. round($Ruler_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[13].'</td>
							<td>'. round($Apprentice_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[17].'</td>
							<td>'. round($Maverick_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[21].'</td>
							<td>'. round($Victim_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[25].'</td>
							<td>'. round($Alchemist_return).'%</td>
						</tr>
						<tr>
							<td>'.$archetypes_data[29].'</td>
							<td>'. round($Nurturer_return).'%</td>
						</tr>
						
					</tbody>
				</table>
			</div>';
			$body .= '<div class="include_details">
				<h3>Detailed Scores</h3>
				<table class="form-table mq-form-table-reports ">
					<tbody>
						<tr>
							<th style="min-width:200px;text-align:left;">ID</th>
							<th style="min-width:200px;text-align:left;">Key Phrase</th>
							<th style="min-width:150px;text-align:left;">Archetype</th>
							<th style="min-width:150px;text-align:left;">'.date('d-M-y').'</th>
						</tr>	
						'.$table_row.'
					</tbody>
				</table>
			</div>';
		
			$body .= '<br><p>Thank you for using the MoneyQuiz.</p>';
			$body .= '<p>Powered by Business Insights Group AG<br> Zurich, Switzerland</p>';
			$body .= '<img src="'.plugins_url('assets/images/money_coach_signature.png', __FILE__).'" > <br/>';  // Money Coach logo
			
			$headers = array('Content-Type: text/html; charset=UTF-8');
			$headers[] = 'From: Money Quiz <no-reply@101businessinsights.com>';  
			
			wp_mail( $to, $subject, $body, $headers );
		}	
	

		#echo '<pre>';
	// CODE FOR MAILERLITE API	
		$select_feilds_arr = array();
		try {
			$Newsletter_val = 0;
			if($all_values[75] == "Only if Newsletter Selected" && $Newsletter == "Yes"){
				$Newsletter_val = 1;
			}
			if($all_values[75] == "All Records"){
				$Newsletter_val = 1;
			}
			if($all_values[55] != "" && $all_values[57] != "" && $all_values[60] != "" && $Newsletter_val == 1){ 
				 
				require_once('vendor/autoload.php');
			 
				$mailerliteClient = new \MailerLiteApi\MailerLite($all_values[57]);
				$subscribersApi = $mailerliteClient->subscribers();
				$groupId = $all_values[60];

				$subscriber = $subscribersApi->find($newmal);
				 
	
				$fields = array();
				if($all_values[61] == "Yes")
					$fields[$all_values[62]] = $Name;
				if($all_values[63] == "Yes")
					$fields[$all_values[64]] = $Surname;
				if($all_values[65] == "Yes")
					$fields[$all_values[66]] = $newmal;
				if($all_values[67] == "Yes")
					$fields[$all_values[68]] = $Telephone;
				if($all_values[69] == "Yes")
					$fields[$all_values[70]] = $Newsletter;
				if($all_values[71] == "Yes")
					$fields[$all_values[72]] = $Consultation;
				if($all_values[73] == "Yes")
					$fields[$all_values[74]] = 'MoneyQuiz';
				
				if(isset($subscriber->error->code)){ // subscriber not found, so create new one 
					$subscriber_data = [
					'email' => $newmal,
					'name' => $Name,
					'fields'=> $fields
					];
					 
					$groupsApi = $mailerliteClient->groups();
					$addedSubscriber = $groupsApi->addSubscriber($groupId, $subscriber_data); // returns added subscriber
                   // echo '<pre>1726->>';
				//print_r($addedSubscriber); 	 
				}else{ // subscriber found, so update new one 
					unset($fields[$all_values[66]]);
					$subscriber_data = [
					'name' => $Name,
					'fields'=> $fields
					];
					$addedSubscriber = $subscribersApi->update($newmal,$subscriber_data);
				 // echo '<pre>1734->>';
				//print_r($addedSubscriber); 	
				} 
				
			}
		}
		catch(Exception $e) {
			echo 'Message: ' .$e->getMessage();
			/* echo '<br> ************ <br> ';
			print_r($e);
			die(); */
		}		
	// end code for mailerlite	
	//die('******');
		ob_end_flush();
		$url = get_permalink()."?result=1&tid=".$taken_id."&prospect=".$prospect_id;
		return "<script>window.location='".$url."';</script>";
		exit;
		$save_msg = "<div class='data_saved'>Thank you for taking the MoneyQuiz. <a href='".get_permalink()."' > click here to go to quiz page</a></div>";
	}
	
	// add style and js file to front end quiz page
	wp_enqueue_style( 'site_css_all', plugins_url('assets/css/style.css', __FILE__),  array(), rand(1,2000).rand(2000,4000));
	wp_enqueue_script( 'moneyquizjsscript',  plugins_url('assets/js/mq.js',__FILE__), array(), rand(1,2000).rand(2000,4000), false );
	

	// get questions to show on quiz page
	$sql1 = "SELECT * from ".$table_prefix.TABLE_MQ_MASTER." ";
	$questions = $wpdb->get_results($sql1, OBJECT);
	$cat_que_arr = array();
	if($questions){
		$current_category = "";
		foreach($questions as $q){
			if($q->ID_Category != $current_category ){
				$current_category = $q->ID_Category;
			}
			//$cat_que_arr[$current_category][] =  $q->Master_ID."~~".$q->Question."~~".str_replace("&","_", str_replace(" ","",$q->Version))."~~Definition: ".$q->Definition.". Example: ".$q->Example;
			$full_version_css = ' full_ques ';
			$short_version_css = '';
			$blitz_version_css = '';
			$classic_version_css = '';
			if($q->Short == "Yes")
				$short_version_css = ' short_ques ';
				
			if($q->Blitz == "Yes")
				$blitz_version_css = ' blitz_ques ';
			
			if($q->Classic == "Yes")
				$classic_version_css = ' classic_ques ';
			
			//$string_def = preg_replace("/\s|&nbsp;/",'',$q->Definition);
			$cat_que_arr[$current_category][] =  $q->Master_ID."~~".$q->Question."~~".$full_version_css.''.$short_version_css.''.$blitz_version_css.''.$classic_version_css."~~<b>Definition: </b>".htmlspecialchars_decode(html_entity_decode($q->Definition)).".<p><b>Example:</b> ".$q->Example."</p>";
		}
	}
	
	$category_names = array(0=>"Start",1=>"How would you describe your current state?",2=>"How do you see the world?",3=>"How do you tend to act by default?",4=>"How do you relate and engage with others?",5=>"Which emotions do you feel most often?",6=>"How do you see yourself? ",7=>"Things you know to be true about yourself?" );
	
	ob_start();
	 
	?>
	<style>.pre_step, .pre_step:hover { background: <?php echo $all_values[45]?>;} .next_step, .next_step:hover { background: <?php echo $all_values[44]?>;}
		.lets_start, .lets_start:hover,.w3-light-grey,.w3-grey { height:<?php echo $all_values[95]?>; }
			.w3-light-grey{ background: <?php echo $all_values[87]?> !important; }
			.w3-grey{ background: <?php echo $all_values[88]?> !important; color: <?php echo $all_values[89]?> !important; }
	</style> 
	<div class="mq-container add-background-gold-ball"> 
	<?php if(isset($_GET['result']) && $_GET['result'] == 2 ){ ?>
	<!--<h4>Thank you for taking the <strong>MoneyQuiz</strong>.</h4> !-->
	<?php echo wpautop($page_question_screen['result_page_below_banner_content']);?>
		<h3><?php echo ($all_values[85])? stripslashes(wpautop($all_values[85])): "";?></h3>
	<?php }elseif(isset($_GET['result']) && $_GET['result'] == 1 ){ 
				
	
		// fetch data for archetypes
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_ARCHETYPES."" ;
		$rows = $wpdb->get_results($sql);
		$archetypes_data= array();
		foreach($rows as $row){
			$archetypes_data[$row->ID] = $row->Value;
		}
	 		 
		$tid = $_REQUEST['tid'];
		$prospect = $_REQUEST['prospect'];
		$sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE  mq_r.Prospect_ID=".$prospect." and mq_r.Taken_ID IN($tid) ORDER BY mq_r.Taken_ID ASC ";
		$sql_rows = $wpdb->get_results($sql_qry, OBJECT);
		 
		
		$Alchemist_score = $Alchemist_question = $Victim_score = $Victim_question = $Maverick_score = $Maverick_question = $Apprentice_score = $Apprentice_question = $Nurturer_score = $Nurturer_question = $Ruler_score = $Ruler_question = $Warrior_score = $Warrior_question = $Initiator_score = $Initiator_question = 0;
		$Alchemist_return =$Victim_return =$Maverick_return =$Apprentice_return =$Ruler_return =$Nurturer_return =$Initiator_return =$Warrior_return = 0;	
		$detailed_summary_rows = "";
		$new_arr = 1;
		$new_tr = 0;
		$table_row = '';
		$Alchemist_return_arr = $Victim_return_arr = $Maverick_return_arr = $Apprentice_return_arr = $Ruler_return_arr = $Warrior_return_arr = $Nurturer_return_arr = $Iniatiator_return_arr = array();	
		$Warrior_chart_arr = $Initiator_chart_arr = $Ruler_chart_arr = $Apprentice_chart_arr = $Maverick_chart_arr = $Victim_chart_arr = $Alchemist_chart_arr = $Nurturer_chart_arr = array();

		if($sql_rows){
			//foreach($t_arr as $tid){
				foreach($sql_rows as $row){
					$str = '<tr><td>'.$row->Question.'</td><td>'.$archetypes_data[$row->Archetype].'</td>';
					//foreach($t_arr as $tidn){
						$str .= '<td>'.$row->Score.'</td>';
						if($row->Archetype == 1) //Warrior
							$Warrior_return_arr[$tid] += $row->Score;
						if($row->Archetype == 5) // Iniatiator
							$Iniatiator_return_arr[$tid] += $row->Score;
						if($row->Archetype == 9) // Ruler
							$Ruler_return_arr[$tid] += $row->Score;
						if($row->Archetype == 13) // Apprentice
							$Apprentice_return_arr[$tid] += $row->Score;
						if($row->Archetype == 17) // Maverick
							$Maverick_return_arr[$tid] += $row->Score;
						if($row->Archetype == 21) //Victim
							$Victim_return_arr[$tid] += $row->Score;
						if($row->Archetype == 25) //Alchemist
							$Alchemist_return_arr[$tid] += $row->Score;
						if($row->Archetype == 29) // Nurturer
							$Nurturer_return_arr[$tid] += $row->Score;
					//}
					$str .= '</tr>';
					$table_row .= $str;
					switch($row->Archetype){
						case 1:
							$Warrior_question++;  
						 break;
						case 5:
							$Initiator_question++;  
						 break;
						case 9:
							$Ruler_question++;  
						 break;
						case 13:
							$Apprentice_question++;  
						 break;
						case 17:
							$Maverick_question++;  
						 break;
						case 21:
							$Victim_question++;  
						 break;
						case 25:
							$Alchemist_question++;  
						 break;
						case 29:
							$Nurturer_question++;  
						 break;
					}
					$new_tr++;
				}
				$new_arr++;
				//break;
			//} 
		}
	 
		$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." where Prospect_ID=".$prospect." " ;
		$row = $wpdb->get_row($sql, OBJECT);
		$prospect_email = $row->Email;
		$surname = $row->Surname;
		$first_name = $row->Name;

	
	
		/* // fetch data for CTA
		$sql_cta = "SELECT * FROM ".$table_prefix.TABLE_MQ_CTA."" ;
		$rows_cta = $wpdb->get_results($sql_cta);
		$cta_data= array();
		foreach($rows_cta as $row_cta){
			$cta_data[$row_cta->ID] = stripslashes($row_cta->Value);
		} */
		
	?>	
		<div class="mq_question_main_wrapper_container">
			<div class="clear"></div>
			<?php 		if($all_values[30] == 'Yes') // if result page show header yes 
							echo $header_image ;
			?>
			<?php echo wpautop($quiz_result_setting[1]);?>
			<!--- <h4>Thank you for taking the <strong>MoneyQuiz</strong>. <br>Here are your results…</h4> !-->
			<div class="clear"></div>
		<div class="include_summary ">
		
			<table class="form-table mq-form-table-reports">
				<tbody>
					<tr>
						<th style="min-width:200px;text-align:left;" >Archetype</th>
					<?php if($all_values[49] == 'Yes'){ ?>	
						<th style="min-width:200px;text-align:center;"><?php echo $all_values[50];?></th>
					<?php } ?>
						<th style="min-width:200px;text-align:center;"><?php echo $all_values[51];?></th>
					</tr>
					<tr>
						<td><?php echo $archetypes_data[1];?><p class="archive-tagline"><?php echo $arch_tag_line[1];?></p></td>
						<?php if($all_values[49] == 'Yes'){ ?>	
						<td align="center"><?php echo $archetypes_data[33];?></td>
						<?php } ?>
						<?php foreach($Warrior_return_arr as $Warrior_score){
							$Warrior_return = get_percentage($Warrior_question,$Warrior_score);
							$Warrior_chart_arr[] = round($Warrior_return);
							echo '<td align="center">'. round($Warrior_return).'%</td>';
						}
						?>
				 
					</tr>
					<tr>
						<td><?php echo $archetypes_data[5];?> <p class="archive-tagline"><?php echo $arch_tag_line[2];?></p></td>
						<?php if($all_values[49] == 'Yes'){ ?>	
						<td align="center"><?php echo $archetypes_data[34];?></td>
						<?php } ?>
						 <?php foreach($Iniatiator_return_arr as $Initiator_score){
							$Initiator_return = get_percentage($Initiator_question,$Initiator_score);
							$Initiator_chart_arr[] = round($Initiator_return);
							echo '<td align="center">'. round($Initiator_return).'%</td>';
						}  ?>
					</tr>				
					<tr>
						<td><?php echo $archetypes_data[9];?><p class="archive-tagline"><?php echo $arch_tag_line[3];?></p> </td>
						<?php if($all_values[49] == 'Yes'){ ?>	
						<td align="center"><?php echo $archetypes_data[35];?></td>
						<?php } ?>
						 <?php foreach($Ruler_return_arr as $ques_score){
							$Ruler_return = get_percentage($Ruler_question,$ques_score);
							$Ruler_chart_arr[] = round($Ruler_return);
							echo '<td align="center">'. round($Ruler_return).'%</td>';
						}  ?>
					</tr>
					<tr>
						<td><?php echo $archetypes_data[13];?> <p class="archive-tagline"><?php echo $arch_tag_line[4];?></p> </td>
						<?php if($all_values[49] == 'Yes'){ ?>	
						<td align="center"><?php echo $archetypes_data[36];?></td>
						<?php } ?>
						 <?php foreach($Apprentice_return_arr as $ques_score){
							$Apprentice_return = get_percentage($Apprentice_question,$ques_score);
							$Apprentice_chart_arr[] = round($Apprentice_return);
							echo '<td align="center">'. round($Apprentice_return).'%</td>';
						}  ?>
					</tr>				
					<tr>
						<td><?php echo $archetypes_data[17];?><p class="archive-tagline"><?php echo $arch_tag_line[5];?></p> </td>
						<?php if($all_values[49] == 'Yes'){ ?>	
						<td align="center"><?php echo $archetypes_data[37];?></td>
						<?php } ?>
						 <?php foreach($Maverick_return_arr as $ques_score){
							$Maverick_return = get_percentage($Maverick_question,$ques_score);
							$Maverick_chart_arr[] = round($Maverick_return);
							echo '<td align="center">'. round($Maverick_return).'%</td>';
						}  ?>
					</tr>
					<tr>
						<td><?php echo $archetypes_data[21];?><p class="archive-tagline"><?php echo $arch_tag_line[6];?></p> </td>
						<?php if($all_values[49] == 'Yes'){ ?>	
						<td align="center"><?php echo $archetypes_data[38];?></td>
						<?php } ?>
						 <?php foreach($Victim_return_arr as $ques_score){
							$Victim_return = get_percentage($Victim_question,$ques_score);
							$Victim_chart_arr[] = round($Victim_return);
							echo '<td align="center">'. round($Victim_return).'%</td>';
						}  ?>
					</tr>
					<tr>
						<td><?php echo $archetypes_data[25];?><p class="archive-tagline"><?php echo $arch_tag_line[7];?></p> </td>
						<?php if($all_values[49] == 'Yes'){ ?>	
						<td align="center"><?php echo $archetypes_data[39];?></td>
						<?php } ?>
						 <?php foreach($Alchemist_return_arr as $ques_score){
							$Alchemist_return = get_percentage($Alchemist_question,$ques_score);
							$Alchemist_chart_arr[] = round($Alchemist_return);
							echo '<td align="center">'. round($Alchemist_return).'%</td>';
						}  ?>
					</tr>
					<tr>
						<td><?php echo $archetypes_data[29];?> <p class="archive-tagline"><?php echo $arch_tag_line[8];?></p> </td>
						<?php if($all_values[49] == 'Yes'){ ?>	
						<td align="center"><?php echo $archetypes_data[40];?></td>
						<?php } ?>
						 <?php foreach($Nurturer_return_arr as $ques_score){
							$Nurturer_return = get_percentage($Nurturer_question,$ques_score);
							$Nurturer_chart_arr[] = round($Nurturer_return);
							echo '<td align="center">'. round($Nurturer_return).'%</td>';
						}  ?>
					</tr>
				</tbody>
			</table>
			<div class="clear"></div>
			<div class="graph_container">
				<div id="container" style="min-width: 600px;width:100%;border: 1px solid #d4d4d4;">
					<canvas id="canvas"></canvas>
				</div>
			</div>
		<script src="<?php echo plugins_url('assets/js/Chart.bundle.js', __FILE__);?>"></script>
		<script src="<?php echo plugins_url('assets/js/utils.js', __FILE__);?>"></script>
		<style>
		canvas {
			-moz-user-select: none;
			-webkit-user-select: none;
			-ms-user-select: none;
		}
		</style>
<?php 
	$chart_colors = array( '#008080','#008000', '#34495e', '#CD5C5C','#800080', '#0000FF','#808000','#008080','#008000', '#34495e', '#CD5C5C','#800080', '#0000FF','#808000');
	$chart_values = array();
	$temp_id=0;
	

	if($all_values[49] == 'Yes'){ 
		$chart_values[]  = "{
					label: '".$all_values[50]."',
					backgroundColor: '".$chart_colors[1]."',
					borderColor: '".$chart_colors[1]."',
					borderWidth: 1,
					data: [
						".str_replace('%','',$archetypes_data[33]).",
						".str_replace('%','',$archetypes_data[34]).",
						".str_replace('%','',$archetypes_data[35]).",
						".str_replace('%','',$archetypes_data[36]).",
						".str_replace('%','',$archetypes_data[37]).",
						".str_replace('%','',$archetypes_data[38]).",
						".str_replace('%','',$archetypes_data[39]).",
						".str_replace('%','',$archetypes_data[40])."
					]
				}";
	}
	
	  
	$chart_values[]  = "{
					label: '".$all_values[51]."',
					backgroundColor: '".$chart_colors[$temp_id]."',
					borderColor: '".$chart_colors[$temp_id]."',
					borderWidth: 1,
					data: [
						".$Warrior_chart_arr[$temp_id].",
						".$Initiator_chart_arr[$temp_id].",
						".$Ruler_chart_arr[$temp_id].",
						".$Apprentice_chart_arr[$temp_id].",
						".$Maverick_chart_arr[$temp_id].",
						".$Victim_chart_arr[$temp_id].",
						".$Alchemist_chart_arr[$temp_id].",
						".$Nurturer_chart_arr[$temp_id]."
					]
				}";
	 
						

?>		
	<script>
		//var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
		var color = Chart.helpers.color;
		var barChartData = {
			labels: ['<?php echo $archetypes_data[1];?>', '<?php echo $archetypes_data[5];?>', '<?php echo $archetypes_data[9];?>', '<?php echo $archetypes_data[13];?>', '<?php echo $archetypes_data[17];?>', '<?php echo $archetypes_data[21];?>', '<?php echo $archetypes_data[25];?>', '<?php echo $archetypes_data[29];?>'],
			datasets: [<?php echo implode(', ',$chart_values);?>]
			};

		window.onload = function() {
			var ctx = document.getElementById('canvas').getContext('2d');
			window.myBar = new Chart(ctx, {
				type: 'bar',
				data: barChartData,
				options: {
					responsive: true,
					legend: {
						position: 'bottom',
					},
					title: {
						display: true,
						text: '<?php echo $first_name?> MoneyQuiz Results'
					},scales: {
						xAxes: [{
							display: true,
							scaleLabel: {
								display: false,
								labelString: 'Month'
							}
						}],
						yAxes: [{
							display: true,
							scaleLabel: {
								display: false,
								labelString: 'Value'
							},
							ticks: {
								min: 0,
								max: 100,

								// forces step size to be 5 units
								stepSize: 20
							}
						}]
					}
				}
			});

		};

		var colorNames = Object.keys(window.chartColors);
	 
	</script>

	
		</div>	
		<div class="clear"></div>		
		<div class="include_details">
		<?php if($cta_data[7] == "Yes") { ?>	
			<div class="arch_type_show benefits">
				<h4 style="margin-bottom:5px;"><?php echo $cta_data[8];?></h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">
						<?php if($cta_data[9] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[9]?>" > 
							</p>
						<?php } 
							echo '<p>'.$cta_data[10].'</p>'; // intro 
						?>
						<div class="clear"></div>
					<?php if($cta_data[11] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[11].'</b><br>';?>
						<?php echo ' '.$cta_data[12];?></div>
						<div class="clear"></div>
					<?php } ?>
					<?php if($cta_data[13] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[13].'</b><br>';?>
						<?php echo ' '.$cta_data[14];?></p>
						<div class="clear"></div>
					<?php } ?>
					<?php if($cta_data[15] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[15].'</b><br>';?>
						<?php echo ' '.$cta_data[16];?></p>
						<div class="clear"></div>
					<?php } ?>
					<?php if($cta_data[17] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[17].'</b><br>';?>
						<?php echo ' '.$cta_data[18];?></p>
						<div class="clear"></div>
					<?php } ?>
					<?php if($cta_data[19] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[19].'</b><br>';?>
						<?php echo ' '.$cta_data[20];?></p>
						<div class="clear"></div>
					<?php } ?>
					<?php if($cta_data[21] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[21].'</b><br>';?>
						<?php echo ' '.$cta_data[22];?></p>
						<div class="clear"></div>
					<?php } ?>
						<p class="benefits-area-closing"><?php echo $cta_data[23];?> </p>
						<div class="clear"></div>
					<?php if($cta_data[25] != ""){ ?>	
						<p class="benefits-area-closing-button"><a  target="_blank" href="<?php echo $cta_data[25];?>" ><button name="start" class="next_step lets_start" ><?php echo $cta_data[24];?> </button></a></p>
					<?php } ?>	
					</div>
				</div>
			</div>	
		<?php } ?>
		
		<?php if($cta_data[26] == "Yes") { ?>
			<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show arch_type_specific">
				<h4 style="margin-bottom:5px;"><?php echo $cta_data[27];?></h4>
				<div class="mq-archetypes-overview">
				 
					<div class="clear mq-archetypes-desc">		
						<?php if($cta_data[28] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[28]?>" > 
							</p>
						<?php } 
							echo '<p>'.$cta_data[29].'</p>'; // intro 
						?>
						<?php if($cta_data[30] != ""){ ?>
							<p class="benefits-area-p"><?php echo '<b>'.$archetypes_data[1].'</b><br>';?>
							<?php echo ' '.$cta_data[30];?></p>
							<div class="clear"></div>
						<?php } ?>
						<?php if($cta_data[31] != ""){ ?>
							<p class="benefits-area-p"><?php echo '<b>'.$archetypes_data[5].'</b><br>';?>
							<?php echo ' '.$cta_data[31];?></p>
							<div class="clear"></div>
						<?php } ?>
						<?php if($cta_data[32] != ""){ ?>
							<p class="benefits-area-p"><?php echo '<b>'.$archetypes_data[9].'</b><br>';?>
							<?php echo ' '.$cta_data[32];?></p>
							<div class="clear"></div>
						<?php } ?>
						<?php if($cta_data[33] != ""){ ?>
							<p class="benefits-area-p"><?php echo '<b>'.$archetypes_data[13].'</b><br>';?>
							<?php echo ' '.$cta_data[33];?></p>
							<div class="clear"></div>
						<?php } ?>
						<?php if($cta_data[34] != ""){ ?>
							<p class="benefits-area-p"><?php echo '<b>'.$archetypes_data[17].'</b><br>';?>
							<?php echo ' '.$cta_data[34];?></p>
							<div class="clear"></div>
						<?php } ?>
						<?php if($cta_data[35] != ""){ ?>
							<p class="benefits-area-p"><?php echo '<b>'.$archetypes_data[21].'</b><br>';?>
							<?php echo ' '.$cta_data[35];?></p>
							<div class="clear"></div>
						<?php } ?>
						<?php if($cta_data[36] != ""){ ?>
							<p class="benefits-area-p"><?php echo '<b>'.$archetypes_data[25].'</b><br>';?>
							<?php echo ' '.$cta_data[36];?></p>
							<div class="clear"></div>
						<?php } ?>
						<?php if($cta_data[37] != ""){ ?>
							<p class="benefits-area-p"><?php echo '<b>'.$archetypes_data[29].'</b><br>';?>
							<?php echo ' '.$cta_data[37];?></p>
							<div class="clear"></div>
						<?php } ?>
						<p class="benefits-area-closing"><?php echo $cta_data[38];?> </p>
							<div class="clear"></div>
						<?php if($cta_data[40] != ""){ ?>	
							<p class="benefits-area-closing-button"><a  target="_blank" href="<?php echo $cta_data[40];?>" ><button name="start" class="next_step lets_start" ><?php echo $cta_data[39];?> </button></a></p>
						<?php } ?>
					</div>
				</div>
			</div>	
		<?php } ?>

		<?php if($cta_data[41] == "Yes") { ?>
			<hr class="result_divider " />
			<div class="clear"></div>		
			<div class="arch_type_show promotion">
				<h4 style="margin-bottom:5px;"><?php echo $cta_data[42];?></h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">
						<?php if($cta_data[43] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[43]?>" > 
							</p>
						<?php } 
							echo '<p>'.$cta_data[44].'</p>'; // intro 
						?>
						<div class="clear"></div>
					<?php if($cta_data[45] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[45].'</b><br>';?>
						<?php if($cta_data[46] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[46]?>" > 
							</p>
						<?php } ?>
						<?php echo ' '.$cta_data[47];?></div>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[48] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[48].'</b><br>';?>
						<?php if($cta_data[49] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[49]?>" > 
							</p>
						<?php } ?>
						<?php echo ' '.$cta_data[50];?></div>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[51] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[51].'</b><br>';?>
						<?php if($cta_data[52] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[52]?>" > 
							</p>
						<?php } ?>
						<?php echo ' '.$cta_data[53];?></div>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[54] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[54].'</b><br>';?>
						<?php if($cta_data[55] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[55]?>" > 
							</p>
						<?php } ?>
						<?php echo ' '.$cta_data[56];?></div>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[57] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[57].'</b><br>';?>
						<?php if($cta_data[58] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[58]?>" > 
							</p>
						<?php } ?>
						<?php echo ' '.$cta_data[59];?></div>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[60] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[60].'</b><br>';?>
						<?php if($cta_data[61] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[61]?>" > 
							</p>
						<?php } ?>
						<?php echo ' '.$cta_data[62];?></div>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[63] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[63].'</b><br>';?>
						<?php if($cta_data[64] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[64]?>" > 
							</p>
						<?php } ?>
						<?php echo ' '.$cta_data[65];?></div>
						<div class="clear"></div>
					<?php } ?>
					
						<p class="benefits-area-closing"><?php echo $cta_data[66];?> </p>
						<div class="clear"></div>
					<?php if($cta_data[68] != ""){ ?>	
						<p class="benefits-area-closing-button"><a  target="_blank" href="<?php echo $cta_data[68];?>" ><button name="start" class="next_step lets_start" ><?php echo $cta_data[67];?> </button></a></p>
					<?php } ?>	
					</div>
				</div>
			</div>	
		<?php } ?>

		<?php if($cta_data[69] == "Yes" && 5 > 7) { ?>
			<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show testimonials">
				<h4 style="margin-bottom:5px;"><?php echo $cta_data[70];?></h4>
				<div class="mq-archetypes-overview">
			 
					<div class="clear mq-archetypes-desc">
						<?php if($cta_data[71] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[71]?>" > 
							</p>
						<?php } 
							echo '<p>'.$cta_data[72].'</p>'; // intro 
						?>
						<div class="clear"></div>
					<?php if($cta_data[73] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[73].'</b><br>';?>
						<?php if($cta_data[74] != ""){ ?>
							<img src="<?php echo $cta_data[74]?>" > 
						<?php } ?>
						<?php echo ' '.$cta_data[75];?></div>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[76] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[76].'</b><br>';?>
						<?php if($cta_data[77] != ""){ ?>
							<img src="<?php echo $cta_data[77]?>" > 
						<?php } ?>
						<?php echo ' '.$cta_data[78];?></div>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[79] != ""){ ?>
						<div class="benefits-area-p"><?php echo '<b>'.$cta_data[79].'</b><br>';?>
						<?php if($cta_data[80] != ""){ ?>
							<img src="<?php echo $cta_data[80]?>" > 
						<?php } ?>
						<?php echo ' '.$cta_data[81];?></div>
						<div class="clear"></div>
					<?php } ?>
					
						<p class="benefits-area-closing"><?php echo $cta_data[82];?> </p>
						<div class="clear"></div>
					<?php if($cta_data[84] != ""){ ?>	
						<p class="benefits-area-closing-button"><a  target="_blank" href="<?php echo $cta_data[84];?>" ><button name="start" class="next_step lets_start" ><?php echo $cta_data[83];?> </button></a></p>
					<?php } ?>	
					</div>
				</div>
			</div>	
		<?php } ?>

		<?php if($cta_data[85] == "Yes") { ?>
			<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show special-offer">
				<h4 style="margin-bottom:5px;"><?php echo $cta_data[86];?></h4>
				<div class="mq-archetypes-overview">
					 
					<div class="clear mq-archetypes-desc">
						<?php if($cta_data[87] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[87]?>" > 
							</p>
						<?php } 
							echo '<p>'.$cta_data[88].'</p>'; // intro 
						?>
						<div class="clear"></div>
					<?php if($cta_data[89] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[89].'</b><br>';?>
						<?php echo ' '.$cta_data[90];?></p>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[91] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[91].'</b><br>';?>
						<?php echo ' '.$cta_data[92];?></p>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[93] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[93].'</b><br>';?>
						<?php echo ' '.$cta_data[94];?></p>
						<div class="clear"></div>
					<?php } ?>
					
						<p class="benefits-area-closing"><?php echo $cta_data[95];?> </p>
						<div class="clear"></div>
					<?php if($cta_data[97] != ""){ ?>	
						<p class="benefits-area-closing-button"><a  target="_blank" href="<?php echo $cta_data[97];?>" ><button name="start" class="next_step lets_start" ><?php echo $cta_data[96];?> </button></a></p>
					<?php } ?>	
					</div>
				</div>
			</div>	
		<?php } ?>

		<?php if($cta_data[98] == "Yes") { ?>
			<hr class="result_divider" />
			<div class="clear"></div>		
			<div class="arch_type_show bonus-offer">
				<h4 style="margin-bottom:5px;"><?php echo $cta_data[99];?></h4>
				<div class="mq-archetypes-overview">
		 
					<div class="clear mq-archetypes-desc">
						<?php if($cta_data[100] != ""){ ?>
							<p class="benefits-area-closing-button">
								<img src="<?php echo $cta_data[100]?>" > 
							</p>
						<?php } 
							echo '<p>'.$cta_data[101].'</p>'; // intro 
						?>
						<div class="clear"></div>
					<?php if($cta_data[102] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[102].'</b><br>';?>
						<?php echo ' '.$cta_data[103];?></p>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[104] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[104].'</b><br>';?>
						<?php echo ' '.$cta_data[105];?></p>
						<div class="clear"></div>
					<?php } ?>
					
					<?php if($cta_data[106] != ""){ ?>
						<p class="benefits-area-p"><?php echo '<b>'.$cta_data[106].'</b><br>';?>
						<?php echo ' '.$cta_data[107];?></p>
						<div class="clear"></div>
					<?php } ?>
					
						<p class="benefits-area-closing"><?php echo $cta_data[108];?> </p>
						<div class="clear"></div>
					<?php if($cta_data[110] != ""){ ?>	
						<p class="benefits-area-closing-button"><a  target="_blank" href="<?php echo $cta_data[110];?>" ><button name="start" class="next_step lets_start" ><?php echo $cta_data[109];?> </button></a></p>
					<?php } ?>	
					</div>
				</div>
			</div>	
		<?php } ?>
		<div class="mindfull-money-footer-content">				
        <?php echo wpautop($quiz_result_setting[4]); ?>
		</div>
		</div>
		<div class="clear"></div>
		
		<!-- 
		<p>Thank you, once again for taking the MoneyQuiz. If you would like to discuss these results with me, please reply to my email and lets set up a time to chat. </p>
		<div class="clear"></div> -->
	 	<br>
		<?php if($prospect_email != "" && $prospect_email !="notcaptured@nowhere.com"){ 
			 
		?>
		<h3><?php echo ($quiz_result_setting[5])? stripslashes($quiz_result_setting[5]): "";?></h3>
		<?php } ?>

		
		<?php if(isset($all_values[83]) && $all_values[83] == 'No'){ ?>
			
				<form name="mq-questions" action="" method="post" id="mq-questions-form" onsubmit="return check_mq_data();">	
					<div class="mq-ques-prospect" >
						<div class="mq-ques-wrapper" >
							<h3><?php echo ($all_values[84])? stripslashes(wpautop($all_values[84])): "";?></h3>	
							<?php if(isset($all_values[14]) && $all_values[14] == 'Yes'){ ?>
								<div class="mq-for-row">
									<label>Name </label>
									<input type="text" name="prospect_data[Name]" placeholder="<?php echo $all_values[13];?>" value="" class="prospect_data_Name" />
								</div>
							<?php } ?>
							<?php if(isset($all_values[16]) && $all_values[16] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label>Surname </label>
										<input type="text" name="prospect_data[Surname]" placeholder="<?php echo $all_values[15];?>" value="" class="prospect_data_surname" />
									</div>							
							<?php } ?>	
							<?php if(isset($all_values[18]) && $all_values[18] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label>Email </label>
										<input type="email" name="prospect_data[Email]" placeholder="<?php echo $all_values[17];?>" value="" class="prospect_data_email"   />
									</div>							
							<?php } ?>	
							<?php if(isset($all_values[20]) && $all_values[20] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label>Telephone </label>
										<input type="text" name="prospect_data[Telephone]" placeholder="<?php echo $all_values[19];?>" value=""    />
									</div>							
							<?php } ?>	
							<?php if(isset($all_values[22]) && $all_values[22] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label>Newsletter (<?php echo $all_values[21];?>)</label>
										<table><tr><td> &nbsp;<input name="prospect_data[Newsletter]" value="Yes" type="radio" checked="checked">&nbsp;Yes </td> <td> <input name="prospect_data[Newsletter]" value="No" type="radio" >&nbsp;No </td> </tr></table>
									</div>							
							<?php } ?>	
							<?php if(isset($all_values[24]) && $all_values[24] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label><?php echo $all_values[23];?></label>
										<table><tr><td> &nbsp;<input name="prospect_data[Consultation]" class="is_call_to_schedule" value="Yes" type="radio" checked="checked">&nbsp;Yes </td> <td> <input name="prospect_data[Consultation]" value="No" type="radio" class="is_call_to_schedule" >&nbsp;No </td> </tr></table>
									</div>							
							<?php } ?>	
							<?php if ($recaptcha_setting['1']=='on'): ?>
								<div class="mq-for-row">
								<?php if($recaptcha_setting['2']=='v2'): ?>	
           						 	<div class="g-recaptcha" data-sitekey="<?php echo $recaptcha_setting['3']; ?>"></div>
								<?php else: ?>
										<input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response" />
									
								<?php endif; ?>
								</div>
        						<?php endif; ?>
							<div class="mq-buttons-area" >
								<div  class="next_step lets_start schedul_call_button" name="schedul_call">Book a call</div>
							</div>
							<div class="mq-buttons-area" >
								<div class="show-mq-errors mq-hide"></div>
								<button type="button" class="next_step lets_start" name="next_step" onclick="showStep('submit');" ><?php echo $all_values[25];?></button><br />
							</div>
						
						</div>
						 
					
					</div>
					 
					<input type="hidden" name="result_taken_id" id="result_taken_id" value="<?php echo $_GET['tid'];?>" />
					<input type="hidden" name="prospect_action" id="prospect_action" value="submit_new" />
				 
				</form>
			
			<?php } ?>
			
			
			
		<div class="clear"></div>
	</div>
	<?php
	

	global $wpdb;
	$temp_conslut_id = $_GET['prospect'];
	$results_test = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Prospect_ID = '".$temp_conslut_id."'", OBJECT );
	
	if($results_test){
		$prospect_id_test = $results_test->Prospect_ID;
		$temp_consult = $results_test->Consultation;

		if($temp_consult=="Yes"){ ?>
			
			<script>
				jQuery('a[href="https://calendly.com/moneymagic/discovery-call-for-quiz-results"]').css("display", "none");
				jQuery('a[href*="https://calendly.com"]').css("display", "none");
				jQuery('.is-not-book-to-call').css("display", "none");
			</script>
			<?php
		}
	}
  

	}else{
		$landing_page_class='';	
		$second_class='';	
		if($cta_data[1] == "Yes"){ $second_class="mq-hide" ; $landing_page_class="landing_step_enabled"; ?>
			<!--div class="mq_landing_page" style="background:url(<?php //echo $cta_data[3]?>) no-repeat;">
				<section id="content-box" class="blackbox">
					<h1 id="primary-headline" class="role-element leadstyle-text"><?php //echo stripslashes(wpautop($cta_data[2]))?> </h1>
					<p id="primary-subheadline" class="role-element leadstyle-text"> <?php //echo stripslashes(wpautop($cta_data[4]))?></p>
					
					<a id="optin-button" href="javascript:;" onclick="show_second_page();" class="role-element leadstyle-link next_step" ><?php //echo $cta_data[6]?></a>
					<p id="subtext" class="role-element leadstyle-text"><?php //echo stripslashes(wpautop($cta_data[5]))?></p>
				</section>
			</div-->
			<?php
			if($all_values[28]=="Yes" && !empty($all_values[34])){?>
			<style>
				.mindfull-money-wrap .mindfull-money-banner-section {
    				background-image: url(<?php echo $all_values[34];?>) !important;
   				    
				}
			</style>
			<?php } ?>
			<div class="mq_question_main_wrapper mq_landing_page mindfull-money-wrap">
				<section id="section1" class="rosebg ptb20 mindfull-money-banner-section" >
					<div class="wc mindfull-money-heading-wrap">
					  <div class="mw3 mindfull-money-banner-heading">
						<div class="zind">
						 <?php if($banner_heading_text){echo "<h2>".$banner_heading_text."</h2>";?> <?php } ?> 
						  <?php /* ?>
						  <div class="btn-block mt30">
							  <a class="optin-button role-element leadstyle-link btn" href="javascript:;" onclick="show_second_page();">Take the Money Quiz Now</a>
						  </div>
						  <?php */ ?>
						</div>
						<div class="cite--">
						  <p><strong> <?php  if($banner_quiz_content) { echo stripslashes(wpautop($banner_quiz_content));} ?></strong></p>
						</div>
						
					  </div>
					</div>
				</section>
				<section id="section2" class="yelbg pb3 mindfull-money-section-2">
					<div class="mindfull-money-heading-wrap mindfull-money-box">
						<div class="mindfull-money-box-wrapper">
							<h3 class="blue mindfull-money-section-2-heading"><?php echo $minfull_money_gift_two_column_heading;?></h3>
							<div class="mindfull-money-display-flex ">
								<div class="mindfull-money-column">
									<?php
									$allowedTags = '<p><a><b><i><strong><em><br><ul><ol><li>';
									echo wpautop($two_column_heading_content);?>
									
									<div>
									<a class="optin-button role-element leadstyle-link btn mindfull-money-button" href="javascript:;" onclick="show_second_page();" ><?php echo $mindfull_money_button_text;?></a>
								</div>
								</div>
								<div class="mindfull-money-column">
									<?php if($column_image){ ?> <img src="<?php echo $column_image;?>" class="abs" alt="money quiz" /> <?php } ?>
								</div>
							</div>
						</div>
					</div>
				</section>

				<?php if($minfull_money_gift_two_display=="Yes" || $minfull_money_gift_one_display=="Yes"){ ?>	
				  <section id="section3" class="bluebg pbot mindfull-money-quiz-item-wrap">
					<div class="mindfull-money-container fl ">
					<?php if($minfull_money_gift_two_display=="Yes" || $minfull_money_gift_one_display=="Yes"){ ?>	
					  <div class="block2 bl2 tc mtm3 mindfull-money-box-wot-mrg">
						<h4 class="fb fs-55 rose"><?php echo stripslashes(wpautop($minfull_money_bottom));?></h4>
					  </div>
					 <?php } ?> 
					</div>
					<div class="mindfull-money-container mindfull-money-gift-items-wrapper">
					  <div class="mindfull-money-gift-items-container">
						<?php 
						if($minfull_money_gift_one_display=="Yes" ){ ?>
								<div class="mindfull-money-column">
								<div class="mind-full-money-prod-box item-product-image">
									<img src="<?php echo $gift_image1;?>" class="" alt="gift" />
									<h3 class="tc black mt50"><?php echo $minfull_money_gift_one_headig;?></h3>
									<div class="mt40">
										<?php echo wpautop($minfull_money_gift_one_content);?>
									</div>
								</div>
					    	</div>
						<?php } ?>
							
						<?php if($minfull_money_gift_two_display=="Yes"){ ?>	
							<div class="mindfull-money-column">
								<div class="mind-full-money-prod-box item-product-image">
									<?php if($gift_image2){ ?> <img width="" height="" src="<?php echo $gift_image2;?>" class="" alt="gift" /><?php } ?>
									<h3 class="tc black mt50"><?php echo $minfull_money_gift_two_heading;?></h3>
									<div class="mt40">
										<?php echo wpautop($minfull_money_gift_two_content);?>
									</div>
								</div>
							</div>
					   <?php } ?>
						
					  </div>

					  <div class="zind tc  d-none mindfull-money-container mindfull-money-bottom-section">
						<p><strong><?php echo $minfull_money_bottom_section_content;?></strong></p>
						<div class="btn-block jc d-none">
						  <a class="optin-button role-element leadstyle-link btn mindfull-money-button" href="javascript:;" onclick="show_second_page();" ><?php echo $mindfull_money_button_text;?></a>
						</div>
					  </div>
					</div>
				  </section>
				  <?php } ?>
			</div>
	  <?php } ?>
	<div class="mq_landing_page_2 <?php echo $second_class;?>">
		
		<div class="clear"></div>  
		<div class="mq-intro">
			<h2> The MoneyQuiz </h2>
			<p>Discover what's driving your behaviour with Money. Take the MoneyQuiz to learn how to transform your relationship with money and create a new reality for yourself.</p>
			<div class="clear"></div>
			<?php 		if($all_values[28] == 'Yes') // if on main page show header yes 
						echo $header_image;
			?>
		</div> 
		<div class="mq-select-version"> 
			<p class="mq_single_hide"><strong>Choose a Quiz Version </strong> 
			<?php $temp_click = array();
			if($all_values[38] == "Yes") {  // show Blitz
				echo '&nbsp;&nbsp;<a href="#" id="mq-blitz-version" data-version="blitz" class="mq-blitz-version select-version " >'.$all_values[41].'</a> ';
				$temp_click['blitz'] = 'blitz';  
			}				
			if($all_values[39] == "Yes") {  // show Short
				echo '&nbsp;&nbsp;<a href="#" id="mq-short-version" data-version="short" class="mq-short-version select-version " >'.$all_values[42].'</a> ';
				$temp_click['short'] = 'short';  
			}	
			if($all_values[52] == "Yes") {  // show classic
				echo '&nbsp;&nbsp;<a href="#" id="mq-classic-version" data-version="classic" class="mq-classic-version select-version " >'.$all_values[53].'</a> ';
				$temp_click['classic'] = 'classic';  
			}				
			if($all_values[40] == "Yes") {  // show Full
				echo '&nbsp;&nbsp;<a href="#" id="mq-full-version" data-version="full" class="mq-full-version select-version " >'.$all_values[43].'</a> ';  
				$temp_click['full'] = 'full';  
			 
			}
			
			if(in_array('full',$temp_click)){
				echo '<script> 
				jQuery(document).ready(function(){  
					setTimeout(function(){ jQuery("a.mq-full-version").trigger("click"); }, 100);
				 });
				</script> ';
			}elseif(in_array('classic',$temp_click)){		
				echo '<script> 
				jQuery(document).ready(function(){  
					setTimeout(function(){ jQuery("a.mq-classic-version").trigger("click"); }, 100);
				 });
				</script> ';
			}elseif(in_array('short',$temp_click)){		
				echo '<script> 
				jQuery(document).ready(function(){  
					setTimeout(function(){ jQuery("a.mq-short-version").trigger("click"); }, 100);
				 });
				</script> ';
			}elseif(in_array('blitz',$temp_click)){
					echo '<script> 
				jQuery(document).ready(function(){  
					setTimeout(function(){ jQuery("a.mq-blitz-version").trigger("click"); }, 100);
				 });
				</script> ';
			}
			
			if(count($temp_click)==1){				
				echo '<style>.mq_single_hide{visibility:hidden;}</style>';			
			
			}
			?>
		
			</p>

			<div class="mq-version-desc">
				<p class="blitz-version-desc mq-hide"><strong><?php echo $all_values[41]?> Version Instructions:</strong>  <?php echo stripslashes(wpautop($all_values[46]))?> </p>
				<p class="short-version-desc mq-hide"><strong><?php echo $all_values[42]?> Version Instructions:</strong> <?php echo stripslashes(wpautop($all_values[47]))?> </p>
				<p class="full-version-desc mq-hide"><strong><?php echo $all_values[43]?> Version Instructions:</strong> <?php echo stripslashes(wpautop($all_values[48]))?> </p>
				<p class="classic-version-desc mq-hide"><strong><?php echo $all_values[53]?> Version Instructions:</strong> <?php echo stripslashes(wpautop($all_values[54]))?> </p>
				
				<div class="mq-button-start mq-hide" >
					<button name="start" class="next_step lets_start click_auto" onclick="showStep('1');" > Let's Start </button> 
				</div>
			</div>
		</div>
		<div class="mq-questions">
		<?php if(isset($all_values[83]) && $all_values[83] == 'Yes'){ ?>
			<form name="mq-questions" action="" method="post" id="mq-questions-form" onsubmit="return check_mq_data();">
		<?php }else{ ?>
			<form name="mq-questions" action="" method="post" id="mq-questions-form" >
		<?php } ?>
			<?php  
			
				$sql_answers_options = "SELECT * from ".$table_prefix.TABLE_MQ_COACH."  ";
				$results_2 = $wpdb->get_results( $sql_answers_options, OBJECT );
				
				if( $results_2 ){
					foreach( $results_2 as $rs ){		
						if( $rs->ID == 90 && $rs->Field == 'never_label' ){
							$never_label = ( !empty( $rs->Value ) ?  $rs->Value : 'Never' );
						}						
						if( $rs->ID == 91 && $rs->Field == 'seldom_label' ){
							$seldom_label = ( !empty( $rs->Value ) ?  $rs->Value : 'Seldom' );
						}						
						if( $rs->ID == 92 && $rs->Field == 'sometimes_label' ){
							$sometimes_label = ( !empty( $rs->Value ) ?  $rs->Value : 'Sometimes' );
						}						
						if( $rs->ID == 93 && $rs->Field == 'mostly_label' ){
							$mostly_label = ( !empty( $rs->Value ) ?  $rs->Value : 'Mostly' );
						}
						if( $rs->ID == 94 && $rs->Field == 'always_label' ){
							$always_label = ( !empty( $rs->Value ) ?  $rs->Value : 'Always' );
						}
					}
				}else{
					$never_label     = 'Never';
					$seldom_label    = 'Seldom';
					$sometimes_label = 'Sometimes';
					$mostly_label    = 'Mostly';
					$always_label    = 'Always';
				}
				
				foreach($cat_que_arr as $cat_key=>$cat_questions){ 
					if(count($cat_questions) < 1 )
						continue; // if no question in category
			?>		
			
			
				<div id="step_<?php echo $cat_key?>" class="steps-container "  >
					<?php

					$title_sequence  = array();

					$title_sequence[1] = "15";
					$title_sequence[2] = "16";
					$title_sequence[3] = "17";
					$title_sequence[4] = "18";
					$title_sequence[5] = "19";
					$title_sequence[6] = "20";
					$title_sequence[7] = "21";
					$sequence_title = $title_sequence[$cat_key];
					?>
					<div class="minfull-money-heading-content"><h3><?php echo $page_question_screen[$sequence_title];?> </h3></div>
					<div class="money-quiz-question-list-content intro-section">
						<p><?php echo wpautop($page_question_screen['screen_'.$cat_key.'_intro_content']);?></p>
					</div>
					<div class="clear"></div>
					<div class="mq-ques test" >
						<table class="" >
							<tr><th style="width:27%">Key Phrase</th>
								<?php if(isset($all_values[76])) {
									$heading_text = "";
									switch($all_values[76]){
										case 'Two':
											$heading_text .='<th style="width:34.4%;text-align:center;">'.$never_label.'</th> 
															<th style="width:34.4%;text-align:center;">'.$always_label.'</th>'; 
										break;
										case 'Three':
											$heading_text .='<th style="width:23.30%;text-align:center;">'.$never_label.'</th>
															<th style="width:23.30%;text-align:center;">'.$sometimes_label.'</th>
															<th style="width:23.30%;text-align:center;">'.$always_label.'</th>'; 
											 
										break;
										case 'Five':
										default:
											$heading_text .='<th style="width:14.13%;text-align:center;">'.$never_label.'</th>
															<th style="width:14.13%;text-align:center;">'.$seldom_label.'</th>
															<th style="width:14.13%;text-align:center;">'.$sometimes_label.'</th>
															<th style="width:14.13%;text-align:center;">'.$mostly_label.'</th>
															<th style="width:14.13%;text-align:center;">'.$always_label.'</th>'; 
										break;
										
									}
									$heading_text .= '<th style="width:14.13%;text-align:center;">'.$answred_label[1].'</th>';
									echo $heading_text;
									?>									
								<?php }else{ ?>
									<th style="width:14.33%;text-align:center;"><?php echo $never_label; ?></th>
									<th style="width:14.33%;text-align:center;"><?php echo $seldom_label; ?></th>
									<th style="width:14.33%;text-align:center;"><?php echo $sometimes_label; ?></th>
									<th style="width:14.33%;text-align:center;"><?php echo $mostly_label; ?></th>
									<th style="width:14.33%;text-align:center;"><?php echo $always_label; ?></th>
									<th style="width:14.33%;text-align:center;"><?php echo $answred_label[1]; ?></th>
								<?php } ?>
							</tr>
							<?php 
							foreach( $cat_questions as $cat_question ){ 
								$new_values         = explode( '~~', $cat_question );
								$question_id        = $new_values[0];
								$question           = $new_values[1];
								$version_type_class = $new_values[2];
								$mouse_over         = $new_values[3];
							?>
							<tr class="mq-tr <?php echo $version_type_class ?> ">
								<td style="text-align:left !important;"><a href="javascript:;" onmouseout="hide_ques_title_hints();" onmouseover="show_ques_title_hints(<?php echo $question_id?>);" class="mq-mouse-over" title=""><?php echo $question;?><span class="mq-quiz-info-new"> <img src="https://cdn4.iconfinder.com/data/icons/game-general-icon-set-1/512/info-128.png" style="width: 15px;margin-top: 2px;" width="15"></span></a><div id="mouse_over_<?php echo $question_id?>" class="mouse_over_text"><?php echo $mouse_over?> </div> </td>
								<!--<td colspan="5">
									<div class="range-slider">
										<input name="question_data[<?php echo $question_id?>]" class="range-slider__range" type="range" value="1" min="1" max="9" step="2">
									</div>
								</td> -->
								<?php if(isset($all_values[76])) {
									$answers_text = "";
									switch($all_values[76]){
										case 'Two':
											?>
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="0" type="radio" > <div class="check"></div>
												</div>
											</td>
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="8" type="radio">    								
													<div class="check"></div>
												</div>
											</td>
											<td align="center" >
												<div class="money-quez-confrim-check-box">
												<label>
													<input name="confrim_checkbox[]" value="1" type="checkbox" disabled  class="disabled-checkbox">    								
													<span class="style-checkobx-with-color"></span>
												</label>	
												</div>
											</td>
										<?php	 
										break;
										case 'Three': ?>
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="0" type="radio" ><div class="check"></div>
												</div>
											</td>
											 
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="4" type="radio">    								
													<div class="check"></div>
												</div>
											</td>
											 
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="8" type="radio">    								
													<div class="check"></div>
												</div>
											</td>
											<td align="center" >
												<div class="money-quez-confrim-check-box">
												<label>
													<input name="confrim_checkbox[]" value="1" type="checkbox" disabled  class="disabled-checkbox">    								
													<span class="style-checkobx-with-color"></span>
												</label>	
												</div>
											</td>
										<?php	 
										break;
										case 'Five':
										default:
										?>
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="0" type="radio"><div class="check"></div>
												</div>
											</td>
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="2" type="radio">    								
													<div class="check"></div>
												</div>
											</td>
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="4" type="radio">    								
													<div class="check"></div>
												</div>
											</td>
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="6" type="radio">    								
													<div class="check"></div>
												</div>
											</td>
											<td align="center">
												<div class="custom-radio">
													<input name="question_data[<?php echo $question_id?>]" value="8" type="radio">    								
													<div class="check"></div>
												</div>
											</td>
											<td align="center" >
												<div class="money-quez-confrim-check-box">
												<label>
													<input name="confrim_checkbox[]" value="1" type="checkbox" disabled  class="disabled-checkbox">    								
													<span class="style-checkobx-with-color"></span>
												</label>	
												</div>
											</td>
											
										<?php 					
										break;
										
									}
								 
									?>
								<?php }else{ ?>	
									<td align="center">
										<div class="custom-radio">
											<input name="question_data[<?php echo $question_id?>]" value="0" type="radio"> <div class="check"></div>
										</div>
									</td>
									<td align="center">
										<div class="custom-radio">
											<input name="question_data[<?php echo $question_id?>]" value="2" type="radio">    								
											<div class="check"></div>
										</div>
									</td>
									<td align="center">
										<div class="custom-radio">
											<input name="question_data[<?php echo $question_id?>]" value="4" type="radio">    								
											<div class="check"></div>
										</div>
									</td>
									<td align="center">
										<div class="custom-radio">
											<input name="question_data[<?php echo $question_id?>]" value="6" type="radio">    								
											<div class="check"></div>
										</div>
									</td>
									<td align="center">
										<div class="custom-radio">
											<input name="question_data[<?php echo $question_id?>]" value="8" type="radio">    								
											<div class="check"></div>
										</div>
									</td>
									<td align="center" >
												<div class="money-quez-confrim-check-box">
												<label>
													<input name="confrim_checkbox[]" value="1" type="checkbox" disabled  class="disabled-checkbox">    								
													<span class="style-checkobx-with-color"></span>
												</label>	
												</div>
											</td>
								<?php } ?>
							</tr>
							<?php } ?>
						</table>
						<div class="answred_error"><?php //echo $answred_label[2];?></div>
						  <input type="hidden" value="<?php echo $answred_label[2];?>" id="answered_error_text" name="answered_error_text">
					</div>
					<div class="money-quiz-question-list-content closing-section">
						<p><?php echo wpautop($page_question_screen['screen_'.$cat_key.'_closing_content']);?></p>
					</div>
					
					<div class="clear"></div>  
					<div class="mq-buttons-area" >
						<div class="blitz_version_buttons mq-hide">
						<?php if($cat_key == 1 || $cat_key == 4 || $cat_key == 6 ){ 
								$next_step=1;	
								$prev_step=0;
								switch($cat_key){
									case 1:
										$next_step=4;	
										$prev_step=0;	
									break;
									case 4:
										$next_step=6;	
										$prev_step=1;	
									break;
									case 6:
										$next_step=8;	
										$prev_step=4;	
									break;
								}
							?>
							<button type="button" name="pre_step" class="pre_step" onclick="showStep('<?php echo ($prev_step);?>');" >Previous, <?php echo $category_names[$prev_step];?> </button>&nbsp;&nbsp;
							<?php if(isset($all_values[83]) && $all_values[83] == 'No'){  if($cat_key == 6) { $next_step="submit";} ?>
								<button  type="button" name="next_step" class="next_step" onclick="showStep('<?php echo ($next_step);?>');" ><?php echo ($cat_key == 6? 'Submit, See Results': "Next, ".$category_names[$next_step]) ;?></button>
							<?php }else{ ?>
									<button  type="button" name="next_step" class="next_step" onclick="showStep('<?php echo ($next_step);?>');" ><?php echo ($cat_key == 6? 'Submit, See Results': "Next, ".$category_names[$next_step]) ;?></button>
							<?php } ?>
						<?php } 
						
							if($all_values[86]== "Yes"){
						?>
								<div class="progress_bar">
								 	<?php $progress = 0;
									
										switch($cat_key){
											case 1:
												$progress = 33;	
												break;
											case 2:
												$progress = 30;	
												break;
											case 3:
												$progress = 45;	
												break;
											case 4:
												$progress = 66;	
												break;
											case 5:
												$progress = 75;	
												break;
											case 6:
												$progress = 100;	
												break;
											case 7:
												$progress = 100;	
												break;
										}
										
								?>		 
									<div class="w3-light-grey">
										<div class="w3-grey" style="width:<?php echo $progress;?>%"><?php echo $progress;?>%</div>
									</div>
								</div>
							<?php } ?>
						</div>
						<div class="full_version_buttons "> 
							<button type="button" name="pre_step" class="pre_step lets_start"  onclick="showStep('<?php echo ($cat_key-1);?>','1');" >Previous </button>
							<?php if(isset($all_values[83]) && $all_values[83] == 'No'){  ?>
								<button  type="button" name="next_step" class="next_step lets_start"  onclick="showStep('<?php echo ($cat_key == 7? 'submit': ($cat_key+1));?>')" ><?php echo ($cat_key == 7? 'Submit, See Results': 'Next');?></button>
							<?php }else{ ?>
								<button  type="button" name="next_step" class="next_step lets_start"  onclick="showStep('<?php echo ($cat_key+1);?>');" ><?php echo ($cat_key == 7? 'Submit, See Results': 'Next');?></button>
							<?php }  
							if($all_values[86]== "Yes"){
						?>
								<div class="progress_bar">
						 
								<?php $progress = 0;
									switch($cat_key){
										case 1:
											$progress = 15;	
											break;
										case 2:
											$progress = 30;	
											break;
										case 3:
											$progress = 45;	
											break;
										case 4:
											$progress = 60;	
											break;
										case 5:
											$progress = 75;	
											break;
										case 6:
											$progress = 90;	
											break;
										case 7:
											$progress = 100;	
											break;
									}
									?>
									 
								<div class="w3-light-grey">
								  <div class="w3-grey" style="width:<?php echo $progress;?>%"><?php echo $progress;?>%</div>
								</div>
							</div>
						<?php } ?>
						</div>
					</div>
					
				</div> <!-- #setp_<?php echo $cat_key?> -->
				
			<?php   
				} // end foreach $cat_que_arr
			?>	
				<!-- capture prospects details -->
				<div class="mq-prospects mq-hide"  >
					<h3><?php echo $register_page_seeting[1];?> </h3>
					<div class="margin-bottom-30 text-editor-heading-section"><?php echo wpautop($register_page_seeting[3]);?></div>
					<div class="clear"></div>
			<?php 	if($all_values[29] == 'Yes') // if capture prospect page show header yes 
						if(!empty($register_page_seeting)){
							echo "<div class='register-banner-image' ><img src='".$register_page_seeting[2]."' class='' /></div>";
						}
			?>
				
					<div class="clear"></div>
			<?php if(isset($all_values[83]) && $all_values[83] == 'Yes'){ ?>
					
					<div class="mq-ques-prospect" >
						<div class="mq-ques-wrapper" >
							<?php if(isset($all_values[14]) && $all_values[14] == 'Yes'){ ?>
								<div class="mq-for-row">
									<label>Name </label>
									<input type="text" name="prospect_data[Name]" placeholder="<?php echo $all_values[13];?>" value="" class="prospect_data_Name" />
								</div>
							<?php } ?>
							<?php if(isset($all_values[16]) && $all_values[16] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label>Surname </label>
										<input type="text" name="prospect_data[Surname]" placeholder="<?php echo $all_values[15];?>" value="" class="prospect_data_surname" />
									</div>							
							<?php } ?>	
							<?php if(isset($all_values[18]) && $all_values[18] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label>Email </label>
										<input type="email" name="prospect_data[Email]" placeholder="<?php echo $all_values[17];?>" value="" class="prospect_data_email"   />
									</div>							
							<?php } ?>	
							<?php if(isset($all_values[20]) && $all_values[20] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label>Telephone </label>
										<input type="text" name="prospect_data[Telephone]" placeholder="<?php echo $all_values[19];?>" value=""    />
									</div>							
							<?php } ?>	
							<?php if(isset($all_values[22]) && $all_values[22] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label>Newsletter (<?php echo $all_values[21];?>)</label>
										<table><tr><td> &nbsp;<input name="prospect_data[Newsletter]" value="Yes" type="radio" checked="checked">&nbsp;Yes </td> <td> <input name="prospect_data[Newsletter]" value="No" type="radio" >&nbsp;No </td> </tr></table>
									</div>							
							<?php } ?>	
							<?php if(isset($all_values[24]) && $all_values[24] == 'Yes'){ ?>
									<div class="mq-for-row">
										<label><?php echo $all_values[23];?></label>
										<table><tr><td> &nbsp;<input class="is_schedul_call" name="prospect_data[Consultation]" value="Yes" type="radio" checked="checked">&nbsp;Yes </td> <td> <input name="prospect_data[Consultation]" value="No" type="radio" class="is_schedul_call">&nbsp;No </td> </tr></table>
									</div>							
							<?php } ?>	
							<?php if ($recaptcha_setting['1']=='on'): ?>
								<div class="mq-for-row">
								<?php if($recaptcha_setting['2']=='v2'): ?>	
           						 	<div class="g-recaptcha" data-sitekey="<?php echo $recaptcha_setting['3']; ?>"></div>
								<?php else: ?>
										<input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response" />
									
								<?php endif; ?>
								</div>
        						<?php endif; ?>
							<div class="mq-buttons-area" >
								<div  class="next_step lets_start schedul_call_button" name="schedul_call">Schedule Free Interpretation Call</div>
							</div>
							<div class="mq-buttons-area" >
								<div class="show-mq-errors mq-hide"></div>
								<button type="button" class="next_step lets_start" id="money_report" name="next_step" onclick="showStep('submit');" ><?php echo $all_values[25];?></button><br />
							</div>
						
						</div>
						 <?php /*
						<table class="mq-prospects-table">
						<?php if(isset($all_values[14]) && $all_values[14] == 'Yes'){ ?>
							<tr><th>Name</th><td> &nbsp;<input type="text" name="prospect_data[Name]" placeholder="<?php echo $all_values[13];?>" value="" class="prospect_data_Name" /> </td> </tr>
						<?php } ?>	
						<?php if(isset($all_values[16]) && $all_values[16] == 'Yes'){ ?>
							<tr><th>Surname</th><td> &nbsp;<input type="text" name="prospect_data[Surname]" placeholder="<?php echo $all_values[15];?>" value="" class="prospect_data_surname" />  </td> </tr>
						<?php } ?>	
						<?php if(isset($all_values[18]) && $all_values[18] == 'Yes'){ ?>	
							<tr><th>Email</th><td> &nbsp;<input type="email" name="prospect_data[Email]" placeholder="<?php echo $all_values[17];?>" value="" class="prospect_data_email"   /> </td> </tr>
						<?php } ?>	
						<?php if(isset($all_values[20]) && $all_values[20] == 'Yes'){ ?>	
							<tr><th>Telephone</th><td> &nbsp;<input type="text" name="prospect_data[Telephone]" placeholder="<?php echo $all_values[19];?>" value=""    /> </td> </tr>
						<?php } ?>	
						<?php if(isset($all_values[22]) && $all_values[22] == 'Yes'){ ?>	
							<tr><th>Newsletter</th><td> &nbsp;<input name="prospect_data[Newsletter]" value="Yes" type="radio" checked="checked" >&nbsp;Yes &nbsp;&nbsp; <input name="prospect_data[Newsletter]" value="No" type="radio" >&nbsp;No &nbsp;&nbsp;(<?php echo $all_values[21];?>)</td> </tr>
						<?php } ?>	
						<?php if(isset($all_values[24]) && $all_values[24] == 'Yes'){ ?>	
							<tr><th>Consultation</th><td> &nbsp;<input name="prospect_data[Consultation]" value="Yes" type="radio" checked="checked">&nbsp;Yes &nbsp;&nbsp; <input name="prospect_data[Consultation]" value="No" type="radio" >&nbsp;No &nbsp;&nbsp;(<?php echo $all_values[23];?>)</td> </tr>
						<?php } ?>	
							
							 
						</table> */ ?>
						
					
					</div>	
			<?php } ?>		
				</div> 
				<input type="hidden" name="mq_version_selected" id="mq_version_selected" value="full" />
				<input type="hidden" name="prospect_action" id="prospect_action" value="submit" />
			</form>
		</div>
	</div>
	<!-- book call model !-->
	<div class="mindfull-money-prefix-model-main">
    <div class="mindfull-money-prefix-model-inner">        
    <div class="close-btn">×</div>
	<div class="mindfull-money-prefix-model-wrap">
            <div class="pop-up-content-wrap">
			<!-------------------<iframe src="https://calendly.com/moneymagic/discovery-call-for-quiz-results" id="book-call-iframe"  ></iframe>
				  -------------!-->
				<!-- Embded code!-->		
				<?php
				if(empty($all_values[11])){
					$all_values[11] = "https://calendly.com/moneymagic/discovery-call-for-quiz-results";
				}
				?>
				<div class="calendly-inline-widget" data-url="<?php echo $all_values[11];?>?hide_gdpr_banner=1" style="min-width:320px;height:700px;"></div>
				<script type="text/javascript" src="https://assets.calendly.com/assets/external/widget.js" async></script>
				
					<script>
						jQuery(".mindfull-money-after-book-call").hide();
					function isCalendlyEvent(e) {
						return e.origin === "https://calendly.com" && e.data.event && e.data.event.indexOf("calendly.") === 0;
					};

					window.addEventListener("message", function(e) {
					if(isCalendlyEvent(e)) {
						console.log("Event name:", e.data.event);
						console.log("Event details:", e.data.payload);
						if(e.data.event=="calendly.event_scheduled"){
							jQuery(".mindfull-money-prefix-model-main").removeClass('model-open');
							showStep('submit');
							
							jQuery(".mindfull-money-after-book-call").show();
						}
						
					}

					});
					</script>
				<!----  End ------------------ !--->
				<!-- Embded code !-->	
			 <div class="mq-buttons-area mindfull-money-align-center mindfull-money-after-book-call" >
				<button type="button" class="next_step lets_start" id="money_report_from_popup" name="next_step"><?php echo $all_values[25];?></button><br />
			</div> 
            </div>
        </div>  
		<script>
			jQuery(".mindfull-money-after-book-call").hide();
		</script>
    </div>  
    <div class="bg-overlay"></div>
</div>
<!-- book call model End!-->
	<div class="mq-modal">
		<div class="mq-modal-wait">
			<div>Please wait while we prepare your results</div>
			<div class="wait-loader"></div>
		</div>
	</div>

	<?php } // end else $save_msg != '' ?> 	
	</div>
	
	<?php if($recaptcha_setting['2']=='v2'): ?>		
		<script src="https://www.google.com/recaptcha/api.js" async defer></script>
	<?php else: ?>
		<script src="https://www.google.com/recaptcha/api.js?render=<?php echo $recaptcha_setting['3']; ?>"></script>
		

	<?php endif; ?>

<?php 

 return ob_get_clean();	
}
