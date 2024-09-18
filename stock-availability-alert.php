<?php
/**
 * Plugin Name: Stock Availability Alert
 * Description: Implement a notification system for out-of-stock items with advanced features including admin interface, better product suggestions, customizable emails, and rate limiting.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            TheBitCraft
 * Text Domain:       stock-alert
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */


defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class Stock_Availability_Alert {

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
        add_action('admin_enqueue_scripts', [ $this, 'admin_script' ]);
    }

    /**
    * Load Text Domain Language
    */
    function stock_alert_language_load(){
        load_plugin_textdomain( 'stock-alert', false, basename(dirname( __FILE__ )).'/languages/');
    }

    /**
     * Initialize a singleton instance
     * @return \Stock_Availability_Alert
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
        define( 'STOCK_ALERT_PATH', plugin_dir_path( STOCK_ALERT_FILE ) ); // Correct path to the plugin's directory
        define( 'STOCK_ALERT_URL', plugin_dir_url( STOCK_ALERT_FILE ) );   // Correct URL for the plugin's assets
        define( 'STOCK_ALERT_ASSETS', STOCK_ALERT_URL . 'assets' );        // URL for the plugin's assets directory
    }

    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
        $installer = new StockAlert\Installer();
        $installer->run();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {
        if ( is_admin() ) {
            new StockAlert\Admin();
        } else {
            new StockAlert\Frontend();
        }
    }

    /**
     * Registering necessary js and css
     * @ Frontend
     */
    public function frontend_script(){
        wp_enqueue_style( 'stock-front', STOCK_ALERT_URL .'/assets/dist/css/notify-style.css', false, STOCK_ALERT_VERSION );

        wp_enqueue_script('stock-alert-notify-script', STOCK_ALERT_URL .'/assets/dist/js/notify-script.js', array('jquery'), STOCK_ALERT_VERSION, true);
        wp_localize_script('stock-alert-notify-script', 'notify_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }

    public function admin_script(){
        wp_enqueue_style( 'stock-admin', STOCK_ALERT_URL .'/assets/dist/css/stock-admin.css', false, STOCK_ALERT_VERSION );
    }
}

/**
 * Initilizes the main plugin
 */
function stock_availability_get_alert() {
    return Stock_Availability_Alert::init();
}

// Kick-off the plugin
stock_availability_get_alert();



function handle_bulk_action_stock_notifications() {
    if (!isset($_POST['submit_bulk_action']) || !isset($_POST['bulk_action_nonce']) || !wp_verify_nonce($_POST['bulk_action_nonce'], 'bulk_action')) {
        return;
    }

    if (isset($_POST['notifications']) && is_array($_POST['notifications'])) {
        $action = sanitize_text_field($_POST['bulk_action']);

        if ($action === 'delete') {
            foreach ($_POST['notifications'] as $notification_id) {
                // Assuming you have a function to delete a notification by ID
                delete_stock_notification($notification_id);
            }

            // Optionally, you can add an admin notice after deletion
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html__('Selected notifications have been deleted.', 'stock-alert') . '</p>';
                echo '</div>';
            });
        }
    }
}
add_action('admin_init', 'handle_bulk_action_stock_notifications');


function delete_stock_notification($notification_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'stock_notifications'; // Assuming this is your table
    $wpdb->delete($table, array('id' => intval($notification_id)), array('%d'));
}
