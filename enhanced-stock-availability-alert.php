<?php
/**
 * Plugin Name: Enhanced Stock Availability Alert
 * Description: Implement a notification system for out-of-stock items with advanced features including admin interface, better product suggestions, customizable emails, and rate limiting.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Rejuan Ahamed
 * Text Domain:       stock-alert
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */


defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class Enhanced_Stock_Availability_Alert {

    /**
     * Plugin version
     *
     * @var string
     */
    const version = '1.0.0';

    /**
     * Class construcotr
     */
    private function __construct() {
        $this->define_constants();
        add_action( 'init', [ $this, 'stock_alert_language_load' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
        add_action('wp_enqueue_scripts', [ $this, 'frontend_script' ]);
    }

    /**
    * Load Text Domain Language
    */
    function stock_alert_language_load(){
        load_plugin_textdomain( 'stock-alert', false, basename(dirname( __FILE__ )).'/languages/');
    }

    /**
     * Initialize a singleton instance
     * @return \Enhanced_Stock_Availability_Alert
     */
    public static function init() {
        static $instance = false;

        if( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'STOCK_ALERT_VERSION', self::version );
        define( 'STOCK_ALERT_FILE', __FILE__ );
        define( 'STOCK_ALERT_PATH', __DIR__ );
        define( 'STOCK_ALERT_URL', plugins_url( '', STOCK_ALERT_FILE ) );
        define( 'STOCK_ALERT_ASSETS', STOCK_ALERT_URL . '/assets' );
    }

    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
        $installed = get_option( 'stock_alert_installed' );

        if ( ! $installed ) {
            update_option( 'stock_alert_installed', time() );
        }

        update_option( 'stock_alert_version', STOCK_ALERT_VERSION );
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {
        if ( is_admin() ) {
            new StockAlert\Admin();
        }

        // else {
        //     new StockAlert\Frontend();
        // }
    }

    /**
     * Registering necessary js and css
     * @ Frontend
     */
    public function frontend_script(){
        wp_enqueue_script('stock-alert-notify-script', STOCK_ALERT_URL .'/assets/dist/js/notify-script.js', array('jquery'), STOCK_ALERT_VERSION, true);
        wp_localize_script('stock-alert-notify-script', 'notify_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }
}

/**
 * Initilizes the main plugin
 */
function stock_availability_get_alert() {
    return Enhanced_Stock_Availability_Alert::init();
}

// Kick-off the plugin
stock_availability_get_alert();

// if (!function_exists('banner_image_function')) {
//     function banner_image_function() {
//         return new Banner_Image\Functions();
//     }
// }





class WC_Stock_Notification {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('woocommerce_single_product_summary', array($this, 'add_notify_me_button'), 30);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_stock_notification', array($this, 'handle_stock_notification'));
        add_action('wp_ajax_nopriv_stock_notification', array($this, 'handle_stock_notification'));
        add_action('woocommerce_product_set_stock_status', array($this, 'send_stock_notifications'), 10, 3);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('woocommerce_product_set_stock', array($this, 'check_stock_and_notify'));

        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function load_plugin_textdomain() {
        load_plugin_textdomain('wc-stock-notification', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_notify_me_button() {
        global $product;
        if (!$product->is_in_stock()) {
            echo '<button class="button" id="notify-me-button">' . esc_html__('Notify Me When Available', 'wc-stock-notification') . '</button>';
            echo '<div id="notify-me-form" style="display:none;">
                    <input type="email" id="notify-email" placeholder="' . esc_attr__('Enter your email', 'wc-stock-notification') . '">
                    <input type="hidden" id="notify-product-id" value="' . esc_attr($product->get_id()) . '">
                    <button id="submit-notify">' . esc_html__('Submit', 'wc-stock-notification') . '</button>
                  </div>';
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script('notify-script', plugin_dir_url(__FILE__) . 'assets/js/notify-script.js', array('jquery'), '2.0', true);
        wp_localize_script('notify-script', 'notify_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }

    public function handle_stock_notification() {
        $email = sanitize_email($_POST['email']);
        $product_id = intval($_POST['product_id']);

        if (!is_email($email)) {
            wp_send_json_error(__('Invalid email address', 'wc-stock-notification'));
        }

        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID', 'wc-stock-notification'));
        }

        if ($this->is_rate_limited($email)) {
            wp_send_json_error(__('Too many requests. Please try again later.', 'wc-stock-notification'));
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
                wp_send_json_error(__('You have already subscribed to notifications for this product. Please wait 24 hours before trying again.', 'wc-stock-notification'));
            } else {
                $wpdb->update(
                    $table_name,
                    array('date_added' => current_time('mysql')),
                    array('id' => $existing_notification->id)
                );
                wp_send_json_success(array(
                    'message' => __("Your notification subscription has been renewed for this product.", 'wc-stock-notification'),
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
            $product_name = $product ? $product->get_name() : __('this product', 'wc-stock-notification');

            wp_send_json_success(array(
                'message' => sprintf(__("Thank you! We've added your email to the notification list for %s. We'll let you know as soon as it's back in stock.", 'wc-stock-notification'), $product_name),
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

    public function activate() {
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

        add_option('stock_notification_email_template', $this->get_default_email_template());
    }

    public function send_stock_notifications($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d",
            $product_id
        ));

        $email_template = get_option('stock_notification_email_template', $this->get_default_email_template());
        $product = wc_get_product($product_id);

        foreach ($notifications as $notification) {
            $to = $notification->email;
            $subject = sprintf(__('Product Back in Stock: %s', 'wc-stock-notification'), $product->get_name());

            $message = str_replace(
                array('{product_name}', '{product_url}', '{site_name}'),
                array($product->get_name(), get_permalink($product_id), get_bloginfo('name')),
                $email_template
            );

            $headers = array('Content-Type: text/html; charset=UTF-8');

            wp_mail($to, $subject, $message, $headers);

            $wpdb->delete($table_name, array('id' => $notification->id));
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Stock Notifications', 'wc-stock-notification'),
            __('Stock Notifications', 'wc-stock-notification'),
            'manage_options',
            'stock-notifications',
            array($this, 'admin_page'),
            'dashicons-email-alt'
        );
        add_submenu_page(
            'stock-notifications',
            __('Export Notifications', 'wc-stock-notification'),
            __('Export CSV', 'wc-stock-notification'),
            'manage_options',
            'stock-notifications-export',
            array($this, 'export_page')
        );
        add_submenu_page(
            'stock-notifications',
            __('Notification Settings', 'wc-stock-notification'),
            __('Settings', 'wc-stock-notification'),
            'manage_options',
            'stock-notifications-settings',
            array($this, 'settings_page')
        );
    }

    public function admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        if (isset($_POST['email_template'])) {
            update_option('stock_notification_email_template', wp_kses_post($_POST['email_template']));
            echo '<div class="updated"><p>' . esc_html__('Email template updated.', 'wc-stock-notification') . '</p></div>';
        }

        $notifications = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date_added DESC");

        include(plugin_dir_path(__FILE__) . 'templates/admin-page.php');
    }

    public function export_page() {
        if (isset($_POST['export_csv'])) {
            $this->generate_csv();
        }

        include(plugin_dir_path(__FILE__) . 'templates/export-page.php');
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
            update_option('stock_notification_email_template', wp_kses_post($_POST['email_template']));
            echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'wc-stock-notification') . '</p></div>';
        }

        $threshold = get_option('stock_notification_threshold', 1);
        $email_template = get_option('stock_notification_email_template', $this->get_default_email_template());

        include(plugin_dir_path(__FILE__) . 'templates/settings-page.php');
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'stock_notification_dashboard_widget',
            __('Stock Notification Statistics', 'wc-stock-notification'),
            array($this, 'dashboard_widget_function')
        );
    }

    public function dashboard_widget_function() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        $total_notifications = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $unique_products = $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM $table_name");
        $unique_emails = $wpdb->get_var("SELECT COUNT(DISTINCT email) FROM $table_name");

        include(plugin_dir_path(__FILE__) . 'templates/dashboard-widget.php');
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

    private function get_default_email_template() {
        return __(
            "Hello,

Great news! The product \"{product_name}\" is now back in stock at {site_name}.

You can purchase it here: {product_url}

Thank you for your patience and interest in our products.

Best regards,
The team at {site_name}",
            'wc-stock-notification'
        );
    }
}

function WC_Stock_Notification() {
    return WC_Stock_Notification::get_instance();
}

WC_Stock_Notification();
