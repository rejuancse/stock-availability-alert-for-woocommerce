<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap stock-notifications">
    <div class="stock-header">
        <h1><?php esc_html_e( 'Stock Notifications', 'stock-availability-alert-for-woocommerce' ); ?></h1>
    </div>

    <form id="bulk-action-form" method="post">
        <!-- Notifications List -->
        <div class="notifications-list-tablenav">
            <?php wp_nonce_field( 'bulk_action', 'bulk_action_nonce' ); ?>
            <div class="bulk-actions">
                <select name="bulk_action" id="bulk-action-selector">
                    <option value=""><?php esc_html_e( 'Bulk Actions', 'stock-availability-alert-for-woocommerce' ); ?></option>
                    <option value="delete"><?php esc_html_e( 'Delete', 'stock-availability-alert-for-woocommerce' ); ?></option>
                </select>
                <input type="submit" name="submit_bulk_action" class="button action" value="<?php esc_attr_e( 'Apply', 'stock-availability-alert-for-woocommerce' ); ?>">
            </div>

            <div class="notification-count">
                <span class="displaying-num">
                    <?php
                        // Ensure $total_notifications is a positive integer
                        $total_notifications = absint( $total_notifications );

                        // Translators: %d is the number of notifications
                        echo esc_html( sprintf( _n( '%d item', '%d items', $total_notifications, 'stock-availability-alert-for-woocommerce' ),
                            $total_notifications )
                        );
                    ?>
                </span>


                <div class="export_csv">
                    <?php wp_nonce_field( 'stock_notification_export', 'stock_notification_export_nonce' ); ?>
                    <input type="submit" name="export_csv" class="button button-primary" value="<?php esc_attr_e( 'Export to CSV', 'stock-availability-alert-for-woocommerce' ); ?>">
                </div>
            </div>
        </div>

        <table class="wp-list-table widefat striped table-view-list posts">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all" type="checkbox">
                    </td>
                    <th><?php esc_html_e( 'Product', 'stock-availability-alert-for-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'stock-availability-alert-for-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'stock-availability-alert-for-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'stock-availability-alert-for-woocommerce' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $notifications ) ) : ?>
                    <?php foreach ( $notifications as $notification ) : ?>
                        <?php $product = wc_get_product( $notification->product_id ); ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="notifications[]" value="<?php echo esc_attr( $notification->id ); ?>" />
                            </th>
                            <td>
                                <a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
                                    <?php
                                    $image_id = $product->get_image_id(); // Get image ID
                                    $image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' ); // Get image URL
                                    if ($image_url) {
                                        echo '<img src="' . esc_url( $image_url ) . '"
                                        alt="' . esc_attr( $product->get_name() ) . '" width="44" height="44" />';
                                    }
                                    echo $product ? esc_html( $product->get_name() ) : esc_html__( 'Product not found', 'stock-availability-alert-for-woocommerce' );
                                    ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( $notification->email ); ?></td>
                            <td><?php echo esc_html( $notification->date_added ); ?></td>
                            <td class="subscribed"><?php esc_html_e( 'Subscribed', 'stock-availability-alert-for-woocommerce' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" class="no-info"><?php esc_html_e( 'No notifications found.', 'stock-availability-alert-for-woocommerce' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php
            // Pagination links
            $pagination_args = array(
                'base' => add_query_arg( 'paged', '%#%'),
                'format' => '',
                'prev_text' => __( '&laquo; Previous', 'stock-availability-alert-for-woocommerce'),
                'next_text' => __( 'Next &raquo;', 'stock-availability-alert-for-woocommerce'),
                'total' => ceil( $total_notifications / $items_per_page ),
                'current' => $paged,
            );

            if ( $pagination_args['total'] > 1 ) {
                echo '<div class="pagination-container">';
                    echo wp_kses_post( paginate_links( $pagination_args ) );
                echo '</div>';
            }
        ?>
    </form>
</div>
