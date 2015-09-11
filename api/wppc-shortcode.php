<?php

	if (!class_exists('WPPCShortcode')):
		class WPPCShortcode
		{
			private $submitFormAction = 'wppc-submit-photo';
			private $votePhotoAction = 'wppc-vote-photo';
			private $filterPhotos = 'wppc-filter-photos';

			/**
			 * GENERAL PLUGIN SETTINGS
			 * @var array
			 */
			private $generalSettings;

			/**
			 * WATERMARK PLUGIN SETTINGS
			 * @var array
			 */
			private $watermarkSettings;

			/**
			 * NUMBER OF PHOTOS TO PRINT PER TAB
			 * @var int
			 */
			private $photosPerPage;

			/**
			 * CONTESTS TABLE
			 */
			private $contestsTable;

			/**
			 * CONTEST ENTRIES TABLE
			 */
			private $contestEntriesTable;

			/**
			 * CONTEST VOTES TABLE
			 */
			private $contestVotesTable;

			/**
			 * CONSTRUCTOR
			 */
			public function __construct()
			{
				global $wpdb;
				
				// LOAD SETTINGS
				$this->generalSettings = get_option(WPPC_SETTINGS_GENERAL);
				$this->watermarkSettings = get_option(WPPC_SETTINGS_WATERMARK);
				$this->photosPerPage = $this->generalSettings['photosPerPage'];

				// initialize params
				$this->contestsTable = $wpdb->prefix.'wppc_contests_all';
				$this->contestEntriesTable = $wpdb->prefix.'wppc_contests_entries';
				$this->contestVotesTable = $wpdb->prefix.'wppc_contests_votes';

				// SET TIMEZONE
				date_default_timezone_set($this->generalSettings['timezone']);

				// LOAD SCRIPTS
				add_action('wp_enqueue_scripts', array($this, 'loadScripts'));

				// REGISTER AJAX FUNCTION FOR ENTRY FORM SUBMISSION
				add_action('wppc_ajax_'.$this->submitFormAction, array($this, 'wppcSubmitPhoto'));
				add_action('wppc_ajax_nopriv_'.$this->submitFormAction, array($this, 'wppcSubmitPhoto'));

				// REGISTER AJAX FUNCTION FOR PHOTO VOTING
				add_action('wppc_ajax_'.$this->votePhotoAction, array($this, 'wppcVotePhoto'));
				add_action('wppc_ajax_nopriv_'.$this->votePhotoAction, array($this, 'wppcVotePhoto'));

				// REGISTER AJAX FUNCTION FOR FILTERS
				add_action('wppc_ajax_'.$this->filterPhotos, array($this, 'filterPhotos'));
				add_action('wppc_ajax_nopriv_'.$this->filterPhotos, array($this, 'filterPhotos'));

				// REGISTER SHORTCODE
				add_shortcode('wphotocontest', array($this, 'wppcShortcode'));

				// LOAD SOCIAL SITES SCRIPTS
				add_action('wp_head', function() {
					$settings = get_option(WPPC_SETTINGS_GENERAL);
					?>
					<script>
						jQuery(document).ready(function($) {
							$('#wppc-photos').imagesLoaded(function() {
								$('#wppc-photos').masonry({
									itemSelector: '.photo',
									isAnimated: true,
									columnWidth: '.photo',
								});
								alert(1);
							});
						});
					</script>
					<?php
					
					if ($settings['loadFacebookJs'] == 1):
						?>
						<script type="text/javascript">
							(function(d, s, id) {
								var js, fjs = d.getElementsByTagName(s)[0];
								if (d.getElementById(id)) return;
								js = d.createElement(s); js.id = id;
								js.src = "//connect.facebook.net/en_US/all.js";
								fjs.parentNode.insertBefore(js, fjs);
							}(document, 'script', 'facebook-jssdk'));
							// async init once loading is done
							window.fbAsyncInit = function() {
								FB.init({appId: <?php echo $settings['facebookAppId'] ?>, status: false});
							};
						
							// share on facebook callback
							function shareOnFacebook(link, picture, name, caption)
							{
								FB.ui({
									method: 'feed',
									link: link,
									picture: picture,
									name: name,
									caption: caption,
								    //description: 'Must read daily!'
								});
							}
						</script>
						
						<script type="text/javascript" async src="//assets.pinterest.com/js/pinit.js" data-pin-build="parsePinBtns"></script>
						</script>
						<?php
					endif;
				});
			}

			/**
			 * LOAD JAVASCRIPT
			 */
			public function loadScripts()
			{
				// LOAD CONTEST CSS
				wp_enqueue_style('wppc-shortcode-css', WPPC_URI.'css/wppc-shortcode.css', '', WPPC_VERSION);
				wp_enqueue_style('wppc-colorbox-css', WPPC_URI.'css/colorbox.css', '', '1.5.13');

				// JQUERY & JQUERY UI TABS
				wp_enqueue_script('jquery','','','',true);
				wp_enqueue_script('jquery-ui-core','','','',true);
				wp_enqueue_script('jquery-ui-tabs','','','',true);

				// LOAD CONTEST JS
				wp_enqueue_script('wppc-shortcode', WPPC_URI.'js/wppc-shortcode.js', array('jquery'), WPPC_VERSION, true);

				// LOAD COLORBOX
				wp_enqueue_script('wppc-colorbox', WPPC_URI.'js/jquery.colorbox-min.js', array('jquery'), '1.5.13', true);

				// LOAD AJAX JS
				wp_enqueue_script('wppc-ajax-frontend', plugins_url('js/wppc-ajax-frontend.min.js', WPPC_FILE), array('jquery'), WPPC_VERSION, true);

				// LOCALIZE WPPC AJAX HANDLER
				wp_localize_script('wppc-ajax-frontend', 'wppcSubmitPhoto', array(
					'ajaxurl' => WPPC_URI.'ajax/ajax-handler.php',
					'action' => $this->submitFormAction,
				));

				// load masonry script
				wp_enqueue_script('masonry');

				// load images loaded script
				wp_enqueue_script('images-loaded', WPPC_URI.'js/imagesloaded.pkgd.min.js', array('jquery'), IMAGES_LOADED_VERSION, true);
			}

			/**
			 * CALLBACK AJAX FUNCTION TO SUBMIT PHOTO
			 */
			public function wppcSubmitPhoto()
			{
				//check_ajax_referer('wppc-ajax-submit', 'security');
				
				// GET CONTEST ID
				$id = isset($_POST['wppc-id']) ? $_POST['wppc-id'] : 1;

				// GET NAME
				$name = isset($_POST['wppc-name']) ? esc_attr($_POST['wppc-name']) : "";

				// GET EMAIL
				$email = filter_var($_POST['wppc-email'], FILTER_VALIDATE_EMAIL) ? esc_attr($_POST['wppc-email']) : "";

				// GET PHOTO FILE
				$photo = isset($_FILES['file-0']) && ($_FILES['file-0']['type'] == "image/png" || $_FILES['file-0']['type'] == "image/jpeg") ? $_FILES['file-0'] : "";

				// GET MOBILE PHOTO
				$mobile = isset($_POST['wppc-mobile-photo']) && $_POST['wppc-mobile-photo'] == "true" ? 1 : "0";

				// GET PHOTO NAME
				$photoName = isset($_POST['wppc-photo-name']) ? esc_attr($_POST['wppc-photo-name']) : "";

				// GET PHOTO LOCATION
				$photoLocation = isset($_POST['wppc-photo-location']) ? esc_attr($_POST['wppc-photo-location']) : "";

				// GET RULES AGREEMENT
				$agreeRules = isset($_POST['wppc-agree-rules']) && $_POST['wppc-agree-rules'] == "true" ? 1 : '';

				// GENERATE RANDOM NUMBER
				$randomNumber = absint(time() * $this->random_0_1());				
				
				// PREPARE AJAX RESOPONSE
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

				// ALL INFO IS CORRECT
				if ($name != '' && $email != '' && $photo != '' && $photoName != '' && $photoLocation != '' && $agreeRules != ''):
					global $wpdb;
					
					// SET TABLE NAME
					$tableName = $wpdb->prefix.'wppc_contests_entries';

					// GET NUMBER OF PHOTOS ALLOWED IN THIS CONTEST
					$contestInfo = $wpdb->get_row("SELECT photos_allowed, photos_mobile_allowed FROM ".$wpdb->prefix.'wppc_contests_all'." WHERE id=".$id);
					
					// GET NUMBER OF CAMERA PHOTOS ADDED BY THIS COMPETITOR
					$wpdb->get_results("SELECT id FROM ".$tableName." WHERE competitor_name='".$name."' AND competitor_email='".$email."' AND photo_mobile=0");
					$totalPhotosAdded = $wpdb->num_rows;
					
					// CHECK IF THE NUMBER OF CAMERA PHOTOS REACHED
					if ($totalPhotosAdded >= $contestInfo->photos_allowed && $mobile != 1):
						$jsonResponse['entryAdded'] = 'TOTAL';
					endif;

					// GET NUMBER OF MOBILE PHOTOS ADDED BY THIS COMPETITOR
					$wpdb->get_results("SELECT id FROM ".$tableName." WHERE competitor_name='".$name."' AND competitor_email='".$email."' AND photo_mobile=1");
					$totalMobilePhotosAdded = $wpdb->num_rows;

					// CHECK IF THE NUMBER OF MOBILE PHOTOS REACHED
					if ($totalMobilePhotosAdded >= $contestInfo->photos_mobile_allowed && $mobile == 1)
						$jsonResponse['entryAdded'] = "MOBILE";

					// CREATE ALL PHOTO FILES AND ADD TO DATABASE
					if ($jsonResponse['entryAdded'] == false):
						
						// GET WP DEFAULT UPLOAD FOLDER
						$wpDir = wp_upload_dir();

						// SET CONTEST FOLDER
						$contestDir = $wpDir['basedir'].'/wppc-photos/wppc-photos-'.$id.'/';

						// SAVE THE RAW FILE FROM THE USER
						$filename = $randomNumber.'-'.strtolower(sanitize_file_name($photo['name']));
						if (move_uploaded_file($photo['tmp_name'], $contestDir.'raw/'.$filename)):
								$jsonResponse['photo'] = $photo['name'];
								
								// LOAD THE FILE TO CREATE NECESSARY COPIES
								$image = wp_get_image_editor($contestDir.'raw/'.$filename);
								
								// CREATE THE "THUMBS" PHOTO
								$image->resize(200, 200, false); // true - creates a square photo
								$image->save($contestDir.'thumbs/'.$filename);

								// CREATE THE "MEDIUM" PHOTO
								$image = wp_get_image_editor($contestDir.'raw/'.$filename);
								$image->resize(1000, 1000, false);
								$image->save($contestDir.'medium/'.$filename);
								$this->watermarkImage($contestDir.'medium/'.$filename, $contestDir.'medium/'.$filename, '© '.$name, $this->hex2rgb($this->watermarkSettings['watermarkTextColor']), $this->watermarkSettings['watermarkTextPosition']);

								// CREATE THE "FULL" PHOTO
								$image = wp_get_image_editor($contestDir.'raw/'.$filename, $this->hex2rgb($this->watermarkSettings['watermarkTextColor']));
								$image->save($contestDir.'full/'.$filename);
								//$this->watermarkImage($contestDir.'raw/'.$filename, $contestDir.'full/'.$filename, '© '.$name);
								
								// INSERT ENTRY INTO THE DATABASE
								$wpdb->insert($tableName,
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

								// NOTIFY ADMINS VIA EMAIL
								if ($this->generalSettings['notifyAdmins'] == 1):
									$contest = $wpdb->get_row("SELECT contest_name FROM ".$wpdb->prefix."wppc_contests_all WHERE id=".$id);
									/*include_once(WPPC_DIR.'php/PHPMailer/PHPMailerAutoload.php');

									$mail = new PHPMailer();
									$mail->IsSMTP();
									$mail->SMTPDebug = 0; // 1 tells it to display SMTP errors and messages, 0 turns off all errors and messages, 2 prints messages only.
									$mail->Host = ini_get('SMTP');
									$mail->IsHTML(true);
									$mail->From = "wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']);
									$mail->FromName = get_bloginfo();
									$mail->Subject = "New Photo Submitted";
									$mail->Body = "A new photo has been submitted in the '".$contest->contest_name."' contest.<br/><br/> <a href=".admin_url('admin.php?page=wppc-contest&wppc-id='.$id.'&wppc-action=view').">Click here to view it</a>";*/
										//$mail->AddCC($admin->data->user_email, $admin->data->display_name);
									//$mail->Send();
									//$mail->ClearAllRecipients();
									

									
						
									// connect to SendGrid API
									$sendgrid = new SendGrid(get_option('sendgrid_user'), get_option('sendgrid_pwd'));

									// send email to all admins
									$admins = get_users(array('role'=>'administrator'));
									foreach ($admins as $admin):
										
										// create new email
										$email = new SendGrid\Email();

										// add recipient email
										$email->addTo($admin->data->user_email, $admin->data->display_name)
											  ->setFrom("wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']))
											  ->setFromName(get_bloginfo())
											  ->setSubject("New Photo Submitted")
											  ->setHtml("A new photo has been submitted in the <strong>".$contest->contest_name."</strong> contest.<br/><br/> <a href=".admin_url('admin.php?page=wppc-contest&contest='.$id.'&activity=view').">Click here to view it</a>");

										// send email to user
										$sendgrid->send($email);

									endforeach;

								endif;

								$jsonResponse['entryAdded'] = true;
							// FILE UPLOAD FAILED
							else:
								$jsonResponse['entryAdded'] = "FILEFAIL";
						endif;
					endif;
				endif;

				// SEND AJAX RESPONSE
				echo json_encode($jsonResponse);

				// TERMINATE
				die();
			}

			/**
			 * CALLBACK AJAX FUNCTION TO VOTE PHOTO
			 */
			public function wppcVotePhoto()
			{
				global $wpdb;

				// GET CONTEST ID
				$id = isset($_POST['wppc-id']) ? $_POST['wppc-id'] : '';

				// GET PHOTO ID
				$photoID = isset($_POST['wppc-value']) ? $_POST['wppc-value'] : '';

				// GET THE MAX NUMBER OF VOTES ALLOWED
				$votesAllowed = $wpdb->get_var("SELECT votes_allowed FROM ".$wpdb->prefix.'wppc_contests_all'." WHERE id=".$id);

				// GET USER'S IP
				$userIP = $this->getUserIP();

				// GET HOW MANY TIMES THE USER ALREADY VOTED
				$userVoted = $wpdb->get_var("SELECT vote_number FROM ".$wpdb->prefix.'wppc_contests_votes'." WHERE vote_ip='".ip2long($userIP)."' AND contest_id='".$id."' AND photo_id='".$photoID."'");

				// GET CONTEST POINTS
				$points = array();
				$points[0] = $_POST['wppc-first-point'];
				$points[1] = $_POST['wppc-second-point'];
				$points[2] = $_POST['wppc-third-point'];
				$points[3] = $_POST['wppc-forth-point'];
				$points[4] = $_POST['wppc-fifth-point'];

				// PREPARE AJAX RESPONSE
				$jsonResponse = array(
					'wppc-value' => $_POST['wppc-value'],
					'votesAllowed' => $votesAllowed,
					'userIP' => ip2long($userIP),
					'userVoted' => $userVoted,
					'photoVotes' => $_POST['wppc-votes'],
					'photoPoints' => $this->getPoints($_POST['wppc-votes'], $points[0], $points[1], $points[2], $points[3], $points[4]),
				);

				// CHECK IF USER ALREADY VOTED THIS PHOTO
				if ($userVoted):
						// USER ALREADY VOTED, CHECK IF HE CAN VOTE AGAIN
						if ($userVoted < $votesAllowed):
								$currentTime = date('Y-m-d h:m:s', mktime());
								$voteTime = $wpdb->get_var("SELECT vote_time FROM ".$wpdb->prefix.'wppc_contests_votes'." WHERE vote_ip='".ip2long($userIP)."' AND contest_id='".$id."' AND photo_id='".$photoID."'");

								// # OF DAYS FROM LAST VOTE
								$days = (strtotime($currentTime)-strtotime($voteTime))/(60*60*24);
								$jsonResponse['days'] = $days;

								// USER CAN STILL VOTE
								if ($days >= 1.0):
										// UPDATE VOTES #
										$wpdb->update($wpdb->prefix.'wppc_contests_votes',
											array('vote_number' => $userVoted + 1, 'vote_time' => $currentTime),
											array('vote_ip' => ip2Long($userIP), 'photo_id' => $photoID));

										// ADD VOTE TO PHOTO
										$wpdb->update($wpdb->prefix.'wppc_contests_entries',
											array('votes' => $_POST['wppc-votes'] + 1),
											array('photo_id' => $photoID, 'contest_id' => $id));
										
										// SET AJAX RESPONSE
										$jsonResponse['voteAdded'] = true;
										$jsonResponse['photoVotes'] = $_POST['wppc-votes'] + 1;
										$jsonResponse['photoPoints'] = $this->getPoints($jsonResponse['photoVotes'], $points[0], $points[1], $points[2], $points[3], $points[4]);
									else:
										// USER HAS TO WAIT A FULL 24H TO VOTE AGAIN
										$jsonResponse['voteAdded'] = '24H';
								endif;
							else:
								// USER ALREADY VOTED THE ALLOWED # OF TIMES
								$jsonResponse['voteAdded'] = false;
						endif;

					// USER NEVER VOTED THIS PHOTO
					else:
						// ADD USER TO VOTERS
						$wpdb->insert($wpdb->prefix.'wppc_contests_votes',
							array(
								'contest_id' => $id,
								'photo_id' => $photoID,
								'vote_ip' => ip2long($userIP),
								'vote_number' => 1
							)
						);

						// ADD VOTE TO PHOTO
						$wpdb->update($wpdb->prefix.'wppc_contests_entries',
							array('votes' => $_POST['wppc-votes'] + 1),
							array('photo_id' => $photoID, 'contest_id' => $id));

						// SET AJAX RESPONSE
						$jsonResponse['voteAdded'] = true;
						$jsonResponse['photoVotes'] = $_POST['wppc-votes'] + 1;
						$jsonResponse['photoPoints'] = $this->getPoints($jsonResponse['photoVotes'], $points[0], $points[1], $points[2], $points[3], $points[4]);
				endif;

				// SEND AJAX RESPONSE
				echo json_encode($jsonResponse);

				// TERMINATE
				die();
			}

			/**
			 * CALLBACK FUNCTION TO FILTER PHOTOS
			 */
			public function filterPhotos()
			{
				global $wpdb;

				// GET CONTEST ID
				$id = isset($_POST['wppc-id']) ? $_POST['wppc-id'] : '';

				// GET FILTER
				$filter = isset($_POST['wppc-filter']) ? $_POST['wppc-filter'] : '';

				$wpDir = wp_upload_dir();
				$contestDir = $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$id.'/';
				$contest = $wpdb->get_row("SELECT contest_name, first_point, second_point, third_point, forth_point, fifth_point, start_date, end_registration, end_vote, contest_social_description FROM ".$wpdb->prefix.'wppc_contests_all'." WHERE id=".$id);
				$contestPoints = array($contest->first_point, $contest->second_point, $contest->third_point, $contest->forth_point, $contest->fifth_point);
				$weeks = absint(((strtotime($contest->end_registration) - strtotime($contest->start_date)) / (60*60*24)) / 7);
				$tableName = $wpdb->prefix.'wppc_contests_entries';

				// PREPARE AJAX RESPONSE
				$ajaxResponse = '<div id="vote-results-680263891" class="alert alert-warning" style=""><p>No photos match the criteria</p></div>';
				
				// GET FILTER TYPE
				$type = explode('-', $filter);
				
				switch ($type[0]):
					// FILTER BY WEEK
					case 'week':
							if ($filter == 'week-all'):
									$contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND visible=1");
								else:
									$startDate = strtotime('+'.absint(($type[1]-1)*7)." day", strtotime($contest->start_date));
									$endDate = strtotime('+'.absint($type[1]*7-1)." day", strtotime($contest->start_date));
									$contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND (upload_date>='".date('Y-m-d, 0:00:00', $startDate)."' AND upload_date<='".date('Y-m-d, 0:00:00', $endDate)."') AND visible=1");
							endif;
						break;

					// FILTER BY POINT
					case 'point':
						if ($filter == 'point-all'):
								$contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND visible=1");
							else:
								switch ($type[1]):
									case 0: $contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND votes<".$contest->first_point." AND visible=1"); break;
									case 1: $contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND votes>=".$contest->first_point." AND votes<".$contest->second_point." AND visible=1"); break;
									case 2: $contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND votes>=".$contest->second_point." AND votes<".$contest->third_point." AND visible=1"); break;
									case 3: $contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND votes>=".$contest->third_point." AND votes<".$contest->forth_point." AND visible=1"); break;
									case 4: $contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND votes>=".$contest->forth_point." AND votes<".$contest->fifth_point." AND visible=1"); break;
									case 5: $contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND votes>=".$contest->fifth_point." AND visible=1"); break;
								endswitch;
						endif;
						break;

					// FILTER MOBILE
					case 'mobile':
						if ($type[1] == 'only') 
								$contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND photo_mobile=1 AND visible=1");
							else
								$contestPhotos = $wpdb->get_results("SELECT * FROM ".$tableName." WHERE contest_id=".$id." AND visible=1");
						break;
				endswitch;

				// NO PHOTOS MATCH THE CRITERIA
				if (!empty($contestPhotos))
					$ajaxResponse = $this->displayContestPhotos($contestPhotos, $contest->contest_name, $contest->end_vote, $contestDir, $contestPoints, $_POST['wppc-url'], $contest->contest_social_description, $this->photosPerPage);

				// SEND AJAX RESPONSE
				echo $ajaxResponse;
				
				// TERMINATE
				die();
			}


			/**
			 * CALLBACK FUNCTION TO PRINT THE CONTEST
			 */
			public function wppcShortcode($atts)
			{
				// SHORTCODE ATTRIBUTES
				$atts = shortcode_atts(array('id' => 1), $atts);

				global $wpdb;
				$tableName = $wpdb->prefix.'wppc_contests_all';
				$contest = $wpdb->get_row('SELECT * FROM '.$tableName.' WHERE id='.$atts['id']);
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
								<?php if ($contest->start_date <= date('Y-m-d h:m:s', time()) && date('Y-m-d h:m:s', time()) <= $contest->end_registration): ?><li style="display: inline; margin-right: 2rem;"><a href="#contest-entry-form">Entry form</a></li><?php endif; ?>
								<?php if ($contest->contest_contact != ''): ?><li style="display: inline; margin-right: 2rem;"><a href="#contest-contact">Contact</a></li><?php endif; ?>
							</ul>
							
							<?php
								/* CONTEST ABOUT */
								if ($contest->contest_about != ''):
									echo '<div id="contest-about">';
										echo $this->formatContent($contest->contest_about);
									echo '</div>';
								endif;

							/* CONTEST PHOTO GALLERY */
							if ($contest->start_date <= date('Y-m-d h:m:s', time())):
								echo '<div id="contest-photo-gallery">';
									echo $contest->contest_photo_gallery;
									$totalDays = (strtotime($contest->end_registration) - strtotime($contest->start_date)) / (60*60*24);
									$weeks = $totalDays % 7 != 0 ? $totalDays / 7 + 1 : $totalDays / 7;
									?>
									<div id="wppc-filters" class="row" style="margin-bottom: 20px;">
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
									<?php
									echo '<div id="wppc-photos">';
										global $wpdb;
										$wpDir = wp_upload_dir();
										$contestDir = $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contest->id.'/';

										// get all photos for this contest
										$contestPhotos = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'wppc_contests_entries WHERE contest_id='.$contest->id.' AND visible=1');

										// shuffle photos
										shuffle($contestPhotos);
										
										// get points
										$contestPoints = array($contest->first_point, $contest->second_point, $contest->third_point, $contest->forth_point, $contest->fifth_point);
										
										$this->displayContestPhotos($contestPhotos, $contest->contest_name, $contest->end_vote, $contestDir, $contestPoints, get_permalink(get_the_id()), $contest->contest_social_description, $this->photosPerPage);
									echo '</div>';
								echo '</div>';
							endif; 

							/* CONTEST WINNERS */
							if ($contestWinners['text'] != ''):
								echo '<div id="contest-winners">';
									echo $contestWinners['text'];
								echo '</div>';
							endif;

							/* CONTEST RULES */
							if ($contest->contest_rules != ''): ?>
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
							<?php endif;

							/* CONTEST PRIZES */
							if ($contest->contest_prizes != ''):
								echo '<div id="contest-prizes">';
									echo $this->formatContent($contest->contest_prizes);
								echo '</div>';
							endif;

							/* CONTEST ENTRY FORM */
							if ($contest->start_date <= date('Y-m-d h:m:s', time()) && date('Y-m-d h:m:s', time()) <= $contest->end_registration):
								echo '<div id="contest-entry-form">';
									$this->setEntryForm($contest->id, $contest->contest_entry_form);
								echo '</div>';
							endif;

							/* CONTEST CONTACT */
							if ($contest->contest_contact != ''):
								echo '<div id="contest-contact">';
									echo $contest->contest_contact;
								echo '</div>';
							endif; ?>
						</div> <!-- end contest-tabs -->
					</div> <!-- end wppc-main -->
					
					<div id="wppc-sidebar">
						<?php /* FOLLOW US */ setFollowUs(); ?>
						<br/><br/>
						<?php echo $contest->contest_sidebar ?>
					</div>
				</div>
				<?php
			}

			/**
			 * DISPLAY PHOTO GALLERY TAB
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
					echo '<div id="wppc-contest-pages">';
						echo '<div class="pagination-div">';
							echo '<ul class="pagination">';
								for ($page=1; $page<=$numberOfPages; $page++)
									echo '<li><a href="#wppc-contest-page-'.$page.'">'.$page.'</a></li>';
							echo '</ul>';
						echo '</div>';
				endif;

				// SHUFFLE PHOTOS
				shuffle($contestPhotos);

				$page = 0;
				$photoNumber = 0;
				
				// PRINT PHOTOS
				foreach ($contestPhotos as $contestPhoto):
					$photoNumber++;
					$mediumPhoto = $contestDir.'medium/'.$contestPhoto->competitor_photo;
					$titlePhoto = $contestPhoto->photo_name.' at '.$contestPhoto->photo_location.' by '.$contestPhoto->competitor_name;
					$caption = $socialDescription !== '' ? $socialDescription : $titlePhoto;
					
					if ($numberOfPages > 1 && $photoNumber % $numberOfPhotos == 1)
						echo '<div id="wppc-contest-page-'.++$page.'">';
					?>
					<div class="photo">
						<a class="group1" href="<?php echo $mediumPhoto ?>" title="<?php echo $titlePhoto ?>">
							<img src="<?php echo $contestDir ?>thumbs/<?php echo $contestPhoto->competitor_photo ?>" alt="<?php echo $contestPhoto->photo_name ?>" />
						</a>
						<p class="help-block" style="text-align: left;"><?php echo $titlePhoto ?></p>
	
						<div class="wppc-social-buttons">
							<img class='wppc-share-button' style="width: 60px !important; height: 20px !important;" src="<?php echo WPPC_URI ?>img/facebook-share.png" onclick="shareOnFacebook('<?php echo $contestURL ?>', '<?php echo $contestDir ?>medium/<?php echo $contestPhoto->competitor_photo ?>', '<?php echo $name ?>', '<?php echo $caption ?>')" />
							<a href="https://plus.google.com/share?url=<?php echo $contestURL ?>" onclick="javascript:window.open(this.href,'', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=600,width=600');return false;"><img class="wppc-share-button" style="width: 60px !important; height: 20px !important; vertical-align: top" src="<?php echo WPPC_URI ?>img/google-share.png" alt="Share on Google+"/></a>
							<a href="//www.pinterest.com/pin/create/button/?url=<?php echo $contestURL ?>&amp;media=<?php echo $mediumPhoto ?>&amp;description=<?php echo $caption ?>" data-pin-do="buttonPin" data-pin-config="none" data-pin-color="white"><img src="<?php echo WPPC_URI ?>img/pinterest-share.png" style="width: 40px !important; height: 20px !important;" /></a>
						</div>

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

				if ($numberOfPages > 1)
					echo '</div></div>';
			}

			/**
			 * DISPLAY CONTEST ENTRY FORM
			 * @param int $id of the contest
			 */
			private function setEntryForm($id, $text)
			{
				global $wpdb;

				// get number of mobile photos
				$mobilePhotos = $wpdb->get_var("SELECT photos_mobile_allowed FROM $this->contestsTable WHERE id=".$id);


				echo $text;
				?>
					<div id="wppc-results"></div>
					<form class="form-horizontal" role="form" method="post" action="" id="wppc-form" enctype="multipart/form-data" style="margin-top: 30px;">
						<input type="hidden" name="wppc-id" id="wppc-id" value="<?php echo $id ?>" />
						<!-- FULL NAME -->
						<div class="form-group has-feedback">
							<label for="wppc-name" class="col-sm-2 control-label">Full name</label>
							<div class="col-sm-4">
								<input type="text" class="form-control" name="wppc-name" id="wppc-name" placeholder="Full name" />
							</div>
						</div>

						<!-- EMAIL ADDRESS -->
						<div class="form-group has-feedback">
							<label for="" class="col-sm-2 control-label">Email</label>
							<div class="col-sm-4">
								<input type="email" class="form-control" name="wppc-email" id="wppc-email" placeholder="Email" />
							</div>
						</div>

						<!-- PHOTO -->
						<div class="form-group has-feedback">
							<label for="" class="col-sm-2 control-label">Photo</label>
							<div class="col-sm-4">
								<input type="file" class="form-control" name="wppc-photo" id="wppc-photo" accept="image/x-png, image/jpeg" />
							</div>
						</div>

						<!-- MOBILE PHOTO -->
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

						<!-- PHOTO NAME AND LOCATION -->
						<div class="form-group has-feedback">
							<label for="" class="col-sm-2 control-label"></label>
							<div class="col-sm-2">
								<input type="text" class="form-control" name="wppc-photo-name" id="wppc-photo-name" placeholder="Photo Name" />
							</div>
							<div class="col-sm-2">
								<input type="text" class="form-control" name="wppc-photo-location" id="wppc-photo-location" placeholder="Photo Location" />
							</div>
						</div>

						<!-- RULES AGREEMENT -->
						<div class="form-group has-feedback">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox col-sm-4">
									<label>
										<input type="checkbox" name="wppc-agree-rules" id="wppc-agree-rules" /> I agree with the rules
									</label>
								</div>
							</div>
						</div>

						<!-- SUBSCRIBE TO NEWSLETTER -->
						<div class="form-group">
							<div class="col-sm-offset-2 col-sm-10">
								<div class="checkbox">
									<label>
										<input type="checkbox" disabled checked /> Subscribe to newsletter
									</label>
								</div>
							</div>
						</div>

						<!-- SUBMIT FORM -->
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
			 * RANDOM FLOAT NUMBER BETWEEN 0 AND 1
			 * @return float between 0 and 1
			 */
			private function random_0_1()
			{   
				return (float)rand()/(float)getrandmax();
			}

			/**
			 * GET THE USERs IP ADDRESS
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
			 * THE NUMBER OF POINTS A PHOTO HAS
			 * @param  int  $votes the number of votes the photo has
			 * @param  int  $one   limit for one point
			 * @param  int  $two   limit for two points
			 * @param  int  $three limit for three points
			 * @param  int  $four  limit for four points
			 * @param  int  $five  limit for five points
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
			 * WATERMARK PHOTO
			 * @param file   $sourceFile       unwatermarked photo
			 * @param file   $destinationFile  watermarked photo
			 * @param string $watermarkText  watermark text
			 * @param rgb    $rgb               watermark color
			 * @param string $position       watermark position
			 */
			private function watermarkImage($sourceFile, $destinationFile, $watermarkText, $rgb, $position) 
			{
				// GET PHOTO EXTENSION
				$extension = pathinfo($sourceFile, PATHINFO_EXTENSION);
				
				// GET PHOTO DIMMENSIONS
				list($width, $height) = getimagesize($sourceFile);

				// CREATE DESTINATION PHOTO
				$destinationPhoto = imagecreatetruecolor($width, $height);

				// CREATE IMAGE FROM INPUT FILE
				if ($extension == 'jpg')
						$inputPhoto = imagecreatefromjpeg($sourceFile);
					elseif ($extension == 'png')
						$inputPhoto = imagecreatefrompng($sourceFile);

				// COPY INPUT PHOTO ONTO DESTINATION PHOTO
				imagecopyresampled($destinationPhoto, $inputPhoto, 0, 0, 0, 0, $width, $height, $width, $height); 
				
				// SET TEXT COLOR
				$textColor = imagecolorallocate($destinationPhoto, $rgb[0], $rgb[1], $rgb[2]);

				// SET TEXT FONT
				$textFont = WPPC_DIR.'font/arial-rounded.ttf';

				// SET TEXT SIZE
				$textSize = 14;

				// GET TEXT DIMMENSIONS
				$textDimensions = imagettfbbox($textSize, 0, $textFont, $watermarkText);
				
				// WRITE TEXT TO PHOTO
				switch ($position):
					// TOP LEFT CORNER
					case 'topLeft':
						$xPosition = 10;
						$yPosition = 30;
						break;
					 
					// TOP CENTER
					case 'topCenter':
						$xPosition = ($width/2) - ($textDimensions[4]/2);
						$yPosition = 30;
						break;

					// TOP RIGHT CORNER
					case 'topRight':
						$xPosition = $width - $textDimensions[2] - 30;
						$yPosition = 30;
						break;
					 
					// CENTER
					case 'center':
						$xPosition = ($width/2) - ($textDimensions[4]/2);
						$yPosition = ($height/2) - ($textDimensions[5]/2);
						break;
					
					// BOTTOM LEFT CORNER
					case 'bottomLeft':
						$xPosition = 10;
						$yPosition = $height - $textDimensions[5] - 30;
						break;

					// BOTTOM CENTER
					case 'bottomCenter':
						$xPosition = ($width/2) - ($textDimensions[4]/2);
						$yPosition = $height - $textDimensions[5] - 30;
						break;

					// BOTTOM RIGHT CORNER
					case 'bottomRight':
						$xPosition = $width - $textDimensions[2] -10;
						$yPosition = $height - $textDimensions[5] - 30;
						break;
				endswitch;
				imagettftext($destinationPhoto, $textSize, 0, $xPosition, $yPosition, $textColor, $textFont, $watermarkText);
				
				// CREATE DESTINATION PHOTO
				if ($extension == 'jpg')
						imagejpeg($destinationPhoto, $destinationFile, 100); 
					elseif ($extension == 'png')
						imagepng($destinationPhoto, $destinationFile, 9);

				// FREE MEMORY
				imagedestroy($inputPhoto); 
				imagedestroy($destinationPhoto);
			}

			/**
			 * TRANSFORM A HEX COLOR INTO RGB
			 * @param  hexadecimal $hex color in hex
			 * @return array      		color in rgb
			 */
			private function hex2rgb($hex)
			{
				$hex = str_replace("#", "", $hex);

				if(strlen($hex) == 3):
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
			 * FORMAT CONTENT
			 */
			private function formatContent($content)
			{
				// breaks to new lines
				$content = nl2br($content);

				// strip slashes
				$content = stripslashes($content);

				// return formatted content
				return $content;
			}
			

		} // END CLASS
	endif;

?>