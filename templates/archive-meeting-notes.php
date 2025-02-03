<?php

/**
 * The template for displaying meeting notes archive
 */

get_header(); ?>

<div class="container">
    <div id="primary" class="content-area">
        <main id="main" class="site-main">

            <header class="page-header">
                <h1 class="page-title"><?php _e('Meeting Notes Archive', 'lcd-meeting-notes'); ?></h1>
            </header>
            <div style="text-align: center;"><a href="<?php echo home_url(); ?>/meetings" class="back-to-meetings">< Back to Meetings</a></div>

            <?php if (have_posts()) : ?>
                <div class="meeting-notes-archive">
                    <?php
                    // Group meetings by year
                    $current_year = '';

                    while (have_posts()) : the_post();
                        $meeting_date = get_post_meta(get_the_ID(), '_meeting_date', true);
                        $meeting_time = get_post_meta(get_the_ID(), '_meeting_time', true);
                        $notes_pdf_id = get_post_meta(get_the_ID(), '_meeting_notes_pdf', true);

                        if ($meeting_date) {
                            $date = new DateTime($meeting_date);
                            $year = $date->format('Y');

                            // Output year header if it's a new year
                            if ($year !== $current_year) {
                                if ($current_year !== '') {
                                    echo '</div>'; // Close previous year's div
                                }
                                $current_year = $year;
                                echo '<h2 class="meeting-year">' . esc_html($year) . '</h2>';
                                echo '<div class="meeting-year-group">';
                            }

                            // Format meeting date and time
                            $formatted_date = $date->format('F j');
                            $time = '';
                            if ($meeting_time) {
                                $datetime = new DateTime($meeting_date . ' ' . $meeting_time);
                                $time = $datetime->format('g:i A');
                            }

                            // Get meeting types
                            $meeting_types = wp_get_object_terms(get_the_ID(), 'meeting_type');
                            $type_names = array_map(function ($term) {
                                return $term->name;
                            }, $meeting_types);
                    ?>

                            <div class="meeting-archive-item">
                                <div class="meeting-info">
                                    <div class="meeting-meta">
                                        <span class="meeting-date">
                                            <?php echo esc_html($formatted_date); ?>
                                            <?php if ($time) : ?>
                                                <span class="meeting-time"><?php echo esc_html($time); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <h3 class="meeting-title">
                                        <?php echo esc_html(implode(' & ', $type_names)); ?> Meeting
                                    </h3>
                                </div>

                                <div class="meeting-actions">
                                    <?php
                                    // Check for agenda PDF
                                    $agenda_pdf_id = get_post_meta(get_the_ID(), '_meeting_agenda_pdf', true);
                                    if (!empty($agenda_pdf_id)) :
                                        $agenda_url = wp_get_attachment_url($agenda_pdf_id);
                                        if ($agenda_url) : ?>
                                            <a href="<?php echo esc_url($agenda_url); ?>" class="view-agenda-link" target="_blank">
                                                <i class="dashicons dashicons-media-document"></i>
                                                <?php _e('View Agenda', 'lcd-meeting-notes'); ?>
                                            </a>
                                        <?php endif;
                                    endif;

                                    // Meeting Notes
                                    if (!empty($notes_pdf_id)) :
                                        $pdf_url = wp_get_attachment_url($notes_pdf_id);
                                        if ($pdf_url) : ?>
                                            <a href="<?php echo esc_url($pdf_url); ?>" class="view-notes-link" target="_blank">
                                                <i class="dashicons dashicons-pdf"></i>
                                                <?php _e('View Meeting Notes', 'lcd-meeting-notes'); ?>
                                            </a>
                                        <?php endif;
                                    else : ?>
                                        <span class="notes-pending">
                                            <?php _e('Meeting notes pending', 'lcd-meeting-notes'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                    <?php
                        }
                    endwhile;

                    // Close the last year group div
                    if ($current_year !== '') {
                        echo '</div>';
                    }
                    ?>
                </div>

                <?php
                // Pagination
                the_posts_pagination(array(
                    'mid_size' => 2,
                    'prev_text' => __('Previous', 'lcd-meeting-notes'),
                    'next_text' => __('Next', 'lcd-meeting-notes'),
                ));
                ?>

            <?php else : ?>
                <div class="no-meetings-found">
                    <p><?php _e('No meetings found.', 'lcd-meeting-notes'); ?></p>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php get_footer(); ?>