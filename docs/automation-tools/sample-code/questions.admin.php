<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
global $wpdb;
$table_prefix = $wpdb->prefix;
$Example = $Definition = $Question = $where = "";
if(isset($_REQUEST['submit']) && $_REQUEST['submit'] == "Search"){
	$where = "";
	$where_arr = array();
	if(isset($_REQUEST['Question']) && $_REQUEST['Question'] !=""){
		$where_arr[] = " Question like '%".sanitize_text_field($_REQUEST['Question'])."%'";
		$Question = sanitize_text_field($_REQUEST['Question']);
	}
	if(isset($_REQUEST['Definition']) && $_REQUEST['Definition'] !=""){
		$where_arr[] = " Definition like '%".sanitize_text_field($_REQUEST['Definition'])."%'";
		$Definition = sanitize_text_field($_REQUEST['Definition']);
	}
	if(isset($_REQUEST['Example']) && $_REQUEST['Example'] !=""){
		$where_arr[] = " Example like '%".sanitize_text_field($_REQUEST['Example'])."%'";
		$Example = sanitize_text_field($_REQUEST['Example']);
	}
	if(count($where_arr)>0){
		$where = " where  ".implode(' or ',$where_arr);
	}	
}
if(isset($_REQUEST['questionid']) && $_REQUEST['questionid'] > 0 ){
	$where = " where Master_ID = ".$_REQUEST['questionid']; 
}

$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_MASTER."  $where  " ;
$rows = $wpdb->get_results($sql, OBJECT);
 

 ?>
 
<div class=" mq-container quiz_page">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<h3>Questions</h3>
	<?php echo $save_msg ?>
	<div class="clear"></div> 
<?php  if(isset($_REQUEST['questionid'])){ 
$row = $wpdb->get_row($sql, OBJECT);
?>
	<form method="post" action="" >
		<input type="hidden" name="page" value="mq_questions">
		<div class="clear"></div> 
		<table class="form-table mq-form-table ">
			<tbody>
				<tr>
					<th scope="row"><label for="Question">Question</label></th>
					<td><textarea rows="2"  name="Question" id="Question" cols="75"><?php echo stripslashes($row->Question)?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="Definition">Definition</label></th>
					<td><textarea rows="5"  name="Definition" id="Definition" cols="75"><?php echo stripslashes($row->Definition)?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="Example">Example</label></th>
					<td><textarea rows="5"  name="Example" id="Example" cols="75"><?php echo stripslashes($row->Example)?></textarea></td>
				</tr>
				<tr>
					<td></td><td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"> 
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?php echo $admin_page_url?>mq_questions" class="button" style="background: green;color: #fff;padding: 0 30px;border: 1px solid green;"> Back </a></p>
					</td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="update_question" value="1" >	
		<input type="hidden" name="questionid" value="<?php echo $_REQUEST['questionid'];?>" >	
	</form>
	<div class="clear"></div> 
<?php }else{ ?>	
	<form method="get" action="" >
		<input type="hidden" name="page" value="mq_questions">
		<h4>Search for a Quiz Question, using one or more of the following fields</h4>
		<div class="clear"></div> 
		<table class="form-table mq-form-table-reports search-mq-form">
			<tbody>
				<tr>
					<th>Question</th>
					<th>Definition</th>
					<th>Example</th>
					<th>&nbsp;</th>
				</tr>
				<tr>
					<td><input type="text" name="Question" value="<?php echo stripslashes($Question)?>"></td>
					<td><input type="text" name="Definition" value="<?php echo stripslashes($Definition)?>"></td>
					<td><input type="text" name="Example" value="<?php echo stripslashes($Example)?>"></td>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Search" type="submit"> &nbsp;&nbsp;<a href="<?php echo admin_url()."admin.php?page=mq_questions"; ?>" class="button button-primary">Clear</a></p></td>
				</tr>
			</tbody>
		</table>		
	</form>
	<div class="clear"></div> 
	<table class="form-table mq-form-table-reports table-questions">
		<tbody>
			<tr>
				<th style="width:80px">Master ID</th>
				<th style="width:150px">Question</th>
				<th style="width:200px">Definition</th>
				<th style="width:200px">Example</th>
				<th style="width:70px">&nbsp;</th>
			</tr>
		</tbody>
	</table>
	<div class="question-table-height">
		<table class="form-table table-questions" style="margin-top:0px;">
			<tbody>
				 
			<?php if($rows) {
					foreach($rows as $row){ ?>
						<tr>
							<td ><?php echo $row->ID_Unique;?></td>
							<td ><?php echo stripslashes($row->Question);?></td>
							<td ><?php echo stripslashes($row->Definition);?></td>
							<td ><?php echo stripslashes($row->Example);?></td>
							<td style="padding-left: 0;"><a href="<?php echo admin_url()."admin.php?page=mq_questions&questionid=".$row->Master_ID;?>" class="button button-primary">Select</a></td>
						</tr>
					<?php } // end foreach 
				}else{ ?>
						<tr>
							<td colspan="3" align="center"><h3 class="no_results">Sorry no result found.<h3></td>
						</tr>				
				<?php } // end else if rows ?>	
			</tbody>
		</table>
	</div>
<?php } ?>	
</div>
<!-- .wrap -->