<?php
/**
 * @package Business Insights Group AG
 * @Author: Business Insights Group AG
 * @Author URI: https://www.101businessinsights.com/
*/
global $wpdb;
$table_prefix = $wpdb->prefix;
$email = $surname = $first_name = $where = "";
if(isset($_REQUEST['submit']) && $_REQUEST['submit'] == "Search"){
	$where = "";
	$where_arr = array();
	if(isset($_REQUEST['first_name']) && $_REQUEST['first_name'] !=""){
		$where_arr[] = " Name like '%".sanitize_text_field($_REQUEST['first_name'])."%'";
		$first_name = sanitize_text_field($_REQUEST['first_name']);
	}
	if(isset($_REQUEST['surname']) && $_REQUEST['surname'] !=""){
		$where_arr[] = " Surname like '%".sanitize_text_field($_REQUEST['surname'])."%'";
		$surname = sanitize_text_field($_REQUEST['surname']);
	}
	if(isset($_REQUEST['email']) && $_REQUEST['email'] !=""){
		$where_arr[] = " Email like '%".sanitize_text_field($_REQUEST['email'])."%'";
		$email = sanitize_text_field($_REQUEST['email']);
	}
	if(count($where_arr)>0){
		$where = " where  ".implode(' or ',$where_arr);
	}	
}

$sql = "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS."  $where order by Prospect_ID DESC " ;
$rows = $wpdb->get_results($sql, OBJECT);
  
 ?>
 
<div class=" mq-container quiz_page">
	<?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
	<h3>Quiz Reports</h3>
	<?php echo $save_msg ?>
	<div class="clear"></div> 
	<form method="get" action="" >
		<input type="hidden" name="page" value="mq_reports">
		<h4>Search for a client, using one or more of the following fields</h4>
		<div class="clear"></div> 
		<table class="form-table mq-form-table-reports search-mq-form">
			<tbody>
				<tr>
					<th>First Name</th>
					<th>Surname</th>
					<th>Email</th>
					<th>&nbsp;</th>
				</tr>
				<tr>
					<td><input type="text" name="first_name" value="<?php echo $first_name?>"></td>
					<td><input type="text" name="surname" value="<?php echo $surname?>"></td>
					<td><input type="text" name="email" value="<?php echo $email?>"></td>
					<td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Search" type="submit"> &nbsp;&nbsp;<a href="<?php echo admin_url()."admin.php?page=mq_reports"; ?>" class="button button-primary">Clear</a></p></td>
				</tr>
			</tbody>
		</table>		
	</form>
	<div class="clear"></div> 
	<table class="form-table mq-form-table-reports">
		<tbody>
			<tr>
				<th>First Name</th>
				<th>Surname</th>
				<th>Email</th>
				<th>&nbsp;</th>
			</tr>
		<?php if($rows) {
				foreach($rows as $row){ ?>
					<tr>
						<td><?php echo $row->Name;?></td>
						<td><?php echo $row->Surname;?></td>
						<td><?php echo $row->Email;?></td>
						<td style="padding-left: 0;"><a href="<?php echo admin_url()."admin.php?page=mq_reports&prospect=".$row->Prospect_ID;?>" class="button button-primary">Select</a></td>
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
<!-- .wrap -->