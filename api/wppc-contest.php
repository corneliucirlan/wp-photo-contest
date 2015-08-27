<?php

	if (!class_exists('WPPCContest')):
		class WPPCContest
		{
			private $viewPhotoSpecs = 'view-photo-specs';
			private $viewPhotoVoters = 'view-photo-voters';
			private $testAdmitEmail = 'test-admit-email';

			/**
			 * CONSTRUCT
			 */
			public function __construct()
			{
				// LOAD ADMIN SCRIPTS
				add_action('admin_enqueue_scripts', array($this, 'loadAdminWPPCScripts'));

				// INSERT "ADD NEW" INTO WPPC MENU
				add_action('admin_menu', array($this, 'addNewWPPC'));

				// SAVE/UPDATE NEW CONTEST IN THE DATABASE
				add_action('admin_post_add_new_wppc', array($this, 'addNewWPPCContest'));
				do_action("admin_post_add_new_wppc");

				// AJAX CALLBACK FOR PHOTO SPECS
				add_action('wp_ajax_'.$this->viewPhotoSpecs, array($this, 'viewPhotoSpecs'));

				// AJAX CALLBACK FOR PHOTO VOTERS
				add_action('wp_ajax_'.$this->viewPhotoVoters, array($this, 'viewPhotoVoters'));

				// AJAX CALLBACK FOR TESTING EMAIL
				add_action('wp_ajax_'.$this->testAdmitEmail, array($this, 'testAdmitEmail'));
			}

			/**
			 * CALLBACK FUNCTION TO LOAD ADMIN PAGE SCRIPTS
			 */
			public function loadAdminWPPCScripts($hook)
			{
				// LOAD PAGE CSS
				wp_enqueue_style('admin-wppc-contests', WPPC_URI.'css/wppc-contest.css', '', WPPC_VERSION);

				// LOAD JQUERY & AJAX CALLBACKS
				wp_enqueue_script('jquery','','','',true);
				wp_enqueue_script('jquery-ui-core','','','',true);
				wp_enqueue_script('jquery-ui-tabs','','','',true);
				wp_enqueue_script('wppc-contest-js', WPPC_URI.'js/wppc-contest.js', array('jquery'), WPPC_VERSION, true);

				// LOAD WP AJAX HANDLER
				wp_localize_script('wppc-contest-js', 'wppcAdminContest', array('ajaxurl' => admin_url('admin-ajax.php')));
			}

			/**
			 * CALLBACK FUNCTION TO "ADD NEW" INTO WPPC MENU
			 */
			public function addNewWPPC()
			{
				// ADD "CONTEST" ITEM
				add_submenu_page('wppc-all-contests', "Photo Contest", "Contest", "manage_options", 'wppc-contest', array($this, 'displayWPPCContest'));
			}

			/**
			 * CALLBACK FUNCTION TO DISPLAY "CONTEST" PAGE
			 */
			public function displayWPPCContest()
			{
				?>
				<div class="wrap">
					<?php
						// SET PAGE TITLE
						if (!isset($_GET['wppc-action'])):
								echo '<h2>New Photo Contest</h2>';
							else:
								switch ($_GET['wppc-action']):
									case 'edit': echo '<h2>Edit contest</h2>'; break;
									case 'view': echo '<h2>View photos</h2>'; break;
									case 'stats': echo '<h2>Contest Stats</h2>'; break;
								endswitch;
						endif;
						
						// CONTEST ACTION
						if (isset($_GET['wppc-action'])):
								switch ($_GET['wppc-action']):									
									
									// EDIT CONTEST
									case 'edit':
										global $wpdb;
										$contest = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix.'wppc_contests_all'." WHERE id=".$_GET['wppc-id']);

										// CONTEST NOTICES
										if (isset($_GET['wppc-perform'])):

											// CONTEST ADDED NOTICE
											if ($_GET['wppc-perform'] == 'added'):
												?>
												<div class="updated">
													<p>Contest added</p>
												</div>
												<?php
											endif;

											// CONTEST EDITED NOTICE
											if ($_GET['wppc-perform'] == 'edited'):
													?>
												<div class="updated">
													<p>Contest edited</p>
												</div>
												<?php
											endif;
										endif;

										$this->editPhotoContest($contest);
										break;

									// VIEW/EDIT CONTEST PHOTOS
									case 'view':
										$this->editeContestPhotos();
										break;

									// CONTEST STATS
									case 'stats':
										$this->viewContestStats();
										break;
								endswitch;
							
							// ADD NEW CONTEST
							else:
								$this->editPhotoContest(null);
						endif;
					?>
				</div>
				<?php
			}
			
			/**
			 * EDIT PHOTO CONTEST
			 */
			private function editPhotoContest($contest)
			{
				global $wpdb;
				
				// get all dates to validate
				$contestStartDate = isset($_POST['contestStartDate']) ? explode("-", $_POST['contestStartDate']) : '';
				$contestEndRegistration = isset($_POST['contestEndRegistration']) ? explode("-", $_POST['contestEndRegistration']) : '';
				$contestEndVote = isset($_POST['contestEndVote']) ? explode("-", $_POST['contestEndVote']) : '';
				$contestEndDate = isset($_POST['contestEndDate']) ? explode("-", $_POST['contestEndDate']) : '';
				$validStartDate = false;
				if ($contestStartDate != "")
					if (checkdate($contestStartDate[1], $contestStartDate[2], $contestStartDate[0]))
						$validStartDate = true;
				$validEndRegistration = false;
				if ($contestEndRegistration != "")
					if (checkdate($contestEndRegistration[1], $contestEndRegistration[2], $contestEndRegistration[0]))
						$validEndRegistration = true;
				$validEndVote = false;
				if ($contestEndVote != "")
					if (checkdate($contestEndVote[0], $contestEndVote[1], $contestEndVote[2]))
						$validEndVote = true;
				$validEndDate = false;
				if ($contestEndDate != "")
					if (checkdate($contestEndDate[1], $contestEndDate[2], $contestEndDate[0]))
						$validEndDate = true;
				$contestWinners = isset($_GET['wppc-id']) && $contest->contest_winners != '' ? unserialize($contest->contest_winners) : array();
				$contestRules = isset($_GET['wppc-id']) && $contest->contest_rules != '' ? unserialize($contest->contest_rules) : array();
				$contestEmails = isset($_GET['wppc-id']) && $contest->contest_emails != '' ? unserialize($contest->contest_emails) : array();
				$photos = isset($_GET['wppc-id']) ? $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'wppc_contests_entries WHERE visible=1 AND contest_id='.$_GET['wppc-id']) : array();
				?>

				<div id="tabs">
					<ul class="nav-tab-wrapper" style="border-bottom: 1px solid #ddd;">
						<li class="nav-tab nav-tab-active"><a href="#general">General</a></li>
						<li class="nav-tab"><a href="#contest-tabs">Contest tabs</a></li>
						<li class="nav-tab"><a href="#sidebar">Sidebar</a></li>
						<li class="nav-tab"><a href="#emails">Emails</a></li>
						<?php if (isset($_GET['wppc-id'])): ?><li class="nav-tab"><a href="#select-winners">Select winners</a></li><?php endif; ?>
					</ul>
					<form method="post" action="">
						<div id="general">
							<table class="form-table">
								<tbody>
									<!-- CONTEST NAME -->
									<tr <?php $_POST && isset($_POST['contestName']) == '' && !isset($_GET['wppc-id']) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Contest name</th>
										<td>
											<input type="text" id="contestName" name="contestName" value="<?php if (isset($_GET['wppc-id'])) echo $contest->contest_name; elseif ($_POST) echo $_POST['contestName']; ?>">
											<label for="contestName"> The name of the new contest</label>
										</td>
									</tr>

									<!-- CONTEST START DATE -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && (isset($_POST['contestStartDate']) == '' || !$validStartDate) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Start date</th>
										<td>
											<input type="date" id="contestStartDate" name="contestStartDate" value="<?php if (isset($_GET['wppc-id'])) echo $contest->start_date; elseif ($_POST) echo $_POST['contestStartDate']?>">
											<label for="contestStartDate"> The start date of the contest</label>
										</td>
									</tr>

									<!-- CONTEST END REGISTRATION -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && (isset($_POST['contestEndRegistration']) == '' || !$validEndRegistration) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Registration end</th>
										<td>
											<input type="date" id="contestEndRegistration" name="contestEndRegistration" value="<?php if (isset($_GET['wppc-id'])) echo $contest->end_registration; elseif ($_POST) echo $_POST['contestEndRegistration']?>">
											<label for="contestEndRegistration"> Until when the contestants can enter the contest</label>
										</td>
									</tr>

									<!-- CONTEST END VOTE -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && (isset($_POST['contestEndVote']) == '' || !$validEndVote) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Vote end</th>
										<td>
											<input type="date" id="contestEndVote" name="contestEndVote" value="<?php if (isset($_GET['wppc-id'])) echo $contest->end_vote; elseif ($_POST) echo $_POST['contestEndVote']?>">
											<label for="contestEndVote"> until when users can vote</label>
										</td>
									</tr>

									<!-- CONTEST END DATE -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && (isset($_POST['contestEndDate']) == '' || !$validEndDate) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">End date</th>
										<td>
											<input type="date" id="contestEndDate" name="contestEndDate" value="<?php if (isset($_GET['wppc-id'])) echo $contest->end_date; elseif ($_POST) echo $_POST['contestEndDate']?>">
											<label for="contestEndDate"> The end date of the contest</label>
										</td>
									</tr>

									<!-- CONTEST PHOTOS ALLOWED -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && isset($_POST['contestPhotosAllowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Number of photos</th>
										<td>
											<input class="small-text" type="number" id="contestPhotosAllowed" name="contestPhotosAllowed" value="<?php if (isset($_GET['wppc-id'])) echo $contest->photos_allowed; else echo $_POST['contestPhotosAllowed'] ?>">
											<label for="contestPhotosAllowed"> Number of photos allowed in the contest</label>
										</td>
									</tr>

									<!-- CONTEST MOBILE PHOTOS ALLOWED -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && isset($_POST['contestPhotosMobileAllowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Number of photos</th>
										<td>
											<input class="small-text" type="number" id="contestPhotosMobileAllowed" name="contestPhotosMobileAllowed" value="<?php if (isset($_GET['wppc-id'])) echo $contest->photos_mobile_allowed; else echo $_POST['contestPhotosMobileAllowed'] ?>">
											<label for="contestPhotosMobileAllowed"> Number of photos taken with a phone allowed in the contest</label>
										</td>
									</tr>

									<!-- CONTEST VOTES ALLOWED -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && isset($_POST['contestVotesAllowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Votes allowed</th>
										<td>
											<input class="small-text" type="number" id="contestVotesAllowed" name="contestVotesAllowed" value="<?php if (isset($_GET['wppc-id'])) echo $contest->votes_allowed; else echo $_POST['contestVotesAllowed'] ?>">
											<label for="contestVotesAllowed"> How many times a visitor can vote a photo</label>
										</td>
									</tr>

									<!-- CONTEST FIRST POINT -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && isset($_POST['contestFirstPoint']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">First point at</th>
										<td>
											<input class="small-text" type="number" id="contestFirstPoint" name="contestFirstPoint" value="<?php if (isset($_GET['wppc-id'])) echo $contest->first_point; else echo $_POST['contestFirstPoint'] ?>">
											<label for="contestFirstPoint"> votes</label>
										</td>
									</tr>

									<!-- CONTEST SECOND POINT -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && isset($_POST['contestSecondPoint']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Second point at</th>
										<td>
											<input class="small-text" type="number" id="contestSecondPoint" name="contestSecondPoint" value="<?php if (isset($_GET['wppc-id'])) echo $contest->second_point; else echo $_POST['contestSecondPoint'] ?>">
											<label for="contestSecondPoint"> votes</label>
										</td>
									</tr>

									<!-- CONTEST THIRD POINT -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && isset($_POST['contestThirdPoint']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Third point at</th>
										<td>
											<input class="small-text" type="number" id="contestThirdPoint" name="contestThirdPoint" value="<?php if (isset($_GET['wppc-id'])) echo $contest->third_point; else echo $_POST['contestThirdPoint'] ?>">
											<label for="contestThirdPoint"> votes</label>
										</td>
									</tr>

									<!-- CONTEST FORTH POINT -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && isset($_POST['contestForthPoint']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Forth point at</th>
										<td>
											<input class="small-text" type="number" id="contestForthPoint" name="contestForthPoint" value="<?php if (isset($_GET['wppc-id'])) echo $contest->forth_point; else echo $_POST['contestForthPoint'] ?>">
											<label for="contestForthPoint"> votes</label>
										</td>
									</tr>

									<!-- CONTEST FIFTH POINT -->
									<tr <?php $_POST && !isset($_GET['wppc-id']) && isset($_POST['contestFifthPoint']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Fifth point at</th>
										<td>
											<input class="small-text" type="number" id="contestFifthPoint" name="contestFifthPoint" value="<?php if (isset($_GET['wppc-id'])) echo $contest->fifth_point; else echo $_POST['contestFifthPoint'] ?>">
											<label for="contestFifthPoint"> votes</label>
										</td>
									</tr>

									<!-- CONTEST SOCIAL DESCRIPTION -->
									<tr>
										<th scope="row">Social Description</td>
										<td>
											<input class="regular-text" type="text" id="contestSocialDescription" name="contestSocialDescription" value="<?php if (isset($_GET['wppc-id'])) echo $contest->contest_social_description; else echo $_POST['contestSocialDescription'] ?>" />
											<label for="contestSocialDescription">text to appear when sharing on Facebook/Pinterest</label>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<!-- CONTEST TABS -->
						<idv id="contest-tabs">
							<table class="form-table">
								<tbody>
									<!-- CONTEST ABOUT TAB -->
									<tr>
										<th scope="row">About Tab</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']))
													wp_editor($_POST && $_POST['contestAbout'] ? $_POST['contestAbout'] : "", "contestAbout");
												else
													wp_editor($contest->contest_about, "contestAbout");
											?>
										</td>
									</tr>
								
									<!-- CONTEST PHOTO GALLERY TAB -->
									<tr>
										<th class="row">Photo Gallery Tab</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']))
													wp_editor($_POST && $_POST['contestPhotoGallery'] ? $_POST['contestPhotoGallery'] : "", "contestPhotoGallery");
												else
													wp_editor($contest->contest_photo_gallery, "contestPhotoGallery");
											?>
										</td>
									</tr>

									<!-- CONTEST WINNERS TAB -->
									<tr>
										<th class="row">Winners Tab</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']))
													wp_editor($_POST && $_POST['contestWinners'] ? $_POST['contestWinners'] : "", "contestWinners");
												else
													wp_editor($contestWinners['text'], "contestWinners");
											?>
										</td>
									</tr>
									
									<!-- CONTEST EN RULES -->
									<tr>
										<th class="row">English Rules</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']) || empty($contestRules['en']))
													wp_editor($_POST && $_POST['contestRulesEn'] ? $_POST['contestRulesEn'] : "", "contestRulesEn");
												else
													wp_editor($contestRules['en'], "contestRulesEn");
											?>
										</td>
									</tr>

									<!-- CONTEST RO RULES -->
									<tr>
										<th class="row">Romanian Rules</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']) || empty($rules['ro']))
													wp_editor($_POST && $_POST['contestRulesRo'] ? $_POST['contestRulesRo'] : "", "contestRulesRo");
												else
													wp_editor($contestRules['ro'], "contestRulesRo");
											?>
										</td>
									</tr>

									<!-- CONTEST PRIZES TAB -->
									<tr>
										<th class="row">Prizes Tab</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']))
													wp_editor($_POST && $_POST['contestPrizes'] ? $_POST['contestPrizes'] : "", "contestPrizes");
												else
													wp_editor($contest->contest_prizes, "contestPrizes");
											?>
										</td>
									</tr>

									<!-- CONTEST ENTRY FORM TAB -->
									<tr>
										<th class="row">Entry Form Tab</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']))
													wp_editor($_POST && $_POST['contestEntryForm'] ? $_POST['contestEntryForm'] : "", "contestEntryForm");
												else
													wp_editor($contest->contest_entry_form, "contestEntryForm");
											?>
										</td>
									</tr>

									<!-- CONTEST CONTACT TAB -->
									<tr>
										<th class="row">Contact Tab</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']))
													wp_editor($_POST && $_POST['contestContact'] ? $_POST['contestContact'] : "", "contestContact");
												else
													wp_editor($contest->contest_contact, "contestContact");
											?>
										</td>
									</tr>
								</tbody>
							</table>
						</idv>

						<!-- CONTEST SIDEBAR TAB -->
						<div id="sidebar">
							<table class="form-table">
								<tbody>
									<tr>
										<th class="row">Sidebar</th>
										<td>
											<?php
											if (!isset($_GET['wppc-id']))
													wp_editor($_POST && $_POST['contestSidebar'] ? $_POST['contestSidebar'] : "", "contestSidebar");
												else
													wp_editor($contest->contest_sidebar, "contestSidebar");
											?>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<!-- CONTEST EMAILS -->
						<div id="emails">
							<table class="form-table">
								<h3 style="text-align: center;">PHOTO ADMITTED</h3>
								<tr>
									<th class="row">Subject</th>
									<td>
										<input type="text" class="regular-text" name="contestAdmitPhotoSubject" id="contestAdmitPhotoSubject" value="<?php if (isset($_GET['wppc-id'])) echo $contestEmails['contestAdmitPhotoSubject']; elseif ($_POST) echo $_POST['contestAdmitPhotoSubject']; ?>" />
										<label for="contestAdmitPhotoSubject"> email's subject</label>
									</td>
								</tr>
								<tr>
									<th class="row">Body</th>
									<td>
										<?php
											if (!isset($_GET['wppc-id']))
													wp_editor($_POST && $_POST['contestAdmitPhotoBody'] ? $_POST['contestAdmitPhotoBody'] : "", "contestAdmitPhotoBody");
												else
													wp_editor($contestEmails['contestAdmitPhotoBody'], "contestAdmitPhotoBody");
											?>
										<input type="submit" class="button-secondary" name="send-admit-test" id="send-admit-test" value="Send Test Email" />
										<span id="admit-result"></span>
									</td>
								</tr>
							</table>
						</div>

						<!-- SELECT WINNERS -->
						<?php if (isset($_GET['wppc-id'])): ?>
						<div id="select-winners">
							<table class="form-table">
								<!-- FIRST PRIZE -->
								<tr>
									<th class="row">First place</th>
									<td>
										<select name="contestFirstPrizeWinner" id="contestFirstPrizeWinner">
											<option value="0"<?php echo $contestWinners['contestFirstPrizeWinner'] == 0 ? ' selected' : '' ?>>Select</option>
											<?php
												foreach ($photos as $photo):
													$selected = $contestWinners['contestFirstPrizeWinner'] == $photo->photo_id ? ' selected' : '';
													echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
												endforeach;
											?>
										</select>
									</td>
								</tr>

								<!-- SECOND PRIZE -->
								<tr>
									<th class="row">Second place</th>
									<td>
										<select name="contestSecondPrizeWinner" id="contestSecondPrizeWinner">
											<option value="0"<?php echo $contestWinners['contestSecondPrizeWinner'] == 0 ? ' selected' : '' ?>>Select</option>
											<?php
												foreach ($photos as $photo):
													$selected = $contestWinners['contestSecondPrizeWinner'] == $photo->photo_id ? ' selected' : '';
													echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
												endforeach;
											?>
										</select>
									</td>
								</tr>

								<!-- THIRD PRIZE -->
								<tr>
									<th class="row">Third place</th>
									<td>
										<select name="contestThirdPrizeWinner" id="contestThirdPrizeWinner">
											<option value="0"<?php echo $contestWinners['contestThirdPrizeWinner'] == 0 ? ' selected' : '' ?>>Select</option>
											<?php
												foreach ($photos as $photo):
													$selected = $contestWinners['contestThirdPrizeWinner'] == $photo->photo_id ? ' selected' : '';
													echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
												endforeach;
											?>
										</select>
									</td>
								</tr>

								<!-- SPECIAL PRIZE -->
								<tr>
									<th class="row">Special place</th>
									<td>
										<select name="contestSpecialPrizeWinner" id="contestSpecialPrizeWinner">
											<option value="0"<?php echo $contestWinners['contestSpecialPrizeWinner'] == 0 ? ' selected' : '' ?>>Select</option>
											<?php
												foreach ($photos as $photo):
													$selected = $contestWinners['contestSpecialPrizeWinner'] == $photo->photo_id ? ' selected' : '';
													echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
												endforeach;
											?>
										</select>
									</td>
								</tr>

								<!-- OUR FAVORITES -->
								<tr>
									<th class="row">Our favorites</th>
									<td>
										<select name="contestOurFavorites[]" multiple>
											<?php
												foreach ($photos as $photo):
													$selected = in_array($photo->photo_id, $contestWinners['contestOurFavorites']) ? ' selected' : '';
													echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
												endforeach;
											?>
										</select>
									</td>
								</tr>
							</table>
						</div>
						<?php endif; ?>

						<!-- SUBMIT BUTTON -->
						<input type="hidden" name="action" value="add_new_wppc" />
						<?php
							if (isset($_GET['wppc-id'])):
									?><input type="submit" name="addNewContest" id="addNewContest" class="button button-primary" value="Update Contest" /><?php
								else:
									?><input type="submit" name="addNewContest" id="addNewContest" class="button button-primary" value="Add Contest" /><?php
							endif;
						?>
					</form>
				</div>
				<?php
			}

			/**
			 * CALLBACK AJAX FUNCTION TO TEST EMAIL
			 */
			public function testAdmitEmail()
			{

				$currentUser = wp_get_current_user();
				
				// init mail
				include_once(WPPC_DIR.'php/PHPMailer/PHPMailerAutoload.php');					
				$mail = new PHPMailer();
				$mail->IsSMTP();
				$mail->SMTPDebug = 0; // 1 tells it to display SMTP errors and messages, 0 turns off all errors and messages, 2 prints messages only.
				$mail->Host = ini_get('SMTP');
				$mail->IsHTML(true);
				$mail->From = "wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']);
				$mail->FromName = get_bloginfo();
				$mail->Subject = esc_attr($_POST['subject']);
				$mail->addCC($currentUser->user_email, $currentUser->user_firstname.' '.$currentUser->user_lastname);
				$mail->Body = wp_kses($_POST['body'], $this->expanded_alowed_tags());
					
				// send mail
				if (!$mail->Send()) $ajaxResponse = $mail->ErrorInfo;
					else $ajaxResponse = ' Email sent to '.$currentUser->user_email;
				$mail->ClearAllRecipients();

				// return ajax response
				echo $ajaxResponse;

				// terminate
				die;
			}

			/**
			 * VIEW/APPROVE/TRASH CONTEST PHOTOS
			 */
			private function editeContestPhotos()
			{
				global $wpdb;
				$tableName = $wpdb->prefix.'wppc_contests_entries';
				$wpDir = wp_upload_dir();
				$contestDir = $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$_GET['wppc-id'].'/';
				$contestPath = $wpDir['path'].'/wppc-photos/wppc-photos-'.$_GET['wppc-id'].'/';
				$contest = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix.'wppc_contests_all'." WHERE id=".$_GET['wppc-id']);
				$contestEmails = unserialize($contest->contest_emails);

				// APPROVE PHOTO
				if (isset($_GET['action']) && $_GET['action'] == 'approve'):
					
					// update the database to add the photo to the contest
					$wpdb->update($wpdb->prefix.'wppc_contests_entries',
						array('visible' => 1),
						array('photo_id' => $_GET['photoid'], 'contest_id' => $_GET['wppc-id'])
					);

					// init mail
					include_once(WPPC_DIR.'php/PHPMailer/PHPMailerAutoload.php');					
					$mail = new PHPMailer();
					$mail->IsSMTP();
					$mail->SMTPDebug = 0; // 1 tells it to display SMTP errors and messages, 0 turns off all errors and messages, 2 prints messages only.
					$mail->Host = ini_get('SMTP');
					$mail->IsHTML(true);
					$mail->From = "wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']);
					$mail->FromName = get_bloginfo();
					$mail->Subject = $contestEmails['contestAdmitPhotoSubject'];
					$mail->AddCC($_GET['email'], $_GET['name']);
					$mail->Body = $_POST['contestAdmitPhotoBody'];
					
					// send mail
					if (!$mail->Send()) echo $mail->ErrorInfo();
					$mail->ClearAllRecipients();
				endif;


				// TRASH PHOTO
				if (isset($_GET['action']) && $_GET['action'] == 'trash'):
					$wpdb->update($tableName, array('visible' => -1), array('photo_id' => $_GET['photoid']));
				endif;


				// RESTORE PHOTO
				if (isset($_GET['action']) && $_GET['action'] == 'restore'):
					$wpdb->update($tableName, array('visible' => 0), array('photo_id' => $_GET['photoid']));
				endif;


				// DELETE PHOTO
				if (isset($_GET['action']) && $_GET['action'] == 'delete'):
					
					// change file permissions
					chmod($contestPath.'raw/'.$_GET['file'], 0755);
					chmod($contestPath.'full/'.$_GET['file'], 0755);
					chmod($contestPath.'medium/'.$_GET['file'], 0755);
					chmod($contestPath.'thumbs/'.$_GET['file'], 0755);
					
					// delete all photo's versions
					if (is_writable($contestPath.'raw/'.$_GET['file'])):
						if (file_exists($contestPath.'raw/'.$_GET['file'])) unlink($contestPath.'raw/'.$_GET['file']);
						if (file_exists($contestPath.'full/'.$_GET['file'])) unlink($contestPath.'full/'.$_GET['file']);					
						if (file_exists($contestPath.'medium/'.$_GET['file'])) unlink($contestPath.'medium/'.$_GET['file']);
						if (file_exists($contestPath.'thumbs/'.$_GET['file'])) unlink($contestPath.'thumbs/'.$_GET['file']);
					endif;

					// delete database entry
					$wpdb->delete($tableName, array('contest_id' => $_GET['wppc-id'], 'photo_id' => $_GET['photoid']));
				endif;

				// get all photos
				$photos = array_reverse($wpdb->get_results('SELECT * FROM '.$tableName.' WHERE contest_id='.$_GET['wppc-id']));
				
				$trashed = 0;
				$approved = 0;
				$new = 0;

				foreach ($photos as $photo):
					if ($photo->visible == -1) $trashed++;
					if ($photo->visible == 0) $new++;
					if ($photo->visible == 1) $approved++;
				endforeach;

				$items = 0;
				if (!isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] == 'new')) $items = $new;
				if (isset($_GET['status']) && $_GET['status'] == 'approve') $items = $approved;
				if (isset($_GET['status']) && $_GET['status'] == 'trash') $items = $trashed;
				?>

				<ul class="subsubsub">
					<li class="all"><a href="?page=wppc-contest&amp;wppc-id=<?php echo $_GET['wppc-id'] ?>&amp;wppc-action=view&amp;status=new" <?php echo !isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] == 'new')  ? 'class="current"' : '' ?>>New <span class="count">(<?php echo $new ?>)</span></a></li>
					<?php if ($approved > 0): ?>
						<li class="trash"> | <a href="?page=wppc-contest&amp;wppc-id=<?php echo $_GET['wppc-id'] ?>&amp;wppc-action=view&amp;status=approve" <?php echo isset($_GET['status']) && $_GET['status'] == 'approve'  ? 'class="current"' : '' ?>>Approved <span class="count">(<?php echo $approved ?>)</span></a></li>
					<?php endif; ?>
					<?php if ($trashed > 0): ?>
						<li class="trash"> | <a href="?page=wppc-contest&amp;wppc-id=<?php echo $_GET['wppc-id'] ?>&amp;wppc-action=view&amp;status=reject" <?php echo isset($_GET['status']) && $_GET['status'] == 'reject'  ? 'class="current"' : '' ?>>Rejected <span class="count">(<?php echo $trashed ?>)</span></a></li>
					<?php endif; ?>
				</ul>

				<form method="post" action="">
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<select name="camera-type">
								<option value="-1" <?php echo !isset($_POST['camera-type']) || $_POST['camera-type'] == -1 ? 'selected' : '' ?>>All devices</option>
								<option value="0" <?php echo isset($_POST['camera-type']) && $_POST['camera-type'] == 0 ? 'selected' : '' ?>>Camera only</option>
								<option value="1" <?php echo isset($_POST['camera-type']) && $_POST['camera-type'] == 1 ? 'selected' : '' ?>>Mobile only</option>
							</select>
							<input type="submit" class="button action" name="filter-device" value="Filter" />
						</div>
					</div>
				</form>

				<div id="wppc-overlay" style="display: none;"></div>
				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th class="row-title">Photo</th>
							<th class="row-title">File</th>
							<th class="row-title">Uploaded by</th>
							<th class="row-title">Photo name</th>
							<th class="row-title">Photo location</th>
							<th class="row-title">Upload date</th>
						</tr>
					</thead>
					<tbody>
						<?php
							$device = isset($_POST['filter-device']) ? $_POST['camera-type'] : -1;
						
							// NEW PHOTOS
							if (!isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] == 'new')):
								$counter = $this->printPhotos($photos, 0, $device, $contestDir);
							endif;

							// APPROVED PHOTOS
							if (isset($_GET['status']) && $_GET['status'] == 'approve'):
								$counter = $this->printPhotos($photos, 1, $device, $contestDir);
							endif;

							// REJECTED PHOTOS
							if (isset($_GET['status']) && $_GET['status'] == 'reject'):
								$counter = $this->printPhotos($photos, -1, $device, $contestDir);
							endif;

							if ($counter == 0) echo '<tr><td>No photos found.</td></tr>';
						?>
					</tbody>
					<tfoot>
						<tr>
							<th class="row-title">Photo</th>
							<th class="row-title">File</th>
							<th class="row-title">Uploaded by</th>
							<th class="row-title">Photo name</th>
							<th class="row-title">Photo location</th>
							<th class="row-title">Upload date</th>
						</tr>
					</tfoot>
				</table>
				<?php
			}

			/**
			 * PRINT PHOTOS
			 */
			private function printPhotos($photos, $status, $device, $contestDir)
			{
				$counter = 0;

				foreach ($photos as $photo):
					if (($device != -1 && $photo->photo_mobile == $device) || $device == -1)
					if ($status == $photo->visible):
						$counter++;

						$html = '<tr';
						if ($counter % 2 == 0) $html .='>';
							else $html .= ' class="alternate">';
						$html .= '<td><a href="'.$contestDir.'raw/'.$photo->competitor_photo.'" target="_blank"><img style="height: 60px;" src="'.$contestDir.'thumbs/'.$photo->competitor_photo.'" /></a></td>';
							
						$html .= '<td>';
							$html .= '<a class="view-photo-details" href="'.$contestDir.'raw/'.$photo->competitor_photo.'" data-photo-id="'.$photo->photo_id.'" target="_blank">'.$photo->competitor_photo.'</a>';
							if ($photo->photo_mobile == 1) $html .= '<p>MOBILE DEVICE';
								else $html .= '<p>PROFESSIONAL CAMERA';
							if ($status == 1) $html .= ' ('.$photo->votes.' votes)</p>';
								else $html .= '</p>';								
							$html .= '<div class="row-actions">';

							switch ($status):
								// trashed photo
								case -1:
									$html .= '<span class="view"><a href="?page=wppc-contest&wppc-id='.$_GET['wppc-id'].'&wppc-action=view&action=restore&photoid='.$photo->photo_id.'">Restore</a> | </span>';
									$html .= '<span class="trash"><a href="?page=wppc-contest&wppc-id='.$_GET['wppc-id'].'&wppc-action=view&action=delete&photoid='.$photo->photo_id.'&file='.$photo->competitor_photo.'">Delete permanently</a></span>';
									break;

								// new photo
								case 0:
									$html .= '<span class="view"><a href="?page=wppc-contest&wppc-id='.$_GET['wppc-id'].'&wppc-action=view&action=approve&photoid='.$photo->photo_id.'&name='.$photo->competitor_name.'&email='.$photo->competitor_email.'">Approve</a> | </span>';
									$html .= '<span class="trash"><a href="?page=wppc-contest&wppc-id='.$_GET['wppc-id'].'&wppc-action=view&action=trash&photoid='.$photo->photo_id.'">Reject</a></span>';
									break;
								
								// approved photo
								case 1:
									$html .= '<span class="view"><a href="'.$contestDir.'raw/'.$photo->competitor_photo.'" download="'.$photo->competitor_photo.'" />Download raw</a> | </span>';
									$html .= '<span class="view"><a href="'.$contestDir.'medium/'.$photo->competitor_photo.'" download="'.$photo->competitor_photo.'" />Download &copy;</a> | </span>';
									$html .= '<span class="view"><a href="#" class="wppc-view" data-photo-id="'.$photo->photo_id.'">View IPs</a></span>';									
									break;
							endswitch;
							
							$html .= '</div>';
						
						$html .= '</td>';
						$html .= '<td><a href="mailto:'.$photo->competitor_email.'" title="'.$photo->competitor_email.'" target="_blank">'.$photo->competitor_name.'</a></td>';
						$html .= '<td>'.$photo->photo_name.'</td>';
						$html .= '<td>'.$photo->photo_location.'</td>';
						$html .= '<td>'.$photo->upload_date.'</td>';
						$html .= '</tr>';

						echo $html;
					endif;
				endforeach;

				return $counter;
			}

			/**
			 * CALLBACK FUNCTION TO DISPLAY PHOTO SPECS VIA AJAX
			 */
			public function viewPhotoSpecs()
			{
				// get photo url
				$photo = $_POST['photo'];

				// get photo specs
				$photoDetails = exif_read_data($photo);
				$primaryDetails = array('FileName', 'DateTimeOriginal', 'Make', 'Model', 'MimeType', 'ExposureTime', 'FNumber', 'ISOSpeedRatings', 'ShutterSpeedValue', 'Flash');
				
				// get photo upload date from db
				global $wpdb;
				$photoUploadDateTime = $wpdb->get_var("SELECT upload_date FROM {$wpdb->prefix}wppc_contests_entries WHERE photo_id={$_POST['photoid']}");


				// create ajax response
				$ajaxResponse = '';

				$ajaxResponse .= '<div id="wppc-details">';
					$ajaxResponse .= '<a href="#"><div class="dashicons dashicons-no" style="position: absolute; top: 10px; right: 10px;"></div></a>';
					$ajaxResponse .= '<div class="entered-photo" style="display: table-cell; width: 50%; vertical-align: top; padding: 3rem;"><img src="'.$photo.'" style="max-width: 100%;" /></div>';
					$ajaxResponse .= '<div class="details-photo" style="display: table-cell; width: 50%; vertical-align: top; padding: 3rem;">';
						$ajaxResponse .= '<p>Filename: '.$photoDetails['FileName'].'</p>';
						$ajaxResponse .= '<p>Dimensions: '.$photoDetails['COMPUTED']['Width'].'px x '.$photoDetails['COMPUTED']['Height'].'px</p>';
						$ajaxResponse .= '<p>Original DateTime: '.$photoDetails['DateTimeOriginal'].'</p>';
						$ajaxResponse .= '<p>Upload DateTime: '.$photoUploadDateTime.'</p>';
						$ajaxResponse .= '<p>Camera: '.$photoDetails['Make'].'</p>';
						$ajaxResponse .= '<p>Model: '.$photoDetails['Model'].'</p>';
						$ajaxResponse .= '<p>File Type: '.$photoDetails['MimeType'].'</p>';
						$ajaxResponse .= '<p>Exposure: '.$photoDetails['ExposureTime'].'</p>';
						$ajaxResponse .= '<p>FNumber: '.$photoDetails['FNumber'].'</p>';
						$ajaxResponse .= '<p>ISO: '.$photoDetails['ISOSpeedRatings'].'</p>';
						$ajaxResponse .= '<p>Shutter: '.$photoDetails['ShutterSpeedValue'].'</p>';
						$ajaxResponse .= '<p>Flash: '.$photoDetails['Flash'].'</p>';
					$ajaxResponse .= '</div>';
				$ajaxResponse .= '</div>';


				// return ajax response
				echo $ajaxResponse;

				// terminate
				die;
			}

			/**
			 * CALLBACK FUNCTION TO DiSPLAY PHOTO VOTERS
			 */
			public function viewPhotoVoters()
			{
				// get photo id;
				$photoID = $_POST['photoid'];

				// get voters
				global $wpdb;
				$votes = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix.'wppc_contests_votes'." WHERE photo_id=".$photoID);


				// create ajax response
				$ajaxResponse = '';

				$ajaxResponse .= '<div id="wppc-details">';
					$ajaxResponse .= '<a href="#"><div class="dashicons dashicons-no" style="position: absolute; top: 10px; right: 10px;"></div></a>';
					foreach ($votes as $vote):
						$ajaxResponse .= '<p style="text-align: center;">IP <a href="http://whatismyipaddress.com/ip/'.long2ip($vote->vote_ip).'" target="_blank">'.long2ip($vote->vote_ip).'</a> voted '.$vote->vote_number.' times</p>';
					endforeach;
				$ajaxResponse .= '</div>';

				// return ajax response
				echo $ajaxResponse;

				// terminate
				die;
			}

			/**
			 * VIEW CONTEST STATS
			 */
			private function viewContestStats()
			{
				global $wpdb;
				?>

				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th class="row-title">Property</th>
							<th class="row-title">Value</th>
						</tr>
					</thead>
					<tbody>
						<?php
							// total # of photos
							$totalPhotos = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix.'wppc_contests_entries'." WHERE contest_id=".$_GET['wppc-id']);

							// total # of approved photos
							$approvedPhotos = 0;

							// total contestants
							$contestants = 0;
							$contestantsList = array();

							// number of rejected photos
							$trashedPhotos = 0;

							// mobile photos
							$mobilePhotos = 0;

							foreach($totalPhotos as $photo):
								if ($photo->visible == 1) $approvedPhotos++;
								if (!in_array($photo->competitor_name, $contestantsList)):
									$contestants++;
									$contestantsList[] = $photo->competitor_name;
								endif;
								if ($photo->visible == -1) $trashedPhotos++;
								if ($photo->photo_mobile == 1) $mobilePhotos++;
							endforeach;

							// total # of mobile devices photos
							$cameraPhotos = count($totalPhotos) - $mobilePhotos;

							// total # of voters
							$totalVoters = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix.'wppc_contests_votes'." WHERE contest_id=".$_GET['wppc-id']);
						
							// total # of votes
							$totalVotes = array();

							// unique voters
							$uniqueVoters = array();

							foreach ($totalVoters as $vote):
								$totalVotes[] = $vote->vote_number;
								if (!in_array($vote->vote_ip, $uniqueVoters)) $uniqueVoters[] = $vote->vote_ip;
							endforeach;
						?>
						<tr class="alternate">
							<td>Total # of photos</td>
							<td><?php echo count($totalPhotos) ?></td>
						</tr>

						<tr>
							<td>Total # of approved photos</td>
							<td><?php echo $approvedPhotos . ' ('.number_format(($approvedPhotos*100)/count($totalPhotos), 2).'%)'?></td>
						</tr>

						<tr class="alternate">
							<td>Total # of rejected photos</td>
							<td><?php echo $trashedPhotos . ' ('.number_format(($trashedPhotos*100)/count($totalPhotos), 2).'%)'?></td>
						</tr>

						<tr>
							<td>Total # of camera photos</td>
							<td><?php echo $cameraPhotos . ' ('.number_format(($cameraPhotos*100)/count($totalPhotos), 2).'%)' ?></td>
						</tr>

						<tr class="alternate">
							<td>Total # of mobile photos</td>
							<td><?php echo $mobilePhotos . ' ('.number_format(($mobilePhotos*100)/count($totalPhotos), 2).'%)' ?></td>
						</tr>

						<tr>
							<td>Total # of contestants</td>
							<td><?php echo $contestants ?></td>
						</tr>
						
						<tr class="alternate">
							<td>Total # of unique voters</td>
							<td><?php echo count($uniqueVoters) ?></td>
						</tr>
						
						<tr>
							<td>Total # of votes</td>
							<td><?php echo array_sum($totalVotes) ?></td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<th class="row-title">Property</th>
							<th class="row-title">Value</th>
						</tr>
					</tfoot>
				<?php
			}

			/**
			 * CALLBACK FUNCTION TO ADD/EDIT CONTEST
			 */
			public function addNewWPPCContest()
			{
				global $wpdb;

				// get general inputs
				$contestName = isset($_POST['contestName']) ? esc_attr($_POST['contestName']) : "";
				$contestStartDate = isset($_POST['contestStartDate']) ? explode("-", $_POST['contestStartDate']) : array(0,0,0);
				$contestEndRegistration = isset($_POST['contestEndRegistration']) ? explode("-", $_POST['contestEndRegistration']) : array(0, 0, 0);
				$contestEndVote = isset($_POST['contestEndVote']) ? explode("-", $_POST['contestEndVote']) : array(0, 0, 0);
				$contestEndDate = isset($_POST['contestEndDate']) ? explode("-", $_POST['contestEndDate']) : array(0, 0, 0);
				$contestPhotosAllowed = isset($_POST['contestPhotosAllowed']) ? absint($_POST['contestPhotosAllowed']) : 0;
				$contestPhotosMobileAllowed = isset($_POST['contestPhotosMobileAllowed']) ? absint($_POST['contestPhotosMobileAllowed']) : 0;
				$contestVotesAllowed = isset($_POST['contestVotesAllowed']) ? absint($_POST['contestVotesAllowed']) : 0;
				$contestFirstPoint = isset($_POST['contestFirstPoint']) ? absint($_POST['contestFirstPoint']) : 0;
				$contestSecondPoint = isset($_POST['contestSecondPoint']) ? absint($_POST['contestSecondPoint']) : 0;
				$contestThirdPoint = isset($_POST['contestThirdPoint']) ? absint($_POST['contestThirdPoint']) : 0;
				$contestForthPoint = isset($_POST['contestForthPoint']) ? absint($_POST['contestForthPoint']) : 0;
				$contestFifthPoint = isset($_POST['contestFifthPoint']) ? absint($_POST['contestFifthPoint']) : 0;
				$contestSocialDescription = isset($_POST['contestSocialDescription']) ? wp_kses($_POST['contestSocialDescription'], $this->expanded_alowed_tags()) : '';

				// convert dates for later check
				$contestStartDate = $this->makeInt($contestStartDate);
				$contestEndRegistration = $this->makeInt($contestEndRegistration);
				$contestEndVote = $this->makeInt($contestEndVote);
				$contestEndDate = $this->makeInt($contestEndDate);

				// get about contest
				$contestAbout = isset($_POST['contestAbout']) ? wp_kses($_POST['contestAbout'], $this->expanded_alowed_tags()) : "";

				// get photo gallery
				$contestPhotoGallery = isset($_POST['contestPhotoGallery']) ? wp_kses($_POST['contestPhotoGallery'], $this->expanded_alowed_tags()) : "";

				// get winners tab & winners photos
				$contestWinners = array();
				$contestWinners['text'] = isset($_POST['contestWinners']) ? wp_kses($_POST['contestWinners'], $this->expanded_alowed_tags()) : "";
				$contestWinners['contestFirstPrizeWinner'] = isset($_POST['contestFirstPrizeWinner']) ? $_POST['contestFirstPrizeWinner'] : 0;
				$contestWinners['contestSecondPrizeWinner'] = isset($_POST['contestSecondPrizeWinner']) ? $_POST['contestSecondPrizeWinner'] : 0;
				$contestWinners['contestThirdPrizeWinner'] = isset($_POST['contestThirdPrizeWinner']) ? $_POST['contestThirdPrizeWinner'] : 0;
				$contestWinners['contestSpecialPrizeWinner'] = isset($_POST['contestSpecialPrizeWinner']) ? $_POST['contestSpecialPrizeWinner'] : 0;
				$contestWinners['contestOurFavorites'] = isset($_POST['contestOurFavorites']) ? $_POST['contestOurFavorites'] : 0;

				// get rules
				$contestRulesEn = isset($_POST['contestRulesEn']) ? wp_kses($_POST['contestRulesEn'], $this->expanded_alowed_tags()) : "";
				$contestRulesRo = isset($_POST['contestRulesRo']) ? wp_kses($_POST['contestRulesRo'], $this->expanded_alowed_tags()) : '';

				// get prizes
				$contestPrizes = isset($_POST['contestPrizes']) ? wp_kses($_POST['contestPrizes'], $this->expanded_alowed_tags()) : "";
				
				// get entry form
				$contestEntryForm = isset($_POST['contestEntryForm']) ? wp_kses($_POST['contestEntryForm'], $this->expanded_alowed_tags()) : '';
				
				// get contact
				$contestContact = isset($_POST['contestContact']) ? wp_kses($_POST['contestContact'], $this->expanded_alowed_tags()) : "";

				// get sidebar
				$contestSidebar = isset($_POST['contestSidebar']) ? wp_kses($_POST['contestSidebar'], $this->expanded_alowed_tags()) : "";

				// get emails details
				$contestEmails = array();
				$contestEmails['contestAdmitPhotoSubject'] = isset($_POST['contestAdmitPhotoSubject']) ? esc_attr($_POST['contestAdmitPhotoSubject']) : "";
				$contestEmails['contestAdmitPhotoBody'] = isset($_POST['contestAdmitPhotoBody']) ? wp_kses($_POST['contestAdmitPhotoBody'], $this->expanded_alowed_tags()) : "";


				// input verification
				$readyForUpload = true;
				if ($contestName == '') $readyForUpload = false;
				if (!checkdate((int)$contestStartDate[1], (int)$contestStartDate[2], (int)$contestStartDate[0])) $readyForUpload = false;
				if (!checkdate((int)$contestEndRegistration[1], (int)$contestEndRegistration[2], (int)$contestEndRegistration[0])) $readyForUpload = false;
				if (!checkdate((int)$contestEndVote[1], (int)$contestEndVote[2], (int)$contestEndVote[0])) $readyForUpload = false;
				if (!checkdate((int)$contestEndDate[1], (int)$contestEndDate[2], (int)$contestEndDate[0])) $readyForUpload = false;
				if (absint($contestPhotosAllowed) <= 0) $readyForUpload = false;
				if (absint($contestVotesAllowed) <= 0) $readyForUpload = false;

				// serialize rules to insert into the database
				$rules = array();
				$rules['en'] = $contestRulesEn;
				$rules['ro'] = $contestRulesRo;
				$rules = serialize($rules);

				// all inputs validated, add to database
				if ($readyForUpload && !isset($_GET['wppc-action']) && !isset($_GET['wppc-id'])):
					$wpdb->insert($wpdb->prefix.'wppc_contests_all',
									array(
										'contest_name' => $contestName,
										'start_date' => $_POST['contestStartDate'],
										'end_registration' => $_POST['contestEndRegistration'],
										'end_vote' => $_POST['contestEndVote'],
										'end_date' => $_POST['contestEndDate'],
										'photos_allowed' => $contestPhotosAllowed,
										'photos_mobile_allowed' => $contestPhotosMobileAllowed,
										'votes_allowed' => $contestVotesAllowed,
										'first_point' => $contestFirstPoint,
										'second_point' => $contestSecondPoint,
										'third_point' => $contestThirdPoint,
										'forth_point' => $contestForthPoint,
										'fifth_point' => $contestFifthPoint,
										'contest_social_description' => $contestSocialDescription,
										'contest_about' => $contestAbout,
										'contest_photo_gallery' => $contestPhotoGallery,
										'contest_winners' => serialize($contestWinners),
										'contest_rules' => $rules,
										'contest_prizes' => $contestPrizes,
										'contest_entry_form' => $contestEntryForm,
										'contest_contact' => $contestContact,
										'contest_sidebar' => $contestSidebar,
										'contest_emails' => serialize($contestEmails),
									)
								);

					// create folders for user uploads
					$upload_dir = wp_upload_dir();
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$wpdb->insert_id.'/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$wpdb->insert_id.'/raw/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$wpdb->insert_id.'/full/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$wpdb->insert_id.'/medium/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$wpdb->insert_id.'/thumbs/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);

					// update url to reflect changes
					header("Location: ".add_query_arg(array("wppc-action" => "edit", "wppc-id" => $wpdb->insert_id, 'wppc-perform' => 'added'), get_permalink()));
					exit;
				endif;

				// update database
				if ($readyForUpload && isset($_GET['wppc-id'])):
					$wpdb->update($wpdb->prefix.'wppc_contests_all',
									array(
										'contest_name' => $contestName,
										'start_date' => $_POST['contestStartDate'],
										'end_registration' => $_POST['contestEndRegistration'],
										'end_vote' => $_POST['contestEndVote'],
										'end_date' => $_POST['contestEndDate'],
										'photos_allowed' => $contestPhotosAllowed,
										'votes_allowed' => $contestVotesAllowed,
										'photos_mobile_allowed' => $contestPhotosMobileAllowed,
										'first_point' => $contestFirstPoint,
										'second_point' => $contestSecondPoint,
										'third_point' => $contestThirdPoint,
										'forth_point' => $contestForthPoint,
										'fifth_point' => $contestFifthPoint,
										'contest_social_description' => $contestSocialDescription,
										'contest_about' => $contestAbout,
										'contest_photo_gallery' => $contestPhotoGallery,
										'contest_winners' => serialize($contestWinners),
										'contest_rules' => $rules,
										'contest_prizes' => $contestPrizes,
										'contest_entry_form' => $contestEntryForm,
										'contest_contact' => $contestContact,
										'contest_sidebar' => $contestSidebar,
										'contest_emails' => serialize($contestEmails),
									),
									array('id' => $_GET['wppc-id'])
								);

					// create folders for user uploads
					$upload_dir = wp_upload_dir();
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$_GET['wppc-id'].'/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$_GET['wppc-id'].'/raw/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$_GET['wppc-id'].'/full/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$_GET['wppc-id'].'/medium/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);
					$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$_GET['wppc-id'].'/thumbs/';
					if (!is_dir($dir))
						wp_mkdir_p($dir);

					// update url to reflect changes
					header("Location: ".add_query_arg(array("wppc-action" => "edit", "wppc-id" => $_GET['wppc-id'], 'wppc-perform' => 'edited'), get_permalink()));
					exit;
				endif;
			}


			/**
			 * MAKE AN ARRAY OF INTs
			 * @param  array  $array array of strings to be changed == array(item1, item2, item3)
			 * @return array 		 array of int
			 */
			private function makeInt($array = array())
			{
				return array((int)$array[0], (int)$array['1'], (int)$array['2']);
			}

			function expanded_alowed_tags() 
			{
				$my_allowed = wp_kses_allowed_html('post');
				// iframe
				$my_allowed['iframe'] = array(
					'src'             => array(),
					'height'          => array(),
					'width'           => array(),
					'frameborder'     => array(),
					'allowfullscreen' => array(),
					);
				// form fields - input
				$my_allowed['input'] = array(
					'class' => array(),
					'id'    => array(),
					'name'  => array(),
					'value' => array(),
					'type'  => array(),
					);
				// select
				$my_allowed['select'] = array(
					'class'  => array(),
					'id'     => array(),
					'name'   => array(),
					'value'  => array(),
					'type'   => array(),
					);
				// select options
				$my_allowed['option'] = array(
					'selected' => array(),
					);
				// style
				$my_allowed['style'] = array(
					'types' => array(),
					);

				// table
				$my_allowed['table'] = array(
					'thead' => array(),
					'tbody' => array(),
					'tfoot' => array(),
					'tr'	=> array(),
					'td'	=> array(),
					);

				return $my_allowed;
			}
		}
	endif;	

?>