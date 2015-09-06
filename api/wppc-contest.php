<?php

	// SECURITY CHECK
	if (!defined('ABSPATH')) die;
	
	// PRE-REQUIREMENTS
	//require_once(ABSPATH.'wp-admin/includes/template.php');
	if (!class_exists('WP_List_Table'))
	    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
	//if (!class_exists('WP_Screen'))
	//	require_once( ABSPATH.'wp-admin/includes/screen.php');

	if (!class_exists('WPPCContest')):
		class WPPCContest extends WP_List_Table
		{
			/**
			 * CONSTANT FOR APPROVED PHOTO VALUE
			 */
			const PHOTO_APPROVED = 1;

			/**
			 * CONSTANT FOR NEW PHOTO VALUE
			 */
			const PHOTO_NEW = 0;

			/**
			 * CONSTANT FOR REJECTED PHOTO VALUE
			 */
			const PHOTO_REJECTED = -1;

			/**
			 * CONSTANT FOR MOBILE DEVICE PHOTO
			 */
			const PHOTO_MOBILE_DEVICE = 1;


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
				global $status, $page, $wpdb;

				// initialize params
				$this->contestsTable = $wpdb->prefix.'wppc_contests_all';
				$this->contestEntriesTable = $wpdb->prefix.'wppc_contests_entries';
				$this->contestVotesTable = $wpdb->prefix.'wppc_contests_votes';

				// set parent defaults
				parent::__construct(array(
					'singular'	=> 'Photo',
					'plural'	=> 'Photos',
					'screen'	=> 'photos-list',
					'ajax'		=> false,
				));

				// load admin scripts
				add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));				 

				// add menu page
				add_action('admin_menu', array($this, 'renderMenuItems')); 
				

				// SAVE/UPDATE NEW CONTEST IN THE DATABASE
				add_action('admin_post_save-wp-photo-contest', array($this, 'saveWPPContest'));
				do_action("admin_post_save-wp-photo-contest");

				// ADD HTML TAGS TO ALLOWED LIST
				add_filter('wp_kses_allowed_html', array($this, 'allowedHTMLTags'), 1, 1);
			}


			/**
			 * CALLBACK FUNCTION TO RENDER MENU ITEMS
			 */
			public function renderMenuItems()
			{
				add_submenu_page('wppc-all-contests', 'Photo Contest', 'Contest', 'manage_options', 'wppc-contest', array($this, 'displayWPPContest'));
			}


			/**
			 * CALLBACK FUNCTION TO ENQUEUE ADMIN SCRIPTS
			 */
			public function enqueueAdminScripts()
			{
				// LOAD PAGE CSS
				wp_enqueue_style('admin-wppc-contests', WPPC_URI.'css/wppc-contest.css', '', WPPC_VERSION);

				// LOAD JQUERY & AJAX CALLBACKS
				wp_enqueue_script('jquery','','','',true);
				wp_enqueue_script('jquery-ui-core','','','',true);
				wp_enqueue_script('jquery-ui-tabs','','','',true);
				wp_enqueue_script('wppc-contest-js', WPPC_URI.'js/wppc-contest.js', array('jquery'), WPPC_VERSION, true);
			}


			/**
			 * CALLBACK FUNCTION TO PRINT CONTEST PAGE
			 */
			public function displayWPPContest()
			{
				// set page title
				switch ($_GET['activity']):
					case "view": $pageTitle = "View contest photos"; break;
					case "edit": $pageTitle = "Edit contest"; break;
					case "stats": $pageTitle = "View contest stats"; break;
					default: $pageTitle = "New photo contest"; break;
				endswitch;
				
				// fetch, prepare, sort, and filter our data...
			    if ($_GET['activity'] == 'view') $this->prepare_items();
				?>

				<div class="wrap">
					<h2><?php _e($pageTitle) ?></h2>

					<?php
						switch ($_GET['activity']):

							// view contest photos
							case "view": $this->viewContestPhotos(); break;

							// edit contest details
							case "edit": $this->editContest(); break;

							// view stats
							case "stats": $this->viewStats(); break;

							// add new contest
							default: $this->editContest(); break;
						endswitch;
					?>
				</div>
				<?php
			}


			/**
			 * VIEW CONTEST PHOTOS
			 */
			private function viewContestPhotos()
			{
				?>
				<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
				<form id="photos" method="get" action="">

					<!-- separate photos by new | approved | rejected -->
					<?php $this->views() ?>

					<!-- For plugins, we also need to ensure that the form posts back to our current page -->
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

					<!-- Now we can render the completed list table -->
					<?php $this->display() ?>
				</form>
				<?php
			}


			/**
			 * EDIT CONTEST DETAILS
			 */
			private function editContest()
			{
				global $wpdb;
				
				$contest = $wpdb->get_row("SELECT * FROM $this->contestsTable WHERE id=".$_GET['contest']);

				// get all dates to validate				
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
				
				// get current contest photos
				$photos = isset($_GET['contest']) ? $wpdb->get_results("SELECT * FROM $this->contestEntriesTable WHERE contest_id='".$_GET['contest']."' visible=".self::PHOTO_APPROVED) : array();

				// unserialize data
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
									<!-- CONTEST NAME -->
									<tr <?php $_POST && isset($_POST['name']) == '' && !isset($_GET['contest']) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Contest name</th>
										<td>
											<input type="text" id="name" name="name" value="<?php if (isset($_GET['contest'])) echo $contest->contest_name; elseif ($_POST) echo $_POST['name'] ?>">
											<label for="name"> The name of the contest</label>
										</td>
									</tr>

									<!-- CONTEST START DATE -->
									<tr <?php $_POST && !isset($_GET['contest']) && (isset($_POST['start-date']) == '' || !$validStartDate) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Start date</th>
										<td>
											<input type="date" id="start-date" name="start-date" value="<?php if (isset($_GET['contest'])) echo $contest->start_date; elseif ($_POST) echo $_POST['start-date'] ?>">
											<label for="start-date"> The start date of the contest</label>
										</td>
									</tr>

									<!-- CONTEST END REGISTRATION -->
									<tr <?php $_POST && !isset($_GET['contest']) && (isset($_POST['end-registration']) == '' || !$validEndRegistration) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Registration end</th>
										<td>
											<input type="date" id="end-registration" name="end-registration" value="<?php if (isset($_GET['contest'])) echo $contest->end_registration; elseif ($_POST) echo $_POST['end-registration']?>">
											<label for="end-registration"> Until when the contestants can enter the contest</label>
										</td>
									</tr>

									<!-- CONTEST END VOTE -->
									<tr <?php $_POST && !isset($_GET['contest']) && (isset($_POST['end-vote']) == '' || !$validEndVote) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Vote end</th>
										<td>
											<input type="date" id="end-vote" name="end-vote" value="<?php if (isset($_GET['contest'])) echo $contest->end_vote; elseif ($_POST) echo $_POST['end-vote']?>">
											<label for="end-vote"> until when users can vote</label>
										</td>
									</tr>

									<!-- CONTEST END DATE -->
									<tr <?php $_POST && !isset($_GET['contest']) && (isset($_POST['end-date']) == '' || !$validEndDate) ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">End date</th>
										<td>
											<input type="date" id="end-date" name="end-date" value="<?php if (isset($_GET['contest'])) echo $contest->end_date; elseif ($_POST) echo $_POST['end-date']?>">
											<label for="end-date"> The end date of the contest</label>
										</td>
									</tr>

									<!-- CONTEST PHOTOS ALLOWED -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['photos-allowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Number of photos</th>
										<td>
											<input class="small-text" type="number" id="photos-allowed" name="photos-allowed" value="<?php if (isset($_GET['contest'])) echo $contest->photos_allowed; else echo $_POST['photos-allowed'] ?>">
											<label for="photos-allowed"> Number of photos allowed in the contest</label>
										</td>
									</tr>

									<!-- CONTEST MOBILE PHOTOS ALLOWED -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['photos-mobile-allowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Number of mobile photos</th>
										<td>
											<input class="small-text" type="number" id="photos-mobile-allowed" name="photos-mobile-allowed" value="<?php if (isset($_GET['contest'])) echo $contest->photos_mobile_allowed; else echo $_POST['photos-mobile-allowed'] ?>">
											<label for="photos-mobile-allowed"> Number of photos taken with a phone allowed in the contest</label>
										</td>
									</tr>

									<!-- CONTEST VOTES ALLOWED -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['votes-allowed']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Votes allowed</th>
										<td>
											<input class="small-text" type="number" id="votes-allowed" name="votes-allowed" value="<?php if (isset($_GET['contest'])) echo $contest->votes_allowed; else echo $_POST['votes-allowed'] ?>">
											<label for="votes-allowed"> How many times a visitor can vote a photo</label>
										</td>
									</tr>

									<!-- CONTEST FIRST POINT -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['first-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">First point at</th>
										<td>
											<input class="small-text" type="number" id="first-point" name="first-point" value="<?php if (isset($_GET['contest'])) echo $contest->first_point; else echo $_POST['first-point'] ?>">
											<label for="first-point"> votes</label>
										</td>
									</tr>

									<!-- CONTEST SECOND POINT -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['second-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Second point at</th>
										<td>
											<input class="small-text" type="number" id="second-point" name="second-point" value="<?php if (isset($_GET['contest'])) echo $contest->second_point; else echo $_POST['second-point'] ?>">
											<label for="second-point"> votes</label>
										</td>
									</tr>

									<!-- CONTEST THIRD POINT -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['third-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Third point at</th>
										<td>
											<input class="small-text" type="number" id="third-point" name="third-point" value="<?php if (isset($_GET['contest'])) echo $contest->third_point; else echo $_POST['third-point'] ?>">
											<label for="third-point"> votes</label>
										</td>
									</tr>

									<!-- CONTEST FORTH POINT -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['forth-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Forth point at</th>
										<td>
											<input class="small-text" type="number" id="forth-point" name="forth-point" value="<?php if (isset($_GET['contest'])) echo $contest->forth_point; else echo $_POST['forth-point'] ?>">
											<label for="forth-point"> votes</label>
										</td>
									</tr>

									<!-- CONTEST FIFTH POINT -->
									<tr <?php $_POST && !isset($_GET['contest']) && isset($_POST['fifth-point']) <= 0 ? printf('class="form-invalid"') : '' ?>>
										<th scope="row">Fifth point at</th>
										<td>
											<input class="small-text" type="number" id="fifth-point" name="fifth-point" value="<?php if (isset($_GET['contest'])) echo $contest->fifth_point; else echo $_POST['fifth-point'] ?>">
											<label for="fifth-point"> votes</label>
										</td>
									</tr>

									<!-- CONTEST SOCIAL DESCRIPTION -->
									<tr>
										<th scope="row">Social Description</td>
											<td>
												<input class="regular-text" type="text" id="social-description" name="social-description" value="<?php if (isset($_GET['contest'])) echo $contest->contest_social_description; else echo $_POST['social-description'] ?>" />
												<label for="social-description">text to appear when sharing on Facebook/Pinterest</label>
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
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['about'] ? $_POST['about'] : "", "about");
												else
													wp_editor($contest->contest_about, "about");
												?>
											</td>
										</tr>

										<!-- CONTEST PHOTO GALLERY TAB -->
										<tr>
											<th class="row">Photo Gallery Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['photos'] ? $_POST['photos'] : "", "photos");
												else
													wp_editor($contest->contest_photo_gallery, "photos");
												?>
											</td>
										</tr>

										<!-- CONTEST WINNERS TAB -->
										<tr>
											<th class="row">Winners Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['winners'] ? $_POST['winners'] : "", "winners");
												else
													wp_editor($contestWinners['text'], "winners");
												?>
											</td>
										</tr>

										<!-- CONTEST EN RULES -->
										<tr>
											<th class="row">English Rules</th>
											<td>
												<?php
												if (!isset($_GET['contest']) || empty($contestRules['en']))
													wp_editor($_POST && $_POST['rules-en'] ? $_POST['rules-en'] : "", "rules-en");
												else
													wp_editor($contestRules['en'], "rules-en");
												?>
											</td>
										</tr>

										<!-- CONTEST RO RULES -->
										<tr>
											<th class="row">Romanian Rules</th>
											<td>
												<?php
												if (!isset($_GET['contest']) || empty($contestRules['ro']))
													wp_editor($_POST && $_POST['rules-ro'] ? $_POST['rules-ro'] : "", "rules-ro");
												else
													wp_editor($contestRules['ro'], "rules-ro");
												?>
											</td>
										</tr>

										<!-- CONTEST PRIZES TAB -->
										<tr>
											<th class="row">Prizes Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['prizes'] ? $_POST['prizes'] : "", "prizes");
												else
													wp_editor($contest->contest_prizes, "prizes");
												?>
											</td>
										</tr>

										<!-- CONTEST ENTRY FORM TAB -->
										<tr>
											<th class="row">Entry Form Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['entry-form'] ? $_POST['entry-form'] : "", "entry-form");
												else
													wp_editor($contest->contest_entry_form, "entry-form");
												?>
											</td>
										</tr>

										<!-- CONTEST CONTACT TAB -->
										<tr>
											<th class="row">Contact Tab</th>
											<td>
												<?php
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['contact'] ? $_POST['contact'] : "", "contact");
												else
													wp_editor($contest->contest_contact, "contact");
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
												if (!isset($_GET['contest']))
													wp_editor($_POST && $_POST['sponsors'] ? $_POST['sponsors'] : "", "sponsors");
												else
													wp_editor($contest->contest_sidebar, "sponsors");
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
												wp_editor($contestEmails['admitted-body'], "admitted-body");
											?>
											<input type="submit" class="button-secondary" name="send-admit-test" id="send-admit-test" value="Send Test Email" />
											<span id="admit-result"></span>
										</td>
									</tr>
								</table>
							</div>

							<!-- SELECT WINNERS -->
							<?php if (isset($_GET['contest'])): ?>
								<div id="select-winners">
									<table class="form-table">
										<!-- FIRST PRIZE -->
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

										<!-- SECOND PRIZE -->
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

										<!-- THIRD PRIZE -->
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

										<!-- SPECIAL PRIZE -->
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

										<!-- OUR FAVORITES -->
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

							<!-- SUBMIT BUTTON -->
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
			 * VIEW CONTEST STATS
			 */
			private function viewStats()
			{
				global $wpdb;

				// total # of photos
				$totalPhotos = $wpdb->get_results("SELECT * FROM $this->contestEntriesTable WHERE contest_id=".$_GET['contest']);

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
				$totalVoters = $wpdb->get_results("SELECT * FROM $this->contestVotesTable WHERE contest_id=".$_GET['contest']);
						
				// total # of votes
				$totalVotes = array();

				// unique voters
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
			 * CALLBACK FUNCTION TO ADD/EDIT CONTEST
			 */
			public function saveWPPContest()
			{
				global $wpdb;

				if ($_POST):
				// CREATE CONTEST DATA ARRAY
				$contestData = array(
					'contest_name' 					=> esc_attr($_POST['name']),
					'start_date' 					=> $_POST['start-date'],
					'end_registration' 				=> $_POST['end-registration'],
					'end_vote' 						=> $_POST['end-vote'],
					'end_date' 						=> $_POST['end-date'],
					'photos_allowed' 				=> isset($_POST['photos-allowed']) ? absint($_POST['photos-allowed']) : 0,
					'photos_mobile_allowed' 		=> isset($_POST['photos-mobile-allowed']) ? absint($_POST['photos-mobile-allowed']) : 0,
					'votes_allowed' 				=> isset($_POST['votes-allowed']) ? absint($_POST['votes-allowed']) : 0,
					'first_point' 					=> isset($_POST['first-point']) ? absint($_POST['first-point']) : 0,
					'second_point' 					=> isset($_POST['second-point']) ? absint($_POST['second-point']) : 0,
					'third_point' 					=> isset($_POST['third-point']) ? absint($_POST['third-point']) : 0,
					'forth_point' 					=> isset($_POST['forth-point']) ? absint($_POST['forth-point']) : 0,
					'fifth_point' 					=> isset($_POST['fifth-point']) ? absint($_POST['fifth-point']) : 0,
					'contest_social_description' 	=> isset($_POST['social-description']) ? $_POST['social-description'] : '',
					'contest_about' 				=> isset($_POST['about']) ? $_POST['about'] : '',
					'contest_photo_gallery'			=> $_POST['photos'],
					'contest_winners' 				=> serialize(array(
															'text'					=> isset($_POST['winners']) ? $_POST['winners'] : "",
															'first-winner'			=> isset($_POST['first-winner']) ? $_POST['first-winner'] : 0,
															'second-winner'			=> isset($_POST['second-winner']) ? $_POST['second-winner'] : 0,
															'third-winner'			=> isset($_POST['third-winner']) ? $_POST['third-winner'] : 0,
															'special-winner'		=> isset($_POST['special-winner']) ? $_POST['special-winner'] : 0,
															'our-favorites'			=> isset($_POST['our-favorites']) ? $_POST['our-favorites'] : 0,
														)),
					'contest_rules' 				=> serialize(array(
															'en'	=> isset($_POST['rules-en']) ? $_POST['rules-en'] : "",
															'ro'	=> isset($_POST['rules-ro']) ? $_POST['rules-ro'] : "",
														)),
					'contest_prizes' 				=> isset($_POST['prizes']) ? $_POST['prizes'] : "",
					'contest_entry_form' 			=> isset($_POST['entry-form']) ? $_POST['entry-form'] : '',
					'contest_contact' 				=> isset($_POST['contact']) ? $_POST['contact'] : "",
					'contest_sidebar' 				=> isset($_POST['sponsors']) ? $_POST['sponsors'] : "",
					'contest_emails' 				=> serialize(array(
															'admitted-subject'	=> isset($_POST['admitted-subject']) ? esc_attr($_POST['admitted-subject']) : "",
															'admitted-body'		=> isset($_POST['admitted-body']) ? $_POST['admitted-body'] : "",
														)),
				);

				// INSERT|UPDATE TABLE
				if (isset($_POST['addNewContest'])):
					$id = 0;
					
					// UPDATE CONTEST
					if (isset($_GET['contest'])):
							$wpdb->update($this->contestsTable, $contestData, array('id'=>$_GET['contest']));
							$id = $_GET['contest'];
						
						// INSERT NEW CONTEST
						else:
							$wpdb->insert($this->contestsTable, $contestData);
							$id = $wpdb->insert_id;
					endif;

					// create folders for user uploads
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
			 * SET COLUMNS AND TITLES
			 */
			public function get_columns()
			{
				$columns = array(
		            'cb'				=> '<input type="checkbox" />', //Render a checkbox instead of text
//		            'photo_id'			=> __('Thumb'),
		            'competitor_photo'	=> __('Filename'),
		            'competitor_name'	=> __('Author'),
		            'photo_name'		=> __('Photo name'),
		            'photo_location'	=> __('Photo location'),
		            'upload_date'		=> __('Date'),
		        );

		        return $columns;
			}


			/**
			 * GENERAL FUNCTION FOR RENDERING COLUMNS
			 */
			public function column_default($item, $column_name)
			{
				return $item[$column_name];
			}


			/**
			 * CHECKBOX COLUMN
			 */
			public function column_cb($item)
			{
				return sprintf(
		            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
		            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
		            /*$2%s*/ $item['id']                //The value of the checkbox should be the record's id
		        );
			}


			/**
			 * THUMB COLUMN
			 */
			public function column_photo_id($item)
			{
				$wpDir = wp_upload_dir();
				$contestDir = $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$_GET['contest'].'/';
				$contestPath = $wpDir['path'].'/wppc-photos/wppc-photos-'.$_GET['contest'].'/';

				return sprintf('<img style="height: 60px" src="%s" />', $contestDir.'thumbs/'.$item['competitor_photo']);
			}


			/**
			 * PHOTO COLUMN
			 */
			public function column_competitor_photo($item)
			{
				global $wpdb;

				$wpDir = wp_upload_dir();
				$contestDir = $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$_GET['contest'].'/';
				$contestPath = $wpDir['path'].'/wppc-photos/wppc-photos-'.$_GET['contest'].'/';

				$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=0 AND contest_id=".$_GET['contest']);

				$photo .= '<a class="view-photo-details" href="'.$contestDir.'raw/'.$item['competitor_photo'].'" data-photo-id="'.
					$item['photo_id'].'" target="_blank">'.$item['competitor_photo'].'</a>';
				
				if ($item['visible' == self::PHOTO_APPROVED]):
					$photo .= '<br/>'.$item['photo_mobile'] == self::PHOTO_MOBILE_DEVICE ?'<br/>MOBILE' : 'PRO CAMERA';
					$photo .= ' ('.$item['votes'].' ';
					$photo .= $item['votes'] != 1 ? 'votes)' : 'vote)';
				endif;
				
				// set actions based on view
				if ($newItems && !isset($_GET['status']))
						$actions = array(
							'publish'	=> sprintf('<a href="?page=wppc-contest&contest=%s&activity=%s&id=%s&action=%s">Approve</a>', $_GET['contest'], $_GET['activity'], $item['photo_id'], 'approve'),
							'trash'		=> sprintf('<a href="?page=wppc-contest&contest=%s&activity=%s&id=%s&action=%s">Reject</a>', $_GET['contest'], $_GET['activity'], $item['photo_id'], 'trash'),
						);
					elseif ((!$newItems && !isset($_GET['status']) || ($newItems && isset($_GET['status']) && $_GET['status'] == 'publish')))
							$actions = array(
								'download-raw'	=> sprintf('<a download href="%s">Download raw</a>', $contestDir.'raw/'.$item['competitor_photo']),
								'download-copy'	=> sprintf('<a download href="%s">Download &copy;</a>', $contestDir.'medium/'.$item['competitor_photo']),
								'voters'		=> sprintf('<a href="#" class="wppc-view" data-photo-id="%s">View IPs</a>', $item['photo_id']),
							);
						else
							$actions = array(
								'restore'	=> sprintf('<a href="?page=wppc-contest&contest=%s&activity=%s&id=%s&action=%s">Restore</a>', $_GET['contest'], $_GET['activity'], $item['photo_id'], 'restore'),
								'delete'	=> sprintf('<a href="?page=wppc-contest&contest=%s&activity=%s&id=%s&action=%s&file=%s">Delete permanelty</a>', $_GET['contest'], $_GET['activity'], $item['photo_id'], 'delete', $item['competitor_photo']),
							);

		        return sprintf('%1$s %2$s', $photo, $this->row_actions($actions));
			}


			/** 
			 * GET VIEWS
			 */
			public function get_views()
			{
				global $wpdb;

		    	$views = array();

				$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=".self::PHOTO_NEW." AND contest_id=".$_GET['contest']);
				$trashedItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=".self::PHOTO_REJECTED." AND contest_id=".$_GET['contest']);
				$publishedItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=".self::PHOTO_APPROVED." AND contest_id=".$_GET['contest']);

				if ($newItems) $current = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'new');
					else $current = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

				// New link
				if ($newItems):
						$class = $current == 'new' ? ' class="current"' : '';
						$newURL = remove_query_arg('status');
						$views['new'] = "<a href='{$newURL}' {$class}>New ({$newItems})</a>";
			
						// Publish link
						if ($publishedItems):
							$class = ($current == 'publish' ? ' class="current"' :'');
							$publishedURL = add_query_arg('status', 'publish');
							$views['publish'] = "<a href='{$publishedURL }' {$class} >Approved ({$publishedItems})</a>";
						endif;

						// Trash link
						if ($trashedItems):
							$class = ($current == 'trash' ? ' class="current"' :'');
							$trashedURL = add_query_arg('status', 'trash');
							$views['trash'] = "<a href='{$trashedURL}' {$class} >Rejected ({$trashedItems})</a>";
						endif;
					else:
						// Publish link
						if ($publishedItems):
							$class = ($current == 'publish' ? ' class="current"' :'');
							$publishedURL = remove_query_arg('status');
							$views['publish'] = "<a href='{$publishedURL }' {$class} >Approved ({$publishedItems})</a>";
						endif;

						// Trash link
						if ($trashedItems):
							$class = ($current == 'trash' ? ' class="current"' :'');
							$trashedURL = add_query_arg('status', 'trash');
							$views['trash'] = "<a href='{$trashedURL}' {$class} >Rejected ({$trashedItems})</a>";
						endif;
				endif;

				return $views;
			}


			/**
		     * PREPARE DATA FOR DISPLAY
		     */
		    public function prepare_items()
		    {
		        // how many records are to be shown on page
				$per_page = 20;

				// columns array to be displayed
		        $columns = $this->get_columns();

		        // columns array to be hidden
		        $hidden = array();

		        // list of sortable columns
		        $sortable = $this->get_sortable_columns();
		        
		        // create the array that is used by the class
		        $this->_column_headers = array($columns, $hidden, $sortable);
		        
		        // process bulk actions
		        $this->process_bulk_action();

		        // process single actions
		        $this->processActions();

		      	// current page
		        $current_page = $this->get_pagenum();

		        // get contests
		        $data = $this->getData();
		        
		        // total number of items
		        $total_items = count($data);
		        
		        // slice data for pagination
		        $data = array_slice($data, (($current_page-1)*$per_page), $per_page);
		        
		        // send processed data to the items property to be used
		        $this->items = $data;
		        
		        // register pagination options & calculations
		        $this->set_pagination_args(array(
		            'total_items' => $total_items,                  //WE have to calculate the total number of items
		            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
		            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
		        ));
		    }


		    /**
		     * PROCESS ANCTIONS
		     */
		    private function processActions()
		    {
		    	global $wpdb;

		    	// APPROVE PHOTO
				if (isset($_GET['action']) && $_GET['action'] == 'approve'):
					
					// update the database to add the photo to the contest
					$wpdb->update($this->contestEntriesTable, array('visible' => self::PHOTO_APPROVED), array('photo_id' => $_GET['id'], 'contest_id' => $_GET['contest']));

					// connect to SendGrid API
					$sendgrid = new SendGrid(get_option('sendgrid_user'), get_option('sendgrid_pwd'));

					// create new email
					$email = new SendGrid\Email();

					// add recipient email
					$email->addTo($_GET['email'], $_GET['name'])
						  ->setFrom("wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']))
						  ->setFromName(get_bloginfo())
						  ->setSubject($contestEmails['admitted-subject'])
						  ->setHtml($contestEmails['admitted-body']);

					// send email to user
					$sendgrid->send($email);


					/*// init mail
					include_once(WPPC_DIR.'php/PHPMailer/PHPMailerAutoload.php');					
					$mail = new PHPMailer();
					$mail->IsSMTP();
					$mail->SMTPDebug = 0; // 1 tells it to display SMTP errors and messages, 0 turns off all errors and messages, 2 prints messages only.
					$mail->Host = ini_get('SMTP');
					$mail->IsHTML(true);
					$mail->From = "wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']);
					$mail->FromName = get_bloginfo();
					$mail->Subject = $contestEmails['admitted-subject'];
					$mail->AddCC($_GET['email'], $_GET['name']);
					$mail->Body = $_POST['admitted-body'];
					
					// send mail
					if (!$mail->Send()) echo $mail->ErrorInfo();
					$mail->ClearAllRecipients();*/
				endif;


				// REJECT PHOTO
				if (isset($_GET['action']) && $_GET['action'] == 'trash'):
					$wpdb->update($this->contestEntriesTable, array('visible' => self::PHOTO_REJECTED), array('photo_id' => $_GET['id']));
				endif;


				// RESTORE PHOTO
				if (isset($_GET['action']) && $_GET['action'] == 'restore'):
					$wpdb->update($this->contestEntriesTable, array('visible' => self::PHOTO_NEW), array('photo_id' => $_GET['id']));
				endif;


			//	 DELETE PHOTO
				if (isset($_GET['action']) && $_GET['action'] == 'delete'):
					?>
					<script>alert("DELETE");</script>
					<?php

				/*	// change file permissions
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
					$wpdb->delete($tableName, array('contest_id' => $_GET['contest'], 'photo_id' => $_GET['photoid']));
				*/endif;
		    }


		    /**
		     * GET PHOTOS
		     */
		    private function getData()
		    {
		    	global $wpdb;

		    	$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=0 AND contest_id=".$_GET['contest']);
				
				if ($newItems) $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'new');
					else $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

				$where = 'contest_id=';
		    	$where .= isset($_GET['contest']) ? $_GET['contest'] : 1;
		    	$where .= ' AND ';

				switch ($status):
					case 'new': $where .= 'visible='.self::PHOTO_NEW.' AND'; break;
					case 'publish': $where .= 'visible='.self::PHOTO_APPROVED.' AND'; break;
					case 'trash': $where .= 'visible='.self::PHOTO_REJECTED.' AND'; break;
				endswitch;

				$where = rtrim($where, 'AND ');

		    	return $wpdb->get_results("SELECT * FROM $this->contestEntriesTable WHERE $where", ARRAY_A);
		    }


		    /**
		     * CALLBACK FUNCTION TO ADD HTML TAGS TO ALLOWED LIST
		     */
		    public function allowedHTMLTags($allowedposttags)
		    {
		    	$allowedposttags['iframe'] = array(
					'src'             => array(),
					'height'          => array(),
					'width'           => array(),
					'frameborder'     => array(),
					'allowfullscreen' => array(),
				);
				
				// form fields - input
				$allowedposttags['input'] = array(
					'class' => array(),
					'id'    => array(),
					'name'  => array(),
					'value' => array(),
					'type'  => array(),
				);
				
				// select
				$allowedposttags['select'] = array(
					'class'  => array(),
					'id'     => array(),
					'name'   => array(),
					'value'  => array(),
					'type'   => array(),
				);
				
				// select options
				$allowedposttags['option'] = array(
					'selected' => array(),
				);
				
				// style
				$allowedposttags['style'] = array(
					'types' => array(),
				);

				return $allowedposttags;
				// table
				$allowedposttags['table'] = array(
					'thead' => array(),
					'tbody' => array(),
					'tfoot' => array(),
					'tr'	=> array(),
					'td'	=> array(),
					);
		    }
			

		} // END CLASS
	endif;


?>