jQuery(document).ready(function($) {
    $('#notify-me-button').click(function() {
        $('#notify-me-form').toggle();
    });

    $('#submit-notify').click(function() {
        var email = $('#notify-email').val().trim();
        var product_id = $('#notify-product-id').val();

        // Enhanced email validation
        var emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
        if (!email || !emailRegex.test(email)) {
            showError('Please enter a valid email address.');
            return;
        }

        if (!product_id) {
            showError('Error: Product ID not found. Please refresh the page and try again.');
            return;
        }

        $('#submit-notify').prop('disabled', true).text('Submitting...');
        $('.error-message, .success-message').remove();

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
                    $('#notify-me-form').html('<p class="success-message">' + response.data.message + '</p>');

                    if (response.data.alternatives && response.data.alternatives.length > 0) {
                        var alternativesHtml = '<h3>In the meantime, you might also like:</h3><ul class="product-alternatives">';
                        response.data.alternatives.forEach(function(product) {
                            alternativesHtml += '<li>' +
                                '<a href="' + escapeHtml(product.url) + '">' +
                                '<img src="' + escapeHtml(product.image) + '" alt="' + escapeHtml(product.name) + '" width="50" height="50">' +
                                '<span class="product-name">' + escapeHtml(product.name) + '</span>' +
                                '<span class="product-price">' + escapeHtml(product.price) + '</span>' +
                                '</a></li>';
                        });
                        alternativesHtml += '</ul>';
                        $('#notify-me-form').after(alternativesHtml);
                    }
                } else {
                    showError(response.data || 'Unknown error occurred. Please try again.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                showError('An error occurred. Please try again later. Error: ' + textStatus);
            },
            complete: function() {
                $('#submit-notify').prop('disabled', false).text('Submit');
            }
        });
    });

    function showError(message) {
        $('.error-message').remove();
        $('#notify-me-form').prepend('<p class="error-message">' + escapeHtml(message) + '</p>');
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
