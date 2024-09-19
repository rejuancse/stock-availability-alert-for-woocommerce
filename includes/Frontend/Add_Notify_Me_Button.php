<?php

namespace StockAlert\Frontend;

/**
 * Shortcode handler class for adding a "Notify Me" button on WooCommerce product pages.
 */
class Add_Notify_Me_Button {

    /**
     * Initializes the class by hooking into WooCommerce single product summary.
     */
    public function __construct() {
        add_action( 'woocommerce_single_product_summary', array( $this, 'add_notify_me_button' ), 30 );
    }

    /**
     * Outputs the "Notify Me" button if the product is out of stock.
     *
     * @return void
     */
    public function add_notify_me_button() {
        global $product;

        // Check if the global product object is available
        if ( empty( $product ) || ! is_a( $product, 'WC_Product' ) ) {
            error_log('Product object is not available or invalid in Notify Me Button.');
            return; // Exit if product is not valid
        }

        // Only show the button if the product is out of stock
        if (!$product->is_in_stock()) {
            echo '<div class="notify-me-button-wrap">';
                echo '<button class="button" id="notify-me-button">' . esc_html__('Notify Me When Available', 'stock-alert') . '</button>';
                echo '<div id="notify-me-form" style="display: none;">
                        <div class="form-fields">
                            <input type="email" id="notify-email" placeholder="' . __('Enter your email', 'stock-alert') . '" required>
                            <input type="hidden" id="notify-product-id" value="' . esc_attr( $product->get_id() ) . '">
                            <button id="submit-notify">' . esc_html__( 'Notify Me', 'stock-alert' ) . '</button>
                        </div>
                    </div>';
            echo '</div>';
        }
    }
}