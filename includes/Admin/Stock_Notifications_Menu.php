<?php

namespace StockAlert\Admin;

class Stock_Notifications_Menu {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));

        add_action('wp_ajax_stock_notification', array($this, 'handle_stock_notification'));
        add_action('wp_ajax_nopriv_stock_notification', array($this, 'handle_stock_notification'));
        add_action('woocommerce_product_set_stock_status', array($this, 'send_stock_notifications'), 10, 3);

        add_action('woocommerce_product_set_stock', array($this, 'check_stock_and_notify'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Stock Notifications', 'stock-alert'),
            __('Stock Notifications', 'stock-alert'),
            'manage_options',
            'stock-notifications',
            array($this, 'admin_page'),
            'dashicons-email-alt'
        );
        add_submenu_page(
            'stock-notifications',
            __('Notification Settings', 'stock-alert'),
            __('Settings', 'stock-alert'),
            'manage_options',
            'stock-notifications-settings',
            array($this, 'settings_page')
        );
    }

    public function admin_page() {
        global $wpdb;

        if (isset($_POST['export_csv'])) {
            $this->generate_csv();
        }

        $table_name = $wpdb->prefix . 'stock_notifications';

        $items_per_page = 10;

        $paged = isset($_GET['paged']) && is_numeric($_GET['paged']) ? intval($_GET['paged']) : 1;
        $offset = ($paged - 1) * $items_per_page;

        $total_notifications = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY date_added DESC LIMIT %d OFFSET %d",
            $items_per_page,
            $offset
        ));

        include(STOCK_ALERT_PATH . 'templates/admin-page.php');
    }

    private function generate_csv() {
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

    public function settings_page() {
        if (isset($_POST['submit_settings'])) {
            update_option('stock_notification_threshold', intval($_POST['notification_threshold']));
            update_option('stock_notification_email_templates', wp_kses_post($_POST['email_templates']));
            echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'stock-alert') . '</p></div>';
        }

        $threshold = get_option('stock_notification_threshold', 1);
        $email_templates = get_option('stock_notification_email_templates', $this->get_default_email_templates());

        include(STOCK_ALERT_PATH . 'templates/settings-page.php');
    }

    private function get_default_email_templates() {
        return __(
            '<html>
                <head>
                    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet" type="text/css">
                </head>
                <body style="font-family: Roboto, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; background-color: #f5f9fc;">
                    <div style="max-width: 600px;margin: 0 auto;overflow: hidden;box-shadow: 0px 3px 3px -1px rgba(10, 22, 70, .1), 0px 0px 1px 0px rgba(10, 22, 70, .06) !important;background-color: #fff;background-clip: border-box;border: 1px solid #eceef3;border-radius: 6px;">
                        <div style="background-color: #5c60f5; color: #ffffff; padding: 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: 600;">Product Back in Stock</h1>
                        </div>
                        <div style="padding: 20px;">
                            <p>Hello,</p>
                            <p>Great news! The product <strong>{product_name}</strong> is now back in stock at <strong>{site_name}</strong>.</p>
                            <p>You can purchase it here: <a href="{product_url}" style="display: inline-block; padding: 10px 20px; margin: 10px 0; background-color: #5c60f5; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;">Buy Now</a></p>
                            <p>Thank you for your patience and interest in our products.</p>
                            <p>Best regards,</p>
                            <p>The team at <strong>{site_name}</strong></p>
                        </div>
                        <div style="background-color: #f1f1f1; text-align: center; padding: 10px; font-size: 12px; color: #16192c;">
                            <p>&copy; 2024 {site_name}. All rights reserved.</p>
                        </div>
                    </div>
                </body>
            </html>',
            'stock-alert'
        );
    }

    public function handle_stock_notification() {
        $email = sanitize_email($_POST['email']);
        $product_id = intval($_POST['product_id']);

        if (!is_email($email)) {
            wp_send_json_error(__('Invalid email address', 'stock-alert'));
        }

        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID', 'stock-alert'));
        }

        if ($this->is_rate_limited($email)) {
            wp_send_json_error(__('Too many requests. Please try again later.', 'stock-alert'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        $existing_notification = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE email = %s AND product_id = %d",
                $email,
                $product_id
            )
        );

        if ($existing_notification) {
            $time_difference = current_time('timestamp') - strtotime($existing_notification->date_added);
            if ($time_difference < 24 * 60 * 60) {
                wp_send_json_error(__('You have already subscribed to notifications for this product.', 'stock-alert'));
            } else {
                $wpdb->update(
                    $table_name,
                    array('date_added' => current_time('mysql')),
                    array('id' => $existing_notification->id)
                );
                wp_send_json_success(array(
                    'message' => __("Your notification subscription has been renewed for this product.", 'stock-alert'),
                    'alternatives' => $this->get_alternative_products($product_id)
                ));
            }
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'email' => $email,
                    'product_id' => $product_id,
                    'date_added' => current_time('mysql')
                )
            );

            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_name() : __('this product', 'stock-alert');

            wp_send_json_success(array(
                'message' => sprintf(__("Thank you! We've added your email to the notification list for %s. We'll let you know as soon as it's back in stock.", 'stock-alert'), $product_name),
                'alternatives' => $this->get_alternative_products($product_id)
            ));
        }
    }

    private function get_alternative_products($product_id) {
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

    public function send_stock_notifications($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d",
            $product_id
        ));

        $email_templates = get_option('stock_notification_email_templates', $this->get_default_email_templates());
        $product = wc_get_product($product_id);

        foreach ($notifications as $notification) {
            $to = $notification->email;
            $subject = sprintf(__('Product Back in Stock: %s', 'stock-alert'), $product->get_name());

            $message = str_replace(
                array('{product_name}', '{product_url}', '{site_name}'),
                array($product->get_name(), get_permalink($product_id), get_bloginfo('name')),
                $email_templates
            );

            $headers = array('Content-Type: text/html; charset=UTF-8');

            wp_mail($to, $subject, $message, $headers);

            $wpdb->delete($table_name, array('id' => $notification->id));
        }
    }

    public function check_stock_and_notify($product) {
        $product_id = $product->get_id();
        $stock_quantity = $product->get_stock_quantity();
        $notification_threshold = get_option('stock_notification_threshold', 1);

        if ($stock_quantity >= $notification_threshold) {
            $this->send_stock_notifications($product_id);
        }
    }

    private function is_rate_limited($email) {
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
}
