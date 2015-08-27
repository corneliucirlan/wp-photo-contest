<?php

	if (!class_exists('WPPCSettings')):
		class WPPCSettings
		{
			/**
			 * WPPC GENERAL SETTINGS
			 * @var array
			 */
			private $generalSettings;

			/**
			 * WPPC WATERMARK SETTINGS
			 * @var array
			 */
			private $watermarkSettings;

			/**
			 * SETTINGS CONSTRUCTOR
			 */
			public function __construct()
			{
				/**
				 * ACTIVATE WPPC SETTINGS
				 */
				register_activation_hook(__FILE__, array($this, 'activateWPPCSettings'));

				/**
				 * CHECK WPPC SETTINGS
				 */
				add_action('init', array($this, 'checkWPPCSettings'));

				/**
				 * LOAD SETTINGS
				 */
				$this->generalSettings = get_option(WPPC_SETTINGS_GENERAL);
				$this->watermarkSettings = get_option(WPPC_SETTINGS_WATERMARK);

				/**
				 * INSERT "SETTINGS" INTO WPPC MENU
				 */
				add_action('admin_menu', array($this, 'addWPPCSettings'));

				/**
				 * REGISTER WPPC SETTINGS
				 */
				add_action('admin_init', array($this, 'registerWPPCSettings'));
			}

			/**
			 * CALLBACK FUNCTION ON PLUGIN ACTIVATION
			 */
			public function activateWPPCSettings()
			{
				// create tables
				$this->createTables();

				// create general settings
				$general = array();
				$general['version'] = WPPC_VERSION;
				$general['deleteTables'] = 0;
				$general['facebookAppId'] = '';
				$general['loadFacebookJs'] = 0;
				$general['timezone'] = 'UTC';
				$general['notifyAdmins'] = 1;

				// create watermark settings
				$watermark = array();
				$watermark['watermarkTextSize'] = WPPC_WATERMARK_TEXT_SIZE;
				$watermark['watermarkTextPosition'] = 'bottomRight';
				$watermark['watermarkTextColor'] = 'white';

				// add general settings
				add_option(WPPC_SETTINGS_GENERAL, $general);

				// add watermark settings
				add_option(WPPC_SETTINGS_WATERMARK, $watermark);
			}

			/**
			 * CALLBACK FUNCTION TO CHECK VERSION AND UPDATE DATABASE
			 */
			public function checkWPPCSettings()
			{
				$general = get_option(WPPC_SETTINGS_GENERAL) ? get_option(WPPC_SETTINGS_GENERAL) : array();
				$watermark = get_option(WPPC_SETTINGS_WATERMARK) ? get_option(WPPC_SETTINGS_WATERMARK) : array();

				if ($general['version'] != WPPC_VERSION):
					// create or update tables
					$this->createTables();

					// update general settings
					$general['version'] = WPPC_VERSION;
					if (!array_key_exists('deleteTables', $general)) $general['deleteTables'] = 0;
					if (!array_key_exists('facebookAppId', $general)) $general['facebookAppId'] = '';
					if (!array_key_exists('loadFacebookJs', $general)) $general['loadFacebookJs'] = 0;
					if (!array_key_exists('timezone', $general)) $general['timezone'] = 'UTC';
					if (!array_key_exists('notifyAdmins', $general)) $general['notifyAdmins'] = 1;

					// update watermark settings
					if (!array_key_exists('watermarkTextSize', $watermark)) $watermark['watermarkTextSize'] = WPPC_WATERMARK_TEXT_SIZE;
					if (!array_key_exists('watermarkTextPosition', $watermark)) $watermark['watermarkTextPosition'] = 'bottomRight';
					if (!array_key_exists('watermarkTextColor', $watermark)) $watermark['watermarkTextColor'] = 'white';

					// update settings
					if (get_option(WPPC_SETTINGS_GENERAL)) update_option(WPPC_SETTINGS_GENERAL, $general);
						else add_option(WPPC_SETTINGS_GENERAL, $general);
					if (get_option(WPPC_SETTINGS_WATERMARK)) update_option(WPPC_SETTINGS_WATERMARK, $watermark);
						else add_option(WPPC_SETTINGS_WATERMARK, $watermark);
				endif;
			}

			/**
			 * CREATE NECESSARY TABLES
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

				// load script to be able to use dbDelta
				require_once(ABSPATH.'wp-admin/includes/upgrade.php');

				// table that contains all contests
				$tableName = $wpdb->prefix.'wppc_contests_all';
				$sql = "CREATE TABLE ".$tableName." (
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

				// table that contains all contests entries
				$tableName = $wpdb->prefix.'wppc_contests_entries';
				$sql = "CREATE TABLE ".$tableName." (
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

				// table that contains votes for contests
				$tableName = $wpdb->prefix.'wppc_contests_votes';
				$sql = "CREATE TABLE ".$tableName." (
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
			 * CALLBACK FUNCTION TO CREATE WPPC SETTINGS
			 */
			public function addWPPCSettings()
			{
				/**
				 * WP PC Settings
				 */
				add_submenu_page('wppc-all-contests', 'WordPress Photo Contests Settings', 'Settings', 'manage_options', 'wppc-settings', array($this, 'displayWPPCSettings'));
			}

			/**
			 * CALLBACK FUNCTION TO REGISTER WPPC SETTINGS
			 */
			public function registerWPPCSettings()
			{
				/**
				 * REGISTER SETTINGS SECTIONS
				 */
				
				// add general settings section
			    add_settings_section(
			        WPPC_SETTINGS_GENERAL,         // ID used to identify this section and with which to register options
			        'General Settings',                  // Title to be displayed on the administration page
			        array($this, 'setWPPCGeneralSettings'), // Callback used to render the description of the section
			        WPPC_SETTINGS_GENERAL              // Page on which to add this section of options
			    );

			    // add watermark settings section
				add_settings_section(WPPC_SETTINGS_WATERMARK, 'Watermark Settings', array($this, 'setWPPCWatermarkSettings'), WPPC_SETTINGS_WATERMARK);


				/**
				 * REGISTER SETTINGS FIELDS
				 */

				// ask to load facebook js
				add_settings_field('wppc-fb-js', 'Load Facebook JS', array($this, 'loadFacebookJS'), WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array('check only if your there doesn\'t already load FB API', $this->generalSettings['loadFacebookJs']));

				// ask for facebook app id
				add_settings_field('wppc-fb-app-id', 'Facebook App ID', array($this, 'getFacebookAppId'), WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array('', $this->generalSettings['facebookAppId']));

				// set timezone
				add_settings_field('wppc-timezone', 'Contest Timezone', array($this, 'getTimezone'), WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array('select your timezone (default UTC)', $this->generalSettings['timezone']));

				// ask to send email to administrators when a new photo is registered
				add_settings_field('wppc-notify-admins', 'Send Registration Email', array($this, 'getNotifyAdmins'), WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array('check if you want your admins to be emailed when a new photo is registered for a contest', $this->generalSettings['notifyAdmins']));

				// ask to delete tables
				add_settings_field('wppc-delete-tables', 'Delete Database on Uninstall', array($this, 'getDeleteTables'), WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array('check this if you want to erase the tables on uninstall', $this->generalSettings['deleteTables']));

				// ask for watermark text size
				add_settings_field('wppc-watermark-text-size', 'Text Size', array($this, 'getWatermarkTextSize'), WPPC_SETTINGS_WATERMARK, WPPC_SETTINGS_WATERMARK, array('px', $this->watermarkSettings['watermarkTextSize']));

				// ask for watermark position
				add_settings_field('wppc-watermark-text-position', 'Text Position', array($this, 'getWatermarkTextPosition'), WPPC_SETTINGS_WATERMARK, WPPC_SETTINGS_WATERMARK, array('where do you want the watermark to be positioned', $this->watermarkSettings['watermarkTextPosition']));

				// ask for watermark color
				add_settings_field('wppc-watermark-text-color', 'Text Color', array($this, 'getWatermarkTextColor'), WPPC_SETTINGS_WATERMARK, WPPC_SETTINGS_WATERMARK, array('the color for the watermark text', $this->watermarkSettings['watermarkTextColor']));


				/**
				 * REGISTER SETTINGS
				 */
				
				// register general settings
				register_setting(WPPC_SETTINGS_GENERAL, WPPC_SETTINGS_GENERAL, array($this, 'validateWPPCGeneralSettings'));

				// register watermark settings
				register_setting(WPPC_SETTINGS_WATERMARK, WPPC_SETTINGS_WATERMARK, array($this, 'validateWPPCWatermarkSettings'));
			}

			/**
			 * CALLBACK FUNCTION TO VALIDATE GENERAL SETTINGS
			 * @param  $input raw input
			 * @return escaped input
			 */
			public function validateWPPCGeneralSettings($input)
			{
				$input['version'] = WPPC_VERSION;
				
				$input['deleteTables'] = $_POST['deleteTables'];
				$input['facebookAppId'] = $_POST['facebookAppId'];
				$input['loadFacebookJs'] = $_POST['loadFacebookJs'];
				$input['timezone'] = $_POST['timezone'];
				$input['notifyAdmins'] = $_POST['notifyAdmins'];

				return $input;
			}

			/**
			 * CALLBACK FUNCTION TO VALIDATE WATERMARK SETTINGS
			 */
			public function validateWPPCWatermarkSettings($input)
			{
				$input['watermarkTextSize'] = absint($_POST['watermarkTextSize']);
				$input['watermarkTextPosition'] = $_POST['watermarkTextPosition'];
				$input['watermarkTextColor'] = $_POST['watermarkTextColor'];

				return $input;
			}


			/**
			 * CALLBACK FUNCTION TO DISPLAY WPPC SETTINGS
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
						// GENERAL TAB
						if ($activeTab == 'general'):
							settings_fields(WPPC_SETTINGS_GENERAL);
							do_settings_sections(WPPC_SETTINGS_GENERAL);
						endif;

						// WATERMARK TAB
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
			 * CALLBACK FUNCTION TO RENDER THE GENERAL SECTION DESCRIPTION
			 */
			public function setWPPCGeneralSettings()
			{
				echo "<p></p>";
			}

			/**
			 * CALLBACK FUNCTION TO RENDER THE WATERMARK SECTION DESCRIPTION
			 */
			public function setWPPCWatermarkSettings()
			{
				echo "<p></p>";
			}


			/**
			 * CALLBACK FUNCTION ASKING TO LOAD FB JS
			 */
			public function loadFacebookJS($args)
			{
				?>
				<input type="checkbox" name="loadFacebookJs" id="loadFacebookJs" value="1"<?php echo checked(1, $args[1], false) ?> />
				<label for="loadFacebookJs"><?php echo $args[0] ?></label>
				<?php
			}


			/**
			 * CALLBACK FUNCTION TO ASK FOR FACEBOOK APP ID
			 */
			public function getFacebookAppId($args)
			{
				?>
				<input type="text" name="facebookAppId" id="facebookAppId" value="<?php echo $args[1] ?>" />
				<label for="facebookAppId"><?php echo $args[0] ?></label>
				<?php
			}

			/**
			 * CALLBACK FUNCTION TO ASK FOR TIMEZONE
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
			 * CALLBACK FUNCTION TO ASK TO NOTIFY ADMINS
			 */
			public function getNotifyAdmins($args)
			{
				?>
				<input type="checkbox" name="notifyAdmins" id="notifyAdmins" value="1"<?php echo checked(1, $args[1], false) ?> />
				<label for="notifyAdmins"><?php echo $args[0] ?></label>
				<?php
			}

			/**
			 * CALLBACK FUNCTION FOR "DELETE TABLES ON UNINSTALL" FIELD
			 */
			public function getDeleteTables($args)
			{
				?>
				<input type="checkbox" name="deleteTables" id="deleteTables" value="1"<?php echo checked(1, $args[1], false) ?> />
				<label for="deleteTables"><?php echo $args[0] ?></label>
				<?php
			}

			/**
			 * CALLBACK FUNCTION TO ASK FOR WATERMARK TEXT SIZE
			 */
			public function getWatermarkTextSize($args)
			{
				?>
				<input class="small-text" type="number" name="watermarkTextSize" id="watermarkTextSize" value="<?php echo $args[1] ?>" />
				<label><?php echo $args[0] ?></label>
				<?php
			}

			/**
			 * CALLBACK FUNCTION TO ASK FOR WATERMARK TEXT POSITION
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
			 * CALLBACK FUNCTION TO ASK FOR WATERMARK TEXT COLOR
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