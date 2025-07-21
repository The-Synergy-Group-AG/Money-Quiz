jQuery(function($){
	/*
	 * Select/Upload image(s) event
	 */
	ClassicEditor.create(document.querySelector("#money_quiz_column_content")).catch((error) => {
		console.error(error);
	  });
	  ClassicEditor.create(document.querySelector("#email_bottom_contnt")).catch((error) => {
		console.error(error);
	  });
	  
	jQuery('body').on('click', '.mq_upload_image_button', function(e){
		e.preventDefault();
 
    		var button = jQuery(this),
    		    custom_uploader = wp.media({
			title: 'Insert image',
			library : {
				// uncomment the next line if you want to attach image to the current post
				// uploadedTo : wp.media.view.settings.post.id, 
				type : 'image'
			},
			button: {
				text: 'Use this image' // button label text
			},
			multiple: false // for multiple image selection set to true
		}).on('select', function() { // it also has "open" and "close" events 
			var attachment = custom_uploader.state().get('selection').first().toJSON();
			jQuery(button).removeClass('button').html('<img class="true_pre_image" src="' + attachment.url + '" style="max-width:240px;display:block;margin-bottom: 10px;" />').next().val(attachment.url).next().show();
			/* if you sen multiple to true, here is some code for getting the image IDs
			var attachments = frame.state().get('selection'),
			    attachment_ids = new Array(),
			    i = 0;
			attachments.each(function(attachment) {
 				attachment_ids[i] = attachment['id'];
				console.log( attachment );
				i++;
			});
			*/
		})
		.open();
	});
 
	/*
	 * Remove image event
	 */
	jQuery('body').on('click', '.mq_remove_image_button', function(){
		jQuery(this).hide().prev().val('').prev().addClass('button').html('Upload image');
		return false;
	});
	
 // color picker code for admin
   // jQuery('.my-color-field').wpColorPicker();
	
 
});

function show_chart(tab){
	jQuery("input#form_tab_value").val(tab);
	jQuery(".tab_tab").removeClass('active');
	jQuery("#"+tab).addClass('active');
	jQuery(".all_tabs").hide();
	jQuery(".tab_content_"+tab).show();
}
	
function include_summary(){
	jQuery(".include_summary").toggleClass('noprint');
}
function include_details(){
	jQuery(".include_details").toggleClass('noprint');
}
function show_2(id){
	jQuery(".hide_all_tables").hide();
	jQuery(".show_"+id).show();
}
function report_selected(){
	 
	jQuery(".show-mq-errors").hide().html();
	var selected = 0;
	var short_version = 0;
	var blitz_version = 0;
	var full_version = 0;
	jQuery('#report_selected_form input[type="checkbox"]').each(function(){
		if(jQuery(this).is(":checked")){
			selected++;
			if("short" == jQuery(this).data("versiontype")){
				short_version = 1;
			}else if("blitz" == jQuery(this).data("versiontype")){
				blitz_version = 1;
			}else{
				full_version = 1;
			}
		}
	});
	if(selected == 0){
		jQuery(".show-mq-errors").show().html('Please select atleast one result.');
		return false ;
	}
	if(selected > 2){
	//	$(".show-mq-errors").show().html('Please select only two results to compare.');
		//return false ;
	}
	if(selected > 1 && ((short_version > 0 && full_version > 0 && blitz_version > 0) || ((short_version > 0 && full_version > 0) || (short_version > 0 && blitz_version > 0) || (full_version > 0 && blitz_version > 0 ))) ){
		//$(".show-mq-errors").show().html('Please select same Quiz Lenght to compare.');
		//return false ;
	}
	return true;
}
jQuery(document).ready(function($){
	jQuery('.post_data_radio').attr('readonly','readonly');
    jQuery('.color-field-87').wpColorPicker();
    jQuery('.color-field-88').wpColorPicker();
    jQuery('.color-field-89').wpColorPicker();
    jQuery('.prev-color-field').wpColorPicker();
    jQuery('.submit-color-field').wpColorPicker();
    jQuery('.mq-container').show();
	// Export Summary Results
	jQuery( document ).on( 'click', '.reports-range-submit', function() {
		jQuery( '.export_summary_result .reports-range-msg' ).html('');
		var start_date = jQuery( '.range-start-date' ).val();
		var end_date = jQuery( '.range-end-date' ).val();
		
		if( start_date && end_date ) {
			if ( new Date( start_date ) > new Date( end_date ) ) {
				jQuery( '.export_summary_result .reports-range-msg' ).html( 'The end date must be greater than the start date' );
				return false;
			} else {
				return true;
			}
		} else {
			jQuery( '.export_summary_result .reports-range-msg' ).html( 'Please select the dates' );
			return false;
		}
	});
	// Export Summary Results 
});
jQuery(document).ready(function($) {
    function adjustEditorHeight(editorId) {
        // Select the editor iframe for a specific editor ID
        var iframe = jQuery('#' + editorId + '_ifr');
        var editorBody = iframe.contents().find('body');
        
        // Function to adjust height
        function updateHeight() {
			var maxHeight = 400;
            var contentHeight = editorBody[0].scrollHeight + 20; // Add padding to prevent clipping
			var newHeight = Math.min(contentHeight, maxHeight); 
			
            iframe.height(newHeight);
            jQuery('#' + editorId).height(newHeight); // Adjust parent container
        }

        // Set event listeners to adjust height on input
        editorBody.on('input', updateHeight);
        editorBody.on('keyup', updateHeight);

        // Initial adjustment
        updateHeight();
    }

    // Wait until all editors are fully initialized
    jQuery(document).on('tinymce-editor-init', function(e, editor) {
        var editorId = editor.id.replace('_iframe', '');
        adjustEditorHeight(editorId);
    });
});