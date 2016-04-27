<?php
	
	/**
	 *	Plugin Name: WP Photo Contest
	 *	Plugin URI: 
	 *	Description: Photo contest for wordpress sites
	 *	Author: Corneliu C&icirc;rlan
	 *	License: GPLv2 or later
	 *	Version: 1.0
	 *	Author URI: http://www.TwoCSoft.com
	 */

	/**
	 *  MIN REQUIREMENTS: MySQL- 5.0.3 | PHP 5.3 | WP 3.3
	 */
	
	global $wpdb;


	// Plugin version
	if (!defined('WPPC_VERSION')) define('WPPC_VERSION', '1.1'); 

	// ImagesLoaded JS version
	if (!defined('IMAGES_LOADED_VERSION')) define('IMAGES_LOADED_VERSION', '3.1.8');

	// Reference to this plugin's file
	if (!defined('WPPC_FILE')) define('WPPC_FILE', __FILE__);

	// Plugin directory path
	if (!defined('WPPC_DIR')) define('WPPC_DIR', plugin_dir_path(__FILE__));

	// Plugin URI
	if (!defined('WPPC_URI')) define('WPPC_URI', trailingslashit(plugins_url('', __FILE__)));

	// General settings group
	if (!defined('WPPC_SETTINGS_GENERAL')) define('WPPC_SETTINGS_GENERAL', 'wppc-settings-general');

	// Watermark settings group
	if (!defined('WPPC_SETTINGS_WATERMARK')) define('WPPC_SETTINGS_WATERMARK', 'wppc-settings-watermark');

	// Watermark default text size
	if (!defined('WPPC_WATERMARK_TEXT_SIZE')) define('WPPC_WATERMARK_TEXT_SIZE', 14);

	// Approved photo
	if (!defined('WPPC_PHOTO_APPROVED')) define('WPPC_PHOTO_APPROVED', 1);

	// New photo
	if (!defined('WPPC_PHOTO_NEW')) define('WPPC_PHOTO_NEW', 0);

	// Rejected photo
	if (!defined('WPPC_PHOTO_REJECTED')) define('WPPC_PHOTO_REJECTED', -1);

	// Mobile device photo
	if (!defined('WPPC_PHOTO_MOBILE_DEVICE')) define('WPPC_PHOTO_MOBILE_DEVICE', 1);

	// All contests table
	if (!defined('WPPC_TABLE_ALL_CONTESTS')) define('WPPC_TABLE_ALL_CONTESTS', $wpdb->prefix.'wppc_contests_all');

	// Contests entries table
	if (!defined('WPPC_TABLE_CONTESTS_ENTRIES')) define('WPPC_TABLE_CONTESTS_ENTRIES', $wpdb->prefix.'wppc_contests_entries');

	// Contests votes table
	if (!defined('WPPC_TABLE_CONTESTS_VOTES')) define('WPPC_TABLE_CONTESTS_VOTES', $wpdb->prefix.'wppc_contests_votes');

	// WPPC Nonce
	if (!defined('WPPC_NONCE')) define('WPPC_NONCE', 'wppc-nonce');


	/**
	 * View all contests
	 */
	include_once(WPPC_DIR.'classes/wppc-all-contests.php');

	/**
	 * Create a new contest/edit existing contest
	 */
	include_once(WPPC_DIR.'classes/wppc-contest.php');
	new WPPCContest();

	/**
	 * View/Approve/Trash contest photos
	 */
	include_once(WPPC_DIR.'classes/wppc-photos.php');

	/**
	 * Settings page
	 */
	include_once(WPPC_DIR.'classes/wppc-settings.php');
	new WPPCSettings();

	/**
	 * Add shortcode
	 */
	include_once(WPPC_DIR.'classes/wppc-shortcode.php');
	new WPPCShortcode();

?>