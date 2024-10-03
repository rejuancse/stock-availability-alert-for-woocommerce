<?php
// templates/dashboard-widget.php
defined('ABSPATH') || exit;
?>

<p><?php printf( esc_html__( 'Total Notifications: %d', 'stock-availability-alert-for-woocommerce' ), $total_notifications ); ?></p>
<p><?php printf( esc_html__( 'Unique Products: %d', 'stock-availability-alert-for-woocommerce' ), $unique_products ); ?></p>
<p><?php printf( esc_html__( 'Unique Subscribers: %d', 'stock-availability-alert-for-woocommerce' ), $unique_emails ); ?></p>
