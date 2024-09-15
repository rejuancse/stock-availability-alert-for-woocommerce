<?php
/**
 * Plugin Name: Enhanced Stock Availability Alert
 * Description: Implement a notification system for out-of-stock items with advanced features including admin interface, better product suggestions, customizable emails, and rate limiting.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Rejuan Ahamed
 * Text Domain:       banner-image
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
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

    // Email validation
    if (!is_email($email)) {
        wp_send_json_error('Invalid email address');
    }

    if (!$product_id) {
        wp_send_json_error('Invalid product ID');
    }

    // Check rate limiting
    if (is_rate_limited($email)) {
        wp_send_json_error('Too many requests. Please try again later.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_notifications';

    // Check if the email-product combination already exists
    $existing_notification = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s AND product_id = %d",
            $email,
            $product_id
        )
    );

    if ($existing_notification) {
        // Check if the existing notification is less than 24 hours old
        $time_difference = current_time('timestamp') - strtotime($existing_notification->date_added);
        if ($time_difference < 24 * 60 * 60) { // 24 hours in seconds
            wp_send_json_error('You have already subscribed to notifications for this product. Please wait 24 hours before trying again.');
        } else {
            // Update the existing notification with the current timestamp
            $wpdb->update(
                $table_name,
                array('date_added' => current_time('mysql')),
                array('id' => $existing_notification->id)
            );
            wp_send_json_success(array(
                'message' => "Your notification subscription has been renewed for this product.",
                'alternatives' => get_alternative_products($product_id)
            ));
        }
    } else {
        // Insert new notification
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

        wp_send_json_success(array(
            'message' => "Thank you! We've added your email to the notification list for {$product_name}. We'll let you know as soon as it's back in stock.",
            'alternatives' => get_alternative_products($product_id)
        ));
    }
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
function send_stock_notifications($product_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_notifications';

    $notifications = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE product_id = %d",
        $product_id
    ));

    $email_template = get_option('stock_notification_email_template', get_default_email_template());
    $product = wc_get_product($product_id);

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


add_action('wp_ajax_unsubscribe_stock_notification', 'unsubscribe_stock_notification');
add_action('wp_ajax_nopriv_unsubscribe_stock_notification', 'unsubscribe_stock_notification');
function unsubscribe_stock_notification() {
    $email = sanitize_email($_POST['email']);
    $product_id = intval($_POST['product_id']);
    $token = sanitize_text_field($_POST['token']);

    if (!$email || !$product_id || !$token) {
        wp_send_json_error('Invalid unsubscribe data');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_notifications';

    $result = $wpdb->delete(
        $table_name,
        array(
            'email' => $email,
            'product_id' => $product_id
        )
    );

    if ($result) {
        wp_send_json_success('You have been unsubscribed successfully.');
    } else {
        wp_send_json_error('Unable to unsubscribe. Please try again.');
    }
}

// Add admin dashboard widget
add_action('wp_dashboard_setup', 'stock_notification_add_dashboard_widget');
function stock_notification_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'stock_notification_dashboard_widget',
        'Stock Notification Statistics',
        'stock_notification_dashboard_widget_function'
    );
}

function stock_notification_dashboard_widget_function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_notifications';

    $total_notifications = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $unique_products = $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM $table_name");
    $unique_emails = $wpdb->get_var("SELECT COUNT(DISTINCT email) FROM $table_name");

    echo "<p>Total Notifications: $total_notifications</p>";
    echo "<p>Unique Products: $unique_products</p>";
    echo "<p>Unique Subscribers: $unique_emails</p>";
}

// Add export to CSV functionality
add_action('admin_menu', 'stock_notification_export_menu');
function stock_notification_export_menu() {
    add_submenu_page(
        'stock-notifications',
        'Export Notifications',
        'Export CSV',
        'manage_options',
        'stock-notifications-export',
        'stock_notification_export_page'
    );
}

function stock_notification_export_page() {
    if (isset($_POST['export_csv'])) {
        stock_notification_generate_csv();
    }

    echo '<div class="wrap">';
    echo '<h1>Export Stock Notifications</h1>';
    echo '<form method="post">';
    echo '<input type="submit" name="export_csv" class="button button-primary" value="Export to CSV">';
    echo '</form>';
    echo '</div>';
}

function stock_notification_generate_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_notifications';

    $notifications = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock_notifications.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Email', 'Product ID', 'Date Added'));

    foreach ($notifications as $notification) {
        fputcsv($output, $notification);
    }

    fclose($output);
    exit;
}

// Integrate with WooCommerce stock management
add_action('woocommerce_product_set_stock', 'check_stock_and_notify');
function check_stock_and_notify($product) {
    $product_id = $product->get_id();
    $stock_quantity = $product->get_stock_quantity();
    $notification_threshold = get_option('stock_notification_threshold', 1);

    if ($stock_quantity >= $notification_threshold) {
        send_stock_notifications($product_id);
    }
}

// Add settings page for customizable notification threshold
add_action('admin_menu', 'stock_notification_settings_menu');
function stock_notification_settings_menu() {
    add_submenu_page(
        'stock-notifications',
        'Notification Settings',
        'Settings',
        'manage_options',
        'stock-notifications-settings',
        'stock_notification_settings_page'
    );
}

function stock_notification_settings_page() {
    if (isset($_POST['submit_settings'])) {
        // Save notification threshold
        update_option('stock_notification_threshold', intval($_POST['notification_threshold']));

        // Save email template
        update_option('stock_notification_email_template', wp_kses_post($_POST['email_template']));

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $threshold = get_option('stock_notification_threshold', 1);
    $email_template = get_option('stock_notification_email_template', get_default_email_template());

    ?>
    <div class="wrap">
        <h1>Stock Notification Settings</h1>
        <form method="post">
            <h2>Notification Threshold</h2>
            <label for="notification_threshold">Notification Threshold:</label>
            <input type="number" id="notification_threshold" name="notification_threshold" value="<?php echo esc_attr($threshold); ?>" min="1">
            <p class="description">Send notifications when stock reaches or exceeds this number.</p>

            <h2>Email Template</h2>
            <p>Customize the email sent to customers when a product is back in stock. You can use the following placeholders:</p>
            <ul>
                <li><code>{product_name}</code> - The name of the product</li>
                <li><code>{product_url}</code> - The URL of the product page</li>
                <li><code>{site_name}</code> - The name of your website</li>
            </ul>
            <textarea name="email_template" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($email_template); ?></textarea>

            <p class="submit">
                <input type="submit" name="submit_settings" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}

function get_default_email_template() {
    return <<<EOT
Hello,

Great news! The product "{product_name}" is now back in stock at {site_name}.

You can purchase it here: {product_url}

Thank you for your patience and interest in our products.

Best regards,
The team at {site_name}
EOT;
}