(function($, Drupal, once) {
    Drupal.behaviors.addToFavourites = {
        attach: function(context, settings) {
            // Using once to ensure behavior is applied only once.
            once('addToFavourites', $('.add-to-favourites', context)).forEach(function(element) {
                // Bind the click event to the element.
                $(element).on('click', function(e) {
                    e.preventDefault(); // Prevent default link behavior

                    var nodeId = $(this).data('node-id'); // Get the node ID from the data attribute.
                    var currentUrl = window.location.href; // Get the current page URL.
                    console.log("currentUrl", currentUrl);


                    // Perform an AJAX request to save the node ID and current URL in the session.
                    $.ajax({
                        url: Drupal.url('custom/save-node-to-session'),
                        type: 'POST',
                        data: {
                            node_id: nodeId,
                            destination: currentUrl // Send the current URL along with the node ID.
                        },
                        success: function(response) {
                            if (response.success) {
                                // Redirect to the login page after saving the node ID and URL.
                                window.location.href = Drupal.url('user/login');
                            }
                        }
                    });
                });
            });
        }
    };
})(jQuery, Drupal, once);