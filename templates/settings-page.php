<?php
// templates/settings-page.php
defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Stock Notification Settings', 'stock-alert'); ?></h1>
    <form method="post">
        <h2><?php esc_html_e('Notification Threshold', 'stock-alert'); ?></h2>
        <label for="notification_threshold"><?php esc_html_e('Notification Threshold:', 'stock-alert'); ?></label>
        <input type="number" id="notification_threshold" name="notification_threshold" value="<?php echo esc_attr($threshold); ?>" min="1">
        <p class="description"><?php esc_html_e('Send notifications when stock reaches or exceeds this number.', 'stock-alert'); ?></p>

        <h2><?php esc_html_e('Email Template', 'stock-alert'); ?></h2>
        <p><?php esc_html_e('Customize the email sent to customers when a product is back in stock. You can use the following placeholders:', 'stock-alert'); ?></p>
        <ul>
            <li><code>{product_name}</code> - <?php esc_html_e('The name of the product', 'stock-alert'); ?></li>
            <li><code>{product_url}</code> - <?php esc_html_e('The URL of the product page', 'stock-alert'); ?></li>
            <li><code>{site_name}</code> - <?php esc_html_e('The name of your website', 'stock-alert'); ?></li>
        </ul>
        <textarea name="email_templates" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($email_templates); ?></textarea>
        <p class="submit">
            <input type="submit" name="submit_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'stock-alert'); ?>">
        </p>
    </form>
</div>
