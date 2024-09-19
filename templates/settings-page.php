<?php defined('ABSPATH') || exit; ?>

<div class="wrap stock-notification-settings">
    <h1><?php esc_html_e( 'Stock Notification Settings', 'stock-alert' ); ?></h1>

    <!-- Settings form -->
    <form method="post">
        <!-- Notification Threshold Section -->
        <div class="notification-threshold">
            <h2><?php esc_html_e( 'Notification Threshold', 'stock-alert' ); ?></h2>

            <!-- Input field for notification threshold -->
            <label for="notification_threshold"><?php esc_html_e( 'Notification Threshold:', 'stock-alert' ); ?></label>
            <input type="number" id="notification_threshold" name="notification_threshold" value="<?php echo esc_attr( $threshold ); ?>" min="1">
            <p class="description"><?php esc_html_e( 'Send notifications when stock reaches or exceeds this number.', 'stock-alert' ); ?></p>
        </div>

        <!-- Email Template Section -->
        <div class="template-guide-line">
            <h2><?php esc_html_e( 'Email Template', 'stock-alert' ); ?></h2>

            <!-- Email template customization guidelines -->
            <div class="guidelines">
                <p><?php esc_html_e( 'Customize the email sent to customers when a product is back in stock. You can use the following placeholders:', 'stock-alert' ); ?></p>
                <ul>
                    <li><code>{product_name}</code> - <?php esc_html_e( 'The name of the product', 'stock-alert' ); ?></li>
                    <li><code>{product_url}</code> - <?php esc_html_e( 'The URL of the product page', 'stock-alert' ); ?></li>
                    <li><code>{site_name}</code> - <?php esc_html_e( 'The name of your website', 'stock-alert' ); ?></li>
                </ul>
            </div>

            <!-- TinyMCE Editor for email template -->
            <div class="email-template">
                <?php
                    $editor_settings = array(
                        'textarea_name' => 'email_templates',
                        'textarea_rows' => 20,
                        'media_buttons' => true,
                        'teeny'         => true,
                        'quicktags'     => true,
                    );
                    wp_editor($email_templates, 'email_templates_editor', $editor_settings);
                ?>
            </div>
        </div>

        <!-- Submit button to save settings -->
        <p class="submit">
            <input type="submit" name="submit_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'stock-alert' ); ?>">
        </p>
    </form>
</div>
