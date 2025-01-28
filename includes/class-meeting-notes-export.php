<?php
/**
 * Meeting Export functionality
 */

if (!defined('ABSPATH')) exit;

class LCD_Meeting_Notes_Export {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize FPDF
        require_once LCD_MEETING_NOTES_PATH . 'lib/fpdf186/fpdf.php';
        
        // Add hook for mail failures
        add_action('wp_mail_failed', array($this, 'log_mail_error'));
    }

    /**
     * Log mail errors from wp_mail_failed action
     */
    public function log_mail_error($wp_error) {
        error_log('Mail Error Details:');
        error_log('Error Message: ' . $wp_error->get_error_message());
        
        $error_data = $wp_error->get_error_data();
        if (!empty($error_data)) {
            error_log('Additional Error Data:');
            error_log(print_r($error_data, true));
        }
    }

    /**
     * Generate PDF from meeting notes
     */
    public function generate_pdf($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'meeting_notes') {
            return new WP_Error('invalid_post', __('Invalid meeting post', 'lcd-meeting-notes'));
        }

        // Get meeting details
        $meeting_date = get_post_meta($post_id, '_meeting_date', true);
        $meeting_location = get_post_meta($post_id, '_meeting_location', true);
        $attendees = get_post_meta($post_id, '_attendees', true);
        $meeting_types = wp_get_object_terms($post_id, 'meeting_type');
        $meeting_type = !empty($meeting_types) ? $meeting_types[0]->name : 'Unknown';

        // Create new PDF document
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetMargins(20, 20, 20);

        // Set font
        $pdf->SetFont('Arial', 'B', 16);

        // Add header with organization name
        $pdf->Cell(0, 10, get_bloginfo('name'), 0, 1, 'C');
        $pdf->Ln(5);

        // Add title
        $pdf->Cell(0, 10, $post->post_title, 0, 1, 'C');
        $pdf->Ln(10);

        // Meeting details
        $pdf->SetFont('Arial', '', 12);
        
        // Date
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(30, 8, 'Date:', 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, date('F j, Y', strtotime($meeting_date)), 0, 1);

        // Location
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(30, 8, 'Location:', 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, $meeting_location, 0, 1);

        // Type
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(30, 8, 'Type:', 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, $meeting_type, 0, 1);

        // Attendees
        if ($attendees) {
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, 'Attendees:', 0, 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->MultiCell(0, 8, $attendees, 0, 'L');
        }

        // Content
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'Meeting Details:', 0, 1);
        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 12);

        // Convert HTML content to plain text and handle line breaks
        $content = wp_strip_all_tags($post->post_content);
        $content = str_replace("\r", '', $content); // Remove carriage returns
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $pdf->MultiCell(0, 6, $line, 0, 'L');
            } else {
                $pdf->Ln(4);
            }
        }

        // Save PDF
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/meeting-notes';
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        $filename = sanitize_file_name($post->post_title . '.pdf');
        $pdf_path = $pdf_dir . '/' . $filename;
        $pdf_url = $upload_dir['baseurl'] . '/meeting-notes/' . $filename;

        $pdf->Output('F', $pdf_path);

        return array(
            'path' => $pdf_path,
            'url' => $pdf_url,
            'filename' => $filename
        );
    }

    /**
     * Send meeting notes email
     */
    public function send_email($post_id, $to, $subject = '', $include_pdf = false, $include_notes = true, $custom_message = '') {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'meeting_notes') {
            return new WP_Error('invalid_post', __('Invalid meeting post', 'lcd-meeting-notes'));
        }

        // Get meeting details
        $meeting_date = get_post_meta($post_id, '_meeting_date', true);
        $meeting_location = get_post_meta($post_id, '_meeting_location', true);
        $attendees = get_post_meta($post_id, '_attendees', true);

        // Build email content
        if (empty($subject)) {
            $subject = sprintf(__('Meeting: %s', 'lcd-meeting-notes'), $post->post_title);
        }
        
        $body = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        
        if (!empty($custom_message)) {
            $body .= wpautop($custom_message) . '<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">';
        }

        if ($include_notes) {
            $body .= sprintf(
                '<p><strong>%s:</strong> %s<br><strong>%s:</strong> %s</p>',
                __('Date', 'lcd-meeting-notes'),
                date('F j, Y', strtotime($meeting_date)),
                __('Location', 'lcd-meeting-notes'),
                esc_html($meeting_location)
            );

            if ($attendees) {
                $body .= sprintf(
                    '<p><strong>%s:</strong><br>%s</p>', 
                    __('Attendees', 'lcd-meeting-notes'),
                    nl2br(esc_html($attendees))
                );
            }

            $body .= '<div style="margin-top: 20px;">';
            $body .= wpautop($post->post_content);
            $body .= '</div>';
        } else {
            $body .= '<p>' . __('Meeting notes are attached as a PDF.', 'lcd-meeting-notes') . '</p>';
        }

        $body .= '</div>';

        $site_name = get_bloginfo('name');
        $admin_email = get_bloginfo('admin_email');
        
        $headers = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        // Let WP Mail SMTP handle the From header
        // $headers[] = sprintf('From: %s <%s>', $site_name, $admin_email);
        
        $attachments = array();
        if ($include_pdf) {
            $pdf_data = $this->generate_pdf($post_id);
            if (!is_wp_error($pdf_data)) {
                $attachments[] = $pdf_data['path'];
            }
        }

        // Debug information
        error_log('Attempting to send email:');
        error_log('To: ' . $to);
        error_log('Subject: ' . $subject);
        error_log('Headers: ' . print_r($headers, true));
        error_log('Attachments: ' . print_r($attachments, true));

        $sent = wp_mail($to, $subject, $body, $headers, $attachments);

        if (!$sent) {
            error_log('WordPress mail system error - wp_mail() returned false');
            return new WP_Error('email_error', __('Failed to send email. Check the WordPress error log for details.', 'lcd-meeting-notes'));
        }

        error_log('Email sent successfully');
        return true;
    }
} 