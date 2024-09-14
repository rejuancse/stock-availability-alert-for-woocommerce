<?php
// Create FAQ Widget.
class DFG_FAQ_Widget extends WP_Widget {

    function __construct() {
        parent::__construct(
            'dfg_faq_widget',
            __('Dynamic FAQ Widget', 'dfg'),
            array('description' => __('Displays dynamically generated FAQs.', 'dfg'))
        );
    }

    // Output of the widget.
    public function widget($args, $instance) {
        echo $args['before_widget'];
        echo $args['before_title'] . apply_filters('widget_title', __('FAQs', 'dfg')) . $args['after_title'];
        echo do_shortcode('[dynamic_faq]');
        echo $args['after_widget'];
    }

    // Widget options form in admin.
    public function form($instance) {
        echo '<p>' . __('No settings required for this widget.', 'dfg') . '</p>';
    }

    // Saving widget settings (if any).
    public function update($new_instance, $old_instance) {
        return $new_instance;
    }
}
