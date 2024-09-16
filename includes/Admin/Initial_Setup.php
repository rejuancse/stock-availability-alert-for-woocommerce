<?php
namespace StockAlert\Admin;

defined( 'ABSPATH' ) || exit;

class Initial_Setup {

    public function __construct() {
        add_action( 'admin_notices', [ $this, 'stock_alert_wc_requirement_notice' ] );
    }

    /**
     * Require WooCommerce admin message.
     */
    public function stock_alert_wc_requirement_notice() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            $text = esc_html__( 'WooCommerce', 'stock-alert' );

            $link = esc_url( add_query_arg( array(
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
                esc_html__( 'To enable eCommerce features, you need to install the', 'stock-alert' ),
                '<a class="thickbox open-plugin-details-modal" href="' . $link . '"><strong>' . esc_html__( 'WooCommerce', 'stock-alert' ) . '</strong></a>',
                esc_html__( 'plugin.', 'stock-alert' )
            );

            // Output the message
            printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
        }
    }
}
