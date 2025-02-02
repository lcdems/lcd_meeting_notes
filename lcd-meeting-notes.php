<?php
/**
 * Plugin Name: LCD Meetings
 * Plugin URI: https://lewiscountydemocrats.org/
 * Description: Manages meetings functionality for lewis County Democrats
 * Version: 1.0.0
 * Author: lewis County Democrats
 * Author URI: https://lewiscountydemocrats.org/
 * Text Domain: lcd-meeting-notes
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('LCD_MEETING_NOTES_PATH', plugin_dir_path(__FILE__));
define('LCD_MEETING_NOTES_URL', plugin_dir_url(__FILE__));

// Include required files
require_once LCD_MEETING_NOTES_PATH . 'includes/class-fpdf-setup.php';
require_once LCD_MEETING_NOTES_PATH . 'includes/class-meeting-notes-export.php';

class LCD_Meeting_Notes {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     */
    private function __construct() {
        // Include required files
        require_once plugin_dir_path(__FILE__) . 'includes/class-fpdf-setup.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-meeting-notes-export.php';

        // Initialize export class using singleton pattern
        LCD_Meeting_Notes_Export::get_instance();

        // Only load upcoming meetings on the frontend
        if (!is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-upcoming-meetings.php';
            new LCD_Upcoming_Meetings();
        }

        // Check and install FPDF if needed
        if (!LCD_FPDF_Setup::is_installed()) {
            add_action('admin_notices', array($this, 'show_fpdf_notice'));
        }

        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meeting_notes_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_lcd_export_meeting_pdf', array($this, 'handle_pdf_export'));
        add_action('wp_ajax_lcd_generate_meeting_email', array($this, 'handle_email_generation'));
        add_action('wp_ajax_lcd_search_people', array($this, 'ajax_search_people'));
        add_action('wp_ajax_lcd_create_person', array($this, 'ajax_create_person'));
        add_action('wp_ajax_upload_agenda_pdf', array($this, 'handle_agenda_pdf_upload'));
        add_action('edit_form_after_title', array($this, 'add_date_field'));
        add_action('admin_notices', array($this, 'show_validation_notice'));
        add_action('admin_init', array($this, 'handle_fpdf_install'));
        
        // Add title field modifications
        add_filter('enter_title_here', array($this, 'change_title_placeholder'), 10, 2);
        add_action('admin_head', array($this, 'make_title_readonly'));
    }

    /**
     * Change title placeholder
     */
    public function change_title_placeholder($placeholder, $post) {
        if ($post->post_type === 'meeting_notes') {
            return __('Title will be auto-generated from Meeting Type and Date', 'lcd-meeting-notes');
        }
        return $placeholder;
    }

    /**
     * Make title field read-only
     */
    public function make_title_readonly() {
        global $post;
        if (isset($post) && $post->post_type === 'meeting_notes') {
            ?>
            <style>
                #title {
                    background: #f0f0f1 !important;
                    pointer-events: none !important;
                }
                .editor-post-title__input {
                    background: #f0f0f1 !important;
                    pointer-events: none !important;
                }
            </style>
            <?php
        }
    }

    /**
     * Register Meeting Notes Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Meetings', 'Post type general name', 'lcd-meeting-notes'),
            'singular_name'         => _x('Meeting', 'Post type singular name', 'lcd-meeting-notes'),
            'menu_name'            => _x('Meetings', 'Admin Menu text', 'lcd-meeting-notes'),
            'name_admin_bar'       => _x('Meeting', 'Add New on Toolbar', 'lcd-meeting-notes'),
            'add_new_item'         => __('Add New Meeting', 'lcd-meeting-notes'),
            'edit_item'            => __('Edit Meeting', 'lcd-meeting-notes'),
            'new_item'             => __('New Meeting', 'lcd-meeting-notes'),
            'view_item'            => __('View Meeting', 'lcd-meeting-notes'),
            'search_items'         => __('Search Meetings', 'lcd-meeting-notes'),
            'not_found'            => __('No meetings found', 'lcd-meeting-notes'),
            'not_found_in_trash'   => __('No meetings found in Trash', 'lcd-meeting-notes'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'meeting-notes'),
            'capability_type'    => 'post',
            'has_archive'       => true,
            'hierarchical'      => false,
            'menu_position'     => null,
            'supports'          => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions'),
            'menu_icon'         => 'dashicons-clipboard'
        );

        register_post_type('meeting_notes', $args);

        // Register Meeting Type Taxonomy
        if (!taxonomy_exists('meeting_type')) {
            register_taxonomy('meeting_type', 'meeting_notes', array(
                'label'              => __('Meeting Type', 'lcd-meeting-notes'),
                'labels'             => array(
                    'name'              => __('Meeting Types', 'lcd-meeting-notes'),
                    'singular_name'     => __('Meeting Type', 'lcd-meeting-notes'),
                    'add_new_item'      => __('Add New Meeting Type', 'lcd-meeting-notes'),
                    'new_item_name'     => __('New Meeting Type', 'lcd-meeting-notes'),
                    'edit_item'         => __('Edit Meeting Type', 'lcd-meeting-notes'),
                    'update_item'       => __('Update Meeting Type', 'lcd-meeting-notes'),
                ),
                'hierarchical'       => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'rewrite'           => array('slug' => 'meeting-type'),
            ));
        }

        // Register Meeting Locations Taxonomy
        if (!taxonomy_exists('meeting_location')) {
            register_taxonomy('meeting_location', 'meeting_notes', array(
                'label'              => __('Meeting Locations', 'lcd-meeting-notes'),
                'labels'             => array(
                    'name'              => __('Meeting Locations', 'lcd-meeting-notes'),
                    'singular_name'     => __('Meeting Location', 'lcd-meeting-notes'),
                    'add_new_item'      => __('Add New Location', 'lcd-meeting-notes'),
                    'new_item_name'     => __('New Location', 'lcd-meeting-notes'),
                    'edit_item'         => __('Edit Location', 'lcd-meeting-notes'),
                    'update_item'       => __('Update Location', 'lcd-meeting-notes'),
                ),
                'hierarchical'       => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'rewrite'           => array('slug' => 'meeting-location'),
            ));
        }

        // Register location meta
        register_meta('term', 'location_address', array(
            'type' => 'string',
            'description' => 'Meeting location address',
            'single' => true,
            'show_in_rest' => true,
        ));

        // Add hooks for location meta fields
        add_action('meeting_location_add_form_fields', array($this, 'meeting_location_add_form_fields'));
        add_action('meeting_location_edit_form_fields', array($this, 'meeting_location_edit_form_fields'));
        add_action('created_meeting_location', array($this, 'save_location_meta'));
        add_action('edited_meeting_location', array($this, 'save_location_meta'));
    }

    /**
     * Add Meeting Location term meta fields
     */
    public function meeting_location_add_form_fields() {
        ?>
        <div class="form-field">
            <label for="location_address"><?php _e('Address', 'lcd-meeting-notes'); ?></label>
            <input type="text" name="location_address" id="location_address" value="">
            <p class="description"><?php _e('Full address of the meeting location', 'lcd-meeting-notes'); ?></p>
        </div>
        <?php
    }

    /**
     * Edit fields for Meeting Location taxonomy
     */
    public function meeting_location_edit_form_fields($term) {
        $address = get_term_meta($term->term_id, 'location_address', true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="location_address"><?php _e('Address', 'lcd-meeting-notes'); ?></label>
            </th>
            <td>
                <input type="text" name="location_address" id="location_address" value="<?php echo esc_attr($address); ?>">
                <p class="description"><?php _e('Full address of the meeting location', 'lcd-meeting-notes'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save Meeting Location term meta
     */
    public function save_location_meta($term_id) {
        if (isset($_POST['location_address'])) {
            update_term_meta(
                $term_id,
                'location_address',
                sanitize_text_field($_POST['location_address'])
            );
        }
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'meeting_attendees',
            __('Attendees', 'lcd-meeting-notes'),
            array($this, 'meeting_attendees_callback'),
            'meeting_notes',
            'normal',
            'high'
        );

        add_meta_box(
            'meeting_agenda',
            __('Agenda', 'lcd-meeting-notes'),
            array($this, 'meeting_agenda_callback'),
            'meeting_notes',
            'normal',
            'high'
        );

        add_meta_box(
            'meeting_facebook',
            __('Facebook Event', 'lcd-meeting-notes'),
            array($this, 'meeting_facebook_callback'),
            'meeting_notes',
            'side',
            'default'
        );
    }

    /**
     * Meeting attendees callback
     */
    public function meeting_attendees_callback($post) {
        wp_nonce_field('lcd_meeting_notes_nonce', 'meeting_notes_nonce');
        
        $attendees = get_post_meta($post->ID, '_attendees', true);
        $attendee_array = array_filter(array_map('trim', explode(',', $attendees)));
        ?>
        <div class="meeting-details-fields">
            <p>
                <label for="attendees_select"><strong><?php _e('Attendees', 'lcd-meeting-notes'); ?></strong></label><br>
                <select id="attendees_select" class="widefat" multiple="multiple">
                    <?php
                    foreach ($attendee_array as $attendee) {
                        echo sprintf(
                            '<option value="%s" selected="selected">%s</option>',
                            esc_attr($attendee),
                            esc_html($attendee)
                        );
                    }
                    ?>
                </select>
                <input type="hidden" name="attendees" id="attendees" value="<?php echo esc_attr($attendees); ?>">
                <p class="description"><?php _e('Start typing to search for people or enter new names', 'lcd-meeting-notes'); ?></p>
            </p>
        </div>

        <!-- Modal for new person -->
        <div id="new-person-modal" style="display: none;" class="lcd-modal">
            <div class="lcd-modal-content">
                <h3><?php _e('Add New Person', 'lcd-meeting-notes'); ?></h3>
                <div class="lcd-modal-body">
                    <p>
                        <label for="new_person_first_name"><?php _e('First Name', 'lcd-meeting-notes'); ?></label><br>
                        <input type="text" id="new_person_first_name" class="widefat">
                    </p>
                    <p>
                        <label for="new_person_last_name"><?php _e('Last Name', 'lcd-meeting-notes'); ?></label><br>
                        <input type="text" id="new_person_last_name" class="widefat">
                    </p>
                </div>
                <div class="lcd-modal-footer">
                    <button type="button" class="button button-primary" id="save-new-person"><?php _e('Add Person', 'lcd-meeting-notes'); ?></button>
                    <button type="button" class="button" id="cancel-new-person"><?php _e('Cancel', 'lcd-meeting-notes'); ?></button>
                </div>
            </div>
        </div>

        <style>
            .select2-container {
                width: 100% !important;
            }
            .select2-results__option--person {
                color: #2271b1;
            }
            .select2-results__option--free-text {
                font-style: italic;
            }
            .lcd-modal {
                display: none;
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .lcd-modal-content {
                background-color: #fefefe;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #ddd;
                width: 400px;
                max-width: 90%;
                border-radius: 4px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .lcd-modal-footer {
                margin-top: 20px;
                text-align: right;
            }
            .lcd-modal-footer .button {
                margin-left: 10px;
            }
        </style>
        <?php
    }

    /**
     * Meeting agenda callback
     */
    public function meeting_agenda_callback($post) {
        wp_nonce_field('lcd_meeting_notes_nonce', 'meeting_notes_nonce');
        
        // Get saved agenda PDF ID
        $agenda_pdf_id = get_post_meta($post->ID, '_meeting_agenda_pdf', true);
        $has_agenda = !empty($agenda_pdf_id);
        
        // Get PDF URL if exists
        $pdf_url = $has_agenda ? wp_get_attachment_url($agenda_pdf_id) : '';
        $pdf_filename = $has_agenda ? basename(get_attached_file($agenda_pdf_id)) : '';
        ?>
        <div class="meeting-agenda-wrapper">
            <div class="agenda-upload-section">
                <?php if ($has_agenda): ?>
                    <div class="current-agenda">
                        <p>
                            <strong><?php _e('Current Agenda:', 'lcd-meeting-notes'); ?></strong>
                            <span class="filename"><?php echo esc_html($pdf_filename); ?></span>
                        </p>
                        <div class="agenda-actions">
                            <a href="<?php echo esc_url($pdf_url); ?>" class="button" target="_blank">
                                <?php _e('View PDF', 'lcd-meeting-notes'); ?>
                            </a>
                            <button type="button" class="button remove-agenda">
                                <?php _e('Remove', 'lcd-meeting-notes'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="upload-new-agenda" <?php echo $has_agenda ? 'style="display:none;"' : ''; ?>>
                    <p class="description">
                        <?php _e('Upload a PDF file containing the meeting agenda.', 'lcd-meeting-notes'); ?>
                    </p>
                    <input type="file" 
                           id="agenda_pdf" 
                           name="agenda_pdf" 
                           accept=".pdf"
                           style="display:none;">
                    <input type="hidden" 
                           name="agenda_pdf_id" 
                           id="agenda_pdf_id" 
                           value="<?php echo esc_attr($agenda_pdf_id); ?>">
                    <button type="button" class="button button-primary select-pdf">
                        <?php _e('Select PDF', 'lcd-meeting-notes'); ?>
                    </button>
                </div>

                <div class="upload-progress" style="display:none;">
                    <div class="progress-bar">
                        <div class="progress-bar-fill"></div>
                    </div>
                    <p class="progress-text"></p>
                </div>
            </div>
        </div>

        <style>
            .meeting-agenda-wrapper {
                padding: 10px;
            }
            .current-agenda {
                background: #f0f0f1;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .current-agenda p {
                margin: 0 0 10px;
            }
            .current-agenda .filename {
                font-family: monospace;
                margin-left: 5px;
            }
            .agenda-actions {
                display: flex;
                gap: 10px;
            }
            .upload-progress {
                margin-top: 15px;
            }
            .progress-bar {
                height: 20px;
                background-color: #f0f0f1;
                border-radius: 10px;
                overflow: hidden;
                margin-bottom: 5px;
            }
            .progress-bar-fill {
                height: 100%;
                background-color: #2271b1;
                width: 0;
                transition: width 0.3s ease;
            }
            .progress-text {
                font-size: 12px;
                color: #666;
                margin: 0;
            }
        </style>
        <?php
    }

    /**
     * Facebook event callback
     */
    public function meeting_facebook_callback($post) {
        wp_nonce_field('lcd_meeting_notes_nonce', 'meeting_notes_nonce');
        
        $facebook_url = get_post_meta($post->ID, '_facebook_event_url', true);
        ?>
        <div class="facebook-event-wrapper">
            <p>
                <label for="facebook_event_url"><?php _e('Event URL:', 'lcd-meeting-notes'); ?></label>
                <input type="url" 
                       id="facebook_event_url" 
                       name="facebook_event_url" 
                       value="<?php echo esc_url($facebook_url); ?>" 
                       class="widefat"
                       placeholder="https://facebook.com/events/...">
            </p>
            <?php if (!empty($facebook_url)): ?>
                <p>
                    <a href="<?php echo esc_url($facebook_url); ?>" 
                       class="button" 
                       target="_blank">
                        <?php _e('View Event', 'lcd-meeting-notes'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <style>
            .facebook-event-wrapper label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .facebook-event-wrapper .button {
                margin-top: 8px;
            }
        </style>
        <?php
    }

    /**
     * Handle PDF export
     */
    public function handle_pdf_export() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lcd_meeting_export_nonce')) {
            wp_send_json_error(array('message' => __('Invalid nonce', 'lcd-meeting-notes')));
        }

        $post_id = intval($_POST['post_id']);
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'preview';

        $export = LCD_Meeting_Notes_Export::get_instance();
        $result = $export->generate_pdf($post_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'url' => $result['url'],
            'filename' => $result['filename']
        ));
    }

    /**
     * Handle email generation
     */
    public function handle_email_generation() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lcd_meeting_export_nonce')) {
            wp_send_json_error(array('message' => __('Invalid nonce', 'lcd-meeting-notes')));
        }

        $post_id = intval($_POST['post_id']);
        $to = sanitize_email($_POST['to']);
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $include_pdf = isset($_POST['include_pdf']) ? (bool)$_POST['include_pdf'] : false;
        $include_notes = isset($_POST['include_notes']) ? (bool)$_POST['include_notes'] : true;
        $custom_message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (!is_email($to)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'lcd-meeting-notes')));
        }

        if (!$include_pdf && !$include_notes) {
            wp_send_json_error(array('message' => __('Please include either the PDF attachment or notes in the email body', 'lcd-meeting-notes')));
        }

        $export = LCD_Meeting_Notes_Export::get_instance();
        $result = $export->send_email($post_id, $to, $subject, $include_pdf, $include_notes, $custom_message);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Email sent successfully', 'lcd-meeting-notes')));
    }

    /**
     * Check if LCD People plugin is active
     */
    private function is_people_plugin_active() {
        return class_exists('LCD_People');
    }

    /**
     * AJAX handler for people search
     */
    public function ajax_search_people() {
        check_ajax_referer('lcd_meeting_notes_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(-1);
        }

        $search = sanitize_text_field($_GET['q']);
        $results = array();

        if ($this->is_people_plugin_active()) {
            // Search for existing people records
            $args = array(
                'post_type' => 'lcd_person',
                'posts_per_page' => 10,
                's' => $search,
                'orderby' => 'title',
                'order' => 'ASC'
            );

            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $results[] = array(
                        'id' => 'person_' . get_the_ID(),
                        'text' => get_the_title(),
                        'type' => 'person'
                    );
                }
            }
            wp_reset_postdata();
        }

        // Always add the search term as a free text option
        $results[] = array(
            'id' => 'text_' . $search,
            'text' => $search,
            'type' => 'free_text'
        );

        wp_send_json($results);
    }

    /**
     * Add date field after title
     */
    public function add_date_field($post) {
        if ($post->post_type !== 'meeting_notes') {
            return;
        }

        $meeting_date = get_post_meta($post->ID, '_meeting_date', true);
        $meeting_time = get_post_meta($post->ID, '_meeting_time', true);
        ?>
        <div class="meeting-date-time-wrapper">
            <div class="meeting-date-field">
                <label for="meeting_date"><?php _e('Meeting Date:', 'lcd-meeting-notes'); ?></label>
                <input type="date" 
                       id="meeting_date" 
                       name="meeting_date" 
                       value="<?php echo esc_attr($meeting_date); ?>" 
                       required>
            </div>
            <div class="meeting-time-field">
                <label for="meeting_time"><?php _e('Meeting Time:', 'lcd-meeting-notes'); ?></label>
                <input type="time" 
                       id="meeting_time" 
                       name="meeting_time" 
                       value="<?php echo esc_attr($meeting_time); ?>" 
                       required>
            </div>
        </div>

        <style>
            .meeting-date-time-wrapper {
                padding: 10px 0;
                display: flex;
                gap: 20px;
                align-items: flex-end;
            }
            .meeting-date-field,
            .meeting-time-field {
                flex: 0 0 auto;
            }
            .meeting-date-field label,
            .meeting-time-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
            }
            #meeting_date,
            #meeting_time {
                padding: 5px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
            }
        </style>
        <?php
    }

    /**
     * Save meeting notes meta and update title
     */
    public function save_meeting_notes_meta($post_id) {
        // Verify this is our post type
        if (get_post_type($post_id) !== 'meeting_notes') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['meeting_notes_nonce']) || !wp_verify_nonce($_POST['meeting_notes_nonce'], 'lcd_meeting_notes_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save meeting date
        if (isset($_POST['meeting_date'])) {
            update_post_meta($post_id, '_meeting_date', sanitize_text_field($_POST['meeting_date']));
        }

        // Save meeting time
        if (isset($_POST['meeting_time'])) {
            update_post_meta($post_id, '_meeting_time', sanitize_text_field($_POST['meeting_time']));
        }

        // Save attendees
        if (isset($_POST['attendees'])) {
            update_post_meta($post_id, '_attendees', sanitize_textarea_field($_POST['attendees']));
        }

        // Save agenda PDF ID
        if (isset($_POST['agenda_pdf_id'])) {
            $pdf_id = intval($_POST['agenda_pdf_id']);
            if ($pdf_id > 0 || empty($_POST['agenda_pdf_id'])) {
                update_post_meta($post_id, '_meeting_agenda_pdf', $pdf_id);
            }
        }

        // Save Facebook event URL
        if (isset($_POST['facebook_event_url'])) {
            $facebook_url = esc_url_raw($_POST['facebook_event_url']);
            if (empty($facebook_url) || wp_http_validate_url($facebook_url)) {
                update_post_meta($post_id, '_facebook_event_url', $facebook_url);
            }
        }

        // Check if we're trying to publish
        if (isset($_POST['post_status']) && $_POST['post_status'] === 'publish') {
            $meeting_date = get_post_meta($post_id, '_meeting_date', true);
            $meeting_time = get_post_meta($post_id, '_meeting_time', true);
            
            // Get meeting type directly from the taxonomy
            $meeting_types = wp_get_object_terms($post_id, 'meeting_type');
            
            if (empty($meeting_date) || empty($meeting_time) || empty($meeting_types)) {
                // Set post status back to draft
                remove_action('save_post', array($this, 'save_meeting_notes_meta'));
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_status' => 'draft'
                ));
                add_action('save_post', array($this, 'save_meeting_notes_meta'));

                // Store validation error message
                set_transient('meeting_notes_validation_error_' . $post_id, true, 45);
                
                // Add error message to redirect
                add_filter('redirect_post_location', function($location) {
                    return add_query_arg(array(
                        'message' => 10,
                        'error' => 1
                    ), $location);
                });
                
                return;
            }
        }

        // Update title if we have both fields
        $meeting_types = wp_get_object_terms($post_id, 'meeting_type');
        $meeting_date = get_post_meta($post_id, '_meeting_date', true);
        $meeting_time = get_post_meta($post_id, '_meeting_time', true);
        
        if (!empty($meeting_types) && !empty($meeting_date) && !empty($meeting_time)) {
            $type_names = array_map(function($term) {
                return $term->name;
            }, $meeting_types);
            
            $datetime = new DateTime($meeting_date . ' ' . $meeting_time);
            $formatted_date = $datetime->format('F j, Y \a\t g:i A');
            $title = sprintf('%s Meeting - %s', implode(' & ', $type_names), $formatted_date);
            
            if ($title !== get_the_title($post_id)) {
                remove_action('save_post', array($this, 'save_meeting_notes_meta'));
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $title,
                    'post_name' => sanitize_title($title)
                ));
                add_action('save_post', array($this, 'save_meeting_notes_meta'));
            }
        }
    }

    /**
     * Show validation notice
     */
    public function show_validation_notice() {
        global $post;
        if (!$post) return;

        $transient_name = 'meeting_notes_validation_error_' . $post->ID;
        if (get_transient($transient_name)) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong><?php _e('Meeting could not be published', 'lcd-meeting-notes'); ?></strong></p>
                <p><?php _e('Both Meeting Type and Meeting Date are required fields. Please set both before publishing.', 'lcd-meeting-notes'); ?></p>
            </div>
            <?php
            delete_transient($transient_name);
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        global $post;

        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if (isset($post) && $post->post_type == 'meeting_notes') {
                wp_enqueue_script('jquery');

                // Enqueue Select2
                wp_enqueue_style(
                    'select2',
                    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                    array(),
                    '4.1.0-rc.0'
                );
                
                wp_enqueue_script(
                    'select2',
                    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                    array('jquery'),
                    '4.1.0-rc.0',
                    true
                );

                // Enqueue main admin script
                wp_enqueue_script(
                    'lcd-meeting-notes-admin',
                    plugins_url('assets/js/admin.js', __FILE__),
                    array('jquery', 'select2'),
                    '1.0.0',
                    true
                );

                // Enqueue agenda script
                wp_enqueue_script(
                    'lcd-meeting-notes-agenda',
                    plugins_url('assets/js/agenda.js', __FILE__),
                    array('jquery'),
                    '1.0.0',
                    true
                );

                // Enqueue exports script
                wp_enqueue_script(
                    'lcd-meeting-notes-exports',
                    plugins_url('assets/js/exports.js', __FILE__),
                    array('jquery'),
                    '1.0.0',
                    true
                );

                // Add localization for JavaScript
                wp_localize_script('lcd-meeting-notes-admin', 'meetingNotesL10n', array(
                    'validationMessage' => __('Please select both a Meeting Type and Date before publishing.', 'lcd-meeting-notes'),
                    'searchPlaceholder' => __('Type to search for people or enter names...', 'lcd-meeting-notes'),
                    'modalTitle' => __('Add New Person', 'lcd-meeting-notes'),
                    'firstNameRequired' => __('First name is required', 'lcd-meeting-notes'),
                    'lastNameRequired' => __('Last name is required', 'lcd-meeting-notes'),
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('lcd_meeting_notes_nonce'),
                    'peopleLookupEnabled' => $this->is_people_plugin_active()
                ));

                // Add localization for exports script
                wp_localize_script('lcd-meeting-notes-exports', 'exportsL10n', array(
                    'exportError' => __('Failed to export. Please try again.', 'lcd-meeting-notes'),
                    'emailRequired' => __('Please enter a recipient email address.', 'lcd-meeting-notes'),
                    'emailError' => __('Failed to send email. Please try again.', 'lcd-meeting-notes'),
                    'sending' => __('Sending...', 'lcd-meeting-notes'),
                    'sendEmail' => __('Send', 'lcd-meeting-notes'),
                    'downloadPDF' => __('Download PDF', 'lcd-meeting-notes'),
                    'previewPDF' => __('Preview PDF', 'lcd-meeting-notes')
                ));
            }
        }
    }

    /**
     * Show FPDF installation notice
     */
    public function show_fpdf_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php _e('FPDF library is required for PDF generation in Meetings.', 'lcd-meeting-notes'); ?>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=meeting-notes&action=install_fpdf'), 'install_fpdf'); ?>" class="button button-primary">
                    <?php _e('Install Now', 'lcd-meeting-notes'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle FPDF installation
     */
    public function handle_fpdf_install() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'install_fpdf') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to install libraries.', 'lcd-meeting-notes'));
        }

        check_admin_referer('install_fpdf');

        $result = LCD_FPDF_Setup::install();

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(admin_url('edit.php?post_type=meeting_notes&fpdf_installed=1'));
        exit;
    }

    public function ajax_create_person() {
        check_ajax_referer('lcd_meeting_notes_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'lcd-meeting-notes')));
        }

        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);

        if (empty($first_name) || empty($last_name)) {
            wp_send_json_error(array('message' => __('First and last name are required', 'lcd-meeting-notes')));
        }

        // Create new person post
        $person_id = wp_insert_post(array(
            'post_type' => 'lcd_person',
            'post_status' => 'publish',
            'post_title' => $first_name . ' ' . $last_name
        ));

        if (is_wp_error($person_id)) {
            wp_send_json_error(array('message' => $person_id->get_error_message()));
        }

        // Add first and last name meta
        update_post_meta($person_id, '_lcd_person_first_name', $first_name);
        update_post_meta($person_id, '_lcd_person_last_name', $last_name);

        wp_send_json_success(array(
            'id' => 'person_' . $person_id,
            'text' => $first_name . ' ' . $last_name,
            'type' => 'person'
        ));
    }

    /**
     * Handle agenda PDF upload
     */
    public function handle_agenda_pdf_upload() {
        // Verify nonce
        check_ajax_referer('lcd_meeting_notes_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'lcd-meeting-notes')));
        }

        // Check if file was uploaded
        if (!isset($_FILES['agenda_pdf'])) {
            wp_send_json_error(array('message' => __('No file was uploaded', 'lcd-meeting-notes')));
        }

        $file = $_FILES['agenda_pdf'];

        // Verify file type
        $file_type = wp_check_filetype(basename($file['name']), array('pdf' => 'application/pdf'));
        if (!$file_type['type']) {
            wp_send_json_error(array('message' => __('Invalid file type. Please upload a PDF.', 'lcd-meeting-notes')));
        }

        // Prepare upload
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }

        // Create attachment
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($file['name'])),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attach_id)) {
            wp_send_json_error(array('message' => $attach_id->get_error_message()));
        }

        // Generate metadata and update attachment
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json_success(array(
            'attachment_id' => $attach_id,
            'url' => $upload['url']
        ));
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        // Create necessary database tables
        self::create_tables();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create necessary database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // No custom tables needed at this time
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function lcd_meeting_notes_init() {
    LCD_Meeting_Notes::get_instance();
}
add_action('plugins_loaded', 'lcd_meeting_notes_init');

// Register activation hook
function lcd_meeting_notes_activate() {
    $instance = LCD_Meeting_Notes::get_instance();
}
register_activation_hook(__FILE__, 'lcd_meeting_notes_activate'); 