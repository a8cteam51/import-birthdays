<?php
/**
 * Plugin Name: Import Birthdays
 * Description: Import user birthdays from CSV file
 * Version: 1.0.0
 * Author: WP Special Projects
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include the main plugin class
require_once plugin_dir_path( __FILE__ ) . 'class-birthday-importer.php';
