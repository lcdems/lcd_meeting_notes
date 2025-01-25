<?php
/**
 * FPDF Setup Helper
 */

if (!defined('ABSPATH')) exit;

class LCD_FPDF_Setup {
    public static function install() {
        $fpdf_url = 'http://www.fpdf.org/en/download/fpdf185.zip';
        $zip_file = LCD_MEETING_NOTES_PATH . 'lib/fpdf.zip';
        $extract_path = LCD_MEETING_NOTES_PATH . 'lib/';

        // Create lib directory if it doesn't exist
        if (!file_exists($extract_path)) {
            wp_mkdir_p($extract_path);
        }

        // Download FPDF
        $zip_content = file_get_contents($fpdf_url);
        if ($zip_content === false) {
            return new WP_Error('download_failed', 'Failed to download FPDF');
        }

        // Save zip file
        if (file_put_contents($zip_file, $zip_content) === false) {
            return new WP_Error('save_failed', 'Failed to save FPDF zip file');
        }

        // Extract zip file
        $zip = new ZipArchive;
        if ($zip->open($zip_file) === true) {
            $zip->extractTo($extract_path);
            $zip->close();

            // Rename directory
            rename(
                $extract_path . 'fpdf185',
                $extract_path . 'fpdf'
            );

            // Clean up zip file
            unlink($zip_file);

            return true;
        }

        return new WP_Error('extract_failed', 'Failed to extract FPDF');
    }

    public static function is_installed() {
        return file_exists(LCD_MEETING_NOTES_PATH . 'lib/fpdf186/fpdf.php');
    }
} 