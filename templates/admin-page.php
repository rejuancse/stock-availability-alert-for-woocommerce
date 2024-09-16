<?php
// templates/admin-page.php
defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Stock Notifications', 'wc-stock-notification'); ?></h1>
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
