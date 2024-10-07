<?php
// templates/dashboard-widget.php
defined('ABSPATH') || exit;
?>

<p>
    <?php
    // Translators: This is the total number of notifications.
    printf( esc_html__( 'Total Notifications: %d', 'stock-availability-alert' ), absint( $total_notifications ) );
    ?>
</p>

<p>
    <?php
    // Translators: This is the total number of unique products.
    printf( esc_html__( 'Unique Products: %d', 'stock-availability-alert' ), absint( $unique_products ) );
    ?>
</p>

<p>
    <?php
    // Translators: This is the total number of unique subscribers.
    printf( esc_html__( 'Unique Subscribers: %d', 'stock-availability-alert' ), absint( $unique_emails ) );
    ?>
</p>
