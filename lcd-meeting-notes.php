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
require_once LCD_MEETING_NOTES_PATH . 'includes/class-upcoming-meetings.php';

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
        // Only load upcoming meetings on the frontend
        if (!is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-upcoming-meetings.php';
            new LCD_Upcoming_Meetings();
        }

        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meeting_notes_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_upload_agenda_pdf', array($this, 'handle_file_upload'));
        add_action('wp_ajax_upload_meeting_notes', array($this, 'handle_file_upload'));
        add_action('wp_ajax_rsvp_meeting', array($this, 'handle_rsvp'));
        add_action('wp_ajax_nopriv_rsvp_meeting', array($this, 'handle_rsvp'));
        add_action('edit_form_after_title', array($this, 'add_date_field'));
        add_action('admin_notices', array($this, 'show_validation_notice'));
        add_action('template_redirect', array($this, 'handle_single_meeting_redirect'));
        add_filter('archive_template', array($this, 'load_meeting_archive_template'));
        add_action('pre_get_posts', array($this, 'modify_meetings_query'));
        
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
            wp_add_inline_style('lcd-meeting-notes-admin', '
                #title, .editor-post-title__input {
                    background: #f0f0f1 !important;
                    pointer-events: none !important;
                }
            ');
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

        // Register Meeting Format Taxonomy
        if (!taxonomy_exists('meeting_format')) {
            register_taxonomy('meeting_format', 'meeting_notes', array(
                'label'              => __('Meeting Format', 'lcd-meeting-notes'),
                'labels'             => array(
                    'name'              => __('Meeting Formats', 'lcd-meeting-notes'),
                    'singular_name'     => __('Meeting Format', 'lcd-meeting-notes'),
                    'add_new_item'      => __('Add New Meeting Format', 'lcd-meeting-notes'),
                    'new_item_name'     => __('New Meeting Format', 'lcd-meeting-notes'),
                    'edit_item'         => __('Edit Meeting Format', 'lcd-meeting-notes'),
                    'update_item'       => __('Update Meeting Format', 'lcd-meeting-notes'),
                ),
                'hierarchical'       => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'rewrite'           => array('slug' => 'meeting-format'),
            ));
        }
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
            'meeting_files',
            __('Agenda & Meeting Notes', 'lcd-meeting-notes'),
            array($this, 'meeting_files_callback'),
            'meeting_notes',
            'normal',
            'high'
        );

        add_meta_box(
            'meeting_rsvp',
            __('RSVP Management', 'lcd-meeting-notes'),
            array($this, 'meeting_rsvp_callback'),
            'meeting_notes',
            'side',
            'default'
        );

        add_meta_box(
            'meeting_youtube',
            __('Meeting Recording', 'lcd-meeting-notes'),
            array($this, 'meeting_youtube_callback'),
            'meeting_notes',
            'side',
            'default'
        );

        add_meta_box(
            'meeting_facebook',
            __('Facebook Event', 'lcd-meeting-notes'),
            array($this, 'meeting_facebook_callback'),
            'meeting_notes',
            'side',
            'default'
        );

        add_meta_box(
            'meeting_zoom',
            __('Zoom Meeting Information', 'lcd-meeting-notes'),
            array($this, 'meeting_zoom_callback'),
            'meeting_notes',
            'side',
            'default'
        );
    }

    /**
     * Meeting files callback
     */
    public function meeting_files_callback($post) {
        wp_nonce_field('lcd_meeting_notes_nonce', 'meeting_notes_nonce');
        
        // Get saved file IDs
        $agenda_pdf_id = get_post_meta($post->ID, '_meeting_agenda_pdf', true);
        $notes_pdf_id = get_post_meta($post->ID, '_meeting_notes_pdf', true);
        
        $has_agenda = !empty($agenda_pdf_id);
        $has_notes = !empty($notes_pdf_id);
        
        // Get PDF URLs if they exist
        $agenda_url = $has_agenda ? wp_get_attachment_url($agenda_pdf_id) : '';
        $agenda_filename = $has_agenda ? basename(get_attached_file($agenda_pdf_id)) : '';
        
        $notes_url = $has_notes ? wp_get_attachment_url($notes_pdf_id) : '';
        $notes_filename = $has_notes ? basename(get_attached_file($notes_pdf_id)) : '';
        ?>
        <div class="meeting-files-wrapper">
            <!-- Agenda Section -->
            <div class="meeting-agenda-wrapper file-upload-wrapper">
                <h3><?php _e('Meeting Agenda', 'lcd-meeting-notes'); ?></h3>
                <div class="agenda-upload-section">
                    <?php if ($has_agenda): ?>
                        <div class="current-file">
                            <p>
                                <strong><?php _e('Current Agenda:', 'lcd-meeting-notes'); ?></strong>
                                <span class="filename"><?php echo esc_html($agenda_filename); ?></span>
                            </p>
                            <div class="file-actions">
                                <a href="<?php echo esc_url($agenda_url); ?>" class="button" target="_blank">
                                    <?php _e('View PDF', 'lcd-meeting-notes'); ?>
                                </a>
                                <button type="button" class="button remove-file">
                                    <?php _e('Remove', 'lcd-meeting-notes'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="upload-new-file<?php echo $has_agenda ? ' hidden' : ''; ?>">
                        <p class="description">
                            <?php _e('Upload a PDF file containing the meeting agenda.', 'lcd-meeting-notes'); ?>
                        </p>
                        <input type="file" 
                               name="file" 
                               accept=".pdf"
                               class="hidden">
                        <input type="hidden" 
                               name="agenda_pdf_id" 
                               value="<?php echo esc_attr($agenda_pdf_id); ?>">
                        <button type="button" class="button button-primary select-file">
                            <?php _e('Select PDF', 'lcd-meeting-notes'); ?>
                        </button>
                    </div>

                    <div class="upload-progress hidden">
                        <div class="progress-bar">
                            <div class="progress-bar-fill"></div>
                        </div>
                        <p class="progress-text"></p>
                    </div>
                </div>
            </div>

            <!-- Meeting Notes Section -->
            <div class="meeting-notes-wrapper file-upload-wrapper">
                <h3><?php _e('Meeting Notes', 'lcd-meeting-notes'); ?></h3>
                <div class="notes-upload-section">
                    <?php if ($has_notes): ?>
                        <div class="current-file">
                            <p>
                                <strong><?php _e('Current Notes:', 'lcd-meeting-notes'); ?></strong>
                                <span class="filename"><?php echo esc_html($notes_filename); ?></span>
                            </p>
                            <div class="file-actions">
                                <a href="<?php echo esc_url($notes_url); ?>" class="button" target="_blank">
                                    <?php _e('View PDF', 'lcd-meeting-notes'); ?>
                                </a>
                                <button type="button" class="button remove-file">
                                    <?php _e('Remove', 'lcd-meeting-notes'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="upload-new-file<?php echo $has_notes ? ' hidden' : ''; ?>">
                        <p class="description">
                            <?php _e('Upload a PDF file containing the meeting notes.', 'lcd-meeting-notes'); ?>
                        </p>
                        <input type="file" 
                               name="file" 
                               accept=".pdf"
                               class="hidden">
                        <input type="hidden" 
                               name="notes_pdf_id" 
                               value="<?php echo esc_attr($notes_pdf_id); ?>">
                        <button type="button" class="button button-primary select-file">
                            <?php _e('Select PDF', 'lcd-meeting-notes'); ?>
                        </button>
                    </div>

                    <div class="upload-progress hidden">
                        <div class="progress-bar">
                            <div class="progress-bar-fill"></div>
                        </div>
                        <p class="progress-text"></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * RSVP management callback
     */
    public function meeting_rsvp_callback($post) {
        $rsvps = get_post_meta($post->ID, '_meeting_rsvps', true);
        if (!is_array($rsvps)) {
            $rsvps = array();
        }
        $count = count($rsvps);
        ?>
        <div class="rsvp-management-wrapper">
            <p>
                <label for="rsvp_count"><strong><?php _e('Current RSVP Count:', 'lcd-meeting-notes'); ?></strong></label>
                <input type="number" 
                       id="rsvp_count" 
                       name="rsvp_count" 
                       value="<?php echo esc_attr($count); ?>" 
                       class="small-text"
                       min="0"
                       style="width: 80px;">
            </p>
            <?php if ($count > 0): ?>
                <p class="description">
                    <?php 
                    printf(
                        _n(
                            '%s person has RSVP\'d to this meeting', 
                            '%s people have RSVP\'d to this meeting', 
                            $count, 
                            'lcd-meeting-notes'
                        ), 
                        number_format_i18n($count)
                    ); 
                    ?>
                </p>
                <p class="description">
                    <?php _e('Last RSVP:', 'lcd-meeting-notes'); ?>
                    <?php echo esc_html(end($rsvps)); ?>
                </p>
            <?php endif; ?>
            <p class="description">
                <?php _e('Manually adjust the RSVP count if needed. This will reset the IP tracking.', 'lcd-meeting-notes'); ?>
            </p>
        </div>

        <style>
            .rsvp-management-wrapper label {
                display: inline-block;
                margin-bottom: 5px;
            }
            .rsvp-management-wrapper input[type="number"] {
                margin-left: 10px;
            }
            .rsvp-management-wrapper .description {
                margin-top: 8px;
                font-style: italic;
            }
        </style>
        <?php
    }

    /**
     * YouTube link callback
     */
    public function meeting_youtube_callback($post) {
        $youtube_url = get_post_meta($post->ID, '_meeting_youtube_url', true);
        ?>
        <div class="youtube-link-wrapper">
            <p>
                <label for="meeting_youtube_url"><?php _e('YouTube Recording URL:', 'lcd-meeting-notes'); ?></label>
                <input type="url" 
                       id="meeting_youtube_url" 
                       name="meeting_youtube_url" 
                       value="<?php echo esc_url($youtube_url); ?>" 
                       class="widefat"
                       placeholder="https://youtube.com/watch?v=...">
            </p>
            <?php if (!empty($youtube_url)): ?>
                <p>
                    <a href="<?php echo esc_url($youtube_url); ?>" 
                       class="button" 
                       target="_blank">
                        <?php _e('View Recording', 'lcd-meeting-notes'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <style>
            .youtube-link-wrapper label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .youtube-link-wrapper .button {
                margin-top: 8px;
            }
        </style>
        <?php
    }

    /**
     * Facebook event callback
     */
    public function meeting_facebook_callback($post) {
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
     * Zoom meeting info callback
     */
    public function meeting_zoom_callback($post) {
        $zoom_link = get_post_meta($post->ID, '_zoom_meeting_link', true);
        $zoom_id = get_post_meta($post->ID, '_zoom_meeting_id', true);
        $zoom_passcode = get_post_meta($post->ID, '_zoom_meeting_passcode', true);
        $zoom_details = get_post_meta($post->ID, '_zoom_meeting_details', true);
        ?>
        <div class="zoom-meeting-wrapper">
            <p>
                <label for="zoom_meeting_link"><?php _e('Meeting Link:', 'lcd-meeting-notes'); ?></label>
                <input type="url" 
                       id="zoom_meeting_link" 
                       name="zoom_meeting_link" 
                       value="<?php echo esc_url($zoom_link); ?>" 
                       class="widefat"
                       placeholder="https://zoom.us/j/...">
            </p>
            <p>
                <label for="zoom_meeting_id"><?php _e('Meeting ID:', 'lcd-meeting-notes'); ?></label>
                <input type="text" 
                       id="zoom_meeting_id" 
                       name="zoom_meeting_id" 
                       value="<?php echo esc_attr($zoom_id); ?>" 
                       class="widefat"
                       placeholder="123 4567 8901">
            </p>
            <p>
                <label for="zoom_meeting_passcode"><?php _e('Passcode:', 'lcd-meeting-notes'); ?></label>
                <input type="text" 
                       id="zoom_meeting_passcode" 
                       name="zoom_meeting_passcode" 
                       value="<?php echo esc_attr($zoom_passcode); ?>" 
                       class="widefat"
                       placeholder="123456">
            </p>
            <p>
                <label for="zoom_meeting_details"><?php _e('Additional Details:', 'lcd-meeting-notes'); ?></label>
                <textarea id="zoom_meeting_details" 
                         name="zoom_meeting_details" 
                         class="widefat" 
                         rows="3" 
                         placeholder="<?php _e('Phone numbers, additional instructions, etc.', 'lcd-meeting-notes'); ?>"><?php echo esc_textarea($zoom_details); ?></textarea>
            </p>
        </div>

        <style>
            .zoom-meeting-wrapper label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .zoom-meeting-wrapper input,
            .zoom-meeting-wrapper textarea {
                margin-bottom: 10px;
            }
            .zoom-meeting-wrapper textarea {
                resize: vertical;
            }
        </style>
        <?php
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
     * Save meeting notes meta
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

        // Save YouTube URL
        if (isset($_POST['meeting_youtube_url'])) {
            $youtube_url = esc_url_raw($_POST['meeting_youtube_url']);
            if (empty($youtube_url) || wp_http_validate_url($youtube_url)) {
                update_post_meta($post_id, '_meeting_youtube_url', $youtube_url);
            }
        }

        // Save agenda PDF ID
        if (isset($_POST['agenda_pdf_id'])) {
            $pdf_id = intval($_POST['agenda_pdf_id']);
            if ($pdf_id > 0 || empty($_POST['agenda_pdf_id'])) {
                update_post_meta($post_id, '_meeting_agenda_pdf', $pdf_id);
            }
        }

        // Save notes PDF ID
        if (isset($_POST['notes_pdf_id'])) {
            $pdf_id = intval($_POST['notes_pdf_id']);
            if ($pdf_id > 0 || empty($_POST['notes_pdf_id'])) {
                update_post_meta($post_id, '_meeting_notes_pdf', $pdf_id);
            }
        }

        // Save Facebook event URL
        if (isset($_POST['facebook_event_url'])) {
            $facebook_url = esc_url_raw($_POST['facebook_event_url']);
            if (empty($facebook_url) || wp_http_validate_url($facebook_url)) {
                update_post_meta($post_id, '_facebook_event_url', $facebook_url);
            }
        }

        // Save Zoom meeting info
        if (isset($_POST['zoom_meeting_link'])) {
            $zoom_link = esc_url_raw($_POST['zoom_meeting_link']);
            if (empty($zoom_link) || wp_http_validate_url($zoom_link)) {
                update_post_meta($post_id, '_zoom_meeting_link', $zoom_link);
            }
        }

        if (isset($_POST['zoom_meeting_id'])) {
            update_post_meta($post_id, '_zoom_meeting_id', sanitize_text_field($_POST['zoom_meeting_id']));
        }

        if (isset($_POST['zoom_meeting_passcode'])) {
            update_post_meta($post_id, '_zoom_meeting_passcode', sanitize_text_field($_POST['zoom_meeting_passcode']));
        }

        if (isset($_POST['zoom_meeting_details'])) {
            update_post_meta($post_id, '_zoom_meeting_details', sanitize_textarea_field($_POST['zoom_meeting_details']));
        }

        // Save RSVP count
        if (isset($_POST['rsvp_count'])) {
            $count = max(0, intval($_POST['rsvp_count']));
            $rsvps = array();
            
            // If count is greater than 0, create dummy entries
            if ($count > 0) {
                $current_time = current_time('mysql');
                for ($i = 0; $i < $count; $i++) {
                    $rsvps['manual_' . $i] = $current_time;
                }
            }
            
            update_post_meta($post_id, '_meeting_rsvps', $rsvps);
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

                // Enqueue admin styles
                wp_enqueue_style(
                    'lcd-meeting-notes-admin',
                    plugins_url('assets/css/admin.css', __FILE__),
                    array(),
                    '1.0.0'
                );

                // Enqueue main admin script
                wp_enqueue_script(
                    'lcd-meeting-notes-admin',
                    plugins_url('assets/js/admin.js', __FILE__),
                    array('jquery'),
                    '1.0.0',
                    true
                );

                // Enqueue file uploads script
                wp_enqueue_script(
                    'lcd-meeting-notes-file-uploads',
                    plugins_url('assets/js/file-uploads.js', __FILE__),
                    array('jquery'),
                    '1.0.0',
                    true
                );

                // Add localization for JavaScript
                wp_localize_script('lcd-meeting-notes-admin', 'meetingNotesL10n', array(
                    'validationMessage' => __('Please select both a Meeting Type and Date before publishing.', 'lcd-meeting-notes'),
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('lcd_meeting_notes_nonce')
                ));
            }
        }
    }

    /**
     * Handle file upload
     */
    public function handle_file_upload() {
        // Verify nonce
        check_ajax_referer('lcd_meeting_notes_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'lcd-meeting-notes')));
        }

        // Check if file was uploaded
        if (!isset($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file was uploaded', 'lcd-meeting-notes')));
        }

        $file = $_FILES['file'];

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
     * Handle redirection of single meeting notes to their PDF
     */
    public function handle_single_meeting_redirect() {
        if (is_singular('meeting_notes')) {
            $post_id = get_the_ID();
            $notes_pdf_id = get_post_meta($post_id, '_meeting_notes_pdf', true);
            
            if (!empty($notes_pdf_id)) {
                $pdf_url = wp_get_attachment_url($notes_pdf_id);
                if ($pdf_url) {
                    wp_redirect($pdf_url);
                    exit;
                }
            }
            
            // If no PDF, redirect to archive
            wp_redirect(get_post_type_archive_link('meeting_notes'));
            exit;
        }
    }

    /**
     * Load custom archive template for meeting notes
     */
    public function load_meeting_archive_template($template) {
        if (is_post_type_archive('meeting_notes')) {
            $custom_template = plugin_dir_path(__FILE__) . 'templates/archive-meeting-notes.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }

    /**
     * Modify the main query for meetings
     */
    public function modify_meetings_query($query) {
        // Only modify the main query for meeting notes archive on the frontend
        if (!is_admin() && $query->is_main_query() && is_post_type_archive('meeting_notes')) {
            // Show only past meetings in the archive
            $query->set('meta_query', array(
                array(
                    'key' => '_meeting_date',
                    'value' => current_time('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                )
            ));
            $query->set('orderby', array(
                '_meeting_date' => 'DESC',
                '_meeting_time' => 'DESC'
            ));
            $query->set('meta_key', '_meeting_date');
        }
    }

    /**
     * Handle RSVP AJAX requests
     */
    public function handle_rsvp() {
        check_ajax_referer('lcd_rsvp_nonce', 'nonce');

        $meeting_id = isset($_POST['meeting_id']) ? intval($_POST['meeting_id']) : 0;
        $action = isset($_POST['rsvp_action']) ? sanitize_text_field($_POST['rsvp_action']) : '';
        $ip_address = sanitize_text_field($_SERVER['REMOTE_ADDR']);

        if (!$meeting_id || !in_array($action, array('add', 'remove'))) {
            wp_send_json_error(array('message' => __('Invalid request', 'lcd-meeting-notes')));
        }

        // Get current RSVPs
        $rsvps = get_post_meta($meeting_id, '_meeting_rsvps', true);
        if (!is_array($rsvps)) {
            $rsvps = array();
        }

        // Check if user has already RSVPed
        $cookie_name = 'lcd_meeting_rsvp_' . $meeting_id;
        $has_rsvped = isset($_COOKIE[$cookie_name]);

        if ($action === 'add' && !$has_rsvped) {
            // Add RSVP
            $rsvps[$ip_address] = current_time('mysql');
            update_post_meta($meeting_id, '_meeting_rsvps', $rsvps);
            
            // Set cookie to expire in 30 days
            setcookie($cookie_name, '1', time() + (30 * DAY_IN_SECONDS), '/');
            
            wp_send_json_success(array(
                'message' => __('RSVP successful', 'lcd-meeting-notes'),
                'count' => count($rsvps)
            ));
        } elseif ($action === 'remove' && $has_rsvped) {
            // Remove RSVP
            unset($rsvps[$ip_address]);
            update_post_meta($meeting_id, '_meeting_rsvps', $rsvps);
            
            // Remove cookie
            setcookie($cookie_name, '', time() - 3600, '/');
            
            wp_send_json_success(array(
                'message' => __('RSVP removed', 'lcd-meeting-notes'),
                'count' => count($rsvps)
            ));
        } else {
            wp_send_json_error(array('message' => __('You have already RSVPed to this meeting', 'lcd-meeting-notes')));
        }
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();
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