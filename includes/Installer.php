<?php

namespace StockAlert;

/**
 * Installer class
 *
 * @package StockAlert
 * @since 1.0.0
 */
class Installer {

    /**
     * Run the installer
     *
     * @since   1.0.0
     * @access  public
     * @return  void
     */
    public function run() {
        $this->add_version();
        $this->create_tables();
    }

    /**
     * Add time and version to the database
     *
     * @since   1.0.0
     * @access  public
     * @return  void
     */
    public function add_version() {
        $installed = get_option('stock_alert_installed');

        if (!$installed) {
            update_option('stock_alert_installed', time());
        }

        update_option('stock_alert_version', STOCK_ALERT_VERSION);
    }

    /**
     * Create necessary tables
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function create_tables() {
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
}

// Register the activation hook in the main plugin file
register_activation_hook(__FILE__, array('StockAlert\Installer', 'run'));
