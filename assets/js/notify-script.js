jQuery(document).ready(function($) {
    $('#notify-me-button').click(function() {
        $('#notify-me-form').toggle();
    });

    $('#submit-notify').click(function() {
        var email = $('#notify-email').val();
        var product_id = $('input[name="product_id"]').val();

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
                    alert(response.data.message);

                    // Display alternative products
                    if (response.data.alternatives.length > 0) {
                        var alternativesHtml = '<h3>You might also like:</h3><ul>';
                        response.data.alternatives.forEach(function(product) {
                            alternativesHtml += '<li><a href="' + product.url + '">' + product.name + '</a></li>';
                        });
                        alternativesHtml += '</ul>';
                        $('#notify-me-form').after(alternativesHtml);
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
});
