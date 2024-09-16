<?php
// templates/export-page.php
defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Export Stock Notifications', 'wc-stock-notification'); ?></h1>

    <p><?php esc_html_e('Use the button below to export all current stock notifications to a CSV file.', 'wc-stock-notification'); ?></p>

    <form method="post">
        <?php wp_nonce_field('stock_notification_export', 'stock_notification_export_nonce'); ?>
        <input type="submit" name="export_csv" class="button button-primary" value="<?php esc_attr_e('Export to CSV', 'wc-stock-notification'); ?>">
    </form>

    <?php if (isset($_POST['export_csv']) && check_admin_referer('stock_notification_export', 'stock_notification_export_nonce')) : ?>
        <div class="notice notice-success">
            <p><?php esc_html_e('Export process started. Your download should begin shortly.', 'wc-stock-notification'); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php esc_html_e('Export Information', 'wc-stock-notification'); ?></h2>
    <p><?php esc_html_e('The exported CSV file will contain the following information for each notification:', 'wc-stock-notification'); ?></p>
    <ul>
        <li><?php esc_html_e('Notification ID', 'wc-stock-notification'); ?></li>
        <li><?php esc_html_e('Email Address', 'wc-stock-notification'); ?></li>
        <li><?php esc_html_e('Product ID', 'wc-stock-notification'); ?></li>
        <li><?php esc_html_e('Date Added', 'wc-stock-notification'); ?></li>
    </ul>

    <p><?php esc_html_e('Note: The export process may take a few moments depending on the number of notifications in the system.', 'wc-stock-notification'); ?></p>
</div>
