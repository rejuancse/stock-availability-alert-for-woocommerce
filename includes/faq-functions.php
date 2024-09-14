<?php
// Function to extract common questions from comments and generate FAQ.
function dfg_generate_faq($atts) {
    global $post;
    $comments = get_comments(array('post_id' => $post->ID));
    $faq = '';

    if ($comments) {
        $faq .= '<div class="dfg-faq">';
        foreach ($comments as $comment) {
            if (strlen($comment->comment_content) > 50) { // Extract comments as questions if long enough.
                $faq .= '<div class="dfg-faq-item">';
                $faq .= '<h4 class="dfg-faq-question">' . esc_html($comment->comment_content) . '</h4>';
                $faq .= '<p class="dfg-faq-answer">' . __('Answer is under review or coming soon...', 'dfg') . '</p>'; // Placeholder for answer.
                $faq .= '</div>';
            }
        }
        $faq .= '</div>';
    } else {
        $faq .= '<p>' . __('No FAQs available for this post yet.', 'dfg') . '</p>';
    }

    // Add Schema Markup
    $faq .= dfg_add_schema_markup($comments);

    return $faq;
}

// Function to add schema markup for FAQ.
function dfg_add_schema_markup($comments) {
    $schema = '';
    if ($comments) {
        $schema .= '<script type="application/ld+json">';
        $schema .= '{
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [';

        foreach ($comments as $key => $comment) {
            if ($key > 0) $schema .= ',';
            $schema .= '{
                "@type": "Question",
                "name": "' . esc_html($comment->comment_content) . '",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "' . __('Answer is under review or coming soon...', 'dfg') . '"
                }
            }';
        }

        $schema .= ']}';
        $schema .= '</script>';
    }
    return $schema;
}
