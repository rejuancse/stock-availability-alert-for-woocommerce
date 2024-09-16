<?php
// templates/settings-page.php
defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Stock Notification Settings', 'wc-stock-notification'); ?></h1>
    <form method="post">
        <h2><?php esc_html_e('Notification Threshold', 'wc-stock-notification'); ?></h2>
        <label for="notification_threshold"><?php esc_html_e('Notification Threshold:', 'wc-stock-notification'); ?></label>
        <input type="number" id="notification_threshold" name="notification_threshold" value="<?php echo esc_attr($threshold); ?>" min="1">
        <p class="description"><?php esc_html_e('Send notifications when stock reaches or exceeds this number.', 'wc-stock-notification'); ?></p>

        <h2><?php esc_html_e('Email Template', 'wc-stock-notification'); ?></h2>
        <p><?php esc_html_e('Customize the email sent to customers when a product is back in stock. You can use the following placeholders:', 'wc-stock-notification'); ?></p>
        <ul>
            <li><code>{product_name}</code> - <?php esc_html_e('The name of the product', 'wc-stock-notification'); ?></li>
            <li><code>{product_url}</code> - <?php esc_html_e('The URL of the product page', 'wc-stock-notification'); ?></li>
            <li><code>{site_name}</code> - <?php esc_html_e('The name of your website', 'wc-stock-notification'); ?></li>
        </ul>
        <textarea name="email_template" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($email_template); ?></textarea>

        <p class="submit">
            <input type="submit" name="submit_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'wc-stock-notification'); ?>">
        </p>
    </form>
</div>
