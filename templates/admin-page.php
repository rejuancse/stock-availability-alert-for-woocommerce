<?php
// templates/admin-page.php
defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Stock Notifications', 'wc-stock-notification'); ?></h1>

    <h2><?php esc_html_e('Email Template', 'wc-stock-notification'); ?></h2>
    <form method="post">
        <textarea name="email_template" rows="10" cols="50"><?php echo esc_textarea(get_option('stock_notification_email_template')); ?></textarea>
        <p><?php esc_html_e('Available placeholders: {product_name}, {product_url}, {site_name}', 'wc-stock-notification'); ?></p>
        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Update Email Template', 'wc-stock-notification'); ?>">
    </form>

    <h2><?php esc_html_e('Current Notifications', 'wc-stock-notification'); ?></h2>
    <table class="widefat">
        <thead>
            <tr>
                <th><?php esc_html_e('Email', 'wc-stock-notification'); ?></th>
                <th><?php esc_html_e('Product', 'wc-stock-notification'); ?></th>
                <th><?php esc_html_e('Date Added', 'wc-stock-notification'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notifications as $notification) : ?>
                <?php $product = wc_get_product($notification->product_id); ?>
                <tr>
                    <td><?php echo esc_html($notification->email); ?></td>
                    <td><?php echo $product ? esc_html($product->get_name()) : esc_html__('Product not found', 'wc-stock-notification'); ?></td>
                    <td><?php echo esc_html($notification->date_added); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>