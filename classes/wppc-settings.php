<?php

	// Security check
	if (!defined('ABSPATH')) die;

	// Define class
	if (!class_exists('WPPCSettings')):
		class WPPCSettings
		{
			/**
			 * General settings
			 * 
			 * @var array
			 *
			 * @since 1.0
			 */
			private $generalSettings;

			/**
			 * Watermark settings
			 * 
			 * @var array
			 *
			 * @since 1.0
			 */
			private $watermarkSettings;


			/**
			 * Class constructor
			 *
			 * @since 1.0
			 */
			public function __construct()
			{
				// Activation hook
				register_activation_hook(__FILE__, array($this, 'activateWPPCSettings'));

				// Check existing settings
				add_action('init', array($this, 'checkWPPCSettings'));

				// Load settings
				$this->generalSettings = get_option(WPPC_SETTINGS_GENERAL);
				$this->watermarkSettings = get_option(WPPC_SETTINGS_WATERMARK);

				// Register menu item
				add_action('admin_menu', array($this, 'addWPPCSettings'));

				// Register settings
				add_action('admin_init', array($this, 'registerWPPCSettings'));
			}

			/**
			 * Plugin activation
			 *
			 * @since 1.0
			 */
			public function activateWPPCSettings()
			{
				// Create tables
				$this->createTables();

				// Create general settings
				$general = array();
				$general['version'] = WPPC_VERSION;
				$general['deleteTables'] = 0;
				$general['timezone'] = 'UTC';
				$general['notifyAdmins'] = 1;

				// Create watermark settings
				$watermark = array();
				$watermark['watermarkTextSize'] = WPPC_WATERMARK_TEXT_SIZE;
				$watermark['watermarkTextPosition'] = 'bottomRight';
				$watermark['watermarkTextColor'] = 'white';

				// Add general settings
				add_option(WPPC_SETTINGS_GENERAL, $general);

				// Add watermark settings
				add_option(WPPC_SETTINGS_WATERMARK, $watermark);
			}

			/**
			 * Check existing settings
			 *
			 * @since 1.0
			 */
			public function checkWPPCSettings()
			{
				$general = get_option(WPPC_SETTINGS_GENERAL) ? get_option(WPPC_SETTINGS_GENERAL) : array();
				$watermark = get_option(WPPC_SETTINGS_WATERMARK) ? get_option(WPPC_SETTINGS_WATERMARK) : array();

				if ($general['version'] != WPPC_VERSION):
					
					// Create or update tables
					$this->createTables();

					// Update general settings
					$general['version'] = WPPC_VERSION;
					if (!array_key_exists('deleteTables', $general)) $general['deleteTables'] = 0;
					if (!array_key_exists('timezone', $general)) $general['timezone'] = 'UTC';
					if (!array_key_exists('notifyAdmins', $general)) $general['notifyAdmins'] = 1;

					// Update watermark settings
					if (!array_key_exists('watermarkTextSize', $watermark)) $watermark['watermarkTextSize'] = WPPC_WATERMARK_TEXT_SIZE;
					if (!array_key_exists('watermarkTextPosition', $watermark)) $watermark['watermarkTextPosition'] = 'bottomRight';
					if (!array_key_exists('watermarkTextColor', $watermark)) $watermark['watermarkTextColor'] = 'white';

					// Update settings
					if (get_option(WPPC_SETTINGS_GENERAL)) update_option(WPPC_SETTINGS_GENERAL, $general);
						else add_option(WPPC_SETTINGS_GENERAL, $general);
					if (get_option(WPPC_SETTINGS_WATERMARK)) update_option(WPPC_SETTINGS_WATERMARK, $watermark);
						else add_option(WPPC_SETTINGS_WATERMARK, $watermark);
				endif;
			}

			/**
			 * Create plugin database tables
			 *
			 * @since 1.0
			 */
			public function createTables()
			{
				global $wpdb;
				
				/**
				 * We'll set the default character set and collation for this table.
				 * If we don't do this, some characters could end up being converted 
				 * to just ?'s when saved in our table.
				 */
				$charset_collate = '';
				if (!empty($wpdb->charset))
					$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
				if (!empty($wpdb->collate))
					$charset_collate .= " COLLATE {$wpdb->collate}";

				// Load script to be able to use dbDelta
				require_once(ABSPATH.'wp-admin/includes/upgrade.php');

				// Table that contains all contests
				$sql = "CREATE TABLE ".WPPC_TABLE_ALL_CONTESTS." (
					id mediumint(9) NOT NULL AUTO_INCREMENT,
					contest_name tinytext NOT NULL,
					start_date date DEFAULT '0000-00-00' NOT NULL,
					end_registration date DEFAULT '0000-00-00' NOT NULL,
					end_vote date DEFAULT '0000-00-00' NOT NULL,
					end_date date DEFAULT '0000-00-00' NOT NULL,
					photos_allowed int UNSIGNED NOT NULL,
					photos_mobile_allowed int UNSIGNED NOT NULL,
					votes_allowed int UNSIGNED NOT NULL,
					first_point int UNSIGNED NOT NULL,
					second_point int UNSIGNED NOT NULL,
					third_point int UNSIGNED NOT NULL,
					forth_point int UNSIGNED NOT NULL,
					fifth_point int UNSIGNED NOT NULL,
					contest_social_description tinytext NOT NULL,
					contest_about text,
					contest_photo_gallery text,
					contest_winners text,
					contest_prizes text,
					contest_entry_form text,
					contest_rules text,
					contest_contact text,
					contest_sidebar text,
					contest_emails text,
					status tinyint(1) DEFAULT 1
					UNIQUE KEY  (id)
					) ".$charset_collate.";";
				dbDelta($sql);

				// Table that contains all contests entries
				$sql = "CREATE TABLE ".WPPC_TABLE_CONTESTS_ENTRIES." (
					id mediumint(9) NOT NULL AUTO_INCREMENT,
					contest_id mediumint(9) UNSIGNED NOT NULL,
					photo_id bigint UNSIGNED NOT NULL,
					competitor_name tinytext,
					competitor_email tinytext,
					competitor_photo tinytext,
					photo_mobile tinyint(1) DEFAULT 0,
					photo_name tinytext,
					photo_location tinytext,
					votes int DEFAULT 0,
					upload_date timestamp DEFAULT CURRENT_TIMESTAMP,
					visible tinyint(1) DEFAULT 0,
					UNIQUE KEY  (id)
					) ".$charset_collate.";";
				dbDelta($sql);

				// Table that contains votes for contests
				$sql = "CREATE TABLE ".WPPC_TABLE_CONTESTS_VOTES." (
					id mediumint(9) NOT NULL AUTO_INCREMENT,
					contest_id mediumint(9) UNSIGNED NOT NULL,
					photo_id bigint UNSIGNED NOT NULL,
					vote_ip bigint,
					vote_number int UNSIGNED NOT NULL DEFAULT 0,
					vote_time timestamp DEFAULT CURRENT_TIMESTAMP,
					UNIQUE KEY  (id)
					) ".$charset_collate.";";
				dbDelta($sql);

				// create folder for images
				$upload_dir = wp_upload_dir();
				$dir = $upload_dir['basedir'].'/wppc-photos/';
				if (!is_dir($dir))
					wp_mkdir_p($dir);
			}

			/**
			 * Create settings page
			 *
			 * @since 1.0
			 */
			public function addWPPCSettings()
			{
				add_submenu_page('wppc-all-contests', 'WordPress Photo Contests Settings', 'Settings', 'manage_options', 'wppc-settings', array($this, 'displayWPPCSettings'));
			}

			/**
			 * Register plugin settings
			 *
			 * @since 1.0
			 */
			public function registerWPPCSettings()
			{
				// General settings section
			    add_settings_section(
			        WPPC_SETTINGS_GENERAL,         			// ID used to identify this section and with which to register options
			        'General Settings',                  	// Title to be displayed on the administration page
			        array($this, 'setWPPCGeneralSettings'), // Callback used to render the description of the section
			        WPPC_SETTINGS_GENERAL              		// Page on which to add this section of options
			    );

			    // Watermark settings section
				add_settings_section(WPPC_SETTINGS_WATERMARK, 'Watermark Settings', array($this, 'setWPPCWatermarkSettings'), WPPC_SETTINGS_WATERMARK);

				// Set timezone
				add_settings_field('wppc-timezone', 'Contest Timezone', array($this, 'getTimezone'), WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array('select your timezone (default UTC)', $this->generalSettings['timezone']));

				// Email admins when new photo submitted
				add_settings_field('wppc-notify-admins', 'Send Registration Email', array($this, 'getNotifyAdmins'), WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array('check if you want your admins to be emailed when a new photo is registered for a contest', $this->generalSettings['notifyAdmins']));

				// Delete tables on plugin uninstall
				add_settings_field('wppc-delete-tables', 'Delete Database on Uninstall', array($this, 'getDeleteTables'), WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array('check this if you want to erase the tables on uninstall', $this->generalSettings['deleteTables']));

				// Watermark text size
				add_settings_field('wppc-watermark-text-size', 'Text Size', array($this, 'getWatermarkTextSize'), WPPC_SETTINGS_WATERMARK, WPPC_SETTINGS_WATERMARK, array('px', $this->watermarkSettings['watermarkTextSize']));

				// Watermark text position
				add_settings_field('wppc-watermark-text-position', 'Text Position', array($this, 'getWatermarkTextPosition'), WPPC_SETTINGS_WATERMARK, WPPC_SETTINGS_WATERMARK, array('where do you want the watermark to be positioned', $this->watermarkSettings['watermarkTextPosition']));

				// Watermark text color
				add_settings_field('wppc-watermark-text-color', 'Text Color', array($this, 'getWatermarkTextColor'), WPPC_SETTINGS_WATERMARK, WPPC_SETTINGS_WATERMARK, array('the color for the watermark text', $this->watermarkSettings['watermarkTextColor']));

				// Register general settings
				register_setting(WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array($this, 'validateWPPCGeneralSettings'));

				// Register watermark settings
				register_setting(WPPC_SETTINGS_WATERMARK, WPPC_SETTINGS_WATERMARK, array($this, 'validateWPPCWatermarkSettings'));
			}

			/**
			 * Validate general settings
			 *
			 * @since 1.0
			 * 
			 * @param  $input raw input
			 * 
			 * @return sanitized input
			 */
			public function validateWPPCGeneralSettings($input)
			{
				$input['version'] = WPPC_VERSION;
				
				$input['deleteTables'] = $_POST['deleteTables'];
				$input['timezone'] = $_POST['timezone'];
				$input['notifyAdmins'] = $_POST['notifyAdmins'];

				return $input;
			}

			/**
			 * Validate watermark settings
			 *
			 * @since 1.0
			 * 
			 * @param  $input raw input
			 *
			 * @return sanitized input
			 */
			public function validateWPPCWatermarkSettings($input)
			{
				$input['watermarkTextSize'] = absint($_POST['watermarkTextSize']);
				$input['watermarkTextPosition'] = $_POST['watermarkTextPosition'];
				$input['watermarkTextColor'] = $_POST['watermarkTextColor'];

				return $input;
			}


			/**
			 * Render settings page
			 *
			 * @since 1.0
			 */
			public function displayWPPCSettings()
			{
				?>
				<h2>Wordpress Photo Contests Settings</h2>
				<?php settings_errors(); ?>

				<?php
					if (isset($_GET['tab'])) $activeTab = $_GET['tab'];
						else $activeTab = 'general';
				?>

				<h2 class="nav-tab-wrapper">
					<a href="?page=wppc-settings&amp;tab=general" class="nav-tab <?php echo $activeTab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
					<a href="?page=wppc-settings&amp;tab=watermark" class="nav-tab <?php echo $activeTab == 'watermark' ? 'nav-tab-active' : ''; ?>">Watermark</a>
				</h2>

				<form method="post" action="options.php">
					<?php
						
						// General tab
						if ($activeTab == 'general'):
							settings_fields(WPPC_SETTINGS_GENERAL);
							do_settings_sections(WPPC_SETTINGS_GENERAL);
						endif;

						// Watermark tab
						if ($activeTab == 'watermark'):
							settings_fields(WPPC_SETTINGS_WATERMARK);
							do_settings_sections(WPPC_SETTINGS_WATERMARK);
						endif;
					?>
					<?php submit_button(); ?>
				</form>
				<?php
			}

			/**
			 * Render general settings description
			 *
			 * @since 1.0
			 */
			public function setWPPCGeneralSettings()
			{
				echo "<p></p>";
			}

			/**
			 * Render watermark settings description
			 *
			 * @since 1.0
			 */
			public function setWPPCWatermarkSettings()
			{
				echo "<p></p>";
			}

			/**
			 * Set timezone
			 *
			 * @since 1.0
			 */
			public function getTimezone($args)
			{
				include_once(WPPC_DIR.'php/timezones.php');
				$timezones = getAllTimezones();
				?>

				<select name="timezone" id="timezone">
					<?php foreach ($timezones as $key => $timezone): ?>
						<option value="<?php echo $timezone ?>"<?php echo $args[1] === $timezone ? ' selected' : '' ?>><?php echo $key ?></option>
					<?php endforeach; ?>
				</select>
				<?php
			}

			/**
			 * Notify admins when new photo submitted
			 *
			 * @since 1.0
			 */
			public function getNotifyAdmins($args)
			{
				?>
				<input type="checkbox" name="notifyAdmins" id="notifyAdmins" value="1"<?php echo checked(1, $args[1], false) ?> />
				<label for="notifyAdmins"><?php echo $args[0] ?></label>
				<?php
			}

			/**
			 * Delete plugin tables on uninstall
			 * 
			 * @since 1.0
			 */
			public function getDeleteTables($args)
			{
				?>
				<input type="checkbox" name="deleteTables" id="deleteTables" value="1"<?php echo checked(1, $args[1], false) ?> />
				<label for="deleteTables"><?php echo $args[0] ?></label>
				<?php
			}

			/**
			 * Watermark text size
			 *
			 * @since 1.0
			 */
			public function getWatermarkTextSize($args)
			{
				?>
				<input class="small-text" type="number" name="watermarkTextSize" id="watermarkTextSize" value="<?php echo $args[1] ?>" />
				<label><?php echo $args[0] ?></label>
				<?php
			}

			/**
			 * Watermark text position
			 *
			 * @since 1.0
			 */
			public function getWatermarkTextPosition($args)
			{
				?>
				<select name="watermarkTextPosition" id="watermarkTextPosition">
					<option value="topLeft"<?php echo $args[1] === 'topLeft' ? ' selected' : '' ?>>Top Left</option>
					<option value="topCenter"<?php echo $args[1] === 'topCenter' ? ' selected' : '' ?>>Top Center</option>
					<option value="topRight"<?php echo $args[1] === 'topRight' ? ' selected' : '' ?>>Top Right</option>
					<option value="center"<?php echo $args[1] === 'center' ? ' selected' : '' ?>>Center</option>
					<option value="bottomLeft"<?php echo $args[1] === 'bottomLeft' ? ' selected' : '' ?>>Bottom Left</option>
					<option value="bottomCenter"<?php echo $args[1] === 'bottomCenter' ? ' selected' : '' ?>>Bottom Center</option>
					<option value="bottomRight"<?php echo $args[1] === 'bottomRight' ? ' selected' : '' ?>>Bottom Right</option>
				</select>
				<label for="watermarkTextPosition"><?php echo $args[0] ?></label>
				<?php
			}

			/**
			 * Watermark text color
			 *
			 * @since 1.0
			 */
			public function getWatermarkTextColor($args)
			{
				?>
				<input type="color" name="watermarkTextColor" id="watermarkTextColor" value="<?php echo $args[1] ?>" />
				<label for="watermarkTextColor"><?php echo $args[0] ?></label>
				<?php
			}

		}
	endif;

?>