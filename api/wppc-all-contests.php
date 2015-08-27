<?php

	if (!class_exists('WPPCAllContests')):
		class WPPCAllContests
		{

			/**
			 * CONSTRUCT
			 */
			public function __construct()
			{
				// CREATE WPPC MENU
				add_action('admin_menu', array($this, 'addWPPCMenu'));
			}

			/**
			 * CALLBACK FUNCTION TO CREATE WPPC MENU
			 */
			public function addWPPCMenu()
			{
				// CREATE ITEM IN ADMIN MENU
				add_menu_page('WordPress Photo Contests', 'Photo Contests', 'manage_options', 'wppc-all-contests', array($this, 'displayWPPCAllContests'), plugins_url('wp-photo-contest/img/icon_16.png'), 99);
	
				// ADD "ALL CONTESTS" ITEM
				add_submenu_page("wppc-all-contests", "All Contests", "All Contests", "manage_options", "wppc-all-contests", array($this, 'displayWPPCAllContests'));
			}

			/**
			 * DISPLAY ALL WPPC CONTESTS
			 */
			public function displayWPPCAllContests()
			{
				global $wpdb;

				// RESTORE CONTEST
				if (isset($_GET['wppc-action']) && $_GET['wppc-action'] == 'restore'):
					$wpdb->update($wpdb->prefix.'wppc_contests_all', array('status' => 1), array('id' => $_GET['wppc-id']));
				endif;
				
				// TRASH CONTEST
				if (isset($_GET['wppc-action']) && $_GET['wppc-action'] == 'trash'):
					$wpdb->update($wpdb->prefix.'wppc_contests_all', array('status' => 0), array('id' => $_GET['wppc-id']));
				endif;

				// DELETE CONTEST
				if (isset($_GET['wppc-action']) && $_GET['wppc-action'] == 'delete'):
					
					// DELETE CONTEST
					$wpdb->delete($wpdb->prefix.'wppc_contests_all', array('id' => $_GET['wppc-id']));

					// DELETE CONTEST ENTRIES
					$wpdb->delete($wpdb->prefix.'wppc_contests_entries', array('contest_id' => $_GET['wppc-id']));

					// DELETE CONTEST VOTES
					$wpdb->delete($wpdb->prefix.'wppc_contests_votes', array('contest_id' => $_GET['wppc-id']));
				endif;

				$tableName = $wpdb->prefix.'wppc_contests_all';
				$contests = $wpdb->get_results('SELECT * FROM '.$tableName.' WHERE 1 ORDER BY id DESC');
				$trash = 0;
				
				foreach ($contests as $contest):
					if ($contest->status == 0) $trash++;
				endforeach;
				?>

				<div class="wrap">
					<h2>
						All Contests 
						<a class="add-new-h2" href="?page=wppc-contest" title="Add New">Add New</a>
					</h2>

					<ul class="subsubsub">
						<?php if (count($contests)-$trash > 0): ?>
							<li class="publish"><a href="?page=wppc-all-contests&amp;status=publish" <?php echo !isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] == 'publish') ? 'class="current"' : '' ?>>Publish <span class="count">(<?php echo count($contests)-$trash ?>)</span></a></li>
						<?php endif; ?>
						<?php if ($trash > 0): ?>
							<li class="trash"> | <a href="?page=wppc-all-contests&amp;status=trash" <?php echo isset($_GET['status']) && $_GET['status'] == 'trash' ? 'class="current"' : '' ?>>Trash <span class="count">(<?php echo $trash ?>)</span></a></li>
						<?php endif; ?>
					</ul>

					<table class="wp-list-table widefat">
						<thead>
							<tr>
								<th class="row-title">Contest name</th>
								<th class="row-title">Start date</th>
								<th class="row-title">End date</th>
								<th class="row-title">Photos allowed</th>
								<th class="row-title">Votes allowed</th>
								<th class="row-title">Shortcode</th>
							</tr>
						</thead>
						<tbody>
							<?php
								$counter = 0;
								$html = '';

								// PUBLISH CONTESTS
								if (!isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] == 'publish')):
									$counter = $this->printContests($contests, 1);
								endif; // end publish contests
								
								
								// TRASH CONTESTS
								if (isset($_GET['status']) && $_GET['status'] == 'trash'):
									$counter = $this->printContests($contests, 0);
								endif; // end trash contests

								if ($counter == 0) echo '<tr><td>No contests found.</td></tr>';
							?>
						</tbody>
						<tfoot>
							<tr>
								<th class="row-title">Contest name</th>
								<th class="row-title">Start date</th>
								<th class="row-title">End date</th>
								<th class="row-title">Photos allowed</th>
								<th class="row-title">Votes allowed</th>
								<th class="row-title">Shortcode</th>
							</tr>
						</tfoot>
					</table>
				</div>
				<?php
			}

			/**
			 * PRINT PHOTOS
			 */
			private function printContests($contests, $status)
			{
				$counter = 0;

				foreach ($contests as $contest):
					if ($status == $contest->status):
						$counter++;
						$html = '<tr';
							if ($counter % 2 == 0) $html .='>';
								else $html .= ' class="alternate">';
							$html .= '<td>';
								$html .= '<a class="row-title" href="?page=wppc-contest&wppc-id='.$contest->id.'&wppc-action=edit" title="Edit \''.$contest->contest_name.'\'">'.$contest->contest_name.'</a>';
								$html .= '<div class="row-actions">';
									if ($status == 0):
											$html .= '<span class="edit"><a href="?page=wppc-all-contests&status=trash&wppc-id='.$contest->id.'&wppc-action=restore">Restore</a></span> | ';
											$html .= '<span class="trash"><a href="?page=wppc-all-contests&status=trash&wppc-id='.$contest->id.'&wppc-action=delete">Delete permanently</a></span>';
										else:
											$html .= '<span class="edit"><a href="?page=wppc-contest&wppc-id='.$contest->id.'&wppc-action=edit">Edit</a></span> | ';
											$html .= '<span colas="view"><a href="?page=wppc-contest&wppc-id='.$contest->id.'&wppc-action=view">View photos</a></span> | ';
											$html .= '<span class="trash"><a href="?page=wppc-all-contests&wppc-id='.$contest->id.'&wppc-action=trash">Trash</a> | </span>';
											$html .= '<span class="view"><a href="?page=wppc-contest&wppc-id='.$contest->id.'&wppc-action=stats">Stats</a></span>';
									endif;
								$html .= '</div>';
							$html .= '</td>';
							$html .= '<td>'.$contest->start_date.'</td>';
							$html .= '<td>'.$contest->end_date.'</td>';
							$html .= '<td>'.$contest->photos_allowed.'</td>';
							$html .= '<td>'.$contest->votes_allowed.'</td>';
							$html .= '<td><code>[wphotocontest id='.$contest->id.']</code></td>';
						$html .= '</tr>';
						
						echo $html;
					endif;
				endforeach;

				return $counter;
			}
		}
	endif;

?>