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

        // Add JavaScript for RSVP functionality
        wp_enqueue_script(
            'lcd-meeting-rsvp',
            plugins_url('assets/js/meeting-rsvp.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('lcd-meeting-rsvp', 'lcdRsvp', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lcd_rsvp_nonce'),
            'messages' => array(
                'success' => __('RSVP successful!', 'lcd-meeting-notes'),
                'removed' => __('RSVP removed', 'lcd-meeting-notes'),
                'error' => __('Error processing RSVP', 'lcd-meeting-notes')
            )
        ));
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

        // Get meeting formats
        $meeting_formats = wp_get_object_terms($meeting->ID, 'meeting_format');
        $format_names = array_map(function($term) {
            return $term->name;
        }, $meeting_formats);

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
     * Get past meetings
     */
    private function get_past_meetings() {
        $args = array(
            'post_type' => 'meeting_notes',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'meta_query' => array(
                array(
                    'key' => '_meeting_date',
                    'value' => current_time('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                )
            ),
            'orderby' => array(
                '_meeting_date' => 'DESC',
                '_meeting_time' => 'DESC'
            ),
            'meta_key' => '_meeting_date'
        );

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Get RSVP count and status for a meeting
     */
    private function get_rsvp_info($meeting_id) {
        $rsvps = get_post_meta($meeting_id, '_meeting_rsvps', true);
        if (!is_array($rsvps)) {
            $rsvps = array();
        }

        $cookie_name = 'lcd_meeting_rsvp_' . $meeting_id;
        $has_rsvped = isset($_COOKIE[$cookie_name]);

        return array(
            'count' => count($rsvps),
            'has_rsvped' => $has_rsvped
        );
    }

    /**
     * Render the upcoming meeting shortcode
     */
    public function render_upcoming_meeting($atts) {
        $output = '<div class="lcd-meetings-tabs">';
        
        // Header with title and archive link
        $output .= '<div class="meetings-header">';
        $output .= '<h2>' . __('Next Meeting', 'lcd-meeting-notes') . '</h2>';
        $archive_url = get_post_type_archive_link('meeting_notes');
        $output .= '<a href="' . esc_url($archive_url) . '" class="view-past-meetings">';
        $output .= '<i class="dashicons dashicons-calendar"></i> ' . __('View Past Meetings', 'lcd-meeting-notes');
        $output .= '</a>';
        $output .= '</div>';
        
        // Meeting content
        $meeting = $this->get_next_meeting();
        
        if (!$meeting) {
            $output .= '<div class="no-upcoming-meetings">' . 
                       __('No upcoming meetings scheduled.', 'lcd-meeting-notes') . 
                       '</div>';
        } else {
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

            // Get meeting formats
            $meeting_formats = wp_get_object_terms($meeting->ID, 'meeting_format');
            $format_names = array_map(function($term) {
                return $term->name;
            }, $meeting_formats);

            // Build output
            $output .= '<div class="upcoming-meeting">';
            
            // Meeting header
            $output .= '<div class="meeting-header">';
            $output .= '<h2>' . implode(' & ', $type_names) . ' Meeting</h2>';
            $output .= '<div class="meeting-details">';
            $output .= '<div class="meeting-datetime">';
            $output .= '<div class="meeting-date"><i class="dashicons dashicons-calendar-alt"></i> ' . $formatted_date . '</div>';
            $output .= '<div class="meeting-time"><i class="dashicons dashicons-clock"></i> ' . $formatted_time . '</div>';
            $output .= '</div>';

            // Meeting formats
            if (!empty($format_names)) {
                $output .= '<div class="meeting-formats">';
                foreach ($format_names as $format) {
                    $output .= '<span class="meeting-format"><i class="dashicons dashicons-format-status"></i> ' . esc_html($format) . '</span>';
                }
                $output .= '</div>';
            }

            if ($location) {
                $output .= '<div class="meeting-location">';
                $output .= '<div class="meeting-location-text">';
                $output .= '<i class="dashicons dashicons-location"></i>';
                $output .= '<span>' . esc_html($location['name']);
                if ($location['address']) {
                    $output .= ' - ' . esc_html($location['address']);
                }
                $output .= '</span>';
                $output .= '</div>';
                if ($location['maps_url']) {
                    $output .= '<a href="' . esc_url($location['maps_url']) . '" class="directions-link" target="_blank">';
                    $output .= '<i class="dashicons dashicons-external"></i> ' . __('Get Directions', 'lcd-meeting-notes');
                    $output .= '</a>';
                }
                $output .= '</div>';
            }

            $output .= '</div>'; // End meeting-details
            $output .= '</div>'; // End meeting-header

            if (!empty($meeting->post_content)) {
                $output .= '<div class="meeting-description">';
                $output .= apply_filters('the_content', $meeting->post_content);
                $output .= '</div>';
            }

            // Combined Meeting Resources Section
            $output .= '<div class="meeting-resources">';
            
            // Left side - Agenda
            $output .= '<div class="resource-section agenda-section">';
            if (!empty($agenda_pdf_id)) {
                $pdf_url = wp_get_attachment_url($agenda_pdf_id);
                if ($pdf_url) {
                    $output .= '<a href="' . esc_url($pdf_url) . '" class="resource-button view-agenda" target="_blank">';
                    $output .= '<i class="dashicons dashicons-pdf"></i> ' . __('View Agenda PDF', 'lcd-meeting-notes');
                    $output .= '</a>';
                }
            } else {
                $output .= '<div class="agenda-pending">';
                $output .= '<i class="dashicons dashicons-clock"></i> ' . __('Agenda pending', 'lcd-meeting-notes');
                $output .= '</div>';
            }
            $output .= '</div>';

            // Right side - Zoom (if available)
            $zoom_link = get_post_meta($meeting->ID, '_zoom_meeting_link', true);
            $zoom_id = get_post_meta($meeting->ID, '_zoom_meeting_id', true);
            $zoom_passcode = get_post_meta($meeting->ID, '_zoom_meeting_passcode', true);
            $zoom_details = get_post_meta($meeting->ID, '_zoom_meeting_details', true);

            if (!empty($zoom_link) || !empty($zoom_id)) {
                $output .= '<div class="resource-section zoom-section">';
                if (!empty($zoom_link)) {
                    $output .= '<a href="' . esc_url($zoom_link) . '" class="resource-button join-zoom" target="_blank">';
                    $output .= '<i class="dashicons dashicons-video-alt3"></i> ' . __('Join Zoom Meeting', 'lcd-meeting-notes');
                    $output .= '</a>';
                }
                if (!empty($zoom_id)) {
                    $output .= '<div class="zoom-meta">';
                    $output .= '<span class="zoom-id"><strong>' . __('ID:', 'lcd-meeting-notes') . '</strong> ' . esc_html($zoom_id) . '</span>';
                    if (!empty($zoom_passcode)) {
                        $output .= '<span class="zoom-passcode"><strong>' . __('Passcode:', 'lcd-meeting-notes') . '</strong> ' . esc_html($zoom_passcode) . '</span>';
                    }
                    $output .= '</div>';
                }
                if (!empty($zoom_details)) {
                    $output .= '<div class="zoom-details-text">' . nl2br(esc_html($zoom_details)) . '</div>';
                }
                $output .= '</div>';
            }
            
            $output .= '</div>'; // End meeting-resources

            // Meeting actions
            $output .= '<div class="meeting-actions">';
            
            // RSVP count display
            $rsvp_info = $this->get_rsvp_info($meeting->ID);
            $output .= '<div class="rsvp-count" data-meeting-id="' . esc_attr($meeting->ID) . '">';
            $output .= '<i class="dashicons dashicons-groups"></i> ';
            if ($rsvp_info['has_rsvped']) {
                if ($rsvp_info['count'] > 1) {
                    $output .= __('You, plus ' . ($rsvp_info['count'] - 1) . ' others have RSVP\'d', 'lcd-meeting-notes');
                } else {
                    $output .= __('You are the first to RSVP', 'lcd-meeting-notes');
                }
            } else {
                $output .= sprintf(_n('%s person has RSVP\'d', '%s people have RSVP\'d', $rsvp_info['count'], 'lcd-meeting-notes'), 
                    $rsvp_info['count']);
            }
            $output .= '</div>';
            
            // Calendar and RSVP links wrapper
            $output .= '<div class="calendar-rsvp-links">';
            
            // RSVP Button
            $output .= '<div class="meeting-rsvp-section">';
            if ($rsvp_info['has_rsvped']) {
                $output .= '<button type="button" class="rsvp-button remove-rsvp" data-meeting-id="' . esc_attr($meeting->ID) . '">';
                $output .= '<i class="dashicons dashicons-no-alt"></i> ' . __('Cancel RSVP', 'lcd-meeting-notes');
                $output .= '</button>';
            } else {
                $output .= '<button type="button" class="rsvp-button add-rsvp" data-meeting-id="' . esc_attr($meeting->ID) . '">';
                $output .= '<i class="dashicons dashicons-yes"></i> ' . __('RSVP Now', 'lcd-meeting-notes');
                $output .= '</button>';
            }
            $output .= '</div>';
            
            // Calendar links
            if (!empty($calendar_links)) {
                if (isset($calendar_links['google'])) {
                    $output .= '<a href="' . esc_url($calendar_links['google']) . '" class="calendar-link google" target="_blank">';
                    $output .= '<i class="dashicons dashicons-calendar-alt"></i> ' . __('Google Calendar', 'lcd-meeting-notes');
                    $output .= '</a>';
                }
                if (isset($calendar_links['apple'])) {
                    $output .= '<a href="' . esc_url($calendar_links['apple']) . '" class="calendar-link apple" download="meeting.ics">';
                    $output .= '<i class="dashicons dashicons-calendar-alt"></i> ' . __('Apple Calendar', 'lcd-meeting-notes');
                    $output .= '</a>';
                }
            }

            // Facebook RSVP button
            if (!empty($facebook_url)) {
                $output .= '<a href="' . esc_url($facebook_url) . '" class="facebook-rsvp" target="_blank">';
                $output .= '<i class="dashicons dashicons-facebook"></i> ' . __('RSVP on Facebook', 'lcd-meeting-notes');
                $output .= '</a>';
            }
            
            $output .= '</div>'; // End calendar-rsvp-links
            $output .= '</div>'; // End meeting-actions
            $output .= '</div>'; // End upcoming-meeting
        }
        
        $output .= '</div>'; // End lcd-meetings-tabs

        return $output;
    }
} 