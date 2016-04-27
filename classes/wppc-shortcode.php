<?php

	// Security check
	if (!defined('ABSPATH')) die;

	// Define class
	if (!class_exists('WPPCShortcode')):
		class WPPCShortcode
		{
			/**
			 * Submit photo Ajax action
			 * 
			 * @var string
			 *
			 * @since 1.0
			 */
			private $submitFormAction = 'wppc-submit-photo';

			/**
			 * Vote photo Ajax Action
			 * 
			 * @var string
			 *
			 * @since 1.0
			 */
			private $votePhotoAction = 'wppc-vote-photo';

			/**
			 * Filter photos Ajax action
			 * 
			 * @var string
			 *
			 * @since 1.0
			 */
			private $filterPhotos = 'wppc-filter-photos';

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
			 * Number of photos per page
			 * 
			 * @var int
			 *
			 * @since 1.0
			 */
			private $photosPerPage;

			
			/**
			 * Class constructor
			 *
			 * @since 1.0
			 */
			public function __construct()
			{
				global $wpdb;
				
				// Load settings
				$this->generalSettings = get_option(WPPC_SETTINGS_GENERAL);
				$this->watermarkSettings = get_option(WPPC_SETTINGS_WATERMARK);
				//$this->photosPerPage = $this->generalSettings['photosPerPage'];

				// Set timezone
				date_default_timezone_set($this->generalSettings['timezone']);

				// Enqueue CSS and JS scripts
				add_action('wp_enqueue_scripts', array($this, 'loadScripts'));

				// Ajax call for entry form submission
				add_action('wppc_ajax_'.$this->submitFormAction, array($this, 'wppcSubmitPhoto'));
				add_action('wppc_ajax_nopriv_'.$this->submitFormAction, array($this, 'wppcSubmitPhoto'));

				// Ajax call for photo voting
				add_action('wppc_ajax_'.$this->votePhotoAction, array($this, 'wppcVotePhoto'));
				add_action('wppc_ajax_nopriv_'.$this->votePhotoAction, array($this, 'wppcVotePhoto'));

				// Ajax call for photos filtering
				add_action('wppc_ajax_'.$this->filterPhotos, array($this, 'filterPhotos'));
				add_action('wppc_ajax_nopriv_'.$this->filterPhotos, array($this, 'filterPhotos'));

				// Register shortcode
				add_shortcode('wphotocontest', array($this, 'wppcShortcode'));

				// Make all photos the same height
				add_action('wp_footer', function() {
					?>
					<script>
						jQuery(document).ready(function($) {

							// Set the maxim height to all elements
							$(document).imagesLoaded(function() {
								
								// Get all heights
								var heights = $("div.photo").map(function () { return $(this).height(); }).get();

								// Get max height
								maxHeight = Math.max.apply(null, heights);

								// Log max height
								console.log(maxHeight);

								// Set max height to all photos
								$('#wppc-photos > .photo').css({'height': maxHeight});
							});
						});
					</script>
					<?php
				});
			}

			/**
			 * Enqueue CSS and JS scripts
			 *
			 * @since 1.0
			 */
			public function loadScripts()
			{
				// Contest CSS
				wp_register_style('wppc-shortcode-css', WPPC_URI.'css/wppc-shortcode.css', '', WPPC_VERSION);
				wp_enqueue_style('wppc-shortcode-css');
				wp_register_style('wppc-colorbox-css', WPPC_URI.'css/colorbox.css', '', '1.5.13');
				wp_enqueue_style('wppc-colorbox-css');

				// jQuery
				wp_enqueue_script('jquery', '', '', '', true);

				// jQuery UI core
				wp_enqueue_script('jquery-ui-core', array('jquery'), '', '', true);

				// jQuery UI Tabs
				wp_enqueue_script('jquery-ui-tabs', array('jquery-ui-core'), '', '', true);

				// Font Awesome
				wp_register_style('font-awesome', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css', '', '', 'all');
				wp_enqueue_style('font-awesome');

				// Contest JS
				wp_register_script('wppc-shortcode', WPPC_URI.'js/wppc-shortcode.js', array('jquery'), WPPC_VERSION, true);
				wp_enqueue_script('wppc-shortcode');

				// Colorbox JS
				wp_register_script('wppc-colorbox', WPPC_URI.'js/jquery.colorbox-min.js', array('jquery'), '1.5.13', true);
				wp_enqueue_script('wppc-colorbox');

				// Ajax calls
				wp_register_script('wppc-ajax-frontend', plugins_url('js/wppc-ajax-frontend.min.js', WPPC_FILE), array('jquery'), WPPC_VERSION, true);
				wp_enqueue_script('wppc-ajax-frontend');

				// Localize Ajax handler - TO BE DELETED LATER
				wp_localize_script('wppc-ajax-frontend', 'wppcSubmitPhoto', array(
					'ajaxurl' => WPPC_URI.'ajax/ajax-handler.php',
					'action' => $this->submitFormAction,
				));

				// ImagesLoaded JS script
				wp_enqueue_script('images-loaded', WPPC_URI.'js/imagesloaded.pkgd.min.js', array('jquery'), IMAGES_LOADED_VERSION, true);
			}

			/**
			 * Submit photo Ajax callback
			 *
			 * @since 1.0
			 */
			public function wppcSubmitPhoto()
			{
				//check_ajax_referer('wppc-ajax-submit', 'security');
				
				// Get contest ID
				$id = isset($_POST['wppc-id']) ? $_POST['wppc-id'] : 1;

				// Get name
				$name = isset($_POST['wppc-name']) ? esc_attr($_POST['wppc-name']) : "";

				// Get email
				$email = filter_var($_POST['wppc-email'], FILTER_VALIDATE_EMAIL) ? esc_attr($_POST['wppc-email']) : "";

				// Get photo file
				$photo = isset($_FILES['file-0']) && ($_FILES['file-0']['type'] == "image/png" || $_FILES['file-0']['type'] == "image/jpeg") ? $_FILES['file-0'] : "";

				// Get mobile photo
				$mobile = isset($_POST['wppc-mobile-photo']) && $_POST['wppc-mobile-photo'] == "true" ? 1 : "0";

				// Get photo name
				$photoName = isset($_POST['wppc-photo-name']) ? esc_attr($_POST['wppc-photo-name']) : "";

				// Get photo location
				$photoLocation = isset($_POST['wppc-photo-location']) ? esc_attr($_POST['wppc-photo-location']) : "";

				// Get rules agreement
				$agreeRules = isset($_POST['wppc-agree-rules']) && $_POST['wppc-agree-rules'] == "true" ? 1 : '';

				// Generate random number
				$randomNumber = absint(time() * $this->random_0_1());				
				
				// Prepare Ajax response
				$jsonResponse = array(
					'cid' => $id,
					'name' => $name,
					'email' => $email,
					'photo' => $photo,
					'mobilePhoto' => $mobile,
					'photoName' => $photoName,
					'photoLocation' => $photoLocation,
					'agreeRules' => $agreeRules,
					'entryAdded' => false,
					);

				// All info is correct
				if ($name != '' && $email != '' && $photo != '' && $photoName != '' && $photoLocation != '' && $agreeRules != ''):
					global $wpdb;

					// Get number of photos allowed in the contest
					$contestInfo = $wpdb->get_row("SELECT photos_allowed, photos_mobile_allowed FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$id);

					// Get number of camera photos added by this competitor
					$wpdb->get_results("SELECT id FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE competitor_name='".$name."' AND competitor_email='".$email."' AND photo_mobile=0 AND contest_id=".$id);
					$totalPhotosAdded = $wpdb->num_rows;

					// Check if the number of camera photos reached
					if ($totalPhotosAdded >= $contestInfo->photos_allowed && $mobile != 1):
						$jsonResponse['entryAdded'] = 'TOTAL';
					endif;

					// Get number of mobile photos added by this competitor
					$wpdb->get_results("SELECT id FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE competitor_name='".$name."' AND competitor_email='".$email."' AND photo_mobile=1 AND contest_id=".$id);
					$totalMobilePhotosAdded = $wpdb->num_rows;

					// Check if the number of mobile photos reached
					if ($totalMobilePhotosAdded >= $contestInfo->photos_mobile_allowed && $mobile == 1)
						$jsonResponse['entryAdded'] = "MOBILE";

					// Create all photo files and add to database
					if ($jsonResponse['entryAdded'] == false):

						// Get WP default upload folder
						$wpDir = wp_upload_dir();

						// Set contest folder
						$contestDir = $wpDir['basedir'].'/wppc-photos/wppc-photos-'.$id.'/';

						// Save the raw file from the user
						$filename = $randomNumber.'-'.strtolower(sanitize_file_name($photo['name']));
						if (move_uploaded_file($photo['tmp_name'], $contestDir.'raw/'.$filename)):
								$jsonResponse['photo'] = $photo['name'];

								// Load the file to create necessary copies
								$image = wp_get_image_editor($contestDir.'raw/'.$filename);

								// Create the thumb photo
								$image->resize(200, 200, true); // true - creates a square photo
								$image->save($contestDir.'thumbs/'.$filename);

								// Create the medium photo
								$image = wp_get_image_editor($contestDir.'raw/'.$filename);
								$image->resize(1000, 1000, false);
								$image->save($contestDir.'medium/'.$filename);
								$this->watermarkImage($contestDir.'medium/'.$filename, $contestDir.'medium/'.$filename, '© '.$name, $this->hex2rgb($this->watermarkSettings['watermarkTextColor']), $this->watermarkSettings['watermarkTextPosition']);

								// Create the full photo
								$image = wp_get_image_editor($contestDir.'raw/'.$filename, $this->hex2rgb($this->watermarkSettings['watermarkTextColor']));
								$image->save($contestDir.'full/'.$filename);
								//$this->watermarkImage($contestDir.'raw/'.$filename, $contestDir.'full/'.$filename, '© '.$name);
								
								// Insert entry into the database
								$wpdb->insert(WPPC_TABLE_CONTESTS_ENTRIES,
									array(
										'contest_id' => $id,
										'photo_id' => $randomNumber,
										'competitor_name' => $name,
										'competitor_email' => $email,
										'competitor_photo' => $filename,
										'photo_mobile' => $mobile == 1 ? 1 : 0,
										'photo_name' => $photoName,
										'photo_location' => $photoLocation,
										//'upload_date' => date('Y-m-d h:m:s', mktime()),
									)
								);

								// Notify admins via email
								if ($this->generalSettings['notifyAdmins'] == 1):
									$contest = $wpdb->get_row("SELECT contest_name FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$id);

									// get admins
									$admins = get_users(array('role' => 'administrator', 'fields' => array('user_email')));

									// create email recipients
									$recipients = array();
									foreach ($admins as $admin)
										$recipients[] = $admin->user_email;

									// set email subject
									$subject = "New Photo Submitted";

									// set email message
									$message = "A new photo has been submitted in the <strong>".$contest->contest_name."</strong> contest.<br/><br/> <a href=".admin_url('admin.php?page=wppc-contest&contest='.$id.'&activity=view').">Click here to view it</a>";

									// add headers
									//$headers = array('From: '.$fullNameAsk.' <'.$emailAddressAsk.'>');

									// send email
									$emailResult = wp_mail($recipients, $subject, $message/*, $headers*/);
								endif;

								$jsonResponse['entryAdded'] = true;
							
							// File upload failed
							else:
								$jsonResponse['entryAdded'] = "FILEFAIL";
						endif;
					endif;
				endif;

				// Send Ajax response and terminate
				die(json_encode($jsonResponse));
			}

			/**
			 * Vote photo Ajax callback
			 *
			 * @since 1.0
			 */
			public function wppcVotePhoto()
			{
				global $wpdb;

				// Get contest ID
				$id = isset($_POST['wppc-id']) ? $_POST['wppc-id'] : '';

				// Get photo ID
				$photoID = isset($_POST['wppc-value']) ? $_POST['wppc-value'] : '';

				// Get the max number of votes allowed
				$votesAllowed = $wpdb->get_var("SELECT votes_allowed FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$id);

				// Get user's IP address
				$userIP = $this->getUserIP();

				// Get how many times the user already voted
				$userVoted = $wpdb->get_var("SELECT vote_number FROM ".WPPC_TABLE_CONTESTS_VOTES." WHERE vote_ip='".ip2long($userIP)."' AND contest_id='".$id."' AND photo_id='".$photoID."'");

				// Get contest points
				$points = array();
				$points[0] = $_POST['wppc-first-point'];
				$points[1] = $_POST['wppc-second-point'];
				$points[2] = $_POST['wppc-third-point'];
				$points[3] = $_POST['wppc-forth-point'];
				$points[4] = $_POST['wppc-fifth-point'];

				// Prepare Ajax response
				$jsonResponse = array(
					'wppc-value' => $_POST['wppc-value'],
					'votesAllowed' => $votesAllowed,
					'userIP' => ip2long($userIP),
					'userVoted' => $userVoted,
					'photoVotes' => $_POST['wppc-votes'],
					'photoPoints' => $this->getPoints($_POST['wppc-votes'], $points[0], $points[1], $points[2], $points[3], $points[4]),
				);

				// Check if user already voted the photo
				if ($userVoted):

						// User already voted, check if he can vote again
						if ($userVoted < $votesAllowed):
								$currentTime = date('Y-m-d h:m:s', mktime());
								$voteTime = $wpdb->get_var("SELECT vote_time FROM ".WPPC_TABLE_CONTESTS_VOTES." WHERE vote_ip='".ip2long($userIP)."' AND contest_id='".$id."' AND photo_id='".$photoID."'");

								// Number of days from last vote
								$days = (strtotime($currentTime)-strtotime($voteTime))/(60*60*24);
								$jsonResponse['days'] = $days;

								// User can still vote
								if ($days >= 1.0):

										// Update votes number
										$wpdb->update(WPPC_TABLE_CONTESTS_VOTES,
											array('vote_number' => $userVoted + 1, 'vote_time' => $currentTime),
											array('vote_ip' => ip2Long($userIP), 'photo_id' => $photoID)
										);

										// Add vote to photo
										$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES,
											array('votes' => $_POST['wppc-votes'] + 1),
											array('photo_id' => $photoID, 'contest_id' => $id)
										);

										// Update Ajax response
										$jsonResponse['voteAdded'] = true;
										$jsonResponse['photoVotes'] = $_POST['wppc-votes'] + 1;
										$jsonResponse['photoPoints'] = $this->getPoints($jsonResponse['photoVotes'], $points[0], $points[1], $points[2], $points[3], $points[4]);
									else:

										// User has to wait a full 24 hours to vote again
										$jsonResponse['voteAdded'] = '24H';
								endif;
							
							// User already voted the allowed number of times
							else:
								$jsonResponse['voteAdded'] = false;
						endif;

					// User never voted the photo
					else:

						// Add user to votes
						$wpdb->insert(WPPC_TABLE_CONTESTS_VOTES,
							array(
								'contest_id' => $id,
								'photo_id' => $photoID,
								'vote_ip' => ip2long($userIP),
								'vote_number' => 1
							)
						);

						// Add vote to photo
						$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES,
							array('votes' => $_POST['wppc-votes'] + 1),
							array('photo_id' => $photoID, 'contest_id' => $id)
						);

						// Update Ajax response
						$jsonResponse['voteAdded'] = true;
						$jsonResponse['photoVotes'] = $_POST['wppc-votes'] + 1;
						$jsonResponse['photoPoints'] = $this->getPoints($jsonResponse['photoVotes'], $points[0], $points[1], $points[2], $points[3], $points[4]);
				endif;

				// Send Ajax response and terminate
				die(json_encode($jsonResponse));
			}

			/**
			 * Filter photos Ajax callback
			 *
			 * @since 1.0
			 */
			public function filterPhotos()
			{
				global $wpdb;

				// Get contest ID
				$id = isset($_POST['wppc-id']) ? $_POST['wppc-id'] : '';

				// Get filter
				$filter = isset($_POST['wppc-filter']) ? $_POST['wppc-filter'] : '';

				$wpDir = wp_upload_dir();
				$contestDir = $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$id.'/';
				$contest = $wpdb->get_row("SELECT contest_name, first_point, second_point, third_point, forth_point, fifth_point, start_date, end_registration, end_vote, contest_social_description FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$id);
				$contestPoints = array($contest->first_point, $contest->second_point, $contest->third_point, $contest->forth_point, $contest->fifth_point);
				$weeks = absint(((strtotime($contest->end_registration) - strtotime($contest->start_date)) / (60*60*24)) / 7);

				// Prepare Ajax response
				$ajaxResponse = '<div id="vote-results-680263891" class="alert alert-warning" style=""><p>No photos match the criteria</p></div>';
				
				// Get filter type
				$type = explode('-', $filter);
				
				switch ($type[0]):

					// Filter by week
					case 'week':
						if ($filter == 'week-all'):
								$contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND visible=1");
							else:
								$startDate = strtotime('+'.absint(($type[1]-1)*7)." day", strtotime($contest->start_date));
								$endDate = strtotime('+'.absint($type[1]*7-1)." day", strtotime($contest->start_date));
								$contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND (upload_date>='".date('Y-m-d, 0:00:00', $startDate)."' AND upload_date<='".date('Y-m-d, 0:00:00', $endDate)."') AND visible=1");
						endif;
						break;

					// Filter by point
					case 'point':
						if ($filter == 'point-all'):
								$contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND visible=1");
							else:
								switch ($type[1]):
									case 0: $contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND votes<".$contest->first_point." AND visible=1"); break;
									case 1: $contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND votes>=".$contest->first_point." AND votes<".$contest->second_point." AND visible=1"); break;
									case 2: $contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND votes>=".$contest->second_point." AND votes<".$contest->third_point." AND visible=1"); break;
									case 3: $contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND votes>=".$contest->third_point." AND votes<".$contest->forth_point." AND visible=1"); break;
									case 4: $contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND votes>=".$contest->forth_point." AND votes<".$contest->fifth_point." AND visible=1"); break;
									case 5: $contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND votes>=".$contest->fifth_point." AND visible=1"); break;
								endswitch;
						endif;
						break;

					// Filter mobile
					case 'mobile':
						if ($type[1] == 'only') $contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND photo_mobile=1 AND visible=1");
							else $contestPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$id." AND visible=1");
						break;
				endswitch;

				// No photos match the criteria
				if (!empty($contestPhotos))
					$ajaxResponse = $this->displayContestPhotos($contestPhotos, $contest->contest_name, $contest->end_vote, $contestDir, $contestPoints, $_POST['wppc-url'], $contest->contest_social_description, $this->photosPerPage);

				// Send Ajax response and terminate
				die($ajaxResponse);
			}


			/**
			 * Shortcode call to render contest
			 *
			 * @since 1.0
			 */
			public function wppcShortcode($atts)
			{
				// Shortcode default atributes
				$atts = shortcode_atts(array('id' => 1), $atts);

				global $wpdb;
				$contest = $wpdb->get_row('SELECT * FROM '.WPPC_TABLE_ALL_CONTESTS.' WHERE id='.$atts['id']);
				$contestWinners = unserialize($contest->contest_winners);
				?>

				<div id="wppc-contest">
					<div id="wppc-main">
						<div id="contest-tabs" style="display: inline;">
							<ul id="contest-nav" style="margin-bottom: 20px; margin-top: 20px;">
								<?php if ($contest->contest_about != ''): ?><li style="display: inline; margin-right: 2rem;" <?php if ($contest->start_date > date('Y-m-d h:m:s', time())): ?>class="ui-state-default ui-corner-top ui-tabs-active ui-state-active"<?php endif; ?>><a href="#contest-about">About</a></li><?php endif; ?>
								<?php if ($contest->start_date <= date('Y-m-d h:m:s', time())): ?><li style="display: inline; margin-right: 2rem;" class="ui-state-default ui-corner-top ui-tabs-active ui-state-active"><a href="#contest-photo-gallery">Photo Gallery</a></li><?php endif; ?>
								<?php if ($contestWinners['text'] != ''): ?><li style="display: inline; margin-right: 2rem;"><a href="#contest-winners">Winners</a></li><?php endif; ?>
								<?php if ($contest->contest_rules != ''): ?><li style="display: inline; margin-right: 2rem;"><a href="#contest-rules">Rules</a></li><?php endif; ?>
								<?php if ($contest->contest_prizes != ''): ?><li style="display: inline; margin-right: 2rem;"><a href="#contest-prizes">Prizes</a></li><?php endif; ?>
								<?php if ($contest->start_date <= date('Y-m-d h:m:s', time()) && date('Y-m-d h:m:s', time()) <= date('Y-m-d', strtotime('+1 day' , strtotime($contest->end_registration)))): ?><li style="display: inline; margin-right: 2rem;"><a href="#contest-entry-form">Entry form</a></li><?php endif; ?>
								<?php if ($contest->contest_contact != ''): ?><li style="display: inline; margin-right: 2rem;"><a href="#contest-contact">Contact</a></li><?php endif; ?>
							</ul>
							
							
							<!-- Contest about -->
							<?php if ($contest->contest_about != ''): ?>
								<div id="contest-about">';
									<?php echo $this->formatContent($contest->contest_about); ?>
								</div>
							<?php endif; ?>

							<!-- Contest photo gallery -->
							<?php if ($contest->start_date <= date('Y-m-d h:m:s', time())): ?>
								<div id="contest-photo-gallery">
									<?php echo $contest->contest_photo_gallery; ?>
									<?php
										$totalDays = (strtotime($contest->end_registration) - strtotime($contest->start_date)) / (60*60*24);
										$weeks = $totalDays % 7 != 0 ? $totalDays / 7 + 1 : $totalDays / 7;
									?>

									<!-- Contest filters -->
									<div id="wppc-filters" class="row" style="margin-top:20px; margin-bottom: 20px;">
										<div class="col-md-3">
											<input type="hidden" value="<?php echo $contest->id ?>" />
											<select name="wppc-weeks" id="wppc-weeks" class="form-control wppc-select-filter">
												<option value="week-all">All weeks</option>
												<?php
												for ($week = 1; $week <= $weeks; $week ++)
													echo '<option value="week-'.$week.'">Week '.$week.'</option>';
												?>
											</select>
										</div>
										<div class="col-md-3 col-md-offset-1">
											<input type="hidden" value="<?php echo $contest->id ?>" />
											<select name="wppc-points" id="wppc-points" class="form-control wppc-select-filter">
												<option value="point-all">All points</option>
												<option value="point-0">0 points</option>
												<option value="point-1">1 point</option>
												<option value="point-2">2 points</option>
												<option value="point-3">3 points</option>
												<option value="point-4">4 points</option>
												<option value="point-5">5 points</option>
											</select>
										</div>
										<?php if ($contest->photos_mobile_allowed > 0): ?>
											<div class="col-md-3 col-md-offset-1">
												<input type="hidden" value="<?php echo $contest->id ?>" />
												<button type="button" value="mobile-only" class="btn btn-primary" id="wppc-button-filter">Mobile only</button>
											</div>
										<?php endif; ?>
									</div>

									<!-- Contest photos -->
									<div id="wppc-photos">
										<?php
											$wpDir = wp_upload_dir();
											$contestDir = $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contest->id.'/';

											// Get all photos for this contest
											$contestPhotos = $wpdb->get_results('SELECT * FROM '.WPPC_TABLE_CONTESTS_ENTRIES.' WHERE contest_id='.$contest->id.' AND visible=1');

											// Shuffle photos
											shuffle($contestPhotos);

											// Get points
											$contestPoints = array($contest->first_point, $contest->second_point, $contest->third_point, $contest->forth_point, $contest->fifth_point);

											$this->displayContestPhotos($contestPhotos, $contest->contest_name, $contest->end_vote, $contestDir, $contestPoints, get_permalink(get_the_id()), $contest->contest_social_description, $this->photosPerPage);
										?>
									</div>
								</div>
							<?php endif; ?> 

							<!-- Contest winners -->
							<?php if ($contestWinners['text'] != ''): ?>
								<div id="contest-winners">
									<?php echo $contestWinners['text'] ?>
									<div class="row">
										<h2>First prize</h2>
										<?php $firstWinner = $wpdb->get_row("SELECT * FROM WPPC_TABLE_CONTESTS_ENTRIES WHERE contest_id='".$atts['id']."' AND photo_id=".$contestWinners['first-winner'], ARRAY_A); ?>
										<a class="group1" href="<?php echo $contestDir ?>medium/<?php echo $firstWinner['competitor_photo'] ?>" title="">
											<img src="<?php echo $contestDir ?>medium/<?php echo $firstWinner['competitor_photo'] ?>" alt="<?php echo $firstWinner['photo_name'] ?>" />
										</a>
									</div>

									<div class="row">
										<div class="no-padding-left col-md-6">
											<h2>Second prize</h2>
											<?php $secondWinner = $wpdb->get_row("SELECT * FROM WPPC_TABLE_CONTESTS_ENTRIES WHERE contest_id='".$atts['id']."' AND photo_id=".$contestWinners['second-winner'], ARRAY_A); ?>
											<a class="group1" href="<?php echo $contestDir ?>medium/<?php echo $secondWinner['competitor_photo'] ?>" title="" style="background-image: url('<?php echo $contestDir ?>medium/<?php echo $secondWinner['competitor_photo'] ?>'); position: relative; float: left; width: 100%; height: 300px; background-position: 50% 50%; background-repeat: no-repeat; background-size: cover;">
												<!-- <img src="<?php echo $contestDir ?>medium/<?php echo $secondWinner['competitor_photo'] ?>" alt="<?php echo $secondWinner['photo_name'] ?>" /> -->
											</a>
										</div>
										<div class="no-padding-right col-md-6">
											<h2>Third prize</h2>
											<?php $thirdWinner = $wpdb->get_row("SELECT * FROM WPPC_TABLE_CONTESTS_ENTRIES WHERE contest_id='".$atts['id']."' AND photo_id=".$contestWinners['third-winner'], ARRAY_A); ?>
											<a class="group1" href="<?php echo $contestDir ?>medium/<?php echo $thirdWinner['competitor_photo'] ?>" title="" style="background-image: url('<?php echo $contestDir ?>medium/<?php echo $thirdWinner['competitor_photo'] ?>'); position: relative; float: left; width: 100%; height: 300px; background-position: 50% 50%; background-repeat: no-repeat; background-size: cover;">
												<!-- <img src="<?php echo $contestDir ?>medium/<?php echo $thirdWinner['competitor_photo'] ?>" alt="<?php echo $thirdWinner['photo_name'] ?>" /> -->
											</a>
										</div>
									</div>

									<div class="row">
										<h2>Our favorites</h2>
										<?php foreach ($contestWinners['our-favorites'] as $photoID): ?>
											<?php $photo = $wpdb->get_row("SELECT * FROM WPPC_TABLE_CONTESTS_ENTRIES WHERE contest_id='".$atts['id']."' AND photo_id=".$photoID, ARRAY_A); ?>
											<div class="col-md-3">
												<a class="group1" href="<?php echo $contestDir ?>medium/<?php echo $photo['competitor_photo'] ?>" title="">
													<img src="<?php echo $contestDir ?>thumbs/<?php echo $photo['competitor_photo'] ?>" alt="<?php echo $photo['photo_name'] ?>" />
												</a>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<!-- Contest rules -->
							<?php if ($contest->contest_rules != ''): ?>
								<div id="contest-rules">
									<div id="rules-tabs">
										<ul>
											<li style="display: inline; margin-right: 2rem;"><a href="#en-rules"><img style="max-width: 30px;" src="<?php echo WPPC_URI.'img/flags/uk-flag.png' ?>" /></a></li>
											<li style="display: inline; margin-right: 2rem;"><a href="#ro-rules"><img style="max-width: 30px;" src="<?php echo WPPC_URI.'img/flags/ro-flag.png' ?>" /></a></li>
										</ul>
										<?php $rules = unserialize($contest->contest_rules); ?>
										<div id="en-rules"><?php echo $rules['en'] ?></div>
										<div id="ro-rules"><?php echo $rules['ro'] ?></div>
									</div>
								</div>
							<?php endif; ?>

							<!-- Contest prizes -->
							<?php if ($contest->contest_prizes != ''): ?>
								<div id="contest-prizes">
									<?php echo $this->formatContent($contest->contest_prizes); ?>
								</div>
							<?php endif; ?>

							<!-- Contest entry form -->
							<?php if ($contest->start_date <= date('Y-m-d h:m:s', time()) && date('Y-m-d h:m:s', time()) <= date('Y-m-d', strtotime('+1 day' , strtotime($contest->end_registration)))): ?>
								<div id="contest-entry-form">
									<?php $this->setEntryForm($contest->id, $contest->contest_entry_form); ?>
								</div>
							<?php endif; ?>

							<!-- Contest contact -->
							<?php if ($contest->contest_contact != ''): ?>
								<div id="contest-contact">
									<?php echo $contest->contest_contact; ?>
								</div>
							<?php endif; ?>
						</div> <!-- end contest-tabs -->
					</div> <!-- end wppc-main -->

					<div id="wppc-sidebar">
						<?php /* Follow us */ setFollowUs(); ?>
						<br/><br/>
						<?php echo $contest->contest_sidebar ?>
					</div>
				</div>
				<?php
			}

			/**
			 * Render photo gallery tab
			 *
			 * @since 1.0
			 * 
			 * @param int    $id     id of the contest
			 * @param string $name   name of the contest
			 * @param array  $points contest points
			 */
			public function displayContestPhotos($contestPhotos, $name, $endVoteDate, $contestDir, $contestPoints, $contestURL, $socialDescription, $numberOfPhotos)
			{	
				$numberOfPages = 0;
				if ($numberOfPhotos > 0):
					$numberOfPages = absint(count($contestPhotos) / $numberOfPhotos);
					if (count($contestPhotos) % $numberOfPhotos > 0) $numberOfPages++;
				endif;
				
				if ($numberOfPages > 1):
					?>
					<div id="wppc-contest-pages">
						<div class="pagination-div">
							<ul class="pagination">
								<?php for ($page=1; $page<=$numberOfPages; $page++): ?>
									<li><a href="#wppc-contest-page-<?php echo $page ?>"><?php echo $page ?></a></li>
								<?php endfor; ?>
							</ul>
					</div>
					<?php
				endif;

				// Shuffle photos
				shuffle($contestPhotos);

				$page = 0;
				$photoNumber = 0;

				// Render photos
				foreach ($contestPhotos as $contestPhoto):
					$photoNumber++;
					$mediumPhoto = $contestDir.'medium/'.$contestPhoto->competitor_photo;
					$titlePhoto = $contestPhoto->photo_name.' at '.$contestPhoto->photo_location.' by '.ucwords(strtolower($contestPhoto->competitor_name));
					$caption = $socialDescription !== '' ? $socialDescription : $titlePhoto;

					if ($numberOfPages > 1 && $photoNumber % $numberOfPhotos == 1)
						echo '<div id="wppc-contest-page-'.++$page.'">';
					?>
					<div class="photo">
						<a class="group1" href="<?php echo $mediumPhoto ?>" title="<?php echo $titlePhoto ?>">
							<img src="<?php echo $contestDir ?>thumbs/<?php echo $contestPhoto->competitor_photo ?>" alt="<?php echo $contestPhoto->photo_name ?>" />
						</a>
						<p class="help-block" style="text-align: left;"><?php echo $titlePhoto ?></p>

						<ul class="wppc-share-buttons">
							<li class="wppc-share-button"><a target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $contestURL ?>" title="Share on Facebook"><i class="fa fa-facebook"></i></a></li>
				            <li class="wppc-share-button"><a target="_blank" href="https://plus.google.com/share?url=<?php echo $contestURL ?>" title="Share on Google+"><i class="fa fa-google-plus"></i></a></li>
				            <li class="wppc-share-button"><a target="_blank" href="https://pinterest.com/pin/create/link/?url=<?php echo $contestURL ?>&amp;media=<?php echo $mediumPhoto ?>&amp;description=<?php echo $caption ?>" title="Share on Twitter"><i class="fa fa-pinterest"></i></a></li>
						</ul>

						<p class="help-block" style="text-align: left;">
							<?php
								$point = $this->getPoints($contestPhoto->votes, $contestPoints[0], $contestPoints[1], $contestPoints[2], $contestPoints[3], $contestPoints[4]);
								echo $point;
								echo $point == 1 ? " point " : " points ";
								echo 'with '.$contestPhoto->votes.' votes so far';
							?>
						</p>

						<div id="vote-results-<?php echo $contestPhoto->photo_id ?>" class="alert" style="display: none;"></div>
						<form method="post" class="wppc-vote-photo" action="">
							<input type="hidden" name="action" value="wppc-vote-photo" />
							<input type="hidden" name="wppc-value" value="<?php echo $contestPhoto->photo_id ?>" />
							<input type="hidden" name="wppc-votes" value="<?php echo $contestPhoto->votes ?>" />
							<?php if (date('Y-m-d', strtotime($endVoteDate.'+1 days')) > date('Y-m-d', time())): ?><input type="submit" class="btn btn-default" value="Vote" /><?php endif; ?>
							<input type="hidden" name="wppc-id" value="<?php echo $contestPhoto->contest_id ?>" />
							<input type="hidden" name="wppc-first-point" value="<?php echo $contestPoints[0] ?>" />
							<input type="hidden" name="wppc-second-point" value="<?php echo $contestPoints[1] ?>" />
							<input type="hidden" name="wppc-third-point" value="<?php echo $contestPoints[2] ?>" />
							<input type="hidden" name="wppc-forth-point" value="<?php echo $contestPoints[3] ?>" />
							<input type="hidden" name="wppc-fifth-point" value="<?php echo $contestPoints[4] ?>" />
						</form>
					</div>
					<?php
					if ($numberOfPages > 1 && $photoNumber % $numberOfPhotos == 0)
						echo '</div>';
				endforeach;

				if ($numberOfPages > 1):
					?></div></div><?php
				endif;
			}

			/**
			 * Render contest entry form
			 *
			 * @since 1.0
			 * 
			 * @param int $id of the contest
			 * @param string $text text displayed before the form
			 */
			private function setEntryForm($id, $text)
			{
				global $wpdb;

				// Get number of mobile photos
				$mobilePhotos = $wpdb->get_var("SELECT photos_mobile_allowed FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$id);

				echo $text;
				?>
				<div id="wppc-results"></div>
				<form class="form-horizontal" role="form" method="post" action="" id="wppc-form" enctype="multipart/form-data" style="margin-top: 30px;">
					<input type="hidden" name="wppc-id" id="wppc-id" value="<?php echo $id ?>" />
					
					<!-- Full name -->
					<div class="form-group has-feedback">
						<label for="wppc-name" class="col-sm-2 control-label">Full name</label>
						<div class="col-sm-4">
							<input type="text" class="form-control" name="wppc-name" id="wppc-name" placeholder="Full name" />
						</div>
					</div>

					<!-- Email address -->
					<div class="form-group has-feedback">
						<label for="" class="col-sm-2 control-label">Email</label>
						<div class="col-sm-4">
							<input type="email" class="form-control" name="wppc-email" id="wppc-email" placeholder="Email" />
						</div>
					</div>

					<!-- Photo -->
					<div class="form-group has-feedback">
						<label for="" class="col-sm-2 control-label">Photo</label>
						<div class="col-sm-4">
							<input type="file" class="form-control" name="wppc-photo" id="wppc-photo" accept="image/x-png, image/jpeg" />
						</div>
					</div>

					<!-- Mobile photo -->
					<?php if ($mobilePhotos > 0): ?>
						<div class="form-group has-feedback">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox col-sm-4">
									<label>
										<input type="checkbox" name="wppc-mobile-photo" id="wppc-mobile-photo" /> Mobile photo
									</label>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<!-- Photo name and location -->
					<div class="form-group has-feedback">
						<label for="" class="col-sm-2 control-label"></label>
						<div class="col-sm-2">
							<input type="text" class="form-control" name="wppc-photo-name" id="wppc-photo-name" placeholder="Photo Name" />
						</div>
						<div class="col-sm-2">
							<input type="text" class="form-control" name="wppc-photo-location" id="wppc-photo-location" placeholder="Photo Location" />
						</div>
					</div>

					<!-- Rules agreement -->
					<div class="form-group has-feedback">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox col-sm-4">
								<label>
									<input type="checkbox" name="wppc-agree-rules" id="wppc-agree-rules" /> I agree with the rules
								</label>
							</div>
						</div>
					</div>

					<!-- Subscribe to newsletter -->
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
								<label>
									<input type="checkbox" disabled checked /> Subscribe to newsletter
								</label>
							</div>
						</div>
					</div>

					<!-- Submit form -->
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<?php wp_nonce_field('wppc-ajax-submit', 'security'); ?>
							<input type="submit" name="wppc-submit" id="wppc-submit" value="Enter contest" class="btn btn-default">
							<span  id="wppc-loading" style="display: none;"><img src="<?php echo WPPC_URI ?>img/ajax-loading.gif" style="width: 20px !important;"></span>
						</div>
					</div> 
				</form>
				<?php
			}

			/**
			 * Generate random float number between 0 and 1
			 * 
			 * @since 1.0
			 * 
			 * @return float between 0 and 1
			 */
			private function random_0_1()
			{   
				return (float)rand()/(float)getrandmax();
			}

			/**
			 * Get the user's IP address
			 *
			 * @since 1.0
			 * 
			 * @return ip address
			 */
			private function getUserIP()
			{
				if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
						if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') > 0):
								$addr = explode(",",$_SERVER['HTTP_X_FORWARDED_FOR']);
								return trim($addr[0]);
							else:
								return $_SERVER['HTTP_X_FORWARDED_FOR'];
						endif;
					else
						return $_SERVER['REMOTE_ADDR'];
				}

			/**
			 * The number of points a photo has
			 *
			 * @since 1.0
			 * 
			 * @param  int  $votes the number of votes the photo has
			 * @param  int  $one   limit for one point
			 * @param  int  $two   limit for two points
			 * @param  int  $three limit for three points
			 * @param  int  $four  limit for four points
			 * @param  int  $five  limit for five points
			 * 
			 * @return int         the number of points the photo has
			 */
			private function getPoints($votes, $one, $two, $three, $four, $five)
			{
				if ($votes >= $five) return 5;
					elseif ($votes >= $four) return 4;
						elseif ($votes >= $three) return 3;
							elseif ($votes >= $two) return 2;
								elseif ($votes >= $one) return 1;
									else return 0;
			}

			/**
			 * Watermark photo
			 *
			 * @since 1.0
			 * 
			 * @param file   $sourceFile       unwatermarked photo
			 * @param file   $destinationFile  watermarked photo
			 * @param string $watermarkText  watermark text
			 * @param rgb    $rgb               watermark color
			 * @param string $position       watermark position
			 */
			private function watermarkImage($sourceFile, $destinationFile, $watermarkText, $rgb, $position) 
			{
				// Get photo extension
				$extension = pathinfo($sourceFile, PATHINFO_EXTENSION);
				
				// Get photo dimmensions
				list($width, $height) = getimagesize($sourceFile);

				// Create destination photo
				$destinationPhoto = imagecreatetruecolor($width, $height);

				// Create image from input file
				if ($extension == 'jpg')
					$inputPhoto = imagecreatefromjpeg($sourceFile);
				elseif ($extension == 'png')
					$inputPhoto = imagecreatefrompng($sourceFile);

				// Copy created image onto the destination photo
				imagecopyresampled($destinationPhoto, $inputPhoto, 0, 0, 0, 0, $width, $height, $width, $height); 
				
				// Set text color
				$textColor = imagecolorallocate($destinationPhoto, $rgb[0], $rgb[1], $rgb[2]);

				// Set text font
				$textFont = WPPC_DIR.'font/arial-rounded.ttf';

				// Set text size
				$textSize = 14;

				// Get text dimmensions
				$textDimensions = imagettfbbox($textSize, 0, $textFont, $watermarkText);
				
				// Write text to photo
				switch ($position):

					// Top left corner
					case 'topLeft':
						$xPosition = 10;
						$yPosition = 30;
						break;

					// Top center
					case 'topCenter':
						$xPosition = ($width/2) - ($textDimensions[4]/2);
						$yPosition = 30;
						break;

					// Top right corner
					case 'topRight':
						$xPosition = $width - $textDimensions[2] - 30;
						$yPosition = 30;
						break;

					// Center
					case 'center':
						$xPosition = ($width/2) - ($textDimensions[4]/2);
						$yPosition = ($height/2) - ($textDimensions[5]/2);
						break;

					// Bottom left corner
					case 'bottomLeft':
						$xPosition = 10;
						$yPosition = $height - $textDimensions[5] - 30;
						break;

					// Bottom center
					case 'bottomCenter':
						$xPosition = ($width/2) - ($textDimensions[4]/2);
						$yPosition = $height - $textDimensions[5] - 30;
						break;

					// Bottom right corner
					case 'bottomRight':
						$xPosition = $width - $textDimensions[2] -10;
						$yPosition = $height - $textDimensions[5] - 30;
						break;
				endswitch;
				imagettftext($destinationPhoto, $textSize, 0, $xPosition, $yPosition, $textColor, $textFont, $watermarkText);
				
				// Create destination photo
				if ($extension == 'jpg')
					imagejpeg($destinationPhoto, $destinationFile, 100); 
				elseif ($extension == 'png')
					imagepng($destinationPhoto, $destinationFile, 9);

				// Free memory
				imagedestroy($inputPhoto); 
				imagedestroy($destinationPhoto);
			}

			/**
			 * Transform hex color into rgb
			 *
			 * @since 1.0
			 * 
			 * @param  hexadecimal $hex color in hex
			 * 
			 * @return array      		color in rgb
			 */
			private function hex2rgb($hex)
			{
				$hex = str_replace("#", "", $hex);

				if (strlen($hex) == 3):
						$r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
						$g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
						$b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
					else:
						$r = hexdec(substr($hex, 0, 2));
						$g = hexdec(substr($hex, 2, 2));
						$b = hexdec(substr($hex, 4, 2));
				endif;

				return array($r, $g, $b);
			}


			/**
			 * Format content
			 *
			 * @since 1.0
			 */
			private function formatContent($content)
			{
				// Breaks to new lines
				$content = nl2br($content);

				// Strip slashes
				$content = stripslashes($content);

				// Return formatted content
				return $content;
			}
			

		}
	endif;

?>