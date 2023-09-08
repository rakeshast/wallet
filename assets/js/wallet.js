jQuery(document).ready(function($) {
    // Replace 'your-custom-button-id' with the ID or class of your custom button.
    $('#your-custom-button-id').click(function(e) {
        e.preventDefault();
        console.log("working");
        // Create a new order using AJAX.
        $.ajax({
            type: 'POST',
            url: ajax_object.ajax_url, // Replace with the actual URL to your WordPress admin-ajax.php file.
            data: {
                action: 'create_custom_order',
            },
            success: function(response) {
                // Redirect to the checkout page with the newly created order.
                window.location.href = response.checkout_url;
            },
        });
    });
});
