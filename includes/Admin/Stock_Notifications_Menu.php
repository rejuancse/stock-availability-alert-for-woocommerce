<?php

namespace Stock_Availability_Alert\Admin;

/**
 * Class Stock_Notifications_Menu
 *
 * Handles the admin menu for stock notifications and associated functionalities.
 */
class Stock_Notifications_Menu {
    private static $instance = null;

    /**
     * Retrieves the singleton instance of the class.
     *
     * @return Stock_Notifications_Menu Singleton instance.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor for initializing hooks and actions for the plugin.
     *
     * This method registers various WordPress and WooCommerce actions that
     * connect plugin functionality to WordPress events and AJAX requests.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'wp_ajax_stock_notification', array( $this, 'handle_stock_notification' ) );
        add_action( 'wp_ajax_nopriv_stock_notification', array( $this, 'handle_stock_notification' ) );
        add_action( 'woocommerce_product_set_stock_status', array( $this, 'send_stock_notifications'), 10, 3);
        add_action( 'woocommerce_product_set_stock', array( $this, 'check_stock_and_notify' ) );
        add_action( 'admin_init', array( $this, 'handle_bulk_action_stock_notifications' ) );
    }

    /**
     * Adds the admin menu items for the stock notifications plugin.
     *
     * This method creates the main menu and a submenu under the WordPress admin dashboard.
     * It defines the menu pages and their corresponding callback functions.
     *
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Stock Availability Alert', 'stock-availability-alert-for-woocommerce' ),
            __( 'Stock Availability Alert', 'stock-availability-alert-for-woocommerce' ),
            'manage_options',
            'stock-availability-alert',
            array( $this, 'admin_page' ),
            'dashicons-email-alt'
        );

        add_submenu_page(
            'stock-availability-alert',
            __( 'Alert Settings', 'stock-availability-alert-for-woocommerce' ),
            __( 'Settings', 'stock-availability-alert-for-woocommerce' ),
            'manage_options',
            'stock-availability-alert-settings',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Displays the admin page for managing stock notifications.
     *
     * This method handles CSV export requests, fetches stock notifications from the database,
     * and includes the admin page template to render the notifications list.
     *
     * @return void
     */
    public function admin_page() {
        global $wpdb;

        // Handle CSV export if the export button was clicked.
        if ( isset( $_POST['export_csv'] ) ) {
            $this->generate_csv(); // Call the method to generate and download the CSV.
        }

        // Table name for stock notifications in the database.
        $table_name = $wpdb->prefix . 'stock_notifications';

        // Number of notifications to show per page.
        $items_per_page = 10;

        // Get the current page number, ensuring it's valid.
        $paged = isset( $_GET['paged'] ) && is_numeric( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
        $offset = ( $paged - 1 ) * $items_per_page;

        // Fetch the total number of stock notifications to calculate pagination.
        $total_notifications = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        // Fetch the notifications for the current page, using SQL LIMIT and OFFSET.
        $notifications = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY date_added DESC LIMIT %d OFFSET %d",
            $items_per_page,
            $offset
        ) );

        // Path to the admin page template file.
        $template_path = STOCK_AVAILABILITY_ALERT_PATH . 'templates/admin-page.php';

        // Check if the template exists before including it.
        if ( file_exists( $template_path ) ) {
            include $template_path; // No parentheses needed for include.
        } else {
            // Template not found, display an error or a fallback message.
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Template file not found.', 'stock-availability-alert-for-woocommerce' ) . '</p></div>';
        }
    }

    /**
     * Generates a CSV file of stock notifications and initiates a download.
     *
     * This method fetches stock notification data from the database, generates a CSV file with the data,
     * and sets appropriate headers to force the browser to download the file.
     *
     * @return void
     */
    private function generate_csv() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        // Select relevant columns to match the CSV headers.
        $notifications = $wpdb->get_results( "SELECT id, email, product_id, date_added FROM {$table_name}", ARRAY_A );

        // Set headers for the CSV file.
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="stock_notifications.csv"' );

        // Open output stream.
        $output = fopen( 'php://output', 'w' );

        // Write the CSV column headers.
        fputcsv( $output, array( 'ID', 'Email', 'Product ID', 'Date Added' ) );

        // Write each row of notification data to the CSV.
        foreach ( $notifications as $notification ) {
            fputcsv( $output, $notification );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $output );

        // Stop further script execution after file download.
        exit;
    }

    /**
     * Displays and handles the settings page for stock notifications.
     *
     * This method processes form submissions to update stock notification settings,
     * retrieves the current settings, and includes the settings page template if it exists.
     *
     * @return void
     */
    public function settings_page() {
        if (isset($_POST['submit_settings'])) {
            if ( isset( $_POST['notification_threshold'] ) ) {
                update_option('stock_notification_threshold', intval( sanitize_text_field( wp_unslash( $_POST['notification_threshold'] ) ) ) );
            }

            if ( isset( $_POST['email_templates'] ) ) {
                update_option('stock_notification_email_templates', wp_kses_post( wp_unslash( $_POST['email_templates'] ) ) );
            }

            // Display success message
            echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'stock-availability-alert-for-woocommerce') . '</p></div>';
        }

        // Retrieve current saved options; use defaults if not set
        $threshold = get_option('stock_notification_threshold', 1);
        $email_templates = get_option('stock_notification_email_templates', $this->get_default_email_templates());

        // Path to the settings page template
        $template_path = STOCK_AVAILABILITY_ALERT_PATH . 'templates/settings-page.php';

        // Check if the settings page template exists and include it
        if (file_exists($template_path)) {
            include($template_path);
        } else {
            // Display an error message if the template file does not exist
            echo '<div class="error"><p>' . esc_html__('Settings page template not found.', 'stock-availability-alert-for-woocommerce') . '</p></div>';
        }
    }

    /**
     * Returns the default email templates for stock notifications.
     *
     * @return string Default email templates.
     */
    private function get_default_email_templates() {
        $template = '';

        // Header section
        $template .= '<table class="body" style="border-collapse: collapse; border-spacing: 0; vertical-align: top; height: 100% !important; width: 100% !important; min-width: 100%; background-color: #f5f9fc; color: #444; font-family: Helvetica,sans-serif; font-weight: normal; padding: 0; margin: 0; text-align: left; font-size: 14px; line-height: 140%;" border="0" width="100%" cellspacing="0" cellpadding="0">
            <tbody>
                <tr style="padding: 0; vertical-align: top; text-align: left;">
                    <td class="body-inner wp-mail-smtp" style="border-collapse: collapse !important; vertical-align: top; color: #444; font-family: Helvetica,sans-serif; font-weight: normal; padding: 0; margin: 0; font-size: 14px; line-height: 140%; text-align: center;" align="center" valign="top">
                        <table class="container" style="border-collapse: collapse; border-spacing: 0; padding: 0; vertical-align: top; width: 600px; margin: 20px auto 0; text-align: inherit;" border="0" cellspacing="0" cellpadding="0">
                            <tbody>
                                <tr>
                                    <td style="padding: 0;">
                                        <div style="background-color: #34495e; color: #f1c40f; padding: 20px; text-align: center;">
                                            <h1 style="margin: 0; font-size: 20px; font-weight: 600;">' . esc_html__('Product Back in Stock', 'stock-availability-alert-for-woocommerce') . '</h1>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <table class="container" style="border-collapse: collapse; border-spacing: 0; padding: 0; vertical-align: top; width: 600px; margin: 0 auto; text-align: inherit;" border="0" cellspacing="0" cellpadding="0">
                            <tbody>
                                <tr style="padding: 0; vertical-align: top; text-align: left;">
                                    <td class="content" style="border-collapse: collapse !important; vertical-align: top; color: #444; font-family: Helvetica,sans-serif; font-weight: normal; margin: 0; text-align: left; font-size: 14px; line-height: 140%; padding: 60px 75px 45px 75px; position: relative; flex-direction: column; min-width: 0; background-color: #fff; border: 1px solid #eceef3;" align="left" valign="top">
                                        <div class="success">
                                            <p class="text-large" style="color: #444; font-family: Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; text-align: left; line-height: 140%; margin: 0 0 15px 0; font-size: 14px;">' . esc_html__('Hello,', 'stock-availability-alert-for-woocommerce') . '</p>
                                            <p class="text-large" style="color: #444; font-family: Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; text-align: left; line-height: 140%; margin: 0 0 15px 0; font-size: 14px;">' . wp_kses_post(__('Great news! The product <strong>{product_name}</strong> is now back in stock at <strong>{site_name}</strong>.', 'stock-availability-alert-for-woocommerce')) . '</p>
                                            <p class="text-large" style="color: #444; font-family: Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; text-align: left; line-height: 140%; margin: 0 0 15px 0; font-size: 14px;">' . esc_html__('You can purchase it here:', 'stock-availability-alert-for-woocommerce') . ' <a style="padding: 10px 20px; margin: 10px 0; background-color: #f1c40f; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;" href="{product_url}">' . esc_html__('Buy Now', 'stock-availability-alert-for-woocommerce') . '</a></p>
                                            <p class="text-large" style="color: #444; font-family: Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; text-align: left; line-height: 140%; margin: 0 0 15px 0; font-size: 14px;">' . esc_html__('Thank you for your patience and interest in our products.', 'stock-availability-alert-for-woocommerce') . '</p>
                                            <p class="text-large" style="color: #444; font-family: Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; text-align: left; line-height: 140%; margin: 0 0 15px 0; font-size: 14px;">' . esc_html__('Best Regards,', 'stock-availability-alert-for-woocommerce') . '</p>
                                            <p class="text-large" style="color: #444; font-family: Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; text-align: left; line-height: 140%; margin: 0 0 15px 0; font-size: 14px;">' . esc_html__('The {site_name} Team', 'stock-availability-alert-for-woocommerce') . '</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <table class="container" style="border-collapse: collapse; border-spacing: 0; padding: 0; vertical-align: top; width: 600px; margin: 0 auto 30px; text-align: inherit;" border="0" cellspacing="0" cellpadding="0">
                            <tbody>
                                <tr>
                                    <td style="padding: 0;">
                                        <div style="background-color: #34495e; color: #ffffff; padding: 12px 20px; text-align: center;">
                                            <span style="margin: 0; font-size: 14px; font-weight: 400;">© 2024 {site_name} | ' . esc_html__('All rights reserved.', 'stock-availability-alert-for-woocommerce') . '</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>';

        return $template;
    }

    /**
     * Handles the stock notification AJAX request.
     *
     * Sanitizes and validates the email and product ID from the request.
     * Checks if the request is rate-limited.
     * Checks for existing notifications and either renews or creates a new one.
     * Sends appropriate JSON response based on the outcome.
     */
    public function handle_stock_notification() {
        if( isset( $_POST['email'] ) && isset( $_POST['product_id'] ) ) {
            // Sanitize and validate inputs
            $email = $this->sanitize_and_validate_email( sanitize_email( wp_unslash( $_POST['email'] ) ) );
            $product_id = $this->sanitize_and_validate_product_id( absint( wp_unslash( $_POST['product_id'] ) ) );

            // Check rate limiting to prevent multiple requests
            if ($this->is_rate_limited($email)) {
                wp_send_json_error( __( 'Too many requests. Please try again later.', 'stock-availability-alert-for-woocommerce' ) );
            }

            // Check for existing notification
            $existing_notification = $this->get_existing_notification( $email, $product_id );

            if ( $existing_notification ) {
                $this->handle_existing_notification( $existing_notification, $product_id );
            } else {
                $this->create_new_notification( $email, $product_id );
            }
        }
    }

    /**
     * Sanitizes and validates the email address.
     *
     * @param string $email The email address to be validated.
     * @return string The sanitized and validated email address.
     * @throws WP_Error If the email address is invalid.
     */
    private function sanitize_and_validate_email( $email ): string {
        $email = sanitize_email( $email );

        if (!is_email($email)) {
            wp_send_json_error( __( 'Invalid email address', 'stock-availability-alert-for-woocommerce' ) );
        }

        return $email;
    }

    /**
     * Sanitizes and validates the product ID.
     *
     * @param mixed $product_id The product ID to be validated.
     * @return int The validated product ID.
     * @throws WP_Error If the product ID is invalid.
     */
    private function sanitize_and_validate_product_id($product_id): int {
        $product_id = intval( $product_id );

        if ($product_id <= 0) {
            wp_send_json_error( __( 'Invalid product ID', 'stock-availability-alert-for-woocommerce' ) );
        }

        return $product_id;
    }

    /**
     * Retrieves an existing notification based on email and product ID.
     *
     * @param string $email The email address of the user.
     * @param int $product_id The ID of the product.
     * @return object|null The notification record or null if not found.
     */
    private function get_existing_notification(string $email, int $product_id): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE email = %s AND product_id = %d",
                $email,
                $product_id
            )
        );
    }

    /**
     * Handles an existing notification by renewing it if it's older than 24 hours.
     *
     * @param object $existing_notification The existing notification record.
     * @param int $product_id The ID of the product for which notifications are managed.
     */
    private function handle_existing_notification(object $existing_notification, int $product_id) {
        $time_difference = current_time('timestamp') - strtotime($existing_notification->date_added);

        // If the existing notification is within the last 24 hours
        if ($time_difference < 24 * 60 * 60) {
            wp_send_json_error(__('You have already subscribed to notifications for this product.', 'stock-availability-alert-for-woocommerce'));
        }

        // Renew the subscription
        $this->renew_notification($existing_notification->id);
        wp_send_json_success(array(
            'message' => __("Your notification subscription has been renewed for this product.", 'stock-availability-alert-for-woocommerce'),
            'alternatives' => $this->get_alternative_products($product_id)
        ));
    }

    /**
     * Renews the notification by updating the date_added field.
     *
     * @param int $notification_id Notification ID to be renewed.
     */
    private function renew_notification(int $notification_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        // Update the notification's date_added field
        $wpdb->update(
            $table_name,
            array('date_added' => current_time('mysql')),
            array('id' => intval($notification_id)),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Creates a new stock notification and sends a confirmation message.
     *
     * @param string $email User's email address.
     * @param int $product_id Product ID.
     */
    private function create_new_notification(string $email, int $product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        // Insert notification into database
        $wpdb->insert(
            $table_name,
            array(
                'email'         => sanitize_email($email),
                'product_id'    => intval($product_id),
                'date_added'    => current_time('mysql')
            ),
            array( '%s', '%d', '%s' )
        );

        // Send confirmation to user
        $this->send_subscription_confirmation( $product_id );
    }

    /**
     * Sends a JSON response confirming the subscription and providing product alternatives.
     *
     * This method generates a JSON response to confirm the user's subscription to stock notifications
     * for a specified product. It includes a thank you message and a list of alternative products.
     *
     * @param int $product_id The ID of the product for which the subscription is made.
     * @return void
     */
    private function send_subscription_confirmation( int $product_id ) {
        // Retrieve the product object from the product ID
        $product = wc_get_product($product_id);

        // Determine the product name, defaulting to 'this product' if not found
        $product_name = $product ? $product->get_name() : __('this product', 'stock-availability-alert-for-woocommerce');

        $response_data = array(
            'message'      => sprintf(
                /* translators: %s: product name */
                __( 'Thank you for subscribing! We\'ll notify you as soon as the <strong>%s</strong> is back in stock. Stay tuned!', 'stock-availability-alert-for-woocommerce' ),
                esc_html( $product_name )
            ),
            'alternatives' => $this->get_alternative_products( $product_id ),
        );

        // Translators: %d is the number of alternative products.
        wp_send_json_success( $response_data );
    }

    private function get_alternative_products( $product_id ) {
        // Get the product and its categories and tags
        $product = wc_get_product( $product_id );
        if ( !$product ) {
            return array(); // Return an empty array if product is not found
        }

        $category_ids = $product->get_category_ids();
        $tag_ids = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );

        // Prepare query arguments to fetch alternative products
        $args = array(
            'category'          => $category_ids,
            'tag'               => $tag_ids,
            'posts_per_page'    => 5, // Limit to 5 alternative products
            'post__not_in'      => array($product_id), // Exclude the current product
            'post_status'       => 'publish',
            'meta_query'        => array(
                array(
                    'key'       => '_stock_status',
                    'value'     => 'instock' // Only get products that are in stock
                )
            )
        );

        // Fetch products based on category, tag, and stock status
        $alternative_products = wc_get_products( $args );

        // Initialize an array to hold the alternative product details
        $alternatives = array();

        // Loop through the fetched products and extract relevant details
        foreach ($alternative_products as $alt_product) {
            $alternatives[] = array(
                'id'        => $alt_product->get_id(),
                'name'      => $alt_product->get_name(),
                'url'       => get_permalink($alt_product->get_id()),
                'price'     => $alt_product->get_price(),
                'image'     => wp_get_attachment_url($alt_product->get_image_id())
            );
        }

        return $alternatives;
    }

    /**
     * Sends stock notifications to users when product stock status changes.
     *
     * @param int $product_id Product ID.
     * @param string $stock_status New stock status.
     * @param int $old_stock_status Old stock status.
     */
    public function send_stock_notifications( $product_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        // Fetch notifications for the given product ID
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d",
            $product_id
        ));

        // Get email templates and product details
        $email_templates = get_option('stock_notification_email_templates', $this->get_default_email_templates());
        $product = wc_get_product($product_id);

        if (!$product) {
            return; // Exit if the product is not found
        }

        // Prepare email headers
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Loop through each notification and send emails
        foreach ($notifications as $notification) {
            $to = sanitize_email( $notification->email );
            $subject = sprintf(
                /* translators: %s: product name */
                __( 'Product Back in Stock: %s', 'stock-availability-alert-for-woocommerce' ),
                esc_html( $product->get_name() )
            );

            // Replace placeholders in the email template with actual values
            $message = str_replace(
                array( '{product_name}', '{product_url}', '{site_name}' ),
                array(
                    esc_html( $product->get_name() ),
                    esc_url( get_permalink( $product_id ) ),
                    esc_html( get_bloginfo( 'name' ) ),
                ),
                wp_kses_post( $email_templates )
            );

            // Send the email
            wp_mail( $to, $subject, $message, $headers );

            // Delete the notification after sending the email
            $wpdb->delete( $table_name, array( 'id' => $notification->id ) );
        }
    }

    /**
     * Checks stock levels and notifies users if necessary.
     *
     * @param int $product_id Product ID.
     */
    public function check_stock_and_notify($product) {
        // Ensure $product is a valid WC_Product object
        if (!$product instanceof WC_Product) {
            return; // Exit if the product is not a valid WC_Product instance
        }

        $product_id = $product->get_id();
        $stock_quantity = $product->get_stock_quantity();
        $notification_threshold = get_option('stock_notification_threshold', 1);

        // Ensure stock quantity and notification threshold are integers
        $stock_quantity = intval($stock_quantity);
        $notification_threshold = intval($notification_threshold);

        // Check if the stock quantity is above the threshold
        if ( $stock_quantity >= $notification_threshold ) {
            $this->send_stock_notifications( $product_id );
        }
    }

    /**
     * Checks if the email address has exceeded the allowed number of notifications
     * within a specified rate limit (e.g., 5 notifications per hour).
     *
     * This method uses a transient to keep track of the number of requests from
     * a particular email address. If the number of requests exceeds the limit,
     * the email address is considered rate-limited.
     *
     * @param string $email The email address to check for rate-limiting.
     * @return bool Returns true if the email address is rate-limited, otherwise false.
     */
    private function is_rate_limited( $email ) {
        // Validate email format
        if (!is_email( $email )) {
            return true; // Treat invalid email addresses as rate-limited
        }

        // Generate a unique transient name based on the email address
        $transient_name = 'stock_notify_' . md5( $email );
        // Retrieve the current count of requests from the transient
        $count = get_transient( $transient_name );

        // If no transient is found, initialize it with a count of 1
        if ( $count === false ) {
            set_transient( $transient_name, 1, HOUR_IN_SECONDS );
            return false;
        }

        // Check if the count exceeds the rate limit (5 requests per hour)
        if ( $count >= 5 ) {
            return true; // Email is rate-limited
        }

        // Increment the count and reset the expiration time of the transient
        set_transient( $transient_name, $count + 1, HOUR_IN_SECONDS );
        return false;
    }

    /**
     * Handles bulk actions for stock notifications in the WordPress admin.
     *
     * This method processes bulk actions (e.g., deleting selected notifications)
     * submitted via the admin interface. It verifies nonce for security and
     * performs the requested action on selected notifications.
     *
     * @return void
     */
    public function handle_bulk_action_stock_notifications() {
        // Check for nonce verification and required POST data
        if ( !isset( $_POST['submit_bulk_action'], $_POST['bulk_action_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bulk_action_nonce'] ) ), 'bulk_action' ) ) {
            return; // Exit if nonce verification fails or POST data is missing
        }

        // Check if notifications are selected and ensure it's an array
        if ( isset( $_POST['notifications'] ) && is_array( $_POST['notifications'] ) ) {
            $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';

            if ( 'delete' === $action ) {
                $notifications = array_map( 'absint', wp_unslash( $_POST['notifications'] ) );
                $this->handle_bulk_delete( $notifications );
            }
        }
    }

    /**
     * Handles the deletion of multiple stock notifications.
     *
     * Iterates through the provided notification IDs and deletes each one
     * from the database. Displays a success message in the admin area upon completion.
     *
     * @param array $notification_ids Array of notification IDs to delete.
     * @return void
     */
    private function handle_bulk_delete( array $notification_ids ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stock_notifications';

        // Delete each notification by ID
        foreach ( $notification_ids as $notification_id ) {
            $this->delete_stock_notification( intval( $notification_id ) );
        }

        // Add admin notice to indicate successful deletion
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Selected notifications have been deleted.', 'stock-availability-alert-for-woocommerce') . '</p>';
            echo '</div>';
        });
    }

    /**
     * Deletes a stock notification from the database.
     *
     * This method removes a specific stock notification entry based on the provided
     * notification ID. It uses the WordPress $wpdb object to perform a safe delete operation.
     *
     * @param int $notification_id The ID of the notification to delete.
     * @return void
     */
    private function delete_stock_notification( int $notification_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'stock_notifications'; // Define the table name

        // Ensure the ID is an integer and delete the record from the database
        $wpdb->delete(
            $table,
            array('id' => $notification_id), // The condition for the delete query
            array('%d') // Format for the value, %d for integer
        );
    }
}
