<?php

namespace StockAlert;

/**
 * The admin class
 */
class Admin {

    protected $wpdb;
    protected $table_name;

    /**
     * Initialize the class
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'stock_notifications';

        new Admin\Initial_Setup();
        new Admin\Stock_Notifications_Menu();

        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
    }

    /**
     * Add the dashboard widget to the WordPress admin dashboard.
     *
     * @return void
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'stock_notification_dashboard_widget',
            __('Stock Notification Statistics', 'stock-availability-alert-for-woocommerce'),
            array($this, 'dashboard_widget_function')
        );
    }

    /**
     * Retrieve stock notification statistics and include the dashboard widget template.
     *
     * This function counts total notifications, unique products, and unique emails
     * from the stock_notifications table, then includes the corresponding template.
     *
     * @since   1.0.0
     * @access  public
     * @return  void
     */
    public function dashboard_widget_function() {
        try {
            // Retrieve stock notification statistics
            $statistics = $this->get_stock_notification_statistics();

            // Include the dashboard widget template
            include_once( STOCK_ALERT_PATH . 'templates/dashboard-widget.php' );
        } catch (\Exception $e) {
            // Handle any exceptions that occur during data retrieval
            error_log('Error retrieving stock notification statistics: ' . $e->getMessage());
            echo '<p>An error occurred while retrieving data. Please try again later.</p>';
        }
    }

    /**
     * Get stock notification statistics from the database.
     *
     * @return array An associative array containing total notifications, unique products, and unique emails.
     */
    protected function get_stock_notification_statistics() {
        return [
            'total_notifications' => $this->get_total_notifications(),
            'unique_products' => $this->get_unique_products(),
            'unique_emails' => $this->get_unique_emails(),
        ];
    }

    /**
     * Get the total number of stock notifications.
     *
     * @return int The total count of notifications.
     */
    protected function get_total_notifications() {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}") ?: 0;
    }

    /**
     * Get the count of unique products with stock notifications.
     *
     * @return int The count of unique products.
     */
    protected function get_unique_products() {
        return (int) $this->wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM {$this->table_name}") ?: 0;
    }

    /**
     * Get the count of unique email addresses subscribed to notifications.
     *
     * @return int The count of unique email addresses.
     */
    protected function get_unique_emails() {
        return (int) $this->wpdb->get_var("SELECT COUNT(DISTINCT email) FROM {$this->table_name}") ?: 0;
    }
}