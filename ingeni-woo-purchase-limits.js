var iwplShowPopup;

jQuery(document).ready(function() {
  var modalHtml = '<div id="iwplModal" class="iwpl_modal"><div class="iwpl_content"><span id="iwplClose">&times;</span><p id="iwpl_message"></p></div></div>';
  if ( jQuery(".main").length > 0 ) {
    jQuery(".main").after(modalHtml);

    if ( jQuery("#iwplModal").length > 0 ) {
        console.log('aftered');
    }
  }



  // When the user clicks on <span> (x), close the modal
  jQuery("#iwplClose").click( function() {
    console.log('close');
    jQuery("#iwplModal").hide();
  });

  // When the user clicks anywhere outside of the modal, close it
  window.onclick = function(event) {
    // Get the modal
    var modal = jQuery("#iwplModal");
    if (event.target == modal) {
      modal.hide;
    }
  }

});

// When the user clicks the button, open the modal 
function iwplShowPopup( msg ) {
  //jQuery("#iwpl_message").text( msg );
  //jQuery("#iwplModal").show();
console.log( msg );
  alert( msg );
}
