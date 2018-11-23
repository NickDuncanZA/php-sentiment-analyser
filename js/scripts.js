/* version 1.1 */
jQuery(document).ready(function(){
	jQuery("body").on("click",".sentiment_confirm", function() {
		var sid = jQuery(this).attr('sid');
		var text = jQuery("#sentiment_text_"+sid).val();
		var rating = jQuery("#sentiment_rating_"+sid).val();
		console.log(text);
		console.log(rating);
		console.log(window.location.href);
		data = {
			'save_dats' : 'ok',
			'rating' : rating,
			'text' : text 
		}
	    jQuery.ajax({
	        url: "./index.php",
	        data:data,
	        type:"POST",
	        success: function(response) {
	        	console.log(response);
	        	jQuery("#tr_"+sid).remove();
	        }
	    });

	})
});