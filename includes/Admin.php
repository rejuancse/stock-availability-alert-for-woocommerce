<?php

namespace StockAlert;

/**
 * The admin class
 */
class Admin {

    /**
     * Initialize the class
     */
    function __construct() {
        new Admin\Initial_Setup();
        new Admin\Stock_Notifications_Menu();

        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'stock_notification_dashboard_widget',
            __('Stock Notification Statistics', 'stock-alert'),
            array($this, 'dashboard_widget_function')
        );
    }

    public function dashboard_widget_function() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        $total_notifications = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $unique_products = $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM $table_name");
        $unique_emails = $wpdb->get_var("SELECT COUNT(DISTINCT email) FROM $table_name");

        include(STOCK_ALERT_PATH . 'templates/dashboard-widget.php');
    }
}
