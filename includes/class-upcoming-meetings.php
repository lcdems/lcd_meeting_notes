<?php
/**
 * Upcoming Meetings Display
 */

class LCD_Upcoming_Meetings {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_shortcode('upcoming_meeting', array($this, 'render_upcoming_meeting'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'lcd-upcoming-meeting',
            plugins_url('assets/css/upcoming-meeting.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );

        // Add to Calendar Button Library
        wp_enqueue_script(
            'add-to-calendar',
            'https://cdn.jsdelivr.net/npm/add-to-calendar-button@2',
            array(),
            '2.0.0',
            true
        );
    }

    /**
     * Get the next upcoming meeting
     */
    private function get_next_meeting() {
        $now = current_time('Y-m-d H:i:s');
        
        $args = array(
            'post_type' => 'meeting_notes',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_meeting_date',
                    'value' => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            ),
            'orderby' => array(
                '_meeting_date' => 'ASC',
                '_meeting_time' => 'ASC'
            ),
            'meta_key' => '_meeting_date'
        );

        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            return $query->posts[0];
        }
        
        return null;
    }

    /**
     * Format meeting datetime for add to calendar
     */
    private function format_meeting_datetime($post_id, $start = true) {
        $date = get_post_meta($post_id, '_meeting_date', true);
        $time = get_post_meta($post_id, '_meeting_time', true);
        
        if (empty($date) || empty($time)) {
            return '';
        }
        
        $datetime = new DateTime($date . ' ' . $time);
        
        // If this is an end time, add 1 hour to the start time
        if (!$start) {
            $datetime->modify('+1 hour');
        }
        
        return $datetime->format('Y-m-d H:i');
    }

    /**
     * Render the upcoming meeting shortcode
     */
    public function render_upcoming_meeting($atts) {
        $meeting = $this->get_next_meeting();
        
        if (!$meeting) {
            return '<div class="no-upcoming-meetings">' . 
                   __('No upcoming meetings scheduled.', 'lcd-meeting-notes') . 
                   '</div>';
        }

        // Get meeting details
        $meeting_date = get_post_meta($meeting->ID, '_meeting_date', true);
        $meeting_time = get_post_meta($meeting->ID, '_meeting_time', true);
        $facebook_url = get_post_meta($meeting->ID, '_facebook_event_url', true);
        $agenda_pdf_id = get_post_meta($meeting->ID, '_meeting_agenda_pdf', true);
        
        // Format datetime for display
        $datetime = new DateTime($meeting_date . ' ' . $meeting_time);
        $formatted_date = $datetime->format('l, F j, Y');
        $formatted_time = $datetime->format('g:i A');

        // Get meeting types
        $meeting_types = wp_get_object_terms($meeting->ID, 'meeting_type');
        $type_names = array_map(function($term) {
            return $term->name;
        }, $meeting_types);

        // Start building output
        $output = '<div class="upcoming-meeting">';
        
        // Meeting header
        $output .= '<div class="meeting-header">';
        $output .= '<h2>' . implode(' & ', $type_names) . ' Meeting</h2>';
        $output .= '<div class="meeting-datetime">';
        $output .= '<div class="meeting-date"><i class="dashicons dashicons-calendar-alt"></i> ' . $formatted_date . '</div>';
        $output .= '<div class="meeting-time"><i class="dashicons dashicons-clock"></i> ' . $formatted_time . '</div>';
        $output .= '</div>';
        $output .= '</div>';

        // Meeting description
        if (!empty($meeting->post_content)) {
            $output .= '<div class="meeting-description">';
            $output .= apply_filters('the_content', $meeting->post_content);
            $output .= '</div>';
        }

        // Meeting actions
        $output .= '<div class="meeting-actions">';
        
        // Add to Calendar button
        $output .= '<div class="add-to-calendar">';
        $output .= '<add-to-calendar-button
            name="' . esc_attr($meeting->post_title) . '"
            description="' . esc_attr(wp_strip_all_tags($meeting->post_content)) . '"
            startDate="' . esc_attr($this->format_meeting_datetime($meeting->ID, true)) . '"
            endDate="' . esc_attr($this->format_meeting_datetime($meeting->ID, false)) . '"
            options="\'Google Calendar\',\'iCal\',\'Outlook\'"
            timeZone="America/New_York"
            buttonStyle="3d"
            hideBackground="true"
            hideIconButton="true"
            size="3"
        ></add-to-calendar-button>';
        $output .= '</div>';

        // Facebook RSVP button
        if (!empty($facebook_url)) {
            $output .= '<a href="' . esc_url($facebook_url) . '" class="facebook-rsvp" target="_blank">';
            $output .= '<i class="dashicons dashicons-facebook"></i> RSVP on Facebook';
            $output .= '</a>';
        }

        // Agenda PDF link
        if (!empty($agenda_pdf_id)) {
            $pdf_url = wp_get_attachment_url($agenda_pdf_id);
            if ($pdf_url) {
                $output .= '<a href="' . esc_url($pdf_url) . '" class="view-agenda" target="_blank">';
                $output .= '<i class="dashicons dashicons-pdf"></i> View Agenda';
                $output .= '</a>';
            }
        }

        $output .= '</div>'; // End meeting-actions
        $output .= '</div>'; // End upcoming-meeting

        return $output;
    }
} 