jQuery(function($){
	/** book to call */
	/** Iframe click event */

	/** end */
	jQuery("#money_report").hide();	
	jQuery('.is_schedul_call').change(function() {
		
		var is_is_schedul_call = jQuery(this).val();
		if(is_is_schedul_call=="Yes"){
			jQuery(".schedul_call_button").show();
			jQuery("#money_report").hide();	
		}else{
			jQuery(".schedul_call_button").hide();
			jQuery("#money_report").show();
			jQuery('#money_report').focus();
		}
	});

	jQuery(".schedul_call_button").on('click', function() {
		jQuery(".mindfull-money-prefix-model-main").addClass('model-open');
	  }); 
	  jQuery(".close-btn, .bg-overlay").click(function(){
		jQuery(".mindfull-money-prefix-model-main").removeClass('model-open');
	  });
	  
	  jQuery("#money_report_from_popup").on('click', function() {
		jQuery("#money_report").hide();
		jQuery(".mindfull-money-prefix-model-main").removeClass('model-open');
		jQuery(".schedul_call_button").show();
		showStep('submit');
	  });
	/** end */
	/** radio button checkbox check */
	jQuery('.custom-radio input[type="radio"]').on('change', function() {
		// Find the checkbox in the same row and check it
		jQuery(this).closest('tr').find('input[type="checkbox"]').prop('checked', true);
	  });
	/** end */

	jQuery("a.select-version").click(function(e){
		e.preventDefault();
		jQuery(".select-version").removeClass("selected");
		jQuery(this).addClass("selected");
		jQuery("#mq_version_selected").val(jQuery(this).data("version"));

		jQuery(".mq-tr").find("input").each(function(){ jQuery(this).attr('disabled',true) });
		jQuery(".mq-tr").hide();
		
		if("blitz" == jQuery(this).data("version")){
			jQuery(".mq-tr.blitz_ques").find("input").each(function(){ jQuery(this).attr('disabled',false) });
			jQuery(".mq-tr.blitz_ques").show();
			jQuery(".blitz-version-desc").removeClass("mq-hide");
			jQuery(".full-version-desc").addClass("mq-hide");			
			jQuery(".short-version-desc").addClass("mq-hide");
			jQuery(".classic-version-desc").addClass("mq-hide");
			jQuery(".full_version_buttons").addClass("mq-hide");
			jQuery(".blitz_version_buttons").removeClass("mq-hide");
			jQuery(".mq-button-start").show();
		}
		
		if("short" == jQuery(this).data("version")){
			jQuery(".mq-tr.short_ques").find("input").each(function(){ jQuery(this).attr('disabled',false) });
			jQuery(".mq-tr.short_ques").show();
			
			jQuery(".full-version-desc").addClass("mq-hide");			
			jQuery(".blitz-version-desc").addClass("mq-hide");	
			jQuery(".classic-version-desc").addClass("mq-hide");			
			jQuery(".full_version_buttons").removeClass("mq-hide");			
			jQuery(".short-version-desc").removeClass("mq-hide");
			jQuery(".blitz_version_buttons").addClass("mq-hide");
			jQuery(".mq-button-start").show();
		}
		if("full" == jQuery(this).data("version")){
			jQuery(".mq-tr.full_ques").find("input").each(function(){ jQuery(this).attr('disabled',false) });
			jQuery(".mq-tr.full_ques").show();
			jQuery(".short-version-desc").addClass("mq-hide");
			jQuery(".blitz-version-desc").addClass("mq-hide");
			jQuery(".blitz_version_buttons").addClass("mq-hide");
			jQuery(".classic-version-desc").addClass("mq-hide");
			jQuery(".full-version-desc").removeClass("mq-hide");
			jQuery(".full_version_buttons").removeClass("mq-hide");
			jQuery(".mq-button-start").show();
		}	
		if("classic" == jQuery(this).data("version")){
			jQuery(".mq-tr.classic_ques").find("input").each(function(){ jQuery(this).attr('disabled',false) });
			jQuery(".mq-tr.classic_ques").show();
			
			jQuery(".full-version-desc").addClass("mq-hide");			
			jQuery(".blitz-version-desc").addClass("mq-hide");
			jQuery(".short-version-desc").addClass("mq-hide");			
			jQuery(".blitz_version_buttons").addClass("mq-hide");
			
			jQuery(".classic-version-desc").removeClass("mq-hide");
			jQuery(".full_version_buttons").removeClass("mq-hide");			
			
			
			jQuery(".mq-button-start").show();
		}
	});
	/*jQuery("#money_report").click(function($){
		jQuery(this).attr('disabled', true);
		var body = jQuery("body");
		body.addClass("loading");
	});*/
 
});
/** Recaptcha  */
var isRecaptchaEnabled = jQuery('#is_recaptcha_enable').val();
var recaptchaType = jQuery('#recaptcha_type').val();
var recaptchaSiteKey = jQuery('#recaptcha_site_key').val();
var recaptchaSecret = jQuery('#recaptcha_secrete').val();


/**  */
/** End */
function showStep(step,is_pre=0){
	jQuery = jQuery;
	var answered_error_text = jQuery("#answered_error_text").val();
	jQuery('.answred_error').hide();
	jQuery('.answred_error').html('');
	if(is_pre!=1 && step!=1){
		if (jQuery('.money-quez-confrim-check-box :visible').length > 0) {
			var uncheck_checkbox = jQuery('.money-quez-confrim-check-box input[type="checkbox"]:visible:not(:checked)').length;
			if(uncheck_checkbox>=1){
				jQuery('.answred_error').html(answered_error_text);
				jQuery('.answred_error').show();
				return false;
			}
		}
	}
	
	if(step == 1){
		jQuery("#mq-questions-form").trigger("reset");
	}
	if(step == "submit"){
		/** Recaptcha Setting  */
		
		var isRecaptchaEnabled = jQuery('#is_recaptcha_enable').val();
		var recaptchaType = jQuery('#recaptcha_type').val();
		var recaptchaSiteKey = jQuery('#recaptcha_site_key').val();
		var recaptchaSecret = jQuery('#recaptcha_secrete').val();
		
		if (isRecaptchaEnabled == 'on') {
			if (recaptchaType == 'v2') {
				var recaptchaResponse = grecaptcha.getResponse();
		
				if (recaptchaResponse.length === 0) {
					alert("Please complete the reCAPTCHA verification.");
				} else {
					jQuery("#mq-questions-form").submit();
		
					var loadingHTML = "<div id='mfm-loading'>\
						<img src='https://mindfulmoneycoaching.online/wp-content/plugins/moneyquiz/assets/images/mind-full-preloader.webp' alt='Loading' class='mfm-rotating-image'>\
						<div id='mfm-loading-text'><h1>Please STAY on this Page and do NOT refresh, we're almost Done!</h1></div>\
					</div>";
		
					// Add the loading HTML to the main container
					jQuery('.mq-container').html(loadingHTML);
				}
			}
			else if(recaptchaType == 'v3'){
				grecaptcha.ready(function () {
					grecaptcha.execute(recaptchaSiteKey, { action: 'quizsubmit' }).then(function (token) {
						// Set token value in hidden input
						jQuery("#g-recaptcha-response").val(token);
						
						// Optional: Debug
						console.log("reCAPTCHA token:", token);
						jQuery("#mq-questions-form").submit();
		
						var loadingHTML = "<div id='mfm-loading'>\
							<img src='https://mindfulmoneycoaching.online/wp-content/plugins/moneyquiz/assets/images/mind-full-preloader.webp' alt='Loading' class='mfm-rotating-image'>\
							<div id='mfm-loading-text'><h1>Please STAY on this Page and do NOT refresh, we're almost Done!</h1></div>\
						</div>";
						jQuery('.mq-container').html(loadingHTML);
					});
				});
		
			}
		} else {
			jQuery("#mq-questions-form").submit();
		
			var loadingHTML = "<div id='mfm-loading'>\
				<img src='https://mindfulmoneycoaching.online/wp-content/plugins/moneyquiz/assets/images/mind-full-preloader.webp' alt='Loading' class='mfm-rotating-image'>\
				<div id='mfm-loading-text'><h1>Please STAY on this Page and do NOT refresh, we're almost Done!</h1></div>\
			</div>";
		
			// Add the loading HTML to the main container
			jQuery('.mq-container').html(loadingHTML);
		}
		
		/** End  */
		
		
	//	jQuery(".entry-content").html("<div class='text-align-center result-loading-padding' style='padding-top:200px;padding-bottom:200px;text-align:center;background-color: #fff5d1;'><h1>Please STAY on this Page and do NOT refresh, were almost Done!<h1></div>");
	}else if(step == 8){
		jQuery(".steps-container").hide();
		//jQuery(".mq-intro").show();	
		jQuery(".mq-prospects").show();	
	}else if(step == 0){
		jQuery(".mq-intro").show();
		jQuery(".mq-select-version").show();
		jQuery(".steps-container").hide();		
	}else{
		jQuery(".mq-intro").hide();
		jQuery(".mq-select-version").hide();
		jQuery(".steps-container").hide();
		if("blitz" == jQuery("#mq_version_selected").val()){
			var new_step = step;
			if(step == 4){
				var new_step = 2;
			}	
			if(step == 6){
				var new_step = 3;
			}	
			jQuery(".section_cntr").html(new_step);
			//console.log(new_step+" = "+step);
		}else{
			jQuery(".section_cntr").html(step);
		}
		jQuery("#step_"+step).show();
	}
	var temp_offset = jQuery(".mq-container").offset();
	if(step!="submit"){
		var body = jQuery("html, body");
		body.stop().animate({scrollTop:(temp_offset.top +80)}, 500, 'swing', function() { 
		
		});
	}

}
function check_mq_data(){
	jQuery=jQuery;
	jQuery(".show-mq-errors").hide().html();
	if(jQuery(".prospect_data_Name").length >0){
		if(jQuery.trim(jQuery(".prospect_data_Name").val()) == ""){
			jQuery(".show-mq-errors").show().html('Please enter name');
			return false;
		}
	}
	if(jQuery(".prospect_data_surname").length >0){
		if(jQuery.trim(jQuery(".prospect_data_surname").val()) == ""){
			jQuery(".show-mq-errors").show().html('Please enter surname');
			return false;
		}
	}
	if(jQuery(".prospect_data_email").length >0){
		if(jQuery.trim(jQuery(".prospect_data_email").val()) == ""  ){
			jQuery(".show-mq-errors").show().html('Please enter email');
			return false;
		}
		var filter = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
		if (!filter.test(jQuery.trim(jQuery(".prospect_data_email").val()))) {
			jQuery(".show-mq-errors").show().html('Please enter valid email address');
			return false;
		}
		 
	}
	jQuery("#money_report").attr('disabled', true);
	var body = jQuery("body");
	body.addClass("loading");
	//jQuery(".mq-container").after("<div class='text-align-center result-loading-padding' style='padding-top:200px;padding-bottom:200px;text-align:center;'><h1>Please wait while result ready...<h1></div>");
	return true;
}

function include_summary(){
	jQuery(".include_summary").toggleClass('noprint');
}
function include_details(){
	jQuery(".include_details").toggleClass('noprint');
}
function show_ques_title_hints(id){
	jQuery("#mouse_over_"+id).show();
}
function hide_ques_title_hints(id){
	jQuery(".mouse_over_text").hide();
}

function show_second_page(){
	jQuery( '.click_auto' ).trigger( 'click' );
	jQuery("#step_1 .pre_step").addClass('mq-hide');
	jQuery(".mq_landing_page").addClass('mq-hide');
	jQuery(".mq_landing_page_2").removeClass('mq-hide');
	setTimeout(function () {
		jQuery( '.click_auto' ).trigger( 'click' );
	}, 100);	
}

var rangeSlider = function(){
	jQuery=jQuery;
  var slider = jQuery('.range-slider'),
      range = jQuery('.range-slider__range'),
      value = jQuery('.range-slider__value');
    
  slider.each(function(){

  /*   value.each(function(){
      var value = jQuery(this).prev().attr('value');
      jQuery(this).html(value);
    });
 */
    range.on('input', function(){
      jQuery(this).next(value).html(this.value);
    });
  });
};

rangeSlider();