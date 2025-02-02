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

        wp_enqueue_style('dashicons');
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
     * Get location details
     */
    private function get_location_details($post_id) {
        $locations = wp_get_object_terms($post_id, 'meeting_location');
        if (empty($locations)) {
            return null;
        }

        $location = $locations[0];
        $address = get_term_meta($location->term_id, 'location_address', true);

        return array(
            'name' => $location->name,
            'address' => $address,
            'maps_url' => !empty($address) ? 
                'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($address) : 
                null
        );
    }

    /**
     * Generate calendar links
     */
    private function get_calendar_links($meeting) {
        $date = get_post_meta($meeting->ID, '_meeting_date', true);
        $time = get_post_meta($meeting->ID, '_meeting_time', true);
        $location = $this->get_location_details($meeting->ID);
        
        if (empty($date) || empty($time)) {
            return array();
        }

        // Format datetime for calendar
        $start_datetime = new DateTime($date . ' ' . $time);
        $end_datetime = clone $start_datetime;
        $end_datetime->modify('+1 hour');

        // Get meeting types
        $meeting_types = wp_get_object_terms($meeting->ID, 'meeting_type');
        $type_names = array_map(function($term) {
            return $term->name;
        }, $meeting_types);

        // Prepare event details
        $title = implode(' & ', $type_names) . ' Meeting';
        $description = wp_strip_all_tags($meeting->post_content);
        $location_string = $location ? $location['name'] . ($location['address'] ? ' - ' . $location['address'] : '') : '';

        // Google Calendar link
        $google_params = array(
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $start_datetime->format('Ymd\THis') . '/' . $end_datetime->format('Ymd\THis'),
            'details' => $description,
            'location' => $location_string,
            'ctz' => 'America/Los_Angeles'
        );
        $google_url = 'https://calendar.google.com/calendar/render?' . http_build_query($google_params);

        // Apple Calendar link (ics file)
        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "BEGIN:VEVENT\r\n";
        $ics_content .= "UID:" . uniqid() . "@lewiscountydemocrats.org\r\n";
        $ics_content .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ics_content .= "DTSTART:" . $start_datetime->format('Ymd\THis') . "\r\n";
        $ics_content .= "DTEND:" . $end_datetime->format('Ymd\THis') . "\r\n";
        $ics_content .= "SUMMARY:" . $this->ics_escape($title) . "\r\n";
        if (!empty($description)) {
            $ics_content .= "DESCRIPTION:" . $this->ics_escape($description) . "\r\n";
        }
        if (!empty($location_string)) {
            $ics_content .= "LOCATION:" . $this->ics_escape($location_string) . "\r\n";
        }
        $ics_content .= "END:VEVENT\r\n";
        $ics_content .= "END:VCALENDAR\r\n";

        $ics_url = 'data:text/calendar;charset=utf8,' . rawurlencode($ics_content);

        return array(
            'google' => $google_url,
            'apple' => $ics_url
        );
    }

    /**
     * Escape special characters for ICS format
     */
    private function ics_escape($string) {
        $string = str_replace(array("\r\n", "\n", "\r"), "\\n", $string);
        $string = str_replace(array(",", ";", "\\"), array("\\,", "\\;", "\\\\"), $string);
        return $string;
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
        $location = $this->get_location_details($meeting->ID);
        $calendar_links = $this->get_calendar_links($meeting);
        
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
        $output .= '<div class="meeting-details">';
        $output .= '<div class="meeting-datetime">';
        $output .= '<div class="meeting-date"><i class="dashicons dashicons-calendar-alt"></i> ' . $formatted_date . '</div>';
        $output .= '<div class="meeting-time"><i class="dashicons dashicons-clock"></i> ' . $formatted_time . '</div>';
        $output .= '</div>';

        // Location
        if ($location) {
            $output .= '<div class="meeting-location">';
            $output .= '<i class="dashicons dashicons-location"></i> ';
            $output .= '<span>' . esc_html($location['name']);
            if ($location['address']) {
                $output .= ' - ' . esc_html($location['address']);
            }
            $output .= '</span>';
            if ($location['maps_url']) {
                $output .= ' <a href="' . esc_url($location['maps_url']) . '" class="directions-link" target="_blank">';
                $output .= '<i class="dashicons dashicons-external"></i> ' . __('Get Directions', 'lcd-meeting-notes');
                $output .= '</a>';
            }
            $output .= '</div>';
        }
        $output .= '</div>'; // End meeting-details
        $output .= '</div>'; // End meeting-header

        // Meeting description
        if (!empty($meeting->post_content)) {
            $output .= '<div class="meeting-description">';
            $output .= apply_filters('the_content', $meeting->post_content);
            $output .= '</div>';
        }

        // Meeting actions
        $output .= '<div class="meeting-actions">';
        
        // Calendar links
        if (!empty($calendar_links)) {
            $output .= '<div class="calendar-links">';
            if (isset($calendar_links['google'])) {
                $output .= '<a href="' . esc_url($calendar_links['google']) . '" class="calendar-link google" target="_blank">';
                $output .= '<i class="dashicons dashicons-calendar-alt"></i> ' . __('Add to Google Calendar', 'lcd-meeting-notes');
                $output .= '</a>';
            }
            if (isset($calendar_links['apple'])) {
                $output .= '<a href="' . esc_url($calendar_links['apple']) . '" class="calendar-link apple" download="meeting.ics">';
                $output .= '<i class="dashicons dashicons-calendar-alt"></i> ' . __('Add to Apple Calendar', 'lcd-meeting-notes');
                $output .= '</a>';
            }
            $output .= '</div>';
        }

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