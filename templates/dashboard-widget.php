<?php
// templates/dashboard-widget.php
defined('ABSPATH') || exit;
?>

<p><?php printf( esc_html__( 'Total Notifications: %d', 'stock-alert' ), $total_notifications ); ?></p>
<p><?php printf( esc_html__( 'Unique Products: %d', 'stock-alert' ), $unique_products ); ?></p>
<p><?php printf( esc_html__( 'Unique Subscribers: %d', 'stock-alert' ), $unique_emails ); ?></p>
