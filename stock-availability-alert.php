<?php
/*
Plugin Name: Enhanced Stock Availability Alert
Description: Implement a notification system for out-of-stock items with advanced features including admin interface, better product suggestions, customizable emails, and rate limiting.
Version: 2.0
Author: Your Name
*/

// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Add "Notify Me" button for out of stock products
add_action('woocommerce_single_product_summary', 'add_notify_me_button', 30);
function add_notify_me_button() {
    global $product;
    if (!$product->is_in_stock()) {
        echo '<button class="button" id="notify-me-button">Notify Me When Available</button>';
        echo '<div id="notify-me-form" style="display:none;">
                <input type="email" id="notify-email" placeholder="Enter your email">
                <input type="hidden" id="notify-product-id" value="' . esc_attr($product->get_id()) . '">
                <button id="submit-notify">Submit</button>
              </div>';
    }
}

// Enqueue JavaScript
add_action('wp_enqueue_scripts', 'enqueue_notify_script');
function enqueue_notify_script() {
    wp_enqueue_script('notify-script', plugin_dir_url(__FILE__) . 'assets/js/notify-script.js', array('jquery'), '2.0', true);
    wp_localize_script('notify-script', 'notify_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}

// Handle AJAX request
add_action('wp_ajax_stock_notification', 'handle_stock_notification');
add_action('wp_ajax_nopriv_stock_notification', 'handle_stock_notification');
function handle_stock_notification() {
    $email = sanitize_email($_POST['email']);
    $product_id = intval($_POST['product_id']);

    if (!is_email($email) || !$product_id) {
        wp_send_json_error('Invalid data');
    }

    // Check rate limiting
    if (is_rate_limited($email)) {
        wp_send_json_error('Too many requests. Please try again later.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_notifications';

    $wpdb->insert(
        $table_name,
        array(
            'email' => $email,
            'product_id' => $product_id,
            'date_added' => current_time('mysql')
        )
    );

    $product = wc_get_product($product_id);
    $product_name = $product ? $product->get_name() : 'this product';

    // Suggest alternative products
    $alternative_products = get_alternative_products($product_id);

    wp_send_json_success(array(
        'message' => "Thank you! We've added your email to the notification list for {$product_name}. We'll let you know as soon as it's back in stock.",
        'alternatives' => $alternative_products
    ));
}

// Function to get alternative products
function get_alternative_products($product_id) {
    $product = wc_get_product($product_id);
    $cat_ids = $product->get_category_ids();
    $tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'ids'));

    $args = array(
        'category' => $cat_ids,
        'tag' => $tags,
        'posts_per_page' => 5,
        'post__not_in' => array($product_id),
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_stock_status',
                'value' => 'instock'
            )
        )
    );

    $products = wc_get_products($args);

    $alternatives = array();
    foreach ($products as $product) {
        $alternatives[] = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'url' => get_permalink($product->get_id()),
            'price' => $product->get_price(),
            'image' => wp_get_attachment_url($product->get_image_id())
        );
    }

    return $alternatives;
}

// Create database table on plugin activation
register_activation_hook(__FILE__, 'stock_notification_activate');
function stock_notification_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_notifications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        product_id bigint(20) NOT NULL,
        date_added datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Add default email template
    add_option('stock_notification_email_template', 'Hello,

The product "{product_name}" is now back in stock!

You can purchase it here: {product_url}

Thank you for your interest.

Best regards,
{site_name}');
}

// Send notifications when stock status changes
add_action('woocommerce_product_set_stock_status', 'send_stock_notifications', 10, 3);
function send_stock_notifications($product_id, $status, $product) {
    if ($status === 'instock') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d",
            $product_id
        ));

        $email_template = get_option('stock_notification_email_template');

        foreach ($notifications as $notification) {
            $to = $notification->email;
            $subject = 'Product Back in Stock: ' . $product->get_name();

            $message = str_replace(
                array('{product_name}', '{product_url}', '{site_name}'),
                array($product->get_name(), get_permalink($product_id), get_bloginfo('name')),
                $email_template
            );

            $headers = array('Content-Type: text/html; charset=UTF-8');

            wp_mail($to, $subject, $message, $headers);

            // Remove the notification from the database
            $wpdb->delete($table_name, array('id' => $notification->id));
        }
    }
}

// Add admin menu
add_action('admin_menu', 'stock_notification_admin_menu');
function stock_notification_admin_menu() {
    add_menu_page('Stock Notifications', 'Stock Notifications', 'manage_options', 'stock-notifications', 'stock_notification_admin_page', 'dashicons-email-alt');
}

// Admin page content
function stock_notification_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_notifications';

    // Handle email template update
    if (isset($_POST['email_template'])) {
        update_option('stock_notification_email_template', wp_kses_post($_POST['email_template']));
        echo '<div class="updated"><p>Email template updated.</p></div>';
    }

    // Get notifications
    $notifications = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date_added DESC");

    // Display admin page
    echo '<div class="wrap">';
    echo '<h1>Stock Notifications</h1>';

    // Email template form
    echo '<h2>Email Template</h2>';
    echo '<form method="post">';
    echo '<textarea name="email_template" rows="10" cols="50">' . esc_textarea(get_option('stock_notification_email_template')) . '</textarea>';
    echo '<p>Available placeholders: {product_name}, {product_url}, {site_name}</p>';
    echo '<input type="submit" class="button button-primary" value="Update Email Template">';
    echo '</form>';

    // Notifications table
    echo '<h2>Current Notifications</h2>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>Email</th><th>Product</th><th>Date Added</th></tr></thead>';
    echo '<tbody>';
    foreach ($notifications as $notification) {
        $product = wc_get_product($notification->product_id);
        echo '<tr>';
        echo '<td>' . esc_html($notification->email) . '</td>';
        echo '<td>' . ($product ? esc_html($product->get_name()) : 'Product not found') . '</td>';
        echo '<td>' . esc_html($notification->date_added) . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo '</div>';
}

// Rate limiting function
function is_rate_limited($email) {
    $transient_name = 'stock_notify_' . md5($email);
    $count = get_transient($transient_name);

    if ($count === false) {
        set_transient($transient_name, 1, HOUR_IN_SECONDS);
        return false;
    }

    if ($count >= 5) {
        return true;
    }

    set_transient($transient_name, $count + 1, HOUR_IN_SECONDS);
    return false;
}
