<?php
// templates/dashboard-widget.php
defined('ABSPATH') || exit;
?>

<p><?php printf(esc_html__('Total Notifications: %d', 'wc-stock-notification'), $total_notifications); ?></p>
<p><?php printf(esc_html__('Unique Products: %d', 'wc-stock-notification'), $unique_products); ?></p>
<p><?php printf(esc_html__('Unique Subscribers: %d', 'wc-stock-notification'), $unique_emails); ?></p>
