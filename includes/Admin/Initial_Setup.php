<?php

namespace StockAlert\Admin;

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Class for handling initial setup requirements for the Stock Alert plugin.
 */
class Initial_Setup {

    /**
     * Initializes the class and adds action hooks.
     */
    public function __construct() {
        add_action('admin_notices', array( $this, 'display_wc_requirement_notice' ) );
    }

    /**
     * Displays an admin notice if WooCommerce is not installed.
     *
     * This message informs users that WooCommerce is required to enable eCommerce features.
     *
     * @return void
     */
    public function display_wc_requirement_notice() {
        // Check if WooCommerce is not active
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->output_wc_requirement_message();
        }
    }

    /**
     * Outputs the WooCommerce requirement message.
     *
     * @return void
     */
    protected function output_wc_requirement_message() {
        $text = esc_html__( 'WooCommerce', 'stock-alert' );

        // Construct the link to install WooCommerce
        $link = esc_url ( add_query_arg(array(
            'tab'       => 'plugin-information',
            'plugin'    => 'woocommerce',
            'TB_iframe' => 'true',
            'width'     => '640',
            'height'    => '500',
        ), admin_url( 'plugin-install.php' ) ) );

        // Construct the message with bold text
        $message = sprintf(
            '<strong>%1$s</strong><br>%2$s %3$s %4$s',
            esc_html__( 'Thanks for installing Enhanced Stock Availability Alert, you rock! 🤘', 'stock-alert' ),
            esc_html__( 'To enable eCommerce features, you need to install the', 'stock-alert'),
            '<a class="thickbox open-plugin-details-modal" href="' . $link . '"><strong>' . esc_html__( 'WooCommerce', 'stock-alert' ) . '</strong></a>',
            esc_html__( 'plugin.', 'stock-alert' )
        );

        // Output the message in an error notice
        printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
    }
}