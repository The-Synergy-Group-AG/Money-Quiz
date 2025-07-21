<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/

$version = $taken_date = $email = $surname = $first_name = $where = "";
if(isset($_REQUEST['prospect']) && $_REQUEST['prospect'] > 0){
	$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." where Prospect_ID=".$_REQUEST['prospect']." " ;
	$row = $wpdb->get_row($sql, OBJECT);
	$email = $row->Email;
	$surname = $row->Surname;
	$first_name = $row->Name;
	// get all results of prospect 
	$sql1 = "SELECT * FROM ".$table_prefix.TABLE_MQ_TAKEN." where Prospect_ID=".$_REQUEST['prospect']." order by Taken_ID ASC " ;
	$rows = $wpdb->get_results($sql1, OBJECT);
	
}
	
 ?>
 
<div class=" mq-container quiz_page">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<h3>Quiz Reports</h3>
	<?php echo $save_msg ?>
	<div class="clear"></div> 
	<?php if(isset($_POST['email_report']) ){ ob_start(); } ?>
	<table class="form-table mq-form-table-reports mq-form-short-table">
		<tbody>
			<tr>
				<th style="min-width:200px;text-align:left;">First Name</th>
				<th style="min-width:200px;text-align:left;">Surname</th>
				<th style="min-width:200px;text-align:left;">Email</th>
				<th style="min-width:200px;text-align:center;background: none;"><a href="<?php echo $admin_page_url?>mq_reports&prospect=<?php echo $_REQUEST['prospect']?>" class="button" style="background: green;color: #fff;padding: 0 30px;border: 1px solid green;"> Back </a></th>
			</tr>
			<tr>
				<td><?php echo $first_name?></td>
				<td><?php echo $surname?></td>
				<td><?php echo $email?></td>
				<td></td>
			</tr>
		</tbody>
	</table>
	 			
	<div class="clear"></div>
	<div class="mq">
		
<?php 
$mq_form_short_table = "mq-form-short-table";
if(isset($_REQUEST['Taken_ID'])){
	$t_date = array();
	$sql_qry = 1;
	
	$n_n_arr = $_REQUEST['Taken_ID'];
	asort($n_n_arr);
	if(count($_REQUEST['Taken_ID'])>0){
		/* foreach($_REQUEST['Taken_ID'] as $t_id){
			$n_tid = explode('__',$t_id);
			$t_date[$n_tid[0]] = $n_tid[1];
			$t_arr[] = $n_tid[0];
			
			$sql_rs_.$sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question,  mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE  mq_r.Prospect_ID=".$_REQUEST['prospect']." and mq_r.Taken_ID IN($n_tid[0]) ORDER BY mq_r.Taken_ID ASC ";
			$sql_rows[$n_tid[0]] = $wpdb->get_results($sql_rs_.$sql_qry, OBJECT);
			$sql_qry++;
			if(count($t_arr) > 2){
				$mq_form_short_table = "";
			}
		} */
		
		foreach($n_n_arr as $t_id){
			$n_tid = explode('__',$t_id);
			$t_date[$n_tid[2]] = $n_tid[1];
			$t_arr[] = $n_tid[2];
			
			$sql_rs_.$sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question,  mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE  mq_r.Prospect_ID=".$_REQUEST['prospect']." and mq_r.Taken_ID IN($n_tid[2]) ORDER BY mq_r.Taken_ID ASC ";
			$sql_rows[$n_tid[2]] = $wpdb->get_results($sql_rs_.$sql_qry, OBJECT);
			$sql_qry++;
			if(count($t_arr) > 2){
				$mq_form_short_table = "";
			}
		} 
		//echo '<pre>';
		//print_r($sql_rows);
		$Alchemist_score = $Alchemist_question = $Victim_score = $Victim_question = $Maverick_score = $Maverick_question = $Apprentice_score = $Apprentice_question = $Nurturer_score = $Nurturer_question = $Ruler_score = $Ruler_question = $Warrior_score = $Warrior_question = $Initiator_score = $Initiator_question = 0;
		$Alchemist_return =$Victim_return =$Maverick_return =$Apprentice_return =$Ruler_return =$Nurturer_return =$Initiator_return =$Warrior_return = 0;	
		$detailed_summary_rows = "";
		$new_arr = 1;
		$new_tr = 0;
		$table_row = '';
		$Alchemist_return_arr = $Victim_return_arr = $Maverick_return_arr = $Apprentice_return_arr = $Ruler_return_arr = $Warrior_return_arr = $Nurturer_return_arr = $Iniatiator_return_arr = array();
		
		$Warrior_chart_arr = $Initiator_chart_arr = $Ruler_chart_arr = $Apprentice_chart_arr = $Maverick_chart_arr = $Victim_chart_arr = $Alchemist_chart_arr = $Nurturer_chart_arr = array();
//	echo '<pre>';
	//print_r($t_arr);
	//echo '<hr> ****************** ';
	$n_t_id=0;
		foreach($sql_rows as $n_row){
			foreach($n_row as $n_r){
				$new_result_sets[$t_arr[$n_t_id]][$n_r->Master_ID] = $n_r;
				
			}
			$n_t_id++;
			//print_r($n_row);
			//$new_result_sets[$t_arr[$n_t_id]][$n_row->Master_ID] = $n_row;
			
			//break;
		}
		//echo '<hr> ****************** ';
		//echo '==>>>>>'.count($new_result_sets); 	
		//print_r($new_result_sets);
		
		if($sql_rows){
			//foreach($t_arr as $tid){
				foreach($new_result_sets as $nn_row){
					foreach($nn_row as $row ){
						//$new_result_sets[3][$row->Master_ID]->Score.'===>masterid->'.$row->Master_ID;
						$str = '<tr><td>'.$row->ID_Unique.'</td><td>'.$row->Question.'</td><td>'.$post_data[$row->Archetype].'</td>';
						foreach($t_arr as $tidn){
								$str .= '<td>'.$new_result_sets[$tidn][$row->Master_ID]->Score.'</td>';
								//echo '<br>-->'.$row->ID_Unique.'===>>'.print_r($sql_rows[$tidn],1);
								/* if(in_array($row->ID_Unique, $sql_rows[$tidn])){
									//echo '<br>-->'.$row->Master_ID.'===>>'.$sql_rows[$tidn][$new_tr]->Master_ID;
									$str .= '<td>'.$sql_rows[$tidn][$new_tr]->Score.'</td>';
								}else{
									$str .= '<td>&nbsp;</td>';
								}  */
						 
								if($new_result_sets[$tidn][$row->Master_ID]->Score == ''){
									continue;
								}
								if($row->Archetype == 1){ //Warrior
									$Warrior_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
									$Warrior_question_arr[$tidn] +=1 ;  
								}
								if($row->Archetype == 5){ // Iniatiator
									$Iniatiator_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
									$Initiator_question_arr[$tidn] +=1;
								}
								if($row->Archetype == 9){ // Ruler
									$Ruler_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
									$Ruler_question_arr[$tidn] +=1;
								}
								if($row->Archetype == 13){ // Apprentice
									$Apprentice_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
									$Apprentice_question_arr[$tidn] +=1;
								}
								if($row->Archetype == 17){ // Maverick
									$Maverick_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
									$Maverick_question_arr[$tidn] +=1;
								}
								if($row->Archetype == 21){ //Victim
									$Victim_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
									$Victim_question_arr[$tidn] +=1;
								}
								if($row->Archetype == 25){ //Alchemist
									$Alchemist_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
									$Alchemist_question_arr[$tidn] +=1;
								}
								if($row->Archetype == 29){ // Nurturer
									$Nurturer_return_arr[$tidn] += $new_result_sets[$tidn][$row->Master_ID]->Score;
									$Nurturer_question_arr[$tidn] +=1;
								}
							
						}
						$str .= '</tr>';
						$table_row .= $str;
						
						$new_tr++;
					}
					break;
				}
				$new_arr++;
			}		 
			 
		//}
		
	/*	if($sql_rows){
			foreach($t_arr as $tid){
				foreach($sql_rows[$tid] as $row){
					$str = '<tr><td>'.$row->ID_Unique.'</td><td>'.$row->Question.'</td><td>'.$post_data[$row->Archetype].'</td>';
					foreach($t_arr as $tidn){
						
							
							$str .= '<td>'.$sql_rows[$tidn][$new_tr]->Score.'</td>';
							 
					 
							if($sql_rows[$tidn][$new_tr]->Score == ''){
								continue;
							}
							if($row->Archetype == 1){ //Warrior
								$Warrior_return_arr[$tidn] += $sql_rows[$tidn][$new_tr]->Score;
								$Warrior_question_arr[$tidn] +=1 ;  
							}
							if($row->Archetype == 5){ // Iniatiator
								$Iniatiator_return_arr[$tidn] += $sql_rows[$tidn][$new_tr]->Score;
								$Initiator_question_arr[$tidn] +=1;
							}
							if($row->Archetype == 9){ // Ruler
								$Ruler_return_arr[$tidn] += $sql_rows[$tidn][$new_tr]->Score;
								$Ruler_question_arr[$tidn] +=1;
							}
							if($row->Archetype == 13){ // Apprentice
								$Apprentice_return_arr[$tidn] += $sql_rows[$tidn][$new_tr]->Score;
								$Apprentice_question_arr[$tidn] +=1;
							}
							if($row->Archetype == 17){ // Maverick
								$Maverick_return_arr[$tidn] += $sql_rows[$tidn][$new_tr]->Score;
								$Maverick_question_arr[$tidn] +=1;
							}
							if($row->Archetype == 21){ //Victim
								$Victim_return_arr[$tidn] += $sql_rows[$tidn][$new_tr]->Score;
								$Victim_question_arr[$tidn] +=1;
							}
							if($row->Archetype == 25){ //Alchemist
								$Alchemist_return_arr[$tidn] += $sql_rows[$tidn][$new_tr]->Score;
								$Alchemist_question_arr[$tidn] +=1;
							}
							if($row->Archetype == 29){ // Nurturer
								$Nurturer_return_arr[$tidn] += $sql_rows[$tidn][$new_tr]->Score;
								$Nurturer_question_arr[$tidn] +=1;
							}
						
					}
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
				break;
			} 
		} 
		*/
		 
	
	?>			
			
		<div class="include_summary" <?php 	if(isset($_POST['email_report']) && !isset($_POST['summary'])){ echo 'style="display:none;"'; } ?> >
			<h4>Summary Results</h4>
			<table class="form-table mq-form-table-reports <?php echo $mq_form_short_table;?>">
				<tbody>
					<tr>
						<th style="min-width:150px;text-align:left;">Archetype</th>
						<?php foreach($t_date as $taken_date){ ?>
							<th style="min-width:100px;text-align:left;"><?php echo  str_replace('~',' (',$taken_date) ?>) </th>
						<?php } ?>
					</tr>
					<tr>
						<td><?php echo $post_data[1];?> </td>
						<?php foreach($Warrior_return_arr as $k=>$Warrior_score){
							$Warrior_return = get_percentage($Warrior_question_arr[$k],$Warrior_score);
							$Warrior_chart_arr[] = round($Warrior_return);
							echo '<td>'. round($Warrior_return).'%</td>';
						}
						?>
				 
					</tr>
					<tr>
						<td><?php echo $post_data[5];?></td>
						 <?php foreach($Iniatiator_return_arr as $k=>$Initiator_score){
							$Initiator_return = get_percentage($Initiator_question_arr[$k],$Initiator_score);
							$Initiator_chart_arr[] = round($Initiator_return);
							echo '<td>'. round($Initiator_return).'%</td>';
						}  ?>
					</tr>				
					<tr>
						<td><?php echo $post_data[9];?></td>
						 <?php foreach($Ruler_return_arr as $k=>$ques_score){
							$Ruler_return = get_percentage($Ruler_question_arr[$k],$ques_score);
							$Ruler_chart_arr[] = round($Ruler_return);
							echo '<td>'. round($Ruler_return).'%</td>';
						}  ?>
					</tr>
					<tr>
						<td><?php echo $post_data[13];?></td>
						 <?php foreach($Apprentice_return_arr as $k=>$ques_score){
							$Apprentice_return = get_percentage($Apprentice_question_arr[$k],$ques_score);
							$Apprentice_chart_arr[] = round($Apprentice_return);
							echo '<td>'. round($Apprentice_return).'%</td>';
						}  ?>
					</tr>				
					<tr>
						<td><?php echo $post_data[17];?></td>
						 <?php foreach($Maverick_return_arr as $k=>$ques_score){
							$Maverick_return = get_percentage($Maverick_question_arr[$k],$ques_score);
							$Maverick_chart_arr[] = round($Maverick_return);
							echo '<td>'. round($Maverick_return).'%</td>';
						}  ?>
					</tr>
					<tr>
						<td><?php echo $post_data[21];?></td>
						 <?php foreach($Victim_return_arr as $k=>$ques_score){
							$Victim_return = get_percentage($Victim_question_arr[$k],$ques_score);
							$Victim_chart_arr[] = round($Victim_return);
							echo '<td>'. round($Victim_return).'%</td>';
						}  ?>
					</tr>
					<tr>
						<td><?php echo $post_data[25];?></td>
						 <?php foreach($Alchemist_return_arr as $k=>$ques_score){
							$Alchemist_return = get_percentage($Alchemist_question_arr[$k],$ques_score);
							$Alchemist_chart_arr[] = round($Alchemist_return);
							echo '<td>'. round($Alchemist_return).'%</td>';
						}  ?>
					</tr>
					<tr>
						<td><?php echo $post_data[29];?></td>
						 <?php foreach($Nurturer_return_arr as $k=>$ques_score){
							$Nurturer_return = get_percentage($Nurturer_question_arr[$k],$ques_score);
							$Nurturer_chart_arr[] = round($Nurturer_return);
							echo '<td>'. round($Nurturer_return).'%</td>';
						}  ?>
					</tr>
				</tbody>
			</table>
			<div class="clear"></div>
			<div id="container" class="bar_chart_container">
				<canvas id="canvas"></canvas>
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
	foreach($t_date as $taken_date){ 
	  
	$chart_values[]  = "{
					label: '".str_replace('~',' (',$taken_date).")',
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
	$temp_id++;			
	} 
						

?>		
	<script>
		var color = Chart.helpers.color;
		var barChartData = {
			labels: ['<?php echo $post_data[1];?>', '<?php echo $post_data[5];?>', '<?php echo $post_data[9];?>', '<?php echo $post_data[13];?>', '<?php echo $post_data[17];?>', '<?php echo $post_data[21];?>', '<?php echo $post_data[25];?>', '<?php echo $post_data[29];?>'],
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
								stepSize: 25
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
		 
		<div class="include_details" <?php if(isset($_POST['email_report']) && !isset($_POST['details'])){ echo 'style="display:none;"'; } ?> >
			<h4>MoneyQuiz Detailed Results</h4>
			<table class="form-table mq-form-table-reports ">
				<tbody>
					<tr>
						<th style="min-width:80px;text-align:left;">ID</th>
						<th style="min-width:200px;text-align:left;">Key Phrase</th>
						<th style="min-width:150px;text-align:left;">Archetype</th>
						<?php foreach($t_date as $taken_date){
							echo '<th style="min-width:100px;text-align:left;">'.str_replace('~',' (',$taken_date).')</th>';
						}?>						
					</tr>	
					<?php echo $table_row?>
				</tbody>
			</table>
		</div>
		<div class="clear"></div>
		<?php  
		if(isset($_POST['email_report'])){
			
			if($all_values[9] != ""){
				$to = $all_values[9]; // prospect email's address 
				$subject = 'Here are MoneyQuiz Results!';
				$body = "Dear ".$all_values[2].",<br>"; 
				$body .= "<p>Here are the results from the MoneyQuiz taken by:</p>"; 
				
				$body .= ob_get_contents();  // detailed result 
				
				$body .= '<br><p>Thank you for using the MoneyQuiz.</p>';
				$body .= '<p>Powered by Business Insights Group AG<br> Zurich, Switzerland</p>';
				$body .= '<img src="'.plugins_url('assets/images/money_coach_signature.png', __FILE__).'" > <br/>';  // Money Coach logo
				
				$headers = array('Content-Type: text/html; charset=UTF-8');
				$headers[] = 'From: Money Quiz <no-reply@101businessinsights.com>';  
				
				wp_mail( $to, $subject, $body, $headers );
				echo $save_msg = "<div class='data_saved data_saved_new'>Report has been sent to ".$all_values[9]." address.</div>";
			}else{
				echo $save_msg = "<div class='data_saved data_saved_new'>Please add money coach email address to receive results email.</div>";
			}
	
		}
		?>	
		<form name="email_report" action="" method="post">			
			<table class="form-table mq-form-table-reports noprint">
				<tbody>
					<tr>
						<td><input type="checkbox" checked="checked"  value="summary" name="summary" onclick="include_summary();"> Include Money Type Quiz Summary </td>
					</tr>
					<tr>				
						<td><input type="checkbox" checked="checked"  value="details" name="details" onclick="include_details();"> Include Money Type Quiz Details </td>
					</tr>
					<tr>
						<td  align="left">
							<div class="show-mq-errorsaa mq-hide"></div>
							<input name="submit" id="button" class="button button-primary" value="Print Report" onclick="window.print();" type="button"> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
							<input name="submit" id="button" class="button button-primary" value="Email Report" type="submit">
							<input type="hidden" name="email_report" value="1">
						</td>
					</tr>				
				</tbody>
			</table>
		</form>			
<?php } // if count token id 
}// if isset token id 
 ?>
	</div>	

	
</div>
<!-- .wrap -->