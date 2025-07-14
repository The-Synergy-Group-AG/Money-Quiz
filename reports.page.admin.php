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
	<form method="get" action="" id="report_selected_form" onsubmit="return report_selected();">
		<input type="hidden" name="page" value="mq_reports">
		<input type="hidden" name="prospect" value="<?php echo $_REQUEST['prospect']?>">
		<table class="form-table mq-form-table-reports mq-form-short-table">
			<tbody>
				<tr>
					<th style="min-width:200px;text-align:left;">First Name</th>
					<th style="min-width:200px;text-align:left;">Surname</th>
					<th style="min-width:200px;text-align:left;">Email</th>
					<th style="min-width:200px;text-align:center;background: none;"><a href="<?php echo $admin_page_url?>mq_reports" class="button" style="background: green;color: #fff;padding: 0 30px;border: 1px solid green;"> Back </a></th>
				</tr>
				<tr>
					<td><?php echo $first_name?></td>
					<td><?php echo $surname?></td>
					<td><?php echo $email?></td>
				</tr>
			</tbody>
		</table>	
		<div class="clear"></div> 
		<div class="">
		<h4>Select the Quiz results you wish to view. To compare, select two or more options</h4>
		<table class="form-table mq-form-table-reports mq-form-short-table">
			<tbody>
				<tr>
					<th>Quiz Taken on</th>
					<th>Quiz Lenght</th>
					<th>Select</th>
				</tr>
				<?php if($rows){
					foreach($rows as $row){
						if($row->Quiz_Length == "full")
							$abc= 'a';
						if($row->Quiz_Length == "classic")
							$abc= 'b';
						if($row->Quiz_Length == "short")
							$abc= 'c';
						if($row->Quiz_Length == "blitz")
							$abc= 'd';
					?>
				<tr>
					<td><?php echo $row->Date_Taken?></td>
					<td><?php echo ucfirst($row->Quiz_Length)?></td>
					<td><input type="checkbox" name="Taken_ID[]" data-versiontype="<?php echo $row->Quiz_Length;?>" value="<?php echo $abc ;?>__<?php echo $row->Date_Taken?>~<?php echo ucfirst($row->Quiz_Length)?>__<?php echo $row->Taken_ID;?>" > </td>
				</tr>
					<?php } ?>
				<tr>
					<td colspan="3" align="center">
						<div class="show-mq-errors mq-hide"></div>
						<input name="submit" id="submit" class="button button-primary" value="Show Report" type="submit">
					</td>
				</tr>
					<?php 
				}else{ ?>
				<tr>
					<td colspan="3" align="center">
						<h3 class="no_results">Sorry no result found.<h3>
					</td>
				</tr>
				<?php } ?>
			</tbody>
		</table>	
		</div>
	</form>
	<div class="clear"></div>
	 
</div>
<!-- .wrap -->