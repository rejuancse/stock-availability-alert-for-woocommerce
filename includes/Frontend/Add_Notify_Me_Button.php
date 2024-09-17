<?php

namespace StockAlert\Frontend;

/**
 * Shortcode handler class
 */
class Add_Notify_Me_Button {

    /**
     * Initializes the class
     */
    public function __construct() {
        add_action('woocommerce_single_product_summary', array($this, 'add_notify_me_button'), 30);
    }

    /**
     * Shortcode handler class
     *
     * @param  array $atts
     * @param  string $content
     *
     * @return string
     */
    public function add_notify_me_button() {
        global $product;
        if (!$product->is_in_stock()) {
            echo '<button class="button" id="notify-me-button">' . esc_html__('Notify Me When Available', 'stock-alert') . '</button>';
            echo '<div id="notify-me-form" style="display:none;">
                    <input type="email" id="notify-email" placeholder="' . esc_attr__('Enter your email', 'stock-alert') . '">
                    <input type="hidden" id="notify-product-id" value="' . esc_attr($product->get_id()) . '">
                    <button id="submit-notify">' . esc_html__('Submit', 'stock-alert') . '</button>
                  </div>';
        }
    }
}
