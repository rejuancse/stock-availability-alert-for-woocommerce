<?php
/**
 * Plugin Name: Stock Availability Alert for WooCommerce
 * Description: Notify customers when out-of-stock WooCommerce products are back in stock. Features "Notify Me" option and automatic email alerts.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            TheBitCraft
 * Author URI:        https://thebitcraft.com
 * Text Domain:       stock-availability-alert-for-woocommerce
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
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
        add_action( 'init', array( $this, 'stock_alert_language_load' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_script' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_script' ) );
    }

    /**
    * Load Text Domain Language
    */
    function stock_alert_language_load(){
        load_plugin_textdomain( 'stock-availability-alert-for-woocommerce', false, basename( dirname( __FILE__ ) ).'/languages/' );
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
        define( 'STOCK_AVAILABILITY_ALERT_VERSION', self::version );
        define( 'STOCK_AVAILABILITY_ALERT_FILE', __FILE__ );
        define( 'STOCK_AVAILABILITY_ALERT_PATH', plugin_dir_path( STOCK_AVAILABILITY_ALERT_FILE ) ); // Correct path to the plugin's directory
        define( 'STOCK_AVAILABILITY_ALERT_URL', plugin_dir_url( STOCK_AVAILABILITY_ALERT_FILE ) );   // Correct URL for the plugin's assets
        define( 'STOCK_AVAILABILITY_ALERT_ASSETS', STOCK_AVAILABILITY_ALERT_URL . 'assets' );        // URL for the plugin's assets directory
    }

    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
        $installer = new Stock_Availability_Alert\Installer();
        $installer->run();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {
        new Stock_Availability_Alert\Admin();
        new Stock_Availability_Alert\Frontend();
    }

    /**
     * Registering necessary js and css
     * @ Frontend
     */
    public function frontend_script(){
        wp_enqueue_style( 'stock-alert-front', STOCK_AVAILABILITY_ALERT_URL .'/assets/dist/css/notify-style.css', false, STOCK_AVAILABILITY_ALERT_VERSION );

        #JS
        wp_enqueue_script( 'stock-alert-notify-script', STOCK_AVAILABILITY_ALERT_URL .'/assets/dist/js/notify-script.js', array('jquery'), STOCK_AVAILABILITY_ALERT_VERSION, true );
        wp_localize_script( 'stock-alert-notify-script', 'notify_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    }

    /**
     * Registering necessary js and css
     * @ Admin
     */
    public function admin_script(){
        wp_enqueue_style( 'stock-alert-admin', STOCK_AVAILABILITY_ALERT_URL .'/assets/dist/css/stock-admin.css', false, STOCK_AVAILABILITY_ALERT_VERSION );
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
