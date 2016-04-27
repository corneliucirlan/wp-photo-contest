<?php

	// Security check
	if (!defined('ABSPATH')) die;

	// Define class
	if (!class_exists('WPPCContest')):
		class WPPCContest 
		{
			/**
			 * Class constructor
			 *
			 * @since 1.0
			 */
			public function __construct()
			{
				// Load admin scripts
				add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));				 

				// Add menu page
				add_action('admin_menu', array($this, 'renderMenuItems')); 
				
				// SAVE/UPDATE NEW CONTEST IN THE DATABASE
				//add_action('admin_post_save-wp-photo-contest', array($this, 'saveWPPContest'));
				//do_action("admin_post_save-wp-photo-contest");

				// Ajax callback to test photo admit
				add_action('wp_ajax_test-admit-email', array($this, 'testAdmitEmail'));
			}


			/**
			 * Render admin menu item
			 *
			 * @since 1.0
			 */
			public function renderMenuItems()
			{
				add_submenu_page('wppc-all-contests', 'Photo Contest', 'Contest', 'manage_options', 'wppc-contest', array($this, 'renderPage'));
			}


			/**
			 * Enqueue admin scripts
			 *
			 * @since 1.0
			 */
			public function enqueueAdminScripts()
			{
				// jQuery core
				wp_enqueue_script('jquery', '', '', '', true);
				
				// jQuery UI core
				wp_enqueue_script('jquery-ui-core', array('jquery'), '', '',true);

				// jQuery UI Tabs
				wp_enqueue_script('jquery-ui-tabs', array('jquery-ui-core'), '', '', true);
				
				// Ajax callbacks script
				wp_enqueue_script('wppc-contest-js', WPPC_URI.'js/wppc-contest.js', array('jquery'), WPPC_VERSION, true);
			}


			/**
			 * Render new contest/edit existing contest
			 *
			 * @since 1.0
			 */
			public function renderPage()
			{
				?>
				<div class="wrap">
					<?php
						$this->setTitle();

						if (!isset($_GET['contest'])):
								$this->editContest();
							else:
								switch ($_GET['activity']):

									// Edit contest
									case 'edit': $this->editContest(); break;

									// Contest stats
									case 'stats': $this->viewStats(); break;

									// New contest
									default: $this->editContest(); break;
								endswitch;
						endif;
					?>
				</div>
				<?php
			}


			/**
			 * Set page title
			 *
			 * @since 1.0
			 */
			private function setTitle()
			{
				global $wpdb;

				echo '<h2>';
				if (!isset($_GET['contest'])):
						_e("New Contest");
					else:
						switch ($_GET['activity']):

							// Edit contest
							case 'edit': echo __("Edit").' <strong>'.__($wpdb->get_var("SELECT contest_name FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest'])).'</strong> '.__('Contest'); break;

							// Contest stats
							case 'stats': echo __("View").' <strong>'.__($wpdb->get_var("SELECT contest_name FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest'])).'</strong> '.__('Stats'); break;

							// Default: New contest
							default: _e("New Contest"); break;
						endswitch;
				endif;
				echo '</h2>';
			}


			/**
			 * Edit contest
			 *
			 * @since 1.0
			 */
			private function editContest()
			{
				global $wpdb;
				
				// Get contest if set
				if (isset($_GET['contest'])) $contest = $wpdb->get_row("SELECT * FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest']);

				// Validate all dates				
				$contestStartDate = isset($_POST['start-date']) ? explode("-", $_POST['start-date']) : '';
				$contestEndRegistration = isset($_POST['end-registration']) ? explode("-", $_POST['end-registration']) : '';
				$contestEndVote = isset($_POST['end-vote']) ? explode("-", $_POST['end-vote']) : '';
				$contestEndDate = isset($_POST['end-date']) ? explode("-", $_POST['end-date']) : '';
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
					if (checkdate($contestEndVote[1], $contestEndVote[2], $contestEndVote[0]))
						$validEndVote = true;
				$validEndDate = false;
				if ($contestEndDate != "")
					if (checkdate($contestEndDate[1], $contestEndDate[2], $contestEndDate[0]))
						$validEndDate = true;
				
				// Get current contest photos
				$photos = isset($_GET['contest']) ? $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id='".$_GET['contest']."' AND visible=".WPPC_PHOTO_APPROVED) : array();

				// Unserialize data
				$contestWinners = isset($_GET['contest']) && $contest->contest_winners != '' ? unserialize($contest->contest_winners) : array();
				$contestEmails = isset($_GET['contest']) && $contest->contest_emails != '' ? unserialize($contest->contest_emails) : array();
				$contestRules = isset($_GET['contest']) && $contest->contest_rules != '' ? unserialize($contest->contest_rules) : array();
				
				?>
				<div id="tabs">
					<ul class="nav-tab-wrapper" style="border-bottom: 1px solid #ddd;">
						<li class="nav-tab nav-tab-active"><a href="#general">General</a></li>
						<li class="nav-tab"><a href="#contest-tabs">Contest tabs</a></li>
						<li class="nav-tab"><a href="#sidebar">Sidebar</a></li>
						<li class="nav-tab"><a href="#emails">Emails</a></li>
						<?php if (isset($_GET['contest'])): ?><li class="nav-tab"><a href="#select-winners">Select winners</a></li><?php endif; ?>
					</ul>
					
					<form method="post" action="">
						<div id="general">
							<table class="form-table">
								<tbody>
				
									<!-- Contest name -->
									<tr <?php $_POST && isset($_POST['name']) == '' && !isset($_GET['contest']) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Contest name</th>
										<td>
											<input type="text" id="name" name="name" value="<?php if (isset($_GET['contest'])) echo $contest->contest_name; elseif ($_POST) echo $_POST['name'] ?>">
											<label for="name"> The name of the contest</label>
										</td>
									</tr>

									<!-- Contest start -->
									<tr <?php $_POST && !isset($_GET['contest']) && (isset($_POST['start-date']) == '' || !$validStartDate) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Start date</th>
										<td>
											<input type="date" id="start-date" name="start-date" value="<?php if (isset($_GET['contest'])) echo $contest->start_date; elseif ($_POST) echo $_POST['start-date'] ?>">
											<label for="start-date"> The start date of the contest</label>
										</td>
									</tr>

									<!-- Contest end registration -->
									<tr <?php $_POST && !isset($_GET['contest']) && (isset($_POST['end-registration']) == '' || !$validEndRegistration) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Registration end</th>
										<td>
											<input type="date" id="end-registration" name="end-registration" value="<?php if (isset($_GET['contest'])) echo $contest->end_registration; elseif ($_POST) echo $_POST['end-registration']?>">
											<label for="end-registration"> Until when the contestants can enter the contest</label>
										</td>
									</tr>

									<!-- Contest end vote -->
									<tr <?php $_POST && !isset($_GET['contest']) && (isset($_POST['end-vote']) == '' || !$validEndVote) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Vote end</th>
										<td>
											<input type="date" id="end-vote" name="end-vote" value="<?php if (isset($_GET['contest'])) echo $contest->end_vote; elseif ($_POST) echo $_POST['end-vote']?>">
											<label for="end-vote"> until when users can vote</label>
										</td>
									</tr>

									<!-- Contest end -->
									<tr <?php $_POST && !isset($_GET['contest']) && (isset($_POST['end-date']) == '' || !$validEndDate) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">End date</th>
										<td>
											<input type="date" id="end-date" name="end-date" value="<?php if (isset($_GET['contest'])) echo $contest->end_date; elseif ($_POST) echo $_POST['end-date']?>">
											<label for="end-date"> The end date of the contest</label>
										</td>
									</tr>

									<!-- Photos allowed -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['photos-allowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Number of photos</th>
										<td>
											<input class="small-text" type="number" id="photos-allowed" name="photos-allowed" value="<?php if (isset($_GET['contest'])) echo $contest->photos_allowed; elseif ($_POST) echo $_POST['photos-allowed'] ?>">
											<label for="photos-allowed"> Number of photos allowed in the contest</label>
										</td>
									</tr>

									<!-- Mobile photos allowed -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['photos-mobile-allowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Number of mobile photos</th>
										<td>
											<input class="small-text" type="number" id="photos-mobile-allowed" name="photos-mobile-allowed" value="<?php if (isset($_GET['contest'])) echo $contest->photos_mobile_allowed; elseif ($_POST) echo $_POST['photos-mobile-allowed'] ?>">
											<label for="photos-mobile-allowed"> Number of photos taken with a phone allowed in the contest</label>
										</td>
									</tr>

									<!-- Votes allowed -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['votes-allowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Votes allowed</th>
										<td>
											<input class="small-text" type="number" id="votes-allowed" name="votes-allowed" value="<?php if (isset($_GET['contest'])) echo $contest->votes_allowed; elseif ($_POST) echo $_POST['votes-allowed'] ?>">
											<label for="votes-allowed"> How many times a visitor can vote a photo</label>
										</td>
									</tr>

									<!-- First point -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['first-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">First point at</th>
										<td>
											<input class="small-text" type="number" id="first-point" name="first-point" value="<?php if (isset($_GET['contest'])) echo $contest->first_point; elseif ($_POST) echo $_POST['first-point'] ?>">
											<label for="first-point"> votes</label>
										</td>
									</tr>

									<!-- Second point -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['second-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Second point at</th>
										<td>
											<input class="small-text" type="number" id="second-point" name="second-point" value="<?php if (isset($_GET['contest'])) echo $contest->second_point; elseif ($_POST) echo $_POST['second-point'] ?>">
											<label for="second-point"> votes</label>
										</td>
									</tr>

									<!-- Third point -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['third-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Third point at</th>
										<td>
											<input class="small-text" type="number" id="third-point" name="third-point" value="<?php if (isset($_GET['contest'])) echo $contest->third_point; elseif ($_POST) echo $_POST['third-point'] ?>">
											<label for="third-point"> votes</label>
										</td>
									</tr>

									<!-- Forth point -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['forth-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Forth point at</th>
										<td>
											<input class="small-text" type="number" id="forth-point" name="forth-point" value="<?php if (isset($_GET['contest'])) echo $contest->forth_point; elseif ($_POST) echo $_POST['forth-point'] ?>">
											<label for="forth-point"> votes</label>
										</td>
									</tr>

									<!-- Fifth point -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['fifth-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Fifth point at</th>
										<td>
											<input class="small-text" type="number" id="fifth-point" name="fifth-point" value="<?php if (isset($_GET['contest'])) echo $contest->fifth_point; elseif ($_POST) echo $_POST['fifth-point'] ?>">
											<label for="fifth-point"> votes</label>
										</td>
									</tr>

									<!-- Social description -->
									<tr>
										<th scope="row">Social Description</td>
											<td>
												<input class="regular-text" type="text" id="social-description" name="social-description" value="<?php if (isset($_GET['contest'])) echo $contest->contest_social_description; elseif ($_POST) echo $_POST['social-description'] ?>" />
												<label for="social-description">text to appear when sharing on Facebook/Pinterest</label>
											</td>
										</tr>
									</tbody>
								</table>
							</div>

							<!-- Contest tabs -->
							<idv id="contest-tabs">
								<table class="form-table">
									<tbody>

										<!-- About tab -->
										<tr>
											<th scope="row">About Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['about'] ? $_POST['about'] : "", "about");
												else
													wp_editor(($contest->contest_about), "about");
												?>
											</td>
										</tr>

										<!-- Photo gallery tab -->
										<tr>
											<th class="row">Photo Gallery Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['photos'] ? $_POST['photos'] : "", "photos");
												else
													wp_editor($this->formatContent($contest->contest_photo_gallery), "photos");
												?>
											</td>
										</tr>

										<!-- Winners tab -->
										<tr>
											<th class="row">Winners Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['winners'] ? $_POST['winners'] : "", "winners");
												else
													wp_editor($this->formatContent($contestWinners['text']), "winners");
												?>
											</td>
										</tr>

										<!-- English rules -->
										<tr>
											<th class="row">English Rules</th>
											<td>
												<?php
												if (!isset($_GET['contest']) || empty($contestRules['en']))
													wp_editor($_POST && $_POST['rules-en'] ? $_POST['rules-en'] : "", "rules-en");
												else
													wp_editor($this->formatContent($contestRules['en']), "rules-en");
												?>
											</td>
										</tr>

										<!-- Romanian rules -->
										<tr>
											<th class="row">Romanian Rules</th>
											<td>
												<?php
												if (!isset($_GET['contest']) || empty($contestRules['ro']))
													wp_editor($_POST && $_POST['rules-ro'] ? $_POST['rules-ro'] : "", "rules-ro");
												else
													wp_editor($this->formatContent($contestRules['ro']), "rules-ro");
												?>
											</td>
										</tr>

										<!-- Prizes tab -->
										<tr>
											<th class="row">Prizes Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['prizes'] ? $_POST['prizes'] : "", "prizes");
												else
													wp_editor($this->formatContent($contest->contest_prizes), "prizes");
												?>
											</td>
										</tr>

										<!-- Entry form tab -->
										<tr>
											<th class="row">Entry Form Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['entry-form'] ? $_POST['entry-form'] : "", "entry-form");
												else
													wp_editor($this->formatContent($contest->contest_entry_form), "entry-form");
												?>
											</td>
										</tr>

										<!-- Contact tab -->
										<tr>
											<th class="row">Contact Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['contact'] ? $_POST['contact'] : "", "contact");
												else
													wp_editor($this->formatContent($contest->contest_contact), "contact");
												?>
											</td>
										</tr>
									</tbody>
								</table>
							</idv>

							<!-- Sidebar -->
							<div id="sidebar">
								<table class="form-table">
									<tbody>
										<tr>
											<th class="row">Sidebar</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['sponsors'] ? $_POST['sponsors'] : "", "sponsors");
												else
													wp_editor($this->formatContent($contest->contest_sidebar), "sponsors");
												?>
											</td>
										</tr>
									</tbody>
								</table>
							</div>

							<!-- Emails -->
							<div id="emails">
								<table class="form-table">
									<h3 style="text-align: center;">PHOTO ADMITTED</h3>
									<tr>
										<th class="row">Subject</th>
										<td>
											<input type="text" class="regular-text" name="admitted-subject" id="admitted-subject" value="<?php if (isset($_GET['contest'])) echo $contestEmails['admitted-subject']; elseif ($_POST) echo $_POST['admitted-subject']; ?>" />
											<label for="admitted-subject"> email's subject</label>
										</td>
									</tr>
									<tr>
										<th class="row">Body</th>
										<td>
											<?php
											if (!isset($_GET['contest']))
												wp_editor($_POST && $_POST['admitted-body'] ? $_POST['admitted-body'] : "", "admitted-body");
											else
												wp_editor($this->formatContent($contestEmails['admitted-body']), "admitted-body");
											?>
											<input type="submit" class="button-secondary" name="send-admit-test" id="send-admit-test" value="Send Test Email" />
											<span id="admit-result"></span>
										</td>
									</tr>
								</table>
							</div>

							<!-- Select winners -->
							<?php if (isset($_GET['contest'])): ?>
								<div id="select-winners">
									<table class="form-table">
							
										<!-- First place -->
										<tr>
											<th class="row">First place</th>
											<td>
												<select name="first-winner" id="first-winner">
													<option value="0"<?php echo $contestWinners['first-winner'] == 0 ? ' selected' : '' ?>>Select</option>
													<?php
														foreach ($photos as $photo):
															$selected = $contestWinners['first-winner'] == $photo->photo_id ? ' selected' : '';
															echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
														endforeach;
													?>
												</select>
											</td>
										</tr>

										<!-- Second place -->
										<tr>
											<th class="row">Second place</th>
											<td>
												<select name="second-winner" id="second-winner">
													<option value="0"<?php echo $contestWinners['second-winner'] == 0 ? ' selected' : '' ?>>Select</option>
													<?php
														foreach ($photos as $photo):
															$selected = $contestWinners['second-winner'] == $photo->photo_id ? ' selected' : '';
															echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
														endforeach;
													?>
												</select>
											</td>
										</tr>

										<!-- Third place -->
										<tr>
											<th class="row">Third place</th>
											<td>
												<select name="third-winner" id="third-winner">
													<option value="0"<?php echo $contestWinners['third-winner'] == 0 ? ' selected' : '' ?>>Select</option>
													<?php
														foreach ($photos as $photo):
															$selected = $contestWinners['third-winner'] == $photo->photo_id ? ' selected' : '';
															echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
														endforeach;
													?>
												</select>
											</td>
										</tr>

										<!-- Special place -->
										<tr>
											<th class="row">Special place</th>
											<td>
												<select name="special-winner" id="special-winner">
													<option value="0"<?php echo $contestWinners['special-winner'] == 0 ? ' selected' : '' ?>>Select</option>
													<?php
														foreach ($photos as $photo):
															$selected = $contestWinners['special-winner'] == $photo->photo_id ? ' selected' : '';
															echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
														endforeach;
													?>
												</select>
											</td>
										</tr>

										<!-- Our favorites -->
										<tr>
											<th class="row">Our favorites</th>
											<td>
												<select name="our-favorites[]" multiple>
													<?php
														foreach ($photos as $photo):
															$selected = is_array($contestWinners['our-favorites']) && in_array($photo->photo_id, $contestWinners['our-favorites']) ? ' selected' : '';
															echo '<option value="'.$photo->photo_id.'"'.$selected.'>'.$photo->photo_name.' by '.$photo->competitor_name.'</option>';
														endforeach;
													?>
												</select>
											</td>
										</tr>
									</table>
								</div>
							<?php endif; ?>

							<!-- Save contest -->
							<input type="hidden" name="action" value="save-wp-photo-contest" />
							<?php
								if (isset($_GET['contest'])):
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
			 * Contest stats
			 *
			 * @since 1.0
			 */
			private function viewStats()
			{
				global $wpdb;

				// Total # of photos
				$totalPhotos = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$_GET['contest']);

				// Total # of approved photos
				$approvedPhotos = 0;

				// Total contestants
				$contestants = 0;
				$contestantsList = array();

				// Number of rejected photos
				$trashedPhotos = 0;

				// Mobile photos
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

				// Total # of mobile devices photos
				$cameraPhotos = count($totalPhotos) - $mobilePhotos;

				// Total # of voters
				$totalVoters = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_VOTES." WHERE contest_id=".$_GET['contest']);
						
				// Total # of votes
				$totalVotes = array();

				// Unique voters
				$uniqueVoters = array();

				foreach ($totalVoters as $vote):
					$totalVotes[] = $vote->vote_number;
					if (!in_array($vote->vote_ip, $uniqueVoters)) $uniqueVoters[] = $vote->vote_ip;
				endforeach;
				?>

				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th class="row-title">Property</th>
							<th class="row-title">Value</th>
						</tr>
					</thead>
					<tbody>
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
			 * Save contest
			 */
			public function saveWPPContest()
			{
				global $wpdb;

				if ($_POST):

					// Create contest data array
					$contestData = array(
						'contest_name' 					=> isset($_POST['name']) ? esc_attr($_POST['name']) : '',
						'start_date' 					=> isset($_POST['start-date']) ? $_POST['start-date'] : 0,
						'end_registration' 				=> isset($_POST['end-registration']) ? $_POST['end-registration'] : 0,
						'end_vote' 						=> isset($_POST['end-vote']) ? $_POST['end-vote'] : 0,
						'end_date' 						=> isset($_POST['end-date']) ? $_POST['end-date'] : 0,
						'photos_allowed' 				=> isset($_POST['photos-allowed']) ? absint($_POST['photos-allowed']) : 0,
						'photos_mobile_allowed' 		=> isset($_POST['photos-mobile-allowed']) ? absint($_POST['photos-mobile-allowed']) : 0,
						'votes_allowed' 				=> isset($_POST['votes-allowed']) ? absint($_POST['votes-allowed']) : 0,
						'first_point' 					=> isset($_POST['first-point']) ? absint($_POST['first-point']) : 0,
						'second_point' 					=> isset($_POST['second-point']) ? absint($_POST['second-point']) : 0,
						'third_point' 					=> isset($_POST['third-point']) ? absint($_POST['third-point']) : 0,
						'forth_point' 					=> isset($_POST['forth-point']) ? absint($_POST['forth-point']) : 0,
						'fifth_point' 					=> isset($_POST['fifth-point']) ? absint($_POST['fifth-point']) : 0,
						'contest_social_description' 	=> isset($_POST['social-description']) ? $_POST['social-description'] : '',
						'contest_about' 				=> isset($_POST['about']) ? wp_kses($_POST['about'], $this->expanded_alowed_tags()) : '',
						'contest_photo_gallery'			=> isset($_POST['photos']) ? wp_kses($_POST['photos'], $this->expanded_alowed_tags()) : '',
						'contest_winners' 				=> serialize(array(
																'text'					=> isset($_POST['winners']) ? $_POST['winners'] : "",
																'first-winner'			=> isset($_POST['first-winner']) ? $_POST['first-winner'] : 0,
																'second-winner'			=> isset($_POST['second-winner']) ? $_POST['second-winner'] : 0,
																'third-winner'			=> isset($_POST['third-winner']) ? $_POST['third-winner'] : 0,
																'special-winner'		=> isset($_POST['special-winner']) ? $_POST['special-winner'] : 0,
																'our-favorites'			=> isset($_POST['our-favorites']) ? $_POST['our-favorites'] : 0,
															)),
						'contest_rules' 				=> serialize(array(
																'en'	=> isset($_POST['rules-en']) ? wp_kses($_POST['rules-en'], $this->expanded_alowed_tags()) : "",
																'ro'	=> isset($_POST['rules-ro']) ? wp_kses($_POST['rules-ro'], $this->expanded_alowed_tags()) : "",
															)),
						'contest_prizes' 				=> isset($_POST['prizes']) ? wp_kses($_POST['prizes'], $this->expanded_alowed_tags()) : "",
						'contest_entry_form' 			=> isset($_POST['entry-form']) ? wp_kses($_POST['entry-form'], $this->expanded_alowed_tags()) : '',
						'contest_contact' 				=> isset($_POST['contact']) ? wp_kses($_POST['contact'], $this->expanded_alowed_tags()) : "",
						'contest_sidebar' 				=> isset($_POST['sponsors']) ? wp_kses($_POST['sponsors'], $this->expanded_alowed_tags()) : "",
						'contest_emails' 				=> serialize(array(
																'admitted-subject'	=> isset($_POST['admitted-subject']) ? esc_attr($_POST['admitted-subject']) : "",
																'admitted-body'		=> isset($_POST['admitted-body']) ? $_POST['admitted-body'] : "",
															)),
					);

					// Insert/update contest
					if (isset($_POST['addNewContest'])):
						$id = 0;
						
						// Update contest
						if (isset($_GET['contest'])):
								$wpdb->update(WPPC_TABLE_ALL_CONTESTS, $contestData, array('id'=>$_GET['contest']));
								$id = $_GET['contest'];
							
							// Insert new contest
							else:
								$wpdb->insert(WPPC_TABLE_ALL_CONTESTS, $contestData);
								$id = $wpdb->insert_id;
						endif;

						// Create folders for user uploads
						$upload_dir = wp_upload_dir();
						$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$id.'/';
						if (!is_dir($dir))
							wp_mkdir_p($dir);
						$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$id.'/raw/';
						if (!is_dir($dir))
							wp_mkdir_p($dir);
						$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$id.'/full/';
						if (!is_dir($dir))
							wp_mkdir_p($dir);
						$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$id.'/medium/';
						if (!is_dir($dir))
							wp_mkdir_p($dir);
						$dir = $upload_dir['basedir'].'/wppc-photos/wppc-photos-'.$id.'/thumbs/';
						if (!is_dir($dir))
							wp_mkdir_p($dir);
					endif;
				endif;
			}


			/**
			 * Send test admit email
			 *
			 * @since 1.0
			 */
			public function testAdmitEmail()
			{
				// Get current user
				$currentUser = wp_get_current_user();

				// Create email
				$to = $currentUser->user_email;
				$subject = esc_attr($_POST['admitted-subject']);
				$message = esc_attr($_POST['admitted-body']);
				$headers   = array();
				$headers[] = "MIME-Version: 1.0";
				$headers[] = "Content-type: text/html; charset=utf-8";
				$headers[] = "From: {get_bloginfo()} <wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']).">";
				$headers[] = "Subject: {$subject}";
				$headers[] = "X-Mailer: PHP/".phpversion();

				// Send email
				$emailResponse = wp_mail($to, $subject, $message, $headers);
				
				// Ajax response
				$ajaxResponse = ' Email sent to '.$currentUser->user_email;
				
				// Return ajax response and terminate
				die($emailResponse);
			}


			/**
		     * Expand allowed HTML tags
		     *
		     * @since 1.0
		     */
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


			/**
			 * Format content
			 *
			 * @since 1.0
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

		}
	endif;

?>