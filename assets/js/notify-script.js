jQuery(document).ready(function($) {
    $('#notify-me-button').click(function() {
        $('#notify-me-form').toggle();
    });

    $('#submit-notify').click(function() {
        var email = $('#notify-email').val();
        var product_id = $('#notify-product-id').val();

        if (!email) {
            alert('Please enter your email address.');
            return;
        }

        if (!product_id) {
            alert('Error: Product ID not found. Please refresh the page and try again.');
            return;
        }

        // Disable the submit button and show loading state
        $('#submit-notify').prop('disabled', true).text('Submitting...');

        $.ajax({
            url: notify_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'stock_notification',
                email: email,
                product_id: product_id
            },
            success: function(response) {
                if (response.success) {
                    // Replace form with success message
                    $('#notify-me-form').html('<p class="success-message">' + response.data.message + '</p>');

                    // Display alternative products
                    if (response.data.alternatives && response.data.alternatives.length > 0) {
                        var alternativesHtml = '<h3>In the meantime, you might also like:</h3><ul class="product-alternatives">';
                        response.data.alternatives.forEach(function(product) {
                            alternativesHtml += '<li>' +
                                '<a href="' + product.url + '">' +
                                '<img src="' + product.image + '" alt="' + product.name + '" width="50" height="50">' +
                                '<span class="product-name">' + product.name + '</span>' +
                                '<span class="product-price">' + product.price + '</span>' +
                                '</a></li>';
                        });
                        alternativesHtml += '</ul>';
                        $('#notify-me-form').after(alternativesHtml);
                    }
                } else {
                    // Show error message within the form
                    $('#notify-me-form').prepend('<p class="error-message">Error: ' + (response.data || 'Unknown error occurred. Please try again.') + '</p>');
                    // Re-enable the submit button
                    $('#submit-notify').prop('disabled', false).text('Submit');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Show error message within the form
                $('#notify-me-form').prepend('<p class="error-message">An error occurred. Please try again later. Error: ' + textStatus + '</p>');
                // Re-enable the submit button
                $('#submit-notify').prop('disabled', false).text('Submit');
            }
        });
    });
});
