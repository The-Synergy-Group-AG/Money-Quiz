<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
/*
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);*/

	$todays_sql = "SELECT count(*) as todays_result from ".$table_prefix.TABLE_MQ_TAKEN." WHERE Date_Taken='".date("d-M-y")."'";
	$todays_rs = $wpdb->get_row($todays_sql, OBJECT);
	$best_sql = "SELECT count(*) as best_result from ".$table_prefix.TABLE_MQ_TAKEN." group by Date_Taken order by best_result desc limit 0, 1";
	$best_rs = $wpdb->get_row($best_sql, OBJECT);
	$all_sql = "SELECT count(*) as all_result from ".$table_prefix.TABLE_MQ_TAKEN."";
	$all_rs = $wpdb->get_row($all_sql, OBJECT);
	$current_date = strtotime(date('d-M-y'));
	$last_thirty_days = ($current_date - (15*24*60*60));	
	$last_week_days = ($current_date - (126*24*60*60));	// 18 weeks = 126 days
	$last_months_days = ($current_date - (548*24*60*60));	// 18 months = 548 days
	$last_thirty_days_count = 0;
	$last_thirty_days_val = array();
	$week_days_count = 0;
	$week_days_val = array();
	$months_days_count = 0;
	$months_days_val = array();
	$new_months_days_val = array(0);
	$taken_ids = array();
	$taken_last_thirty_days_ids = array();
	$taken_week_days_ids = array();
	$taken_months_days_ids = array();
	$new_taken_months_days_ids = array();
	$new_week_days_val = array();
	$week_days_val = array(0);
	$quiz_length_arr = array();	
	if($post_data[38] == "Yes") {  // show Blitz
		$quiz_length_arr['blitz'] = $post_data[41];
	}				
	if($post_data[39] == "Yes") {  // show Short
		$quiz_length_arr['short'] = $post_data[42];
	}
	if($post_data[40] == "Yes") {  // show Full
		$quiz_length_arr['full'] = $post_data[43]; 
	}
	if($post_data[52] == "Yes") {  // show Classic
		$quiz_length_arr['classic'] = $post_data[53]; 
	}
	$show_quiz_length_no_tab_selected = 0;
	if(isset($_REQUEST['quizlength'])){
		$show_quiz_length=$_REQUEST['quizlength'];
		$show_quiz_length_no_tab_selected = 1;
	}else{
		$show_quiz_length="full";
		if(array_key_exists('blitz', $quiz_length_arr)){
			$show_quiz_length = "blitz";
		}elseif(array_key_exists('short', $quiz_length_arr)){
			$show_quiz_length = "short";
		}elseif(array_key_exists('full', $quiz_length_arr)){
			$show_quiz_length = "full";
		}elseif(array_key_exists('classic', $quiz_length_arr)){
			$show_quiz_length = "classic";
		}else{
			$show_quiz_length = "no array";
		}
	}	
	if(isset($_REQUEST['quizlength_arr'])){
		$show_quiz_length_arr=$_REQUEST['quizlength_arr'];
		$show_quiz_length_no_tab_selected = 1;
	}else{
		//$show_quiz_length_arr[] ="full";
		if(array_key_exists('blitz', $quiz_length_arr)){
			$show_quiz_length_arr[] = "blitz";
		}
		if(array_key_exists('short', $quiz_length_arr)){
			$show_quiz_length_arr[] = "short";
		}
		if(array_key_exists('full', $quiz_length_arr)){
			$show_quiz_length_arr[] = "full";
		}
		if(array_key_exists('classic', $quiz_length_arr)){
			$show_quiz_length_arr[] = "classic";
		}
	}	
	$sql = "SELECT * from ".$table_prefix.TABLE_MQ_TAKEN."";
	$results = $wpdb->get_results($sql, OBJECT);	
	foreach($results as $row){	
		// 30 days data
		if(strtotime($row->Date_Taken) >= $last_thirty_days){
			$last_thirty_days_count++;	
			$last_thirty_days_val[$row->Date_Taken] +=1;
			//$taken_last_thirty_days_ids[$row->Date_Taken][] = $row->Taken_ID;
		}
		// 18 weeks data 
		if(strtotime($row->Date_Taken) >= $last_week_days){
			$week_days_count++;	
			$ddate = date('Y-m-d',strtotime($row->Date_Taken));
			$date = new DateTime($ddate);
			$week = $date->format("W");
			$week_days_val[$row->Date_Taken] +=1;
			$new_week_days_val[$week.'-week-'.date('y',strtotime($row->Date_Taken))] +=1;
			//$taken_week_days_ids[$row->Date_Taken][] = $row->Taken_ID;
		}
		// 18 months data 
		if(strtotime($row->Date_Taken) >= $last_months_days){
			$months_days_count++;	
			$months_days_val[$row->Date_Taken] +=1;
			$new_months_days_val[date('M-y',strtotime($row->Date_Taken))] +=1;
			//$new_taken_months_days_ids[date('M-y',strtotime($row->Date_Taken))][] = $row->Taken_ID;
			$taken_months_days_ids[$row->Date_Taken][] = $row->Taken_ID;
		}
		$taken_ids[] = $row->Taken_ID;
	}
	$sql = "SELECT * from ".$table_prefix.TABLE_MQ_TAKEN." where Quiz_Length IN ('".implode("','", $show_quiz_length_arr)."')";
	$results = $wpdb->get_results($sql, OBJECT);	
	foreach($results as $row){	
		// 30 days data
		if(strtotime($row->Date_Taken) >= $last_thirty_days){
			//$last_thirty_days_count++;	
			//$last_thirty_days_val[$row->Date_Taken] +=1;
			$taken_last_thirty_days_ids[$row->Date_Taken][] = $row->Taken_ID;
		}
		// 18 weeks data 
		if(strtotime($row->Date_Taken) >= $last_week_days){
			//$week_days_count++;	
			//$week_days_val[$row->Date_Taken] +=1;
			$ddate = date('Y-m-d',strtotime($row->Date_Taken));
			$date = new DateTime($ddate);
			$week = $date->format("W");
			$taken_week_days_ids[$week.'-week-'.date('y',strtotime($row->Date_Taken))][] = $row->Taken_ID;
		}
		// 18 months data 
		if(strtotime($row->Date_Taken) >= $last_months_days){
			//$months_days_count++;	
			//$months_days_val[$row->Date_Taken] +=1;
			//$new_months_days_val[date('M-y',strtotime($row->Date_Taken))] +=1;
			$new_taken_months_days_ids[date('M-y',strtotime($row->Date_Taken))][] = $row->Taken_ID;
			//$taken_months_days_ids[$row->Date_Taken][] = $row->Taken_ID;
		}
		$taken_ids[] = $row->Taken_ID;
	}
	// stats by archetype
	$Alchemist_return_arr = $Victim_return_arr = $Maverick_return_arr = $Apprentice_return_arr = $Ruler_return_arr = $Warrior_return_arr = $Nurturer_return_arr = $Iniatiator_return_arr =0;
	$last_week_Warrior_return_arr = $last_week_Warrior_question = $Alchemist_score = $Alchemist_question = $Victim_score = $Victim_question = $Maverick_score = $Maverick_question = $Apprentice_score = $Apprentice_question = $Nurturer_score = $Nurturer_question = $Ruler_score = $Ruler_question = $Warrior_score = $Warrior_question = $Initiator_score = $Initiator_question = 0;
	$Alchemist_return =$Victim_return =$Maverick_return =$Apprentice_return =$Ruler_return =$Nurturer_return =$Initiator_return =$Warrior_return = 0;			
	if(isset($_REQUEST['archetype'])){
		$show_archetype=$_REQUEST['archetype'];
	}else{
		$show_archetype="Hero";
	}
	if(isset($_REQUEST['tab'])){
		$show_archetype_tab=$_REQUEST['tab'];
		$show_function_values = 'show_chart("'.$show_archetype_tab.'");';
		$show_function_values .= 'jQuery("html, body").animate({ scrollTop: jQuery(document).height() }, 1000);' ;
	}else{
		$show_function_values = '';
		$show_archetype_tab="today";
	}
	$temp_quiz = '<!--<select name="quizlength" style="width: 200px;"> ';
	foreach($quiz_length_arr as $quiz_key=>$quiz_length_row){
		$temp_quiz .= "<option value='".$quiz_key."' ".($quiz_key == $show_quiz_length ? 'selected="selected"' : '' )." >".$quiz_length_row."</option>";
	}	
	$temp_quiz .= '</select>-->';
	$temp_quiz_new = '';
	foreach($quiz_length_arr as $quiz_key=>$quiz_length_row){
		if($show_quiz_length_no_tab_selected == 0){
			$temp_quiz_new .="&nbsp;&nbsp;&nbsp;<input checked='checked' type='checkbox' name='quizlength_arr[]' value='".$quiz_key."' />".$quiz_length_row." ";
		}elseif(in_array($quiz_key, $show_quiz_length_arr)){
			$temp_quiz_new .="&nbsp;&nbsp;&nbsp;<input checked='checked' type='checkbox' name='quizlength_arr[]' value='".$quiz_key."' />".$quiz_length_row." ";
		}else{
			$temp_quiz_new .="&nbsp;&nbsp;&nbsp;<input type='checkbox' name='quizlength_arr[]' value='".$quiz_key."' />".$quiz_length_row." ";
		}
	}	
	$archetype_form_section = '<form name="searchfilter" method="get" action="">
			<input type="hidden" name="page" value="mq_stats" >
			<input type="hidden" name="tab" id="form_tab_value" value="today" >
			<table class="archetype_dropdown form-table ">
				<tr> 
				<th style="width: 210px;">Archetype <select name="archetype" > 
						<option value="'.$archetype_data[1].'" '.($show_archetype == $archetype_data[1] ? 'selected="selected"' : '' ).' >'.$archetype_data[1].'</option>
						<option value="'.$archetype_data[5].'" '.($show_archetype == $archetype_data[5] ? 'selected="selected"' : '' ).' >'.$archetype_data[5].'</option>
						<option value="'.$archetype_data[9].'" '.($show_archetype == $archetype_data[9] ? 'selected="selected"' : '' ).' >'.$archetype_data[9].'</option>
						<option value="'.$archetype_data[13].'" '.($show_archetype == $archetype_data[13] ? 'selected="selected"' : '' ).' >'.$archetype_data[13].'</option>
						<option value="'.$archetype_data[17].'" '.($show_archetype == $archetype_data[17] ? 'selected="selected"' : '' ).' >'.$archetype_data[17].'</option>
						<option value="'.$archetype_data[21].'" '.($show_archetype == $archetype_data[21] ? 'selected="selected"' : '' ).' >'.$archetype_data[21].'</option>
						<option value="'.$archetype_data[25].'" '.($show_archetype == $archetype_data[25] ? 'selected="selected"' : '' ).' >'.$archetype_data[25].'</option>
						<option value="'.$archetype_data[29].'" '.($show_archetype == $archetype_data[29] ? 'selected="selected"' : '' ).' >'.$archetype_data[29].'</option>
					</select> 
				</th>
				<th style="width: 400px;">Quiz Length: '.$temp_quiz_new.' 
				</th>	
				<th style="width: 120px;"><input type="submit" value="Refresh" class="button button-primary"> </th>		
				</tr>
			</table>  
		</form>';
 ?>
  	<script src="<?php echo plugins_url('assets/js/Chart.bundle.js', __FILE__);?>"></script>
	<script src="<?php echo plugins_url('assets/js/utils.js', __FILE__);?>"></script>
	<style>
	canvas {
		-moz-user-select: none;
		-webkit-user-select: none;
		-ms-user-select: none;
	}
	</style>
<div class=" mq-container">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<!-- Export Summary Results  -->
	<h3>Export Summary Results With Date Range</h3>
	<div class="export_summary_result">
		<form action="" method="post">
			<table class="form-table mq-form-table-reports">
				<tbody>
					<tr>
						<th>Start Date</th>
						<th>End Date</th>
						<th>&nbsp;</th>
					</tr>
					<tr>
						<td><input type="date" class="range-start-date" name="range-start-date"/></td>
						<td><input type="date" class="range-end-date" name="range-end-date"/></td>
						<td><input type="submit" value="Submit" name="reports-range-submit" class="button-primary reports-range-submit"></td>
					</tr>
				</tbody>
			</table>
			<div class="reports-range-msg" style="color: #f00;"></div>
		</form>
	</div>
	<!-- Export Summary Results  -->
	<h3>Overall Stats</h3>
 	<div class="clear"></div>
	<table class="form-table mq-form-table stats-table">
		<tbody>
			<tr>
				<td scope="row" align="center"><h3 style="margin-bottom: 3px;" >Today</h3>
				<?php echo $todays_rs->todays_result;?> <br>quizzes </td>
				<td scope="row" align="center"><h3 style="margin-bottom: 3px;" >Best ever</h3>
				<?php echo ($best_rs->best_result ? $best_rs->best_result : '0');?> <br>quizzes </td>
				<td scope="row" align="center"><h3 style="margin-bottom: 3px;" >All time</h3>
				<?php echo $all_rs->all_result;?> <br>quizzes </td>
			</tr>	
		</tbody>
	</table>
	<div class="clear"></div>
	<table class="form-table mq-form-table stats-chart-tabs" >
		<tbody>
			<tr>
				<td scope="row" id="today" class="active tab_tab" align="center"><a href="javascript:;" onclick="show_chart('today');"> Daily</a></td>
				<td scope="row" id="week" class=" tab_tab" align="center"><a href="javascript:;" onclick="show_chart('week');">Weekly</a></td>
				<td scope="row" id="month" class=" tab_tab"  align="center"><a href="javascript:;" onclick="show_chart('month');">Monthly</a></td>
				<td scope="row" align="center" colspan="5"></td>
			</tr>	
		</tbody>
	</table>
	<div class="tab_today_content tab_content_today all_tabs">
		<h3>Daily Data</h3>	
		<div id="container" style="width: 96%;margin: 30px 0px;border: 1px solid #d4d4d4;padding: 10px;">
			<canvas id="canvas_day"></canvas>
		</div>
		<br>
		<hr style="margin-left: -16px;">
		<br>
		<h3>By Archetypes</h3>
		<div class="clear"></div>
		<?php echo $archetype_form_section; ?>
		<div id="container" style="width: 96%;margin: 30px 0px;border: 1px solid #d4d4d4;padding: 10px;">
			<canvas id="canvas_line_day"></canvas>
		</div> 
	</div>
	<div class="tab_weekly_content tab_content_week all_tabs">
		<h3>Weekly Data</h3>	
		<div id="container" style="width: 96%;margin: 30px 0px;border: 1px solid #d4d4d4;padding: 10px;">
			<canvas id="canvas_week"></canvas>
		</div>
		<br>
		<hr style="margin-left: -16px;">
		<br>
		<h3>By Archetypes</h3>
		<div class="clear"></div>
		<?php echo $archetype_form_section; ?>
		<div id="container" style="width: 96%;margin: 30px 0px;border: 1px solid #d4d4d4;padding: 10px;">
			<canvas id="canvas_line_week"></canvas>
		</div> 
	</div>
	<div class="tab_monthly_content tab_content_month all_tabs">
		<h3>Monthly Data</h3>	
		<div id="container" style="width: 96%;margin: 30px 0px;border: 1px solid #d4d4d4;padding: 10px;">
			<canvas id="canvas_month"></canvas>
		</div>
		<br>
		<hr style="margin-left: -16px;">
		<br>
		<h3>By Archetypes</h3>
		<div class="clear"></div>
		<?php echo $archetype_form_section; ?>	 
		<div id="container" style="width: 96%;margin: 30px 0px;border: 1px solid #d4d4d4;padding: 10px;">
			<canvas id="canvas_line_month"></canvas>
		</div> 
	</div>
<?php 
$last_thirty_days_new=array();
for($day=1;$day<16;$day++){
	$new_day = date('d-M-y',($last_thirty_days + ($day*24*60*60)));
	if(!array_key_exists($new_day ,$last_thirty_days_val)){
		$last_thirty_days_new[$new_day] = 0; // no quiz on this date
		$last_thirty_Warrior_return[$new_day] = 0;
		$last_thirty_Initiator_return[$new_day] = 0;
		$last_thirty_Ruler_return[$new_day] = 0;
		$last_thirty_Apprentice_return[$new_day] = 0;
		$last_thirty_Maverick_return[$new_day] = 0;
		$last_thirty_Victim_return[$new_day] = 0;
		$last_thirty_Alchemist_return[$new_day] = 0;
		$last_thirty_Nurturer_return[$new_day] = 0;
	}else{
		$last_thirty_days_new[$new_day] = $last_thirty_days_val[$new_day];
		//$last_thirty_days_new[$new_day] = 3;
		if(count($taken_last_thirty_days_ids)>0 && isset($taken_last_thirty_days_ids[$new_day])){
			$sql_sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question,  mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE mq_r.Taken_ID IN(".implode(',',$taken_last_thirty_days_ids[$new_day]).") ORDER BY mq_r.Taken_ID ASC ";
			$sql_rows = $wpdb->get_results($sql_sql_qry, OBJECT);
			if($sql_rows){
				$last_thirty_Warrior_question = 0;
				$last_thirty_Warrior_return_arr = 0;
				$last_thirty_Initiator_question = 0;
				$last_thirty_Iniatiator_return_arr = 0;
				$last_thirty_Ruler_question = 0;
				$last_thirty_Ruler_return_arr = 0;
				$last_thirty_Apprentice_question = 0;
				$last_thirty_Apprentice_return_arr = 0;
				$last_thirty_Maverick_question = 0;
				$last_thirty_Maverick_return_arr = 0;
				$last_thirty_Victim_question = 0;
				$last_thirty_Victim_return_arr = 0;
				$last_thirty_Alchemist_question = 0;
				$last_thirty_Alchemist_return_arr = 0;
				$last_thirty_Nurturer_question = 0;
				$last_thirty_Nurturer_return_arr = 0;
				foreach($sql_rows as $row){
					switch($row->Archetype){
						case 1:
							$last_thirty_Warrior_question++;  
							$last_thirty_Warrior_return_arr += $row->Score;
						 break;
						case 5:
							$last_thirty_Initiator_question++; 
							$last_thirty_Iniatiator_return_arr += $row->Score;				
						 break;
						case 9:
							$last_thirty_Ruler_question++;  
							$last_thirty_Ruler_return_arr += $row->Score;
						 break;
						case 13:
							$last_thirty_Apprentice_question++;
							$last_thirty_Apprentice_return_arr += $row->Score;				
						 break;
						case 17:
							$last_thirty_Maverick_question++;
							$last_thirty_Maverick_return_arr += $row->Score;				
						 break;
						case 21:
							$last_thirty_Victim_question++;  
							$last_thirty_Victim_return_arr += $row->Score;
						 break;
						case 25:
							$last_thirty_Alchemist_question++;  
							$last_thirty_Alchemist_return_arr += $row->Score;
						 break;
						case 29:
							$last_thirty_Nurturer_question++;  
							$last_thirty_Nurturer_return_arr += $row->Score;
						 break;
					}
				}
				//$last_thirty_Warrior_question .'-----'.$last_thirty_Warrior_return_arr;
				$last_thirty_Warrior_return[$new_day] = round(get_percentage($last_thirty_Warrior_question,$last_thirty_Warrior_return_arr));
				$last_thirty_Initiator_return[$new_day] = round(get_percentage($last_thirty_Initiator_question,$last_thirty_Iniatiator_return_arr));
				$last_thirty_Ruler_return[$new_day] = round(get_percentage($last_thirty_Ruler_question,$last_thirty_Ruler_return_arr));
				$last_thirty_Apprentice_return[$new_day] = round(get_percentage($last_thirty_Apprentice_question,$last_thirty_Apprentice_return_arr));
				$last_thirty_Maverick_return[$new_day] = round(get_percentage($last_thirty_Maverick_question,$last_thirty_Maverick_return_arr));
				$last_thirty_Victim_return[$new_day] = round(get_percentage($last_thirty_Victim_question,$last_thirty_Victim_return_arr));
				$last_thirty_Alchemist_return[$new_day] = round(get_percentage($last_thirty_Alchemist_question,$last_thirty_Alchemist_return_arr));
				$last_thirty_Nurturer_return[$new_day] = round(get_percentage($last_thirty_Nurturer_question,$last_thirty_Nurturer_return_arr));
			}
			}else{
			$last_thirty_Warrior_return[$new_day] = 0;
			$last_thirty_Initiator_return[$new_day] = 0;
			$last_thirty_Ruler_return[$new_day] = 0;
			$last_thirty_Apprentice_return[$new_day] = 0;
			$last_thirty_Maverick_return[$new_day] = 0;
			$last_thirty_Victim_return[$new_day] = 0;
			$last_thirty_Alchemist_return[$new_day] = 0;
			$last_thirty_Nurturer_return[$new_day] = 0;	
		}
	}
}
$daily_max_value = max($last_thirty_days_new);
if($daily_max_value > 10){
	$daily_max_steps = round($daily_max_value/5);
}else{
	$daily_max_value = 10;
	$daily_max_steps = 2;
}
	$da_val=1;
	/*$last_thirty_days_new_rev = array_reverse($last_thirty_days_new, true);
	$last_thirty_days_new =$last_thirty_days_new_rev;
	$last_thirty_Warrior_return = array_reverse($last_thirty_Warrior_return, true);
	$last_thirty_Initiator_return =	array_reverse($last_thirty_Initiator_return, true);
	$last_thirty_Ruler_return =	array_reverse($last_thirty_Ruler_return, true);
	$last_thirty_Apprentice_return = array_reverse($last_thirty_Apprentice_return, true);
	$last_thirty_Maverick_return = array_reverse($last_thirty_Maverick_return, true);
	$last_thirty_Victim_return = array_reverse($last_thirty_Victim_return, true);
	$last_thirty_Alchemist_return =	array_reverse($last_thirty_Alchemist_return, true);
	$last_thirty_Nurturer_return = array_reverse($last_thirty_Nurturer_return, true);	
	 foreach($last_thirty_days_new_rev as $k=>$v){
		$last_week_days_new_new[$da_val] = $v;
		$da_val++;
	} */

switch($show_archetype){
	case $archetype_data[1]:
		$last_thirty_data_show = $last_thirty_Warrior_return;
	break;
	case $archetype_data[5]:
		$last_thirty_data_show = $last_thirty_Initiator_return;
	break;
	case $archetype_data[9]:
		$last_thirty_data_show = $last_thirty_Ruler_return;
	break;
	case $archetype_data[13]:
		$last_thirty_data_show = $last_thirty_Apprentice_return;
	break;
	case $archetype_data[17]:
		$last_thirty_data_show = $last_thirty_Maverick_return;
	break;
	case $archetype_data[21]:
		$last_thirty_data_show = $last_thirty_Victim_return;
	break;
	case $archetype_data[25]:
		$last_thirty_data_show = $last_thirty_Alchemist_return;
	break;
	case $archetype_data[29]:
		$last_thirty_data_show = $last_thirty_Nurturer_return;
	break;
}
  //echo 'here->'.$taken_week_days_ids[$new_day];
 /*  echo '<pre>';
  print_r($new_week_days_val);
  print_r($taken_week_days_ids); */
// 18 weeks data 
$last_week_days_new=array();
if(count($new_week_days_val) > 0){ 
	for($day=1;$day<19;$day++){
		$new_day = date('d-M-y',($last_week_days + ($day*24*60*60)));
		$new_n_day = date('Y-m-d',($last_week_days + ($day*7*24*60*60)));
		$date = new DateTime($new_n_day);
		$week = $date->format("W");
		$new_new_day = $week.'-week-'.date('y',strtotime($new_n_day));
		if(!array_key_exists($new_new_day ,$new_week_days_val)){
			$last_week_days_new[$new_new_day] = 0; // no quiz on this date
			$last_week_Warrior_return[$new_new_day] = 0;
			$last_week_Initiator_return[$new_new_day] = 0;
			$last_week_Ruler_return[$new_new_day] = 0;
			$last_week_Apprentice_return[$new_new_day] = 0;
			$last_week_Maverick_return[$new_new_day] = 0;
			$last_week_Victim_return[$new_new_day] = 0;
			$last_week_Alchemist_return[$new_new_day] = 0;
			$last_week_Nurturer_return[$new_new_day] = 0;
		}else{
			//$last_week_days_new[$new_new_day] = $week_days_val[$new_new_day];
			$last_week_days_new[$new_new_day] = $new_week_days_val[$new_new_day];
			if(count($taken_week_days_ids)>0 && isset($taken_week_days_ids[$new_new_day])){
				$sql_sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question,  mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE mq_r.Taken_ID IN(".implode(',',$taken_week_days_ids[$new_new_day]).") ORDER BY mq_r.Taken_ID ASC ";
				$sql_rows = $wpdb->get_results($sql_sql_qry, OBJECT);
				if($sql_rows){
					$last_week_Warrior_question = 0;
					$last_week_Warrior_return_arr = 0;
					$last_week_Initiator_question = 0;
					$last_week_Iniatiator_return_arr = 0;
					$last_week_Ruler_question = 0;
					$last_week_Ruler_return_arr = 0;
					$last_week_Apprentice_question = 0;
					$last_week_Apprentice_return_arr = 0;
					$last_week_Maverick_question = 0;
					$last_week_Maverick_return_arr = 0;
					$last_week_Victim_question = 0;
					$last_week_Victim_return_arr = 0;
					$last_week_Alchemist_question = 0;
					$last_week_Alchemist_return_arr = 0;
					$last_week_Nurturer_question = 0;
					$last_week_Nurturer_return_arr = 0;
					foreach($sql_rows as $row){
						switch($row->Archetype){
							case 1:
								$last_week_Warrior_question++;  
								$last_week_Warrior_return_arr += $row->Score;
							 break;
							case 5:
								$last_week_Initiator_question++; 
								$last_week_Iniatiator_return_arr += $row->Score;				
							 break;
							case 9:
								$last_week_Ruler_question++;  
								$last_week_Ruler_return_arr += $row->Score;
							 break;
							case 13:
								$last_week_Apprentice_question++;
								$last_week_Apprentice_return_arr += $row->Score;				
							 break;
							case 17:
								$last_week_Maverick_question++;
								$last_week_Maverick_return_arr += $row->Score;				
							 break;
							case 21:
								$last_week_Victim_question++;  
								$last_week_Victim_return_arr += $row->Score;
							 break;
							case 25:
								$last_week_Alchemist_question++;  
								$last_week_Alchemist_return_arr += $row->Score;
							 break;
							case 29:
								$last_week_Nurturer_question++;  
								$last_week_Nurturer_return_arr += $row->Score;
							 break;
						}
					}
					//	echo '<br>'.$new_day.'-441=>'.$last_week_Warrior_question .'-----'.$last_week_Warrior_return_arr;
					$last_week_Warrior_return[$new_new_day] = round(get_percentage($last_week_Warrior_question,$last_week_Warrior_return_arr));
					$last_week_Initiator_return[$new_new_day] = round(get_percentage($last_week_Initiator_question,$last_week_Iniatiator_return_arr));
					$last_week_Ruler_return[$new_new_day] = round(get_percentage($last_week_Ruler_question,$last_week_Ruler_return_arr));
					$last_week_Apprentice_return[$new_new_day] = round(get_percentage($last_week_Apprentice_question,$last_week_Apprentice_return_arr));
					$last_week_Maverick_return[$new_new_day] = round(get_percentage($last_week_Maverick_question,$last_week_Maverick_return_arr));
					$last_week_Victim_return[$new_new_day] = round(get_percentage($last_week_Victim_question,$last_week_Victim_return_arr));
					$last_week_Alchemist_return[$new_new_day] = round(get_percentage($last_week_Alchemist_question,$last_week_Alchemist_return_arr));
					$last_week_Nurturer_return[$new_new_day] = round(get_percentage($last_week_Nurturer_question,$last_week_Nurturer_return_arr));
				}
			}else{
				$last_week_Warrior_return[$new_new_day] = 0;
				$last_week_Initiator_return[$new_new_day] = 0;
				$last_week_Ruler_return[$new_new_day] = 0;
				$last_week_Apprentice_return[$new_new_day] = 0;
				$last_week_Maverick_return[$new_new_day] = 0;
				$last_week_Victim_return[$new_new_day] = 0;
				$last_week_Alchemist_return[$new_new_day] = 0;
				$last_week_Nurturer_return[$new_new_day] = 0;
			}
		}
	}
	  $we_val=1;
	//$last_week_days_new_rev = array_reverse($last_week_days_new);
	foreach($last_week_days_new as $k=>$v){
		$last_week_days_new_new[$we_val] = $v;
		$we_val++;
	}  
	//$last_week_days_new_new =  $last_week_days_new;
	switch($show_archetype){
		case $archetype_data[1]:
			$last_week_data_show = $last_week_Warrior_return;
		break;
		case $archetype_data[5]:
			//$last_week_data_show = array_reverse($last_week_Initiator_return);
			$last_week_data_show = $last_week_Initiator_return;
		break;
		case $archetype_data[9]:
			$last_week_data_show = $last_week_Ruler_return;
		break;
		case $archetype_data[13]:
			$last_week_data_show = $last_week_Apprentice_return;
		break;
		case $archetype_data[17]:
			$last_week_data_show = $last_week_Maverick_return;
		break;
		case $archetype_data[21]:
			$last_week_data_show = $last_week_Victim_return;
		break;
		case $archetype_data[25]:
			$last_week_data_show = $last_week_Alchemist_return;
		break;
		case $archetype_data[29]:
			$last_week_data_show = $last_week_Nurturer_return;
		break;
	}
}
$week_max_value = max($week_days_val);
if($week_max_value > 10){
	$week_max_steps = round($week_max_value/5);
}else{
	$week_max_value = 10;
	$week_max_steps = 2;
}
// echo '<hr>';
//print_r($last_week_Warrior_return);
 // 18 months data 
$last_month_days_new=array();
/*
for($day=1;$day<549;$day++){
	$new_day = date('d-M-y',($last_months_days + ($day*24*60*60)));
	if(!array_key_exists($new_day ,$months_days_val)){
		$last_month_days_new[$new_day] = 0; // no quiz on this date
		$new_last_month_days_new[$new_day] = 0; // no quiz on this date
		$last_month_Warrior_return[$new_day] = 0;
		$last_month_Initiator_return[$new_day] = 0;
		$last_month_Ruler_return[$new_day] = 0;
		$last_month_Apprentice_return[$new_day] = 0;
		$last_month_Maverick_return[$new_day] = 0;
		$last_month_Victim_return[$new_day] = 0;
		$last_month_Alchemist_return[$new_day] = 0;
		$last_month_Nurturer_return[$new_day] = 0;
	}else{
		//$last_month_days_new[$new_day] = $months_days_val[$new_day];
		$last_month_days_new[$new_day] = $months_days_val[$new_day];
		$sql_sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question,  mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE mq_r.Taken_ID IN(".implode(',',$taken_months_days_ids[$new_day]).") ORDER BY mq_r.Taken_ID ASC ";
		$sql_rows = $wpdb->get_results($sql_sql_qry, OBJECT);
		if($sql_rows){
			$last_month_Warrior_question = 0;
			$last_month_Warrior_return_arr = 0;
			$last_month_Initiator_question = 0;
			$last_month_Iniatiator_return_arr = 0;
			$last_month_Ruler_question = 0;
			$last_month_Ruler_return_arr = 0;
			$last_month_Apprentice_question = 0;
			$last_month_Apprentice_return_arr = 0;
			$last_month_Maverick_question = 0;
			$last_month_Maverick_return_arr = 0;
			$last_month_Victim_question = 0;
			$last_month_Victim_return_arr = 0;
			$last_month_Alchemist_question = 0;
			$last_month_Alchemist_return_arr = 0;
			$last_month_Nurturer_question = 0;
			$last_month_Nurturer_return_arr = 0;
			foreach($sql_rows as $row){
				switch($row->Archetype){
					case 1:
						$last_month_Warrior_question++;  
						$last_month_Warrior_return_arr += $row->Score;
					 break;
					case 5:
						$last_month_Initiator_question++; 
						$last_month_Iniatiator_return_arr += $row->Score;				
					 break;
					case 9:
						$last_month_Ruler_question++;  
						$last_month_Ruler_return_arr += $row->Score;
					 break;
					case 13:
						$last_month_Apprentice_question++;
						$last_month_Apprentice_return_arr += $row->Score;				
					 break;
					case 17:
						$last_month_Maverick_question++;
						$last_month_Maverick_return_arr += $row->Score;				
					 break;
					case 21:
						$last_month_Victim_question++;  
						$last_month_Victim_return_arr += $row->Score;
					 break;
					case 25:
						$last_month_Alchemist_question++;  
						$last_month_Alchemist_return_arr += $row->Score;
					 break;
					case 29:
						$last_month_Nurturer_question++;  
						$last_month_Nurturer_return_arr += $row->Score;
					 break;
				}
			}
			$last_month_Warrior_return[$new_day] = round(get_percentage($last_month_Warrior_question,$last_month_Warrior_return_arr));
			$last_month_Initiator_return[$new_day] = round(get_percentage($last_month_Initiator_question,$last_month_Iniatiator_return_arr));
			$last_month_Ruler_return[$new_day] = round(get_percentage($last_month_Ruler_question,$last_month_Ruler_return_arr));
			$last_month_Apprentice_return[$new_day] = round(get_percentage($last_month_Apprentice_question,$last_month_Apprentice_return_arr));
			$last_month_Maverick_return[$new_day] = round(get_percentage($last_month_Maverick_question,$last_month_Maverick_return_arr));
			$last_month_Victim_return[$new_day] = round(get_percentage($last_month_Victim_question,$last_month_Victim_return_arr));
			$last_month_Alchemist_return[$new_day] = round(get_percentage($last_month_Alchemist_question,$last_month_Alchemist_return_arr));
			$last_month_Nurturer_return[$new_day] = round(get_percentage($last_month_Nurturer_question,$last_month_Nurturer_return_arr));
		}
	}
}
*/
 //echo '<pre><hr>***********';
// print_r($new_months_days_val);
 //print_r($taken_week_days_ids);
for($day=1;$day<19;$day++){
	$new_day = date('d-M-y',($last_months_days + ($day*24*60*60)));
	$new_new_day = date('M-y',($last_months_days + ($day*30.5*24*60*60)));
	//if(!array_key_exists($new_day ,$months_days_val)){
	if(!array_key_exists($new_new_day ,$new_months_days_val)){
		$last_month_days_new[$new_new_day] = 0; // no quiz on this date
		$new_last_month_days_new[$new_new_day] = 0; // no quiz on this date
		$last_month_Warrior_return[$new_new_day] = 0;
		$last_month_Initiator_return[$new_new_day] = 0;
		$last_month_Ruler_return[$new_new_day] = 0;
		$last_month_Apprentice_return[$new_new_day] = 0;
		$last_month_Maverick_return[$new_new_day] = 0;
		$last_month_Victim_return[$new_new_day] = 0;
		$last_month_Alchemist_return[$new_new_day] = 0;
		$last_month_Nurturer_return[$new_new_day] = 0;
	}else{
		$last_month_days_new[$new_new_day] = $months_days_val[$new_new_day];
		$new_last_month_days_new[$new_new_day] = $new_months_days_val[$new_new_day];
		if(count($new_taken_months_days_ids)>0 && isset($new_taken_months_days_ids[$new_new_day])){
			$sql_sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question,  mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE mq_r.Taken_ID IN(".implode(',',$new_taken_months_days_ids[$new_new_day]).") ORDER BY mq_r.Taken_ID ASC ";
			$sql_rows = $wpdb->get_results($sql_sql_qry, OBJECT);
			if($sql_rows){
				$last_month_Warrior_question = 0;
				$last_month_Warrior_return_arr = 0;
				$last_month_Initiator_question = 0;
				$last_month_Iniatiator_return_arr = 0;
				$last_month_Ruler_question = 0;
				$last_month_Ruler_return_arr = 0;
				$last_month_Apprentice_question = 0;
				$last_month_Apprentice_return_arr = 0;
				$last_month_Maverick_question = 0;
				$last_month_Maverick_return_arr = 0;
				$last_month_Victim_question = 0;
				$last_month_Victim_return_arr = 0;
				$last_month_Alchemist_question = 0;
				$last_month_Alchemist_return_arr = 0;
				$last_month_Nurturer_question = 0;
				$last_month_Nurturer_return_arr = 0;
				foreach($sql_rows as $row){
					switch($row->Archetype){
						case 1:
							$last_month_Warrior_question++;  
							$last_month_Warrior_return_arr += $row->Score;
						 break;
						case 5:
							$last_month_Initiator_question++; 
							$last_month_Iniatiator_return_arr += $row->Score;				
						 break;
						case 9:
							$last_month_Ruler_question++;  
							$last_month_Ruler_return_arr += $row->Score;
						 break;
						case 13:
							$last_month_Apprentice_question++;
							$last_month_Apprentice_return_arr += $row->Score;				
						 break;
						case 17:
							$last_month_Maverick_question++;
							$last_month_Maverick_return_arr += $row->Score;				
						 break;
						case 21:
							$last_month_Victim_question++;  
							$last_month_Victim_return_arr += $row->Score;
						 break;
						case 25:
							$last_month_Alchemist_question++;  
							$last_month_Alchemist_return_arr += $row->Score;
						 break;
						case 29:
							$last_month_Nurturer_question++;  
							$last_month_Nurturer_return_arr += $row->Score;
						 break;
					}
				}
				$last_month_Warrior_return[$new_new_day] = round(get_percentage($last_month_Warrior_question,$last_month_Warrior_return_arr));
				$last_month_Initiator_return[$new_new_day] = round(get_percentage($last_month_Initiator_question,$last_month_Iniatiator_return_arr));
				$last_month_Ruler_return[$new_new_day] = round(get_percentage($last_month_Ruler_question,$last_month_Ruler_return_arr));
				$last_month_Apprentice_return[$new_new_day] = round(get_percentage($last_month_Apprentice_question,$last_month_Apprentice_return_arr));
				$last_month_Maverick_return[$new_new_day] = round(get_percentage($last_month_Maverick_question,$last_month_Maverick_return_arr));
				$last_month_Victim_return[$new_new_day] = round(get_percentage($last_month_Victim_question,$last_month_Victim_return_arr));
				$last_month_Alchemist_return[$new_new_day] = round(get_percentage($last_month_Alchemist_question,$last_month_Alchemist_return_arr));
				$last_month_Nurturer_return[$new_new_day] = round(get_percentage($last_month_Nurturer_question,$last_month_Nurturer_return_arr));
			}
		}else{
			$last_month_Warrior_return[$new_new_day] = 0;
			$last_month_Initiator_return[$new_new_day] = 0;
			$last_month_Ruler_return[$new_new_day] = 0;
			$last_month_Apprentice_return[$new_new_day] = 0;
			$last_month_Maverick_return[$new_new_day] = 0;
			$last_month_Victim_return[$new_new_day] = 0;
			$last_month_Alchemist_return[$new_new_day] = 0;
			$last_month_Nurturer_return[$new_new_day] = 0;
		}
	}
}
/* $new_last_month_days_new_rev = array_reverse($new_last_month_days_new, true);
$new_last_month_days_new = $new_last_month_days_new_rev;
	$last_month_Warrior_return = array_reverse($last_month_Warrior_return, true);
	$last_month_Initiator_return =	array_reverse($last_month_Initiator_return, true);
	$last_month_Ruler_return =	array_reverse($last_month_Ruler_return, true);
	$last_month_Apprentice_return = array_reverse($last_month_Apprentice_return, true);
	$last_month_Maverick_return = array_reverse($last_month_Maverick_return, true);
	$last_month_Victim_return = array_reverse($last_month_Victim_return, true);
	$last_month_Alchemist_return =	array_reverse($last_month_Alchemist_return, true);
	$last_month_Nurturer_return = array_reverse($last_month_Nurturer_return, true);	
	 */
switch($show_archetype){
	case $archetype_data[1]:
		$last_month_data_show = $last_month_Warrior_return;
	break;
	case $archetype_data[5]:
		$last_month_data_show = $last_month_Initiator_return;
	break;
	case $archetype_data[9]:
		$last_month_data_show = $last_month_Ruler_return;
	break;
	case $archetype_data[13]:
		$last_month_data_show = $last_month_Apprentice_return;
	break;
	case $archetype_data[17]:
		$last_month_data_show = $last_month_Maverick_return;
	break;
	case $archetype_data[21]:
		$last_month_data_show = $last_month_Victim_return;
	break;
	case $archetype_data[25]:
		$last_month_data_show = $last_month_Alchemist_return;
	break;
	case $archetype_data[29]:
		$last_month_data_show = $last_month_Nurturer_return;
	break;
}
$monthly_max_value = max($new_months_days_val);
if($monthly_max_value > 10){
	$monthly_max_steps = round($monthly_max_value/5);
}else{
	$monthly_max_value = 10;
	$monthly_max_steps = 2;
}
// echo '<pre>';
// print_r($last_month_Warrior_return);
//die;
 /*  echo '<pre>';
	print_r($last_month_days_new);
	die;   */
$week_days = array('1','2');
?>
	<script>
		var color = Chart.helpers.color;
		var barChartData1 = {
			labels: ['<?php echo implode("','",array_keys($last_thirty_days_new));?>'],
			datasets: [{
					label: 'Daily Data',
					backgroundColor: '#34495e',
					borderColor: '#34495e',
					borderWidth: 1,
					data: [
						<?php echo implode(",",$last_thirty_days_new)?>
					]
				}]
			};
		var color = Chart.helpers.color;
		var barChartData = {
			labels: ['<?php echo implode("','",array_keys($last_week_days_new_new));?>'],
			datasets: [{
					label: 'Weekly Stats',
					backgroundColor: '#008000',
					borderColor: '#008000',
					borderWidth: 1,
					data: [
						<?php echo implode(",",$last_week_days_new_new)?>
					]
				}]
			};
	// monthly data
		var color = Chart.helpers.color;
		var barChartData2 = {
			labels: ['<?php echo implode("','",array_keys($new_last_month_days_new));?>'],
			datasets: [{
					label: 'Monthly Stats',
					backgroundColor: '#008080',
					borderColor: '#008080',
					borderWidth: 1,
					data: [
						<?php echo implode(",",$new_last_month_days_new)?>
					]
				}]
			};
// archetype line graph DAY			
		var barChartData3 = {
			labels: ['<?php echo implode("','",array_keys($last_thirty_days_new));?>'],
			datasets: [{
					label: '<?php echo $show_archetype?> stats',
					backgroundColor: '#008000',
					borderColor: '#008000',
					fill: false,
					borderWidth: 1,
					borderDash: [5, 5],
					data: [
						'<?php echo implode("','",$last_thirty_data_show);?>'
					]
				}]
			};
// archetype line graph week			
		var barChartData4 = {
			labels: ['<?php echo implode("','",array_keys($last_week_days_new_new));?>'],
			datasets: [{
					label: '<?php echo $show_archetype?> stats',
					backgroundColor: '#008000',
					borderColor: '#008000',
					fill: false,
					borderWidth: 1,
					borderDash: [5, 5],
					data: [
						'<?php echo implode("','",$last_week_data_show);?>'
					]
				}]
			};
// archetype line graph MONTH			
		var barChartData5 = {
			labels: ['<?php echo implode("','",array_keys($new_last_month_days_new));?>'],
			datasets: [{
					label: '<?php echo $show_archetype?> stats',
					backgroundColor: '#008000',
					borderColor: '#008000',
					fill: false,
					borderWidth: 1,
					borderDash: [5, 5],
					data: [
						'<?php echo implode("','",$last_month_data_show);?>'
					]
				}]
			};
		window.onload = function() {
			<?php echo $show_function_values ; ?>
			var ctx = document.getElementById('canvas_day').getContext('2d');
			window.myBar = new Chart(ctx, {
				type: 'bar',
				data: barChartData1,
				options: {
					responsive: true,
					legend: {
						position: 'bottom',
					},
					title: {
						display: false,
						text: ' MoneyQuiz Results'
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
								max: <?php echo $daily_max_value?>,
								// forces step size to be 5 units
								stepSize: <?php echo $daily_max_steps?>
							}
						}]
					}
				}
			});
			var ctx1 = document.getElementById('canvas_week').getContext('2d');
			window.myBar = new Chart(ctx1, {
				type: 'bar',
				data: barChartData,
				options: {
					responsive: true,
					legend: {
						position: 'bottom',
					},
					title: {
						display: false,
						text: ' MoneyQuiz Results'
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
								max: <?php echo $week_max_value?>,
								// forces step size to be 5 units
								stepSize: <?php echo $week_max_steps?>
							}
						}]
					}
				}
			});	
			var ctx2 = document.getElementById('canvas_month').getContext('2d');
			window.myBar = new Chart(ctx2, {
				type: 'bar',
				data: barChartData2,
				options: {
					responsive: true,
					legend: {
						position: 'bottom',
					},
					title: {
						display: false,
						text: ' MoneyQuiz Results'
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
								max: <?php echo $monthly_max_value?>,
								// forces step size to be 5 units
								stepSize: <?php echo $monthly_max_steps?>
							}
						}]
					}
				}
			});	
			var ctx3 = document.getElementById('canvas_line_day').getContext('2d');
			window.myBar = new Chart(ctx3, {
				type: 'line',
				data: barChartData3,
				options: {
					responsive: true,
					legend: {
						position: 'bottom',
					},
					title: {
						display: false,
						text: ' MoneyQuiz Results'
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
								stepSize: 10
							}
						}]
					}
				}
			});
			var ctx4 = document.getElementById('canvas_line_week').getContext('2d');
			window.myBar = new Chart(ctx4, {
				type: 'line',
				data: barChartData4,
				options: {
					responsive: true,
					legend: {
						position: 'bottom',
					},
					title: {
						display: false,
						text: ' MoneyQuiz Results'
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
								// forces step size to be 5 units
								stepSize: 10
							}
						}]
					}
				}
			});
			var ctx5 = document.getElementById('canvas_line_month').getContext('2d');
			window.myBar = new Chart(ctx5, {
				type: 'line',
				data: barChartData5,
				options: {
					responsive: true,
					legend: {
						position: 'bottom',
					},
					title: {
						display: false,
						text: ' MoneyQuiz Results'
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
								// forces step size to be 5 units
								stepSize: 10
							}
						}]
					}
				}
			});
		};
		var colorNames = Object.keys(window.chartColors);
	</script>
<br> 
</div>
<!-- .wrap -->