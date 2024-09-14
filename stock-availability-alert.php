<?php
/*
Plugin Name: Stock Availability Alert
Description: Implement a notification system for out-of-stock items that allows customers to sign up for alerts when the product becomes available again, or offer similar alternative products.
Version: 1.0
Author: Rejuan Ahamed
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
                <button id="submit-notify">Submit</button>
              </div>';
    }
}

// Enqueue JavaScript
add_action('wp_enqueue_scripts', 'enqueue_notify_script');
function enqueue_notify_script() {
    wp_enqueue_script('notify-script', plugin_dir_url(__FILE__) . 'assets/js/notify-script.js', array('jquery'), '1.0', true);
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

    // Suggest alternative products
    $alternative_products = get_alternative_products($product_id);

    wp_send_json_success(array(
        'message' => 'You will be notified when this product is back in stock.',
        'alternatives' => $alternative_products
    ));
}

// Function to get alternative products
function get_alternative_products($product_id) {
    $product = wc_get_product($product_id);
    $cat_ids = $product->get_category_ids();

    $args = array(
        'category' => $cat_ids,
        'posts_per_page' => 3,
        'post__not_in' => array($product_id),
        'post_status' => 'publish',
    );

    $products = wc_get_products($args);

    $alternatives = array();
    foreach ($products as $product) {
        $alternatives[] = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'url' => get_permalink($product->get_id())
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

        foreach ($notifications as $notification) {
            $to = $notification->email;
            $subject = 'Product Back in Stock';
            $message = 'The product you were interested in is now back in stock. Visit our store to purchase.';
            $headers = array('Content-Type: text/html; charset=UTF-8');

            wp_mail($to, $subject, $message, $headers);

            // Remove the notification from the database
            $wpdb->delete($table_name, array('id' => $notification->id));
        }
    }
}
