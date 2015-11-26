<?php

	// SECURITY CHECK
	if (!defined('ABSPATH')) die;

	// PRE-REQUIREMENTS
	require_once(ABSPATH.'wp-admin/includes/template.php');
	if (!class_exists('WP_List_Table'))
	    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
	if (!class_exists('WP_Screen'))
		require_once( ABSPATH.'wp-admin/includes/screen.php');

	if (!class_exists('WPPCPhotos')):

		class WPPCPhotos extends WP_List_Table
		{
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
			 * PHOTOS FOLDERS
			 */
			private $folders;

			/**
			 * NONCE VARIABLE
			 */
			private $nonce = 'wppc-nonce';
	

			/**
			 * CONSTRUCTOR
			 */
			public function __construct()
			{
				global $status, $page, $wpdb;

				$wpDir = wp_upload_dir();

				// initialize params
				$this->contestsTable = $wpdb->prefix.'wppc_contests_all';
				$this->contestEntriesTable = $wpdb->prefix.'wppc_contests_entries';
				$this->contestVotesTable = $wpdb->prefix.'wppc_contests_votes';
				$contestID = isset($_GET['contest']) ? $_GET['contest'] : $wpdb->get_var("SELECT id FROM $this->contestsTable WHERE 1 ORDER BY id DESC LIMIT 1");
				$this->folders = array(
					'raw'		=> $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contestID.'/raw/',
					'full'		=> $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contestID.'/full/',
					'medium'	=> $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contestID.'/medium/',
					'thumb'		=> $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contestID.'/thumbs/',
				);

				// set parent defaults
				parent::__construct(array(
					'singular'	=> 'photo',
					'plural'	=> 'photos',
					'screen'	=> 'photos-list',
					'ajax'		=> false,
				));

				// load admin scripts
				add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));				 

				// add menu page
				add_action('admin_menu', array($this, 'renderMenuItems')); 
				
				// AJAX CALL TO GET PHOTO VOTERS
				add_action('wp_ajax_view-photo-voters', array($this, 'viewPhotoVoters'));

				// AJAX CALL GET GET PHOTO SPECS
				add_action('wp_ajax_view-photo-specs', array($this, 'viewPhotoSpecs'));
			}

			/**
			 * CALLBACK FUNCTION TO RENDER MENU ITEMS
			 */
			public function renderMenuItems()
			{
				add_submenu_page('wppc-all-contests', 'Contest Photos', 'Photos', 'manage_options', 'wppc-photos', array($this, 'renderPage'));
			}


			/**
			 * CALLBACK FUNCTION TO ENQUEUE ADMIN SCRIPTS
			 */
			public function enqueueAdminScripts()
			{
				// LOAD PAGE CSS
				wp_enqueue_style('admin-wppc-contests', WPPC_URI.'css/wppc-photos.css', '', WPPC_VERSION);

				// LOAD JQUERY & AJAX CALLBACKS
				wp_enqueue_script('jquery','','','',true);
				wp_enqueue_script('jquery-ui-core','','','',true);
				wp_enqueue_script('jquery-ui-tabs','','','',true);
				wp_enqueue_script('wppc-photos-js', WPPC_URI.'js/wppc-photos.js', array('jquery'), WPPC_VERSION, true);

				// jquery ui
	    		wp_enqueue_style('wp-jquery-ui-dialog');
				wp_enqueue_script('jquery-ui-dialog');

				wp_localize_script('wppc-photos-js', 'wppc', array(
					'nonce' 	=> wp_create_nonce($this->nonce),
					'photoURL'	=> urlencode($this->folders['raw']),
				));

				// Google Maps JavaScript API
				wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBiYR3nxRN3-a8t6o2ZSxM6_4UTv6lo41g', '', '', true);
			}


			/**
			 * RENDER PAGE
			 */
			public function renderPage()
			{
				//Fetch, prepare, sort, and filter our data...
			    $this->prepare_items();
				?>
				<div class="wrap">
					<?php $this->setTitle() ?>

					<div id="modal-content" title="" class="hidden"></div>

					<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
					<form id="photos" method="get" action="">

						<!-- separate photos by new | approved | rejected -->
						<?php $this->views() ?>

						<!-- search box -->
			        	<?php $this->search_box('search', 'photo') ?>

						<!-- For plugins, we also need to ensure that the form posts back to our current page -->
						<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

						<!-- Now we can render the completed list table -->
						<?php $this->display() ?>
					</form>
				</div>
				<?php
			}


			/**
			 * SET PAGE TITLE
			 */
			public function setTitle()
			{
				global $wpdb;

				echo '<h2><strong>'.$wpdb->get_var("SELECT contest_name FROM $this->contestsTable WHERE id=".$_GET['contest']).'</strong> Photos</h2>';
			}


			/**
			 * SET COLUMNS AND TITLES
			 */
			public function get_columns()
			{
				$columns = array(
		            'cb'				=> '<input type="checkbox" />', //Render a checkbox instead of text
		            'photo_id'			=> __('Thumb'),
		            'photo_name'		=> __('Photo | Location'),
		            'competitor_name'	=> __('Author'),
		            'votes'				=> __('Votes'),
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
				return sprintf('<input type="checkbox" name="%1$s[]" value="%2$s" />', $this->_args['singular'], $item['id']);
			}


			/**
			 * THUMB COLUMN
			 */
			public function column_photo_id($item)
			{
				return sprintf('<a target="_blank" href="%s"><img style="width: 60px" src="%s" /></a>', $this->folders['raw'].$item['competitor_photo'], $this->folders['thumb'].$item['competitor_photo']);
			}


			/**
			 * PHOTO COLUMN
			 */
			public function column_photo_name($item)
			{
				global $wpdb;
				
				$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=0 AND contest_id=".$_GET['contest']);

				$photo = '<a class="view-photo-details" href="'.$this->folders['raw'].$item['competitor_photo'].'" data-photo-id="'.
					$item['photo_id'].'" target="_blank">'.ucwords($item['photo_name']).' | '.ucwords($item['photo_location']).'</a>';
				
				if ($item['visible'] == WPPC_PHOTO_APPROVED):
					$photo .= '<br/>'.$item['photo_mobile'] == WPPC_PHOTO_MOBILE_DEVICE ?'<br/>MOBILE' : '<br/>PRO CAMERA';
					$photo .= ' ('.$item['votes'].' ';
					$photo .= $item['votes'] != 1 ? 'votes)' : 'vote)';
				endif;
				
				// set actions based on view
				if ($newItems && !isset($_GET['status']))
						$actions = array(
							'publish'	=> sprintf('<a href="?page=wppc-photos&contest=%s&photo=%s&action=%s">Approve</a>', $_GET['contest'], $item['photo_id'], 'approve'),
							'trash'		=> sprintf('<a href="?page=wppc-photos&contest=%s&photo=%s&action=%s">Reject</a>', $_GET['contest'], $item['photo_id'], 'trash'),
						);
					elseif ((!$newItems && !isset($_GET['status']) || ($newItems && isset($_GET['status']) && $_GET['status'] == 'publish')))
							$actions = array(
								'download-raw'	=> sprintf('<a download href="%s">Download raw</a>', $this->folders['raw'].$item['competitor_photo']),
								'download-copy'	=> sprintf('<a download href="%s">Download &copy;</a>', $this->folders['medium'].$item['competitor_photo']),
								'voters'		=> sprintf('<a href="#" data-photo-id="%s">View IPs</a>', $item['photo_id']),
							);
						else
							$actions = array(
								'restore'	=> sprintf('<a href="?page=wppc-photos&contest=%s&photo=%s&action=%s">Restore</a>', $_GET['contest'], $item['photo_id'], 'restore'),
								'delete'	=> sprintf('<a href="?page=wppc-photos&contest=%s&photo=%s&action=%s&file=%s">Delete permanelty</a>', $_GET['contest'], $item['photo_id'], 'delete', $item['competitor_photo']),
							);

		        return sprintf('%1$s %2$s', $photo, $this->row_actions($actions));
			}


			/**
			 * COMPETITOR NAME
			 */
			public function column_competitor_name($item)
			{
				return sprintf('<a target="_blank" href="mailto:%s">%s</a>', $item['competitor_email'], $item['competitor_name']);
			}


			/**
		     * SET SORTABLE COLUMNS
		     */
		    public function get_sortable_columns()
		    {
		        $sortable_columns = array(
		            'photo_name'		=> array('photo_name', false),
		            'author'			=> array('competitor_name', false),
		            'votes'				=> array('votes', false),
		            'upload_date'		=> array('upload_date', false),
		        );

		        return $sortable_columns;
		    }


			/** 
			 * GET VIEWS
			 */
			public function get_views()
			{
				global $wpdb;

		    	$views = array();

				$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=".WPPC_PHOTO_NEW." AND contest_id=".$_GET['contest']);
				$trashedItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=".WPPC_PHOTO_REJECTED." AND contest_id=".$_GET['contest']);
				$publishedItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=".WPPC_PHOTO_APPROVED." AND contest_id=".$_GET['contest']);

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
			 * BULK ACTIONS
			 */
			public function get_bulk_actions()
		    {
		    	/*if (isset($_GET['status']) && $_GET['status'] == "trash")
		    			$actions = array('restore' => __('Restore'), 'delete' => __('Delete permanently'));
		    		else
		    			$actions = array('approve' => __('Approve'),'reject' => __('Reject'));

		    	return $actions;*/
		    
		    	return array();
		    }


		    /**
		     * PROCESS BULK ACTIONS
		     */
		    public function processActions()
		    {
		    	global $wpdb;


		    	switch ($this->current_action()):

		    		// APPROVE PHOTOS
		    		case "approve":
		    			if (!is_array($_GET['photo'])):
								
								// update the database to add the photo to the contest
		    					$wpdb->update($this->contestEntriesTable, array('visible' => WPPC_PHOTO_APPROVED), array('photo_id' => $_GET['photo'], 'contest_id' => $_GET['contest']));
		    				
								// get contact information
								$contactInfo = $wpdb->get_row("SELECT competitor_name, competitor_email FROM $this->contestEntriesTable WHERE contest_id=".$_GET['contest']." AND photo_id=".$_GET['photo'], ARRAY_A);

		    					// get email subject and body
								$contestEmails = unserialize($wpdb->get_var("SELECT contest_emails FROM $this->contestsTable WHERE id=".$_GET['contest']));

								// connect to SendGrid API
								$sendgrid = new SendGrid(get_option('sendgrid_user'), get_option('sendgrid_pwd'));

								// create new email
								$email = new SendGrid\Email();

								// add recipient email
								$email->addTo($contactInfo['competitor_email'], $contactInfo['competitor_name'])
									  ->setFrom("wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']))
									  ->setFromName(get_bloginfo())
									  ->setSubject($contestEmails['admitted-subject'])
									  ->setHtml($contestEmails['admitted-body']);

								// send email to user
								$sendgrid->send($email);
		    				else:
		    					foreach ($_GET['photo'] as $photo):
	
									// update the database to add the photo to the contest
		    						$wpdb->update($this->contestEntriesTable, array('visible' => WPPC_PHOTO_APPROVED), array('photo_id' => $photo, 'contest_id' => $_GET['contest']));

		    						// get contact information
									$contactInfo = $wpdb->get_row("SELECT competitor_name, competitor_email FROM $this->contestEntriesTable WHERE contest_id=".$_GET['contest']." AND photo_id=".$photo, ARRAY_A);

			    					// get email subject and body
									$contestEmails = unserialize($wpdb->get_var("SELECT contest_emails FROM $this->contestsTable WHERE id=".$_GET['contest']));

									// connect to SendGrid API
									$sendgrid = new SendGrid(get_option('sendgrid_user'), get_option('sendgrid_pwd'));

									// create new email
									$email = new SendGrid\Email();

									// add recipient email
									$email->addTo($contactInfo['competitor_email'], $contactInfo['competitor_name'])
										  ->setFrom("wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']))
										  ->setFromName(get_bloginfo())
										  ->setSubject($contestEmails['admitted-subject'])
										  ->setHtml($contestEmails['admitted-body']);

									// send email to user
									$sendgrid->send($email);
		    					endforeach;
		    			endif;
		    			break;

		    		// REJECT PHOTO
		    		case "trash":
		    			if (!is_array($_GET['photo']))
		    					$wpdb->update($this->contestEntriesTable, array('visible' => WPPC_PHOTO_REJECTED), array('photo_id' => $_GET['photo'], 'contest_id' => $_GET['contest']));			
		    				else foreach ($_GET['photo'] as $photo)
		    					$wpdb->update($this->contestEntriesTable, array('visible' => WPPC_PHOTO_REJECTED), array('photo_id' => $photo, 'contest_id' => $_GET['contest']));			
		    			break;

		    		// RESTORE PHOTO
		    		case 'restore':
						if (!is_array($_GET['photo']))
								$wpdb->update($this->contestEntriesTable, array('visible' => WPPC_PHOTO_NEW), array('photo_id' => $_GET['photo']));
							else foreach ($_GET['photo'] as $photo)
								$wpdb->update($this->contestEntriesTable, array('visible' => WPPC_PHOTO_NEW), array('photo_id' => $photo));
		    			break;

		    		// DELETE PHOTO
		    		case 'delete':
		    			if (!is_array($_GET['photo'])):
				    			$wpdb->delete($this->contestEntriesTable, array('photo_id' => $_GET['photo']));
							
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
								$wpdb->delete($tableName, array('contest_id' => $_GET['contest'], 'photo_id' => $_GET['photoid']));
		    			endif;
		    			break;

		    	endswitch;
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
		     * GET PHOTOS
		     */
		    private function getData()
		    {
		    	global $wpdb;

		    	// set default contest as last contest
		    	if (!isset($_GET['contest'])) $_GET['contest'] = $wpdb->get_var("SELECT id FROM $this->contestsTable WHERE 1 ORDER BY id DESC LIMIT 1");

		    	// get order params for the SQL query
				$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id'; //If no sort, default to title
				$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC'; //If no order, default to asc

		    	$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM $this->contestEntriesTable WHERE visible=0 AND contest_id=".$_GET['contest']);
				
				if ($newItems) $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'new');
					else $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

				$where = 'contest_id=';
		    	$where .= isset($_GET['contest']) ? $_GET['contest'] : $wpdb->get_var("SELECT id FROM $this->contestsTable WHERE 1 ORDER BY id DESC LIMIT 1");
		    	$where .= ' AND ';

		    	// search
		    	if (isset($_GET['s']))
		    		$where .= 'competitor_name LIKE "%'.esc_attr($_GET['s']).'%" OR competitor_email LIKE "%'.esc_attr($_GET['s']).'%" OR photo_name LIKE "%'.esc_attr($_GET['s']).'%" OR photo_location LIKE "%'.esc_attr($_GET['s']).'%" AND ';

				switch ($status):
					case 'new': $where .= 'visible='.WPPC_PHOTO_NEW.' AND'; break;
					case 'publish': $where .= 'visible='.WPPC_PHOTO_APPROVED.' AND'; break;
					case 'trash': $where .= 'visible='.WPPC_PHOTO_REJECTED.' AND'; break;
				endswitch;

				$where = rtrim($where, 'AND ');

		    	return $wpdb->get_results("SELECT * FROM $this->contestEntriesTable WHERE $where ORDER BY $orderby $order", ARRAY_A);
		    }


		    /**
		     * CALLBACK FUNCTION TO VIEW PHOTO VOTERS
		     */
		  	public function viewPhotoVoters()
			{
				global $wpdb;

				// get photo id;
				$photoID = $_GET['photoid'];

				// get voters
				$votes = $wpdb->get_results("SELECT * FROM $this->contestVotesTable WHERE photo_id=".$photoID);

				// create ajax response
				$ajaxResponse = array();

				foreach ($votes as $vote)
					$ajaxResponse[] = array(
						'ip'	=> long2ip($vote->vote_ip),
						'votes'	=> $vote->vote_number
					);

				// return ajax response and terminate
				die(json_encode($ajaxResponse));
			}


			/**
			 * CALLBACK FUNCTION TO VIEW PHOTO SPECS
			 */
			public function viewPhotoSpecs()
			{
				global $wpdb;

				// security check
				if (!wp_verify_nonce($_GET['nonce'], $this->nonce))
					die(__("Security check failed"));

				// get photo url
				$photo = $_GET['photo'];
				
				// get photo upload date from db
				$dbPhoto = $wpdb->get_row("SELECT upload_date, contest_id, competitor_photo FROM $this->contestEntriesTable WHERE photo_id=$photo");

				$photo = urldecode($_GET['photoURL']).$dbPhoto->competitor_photo;

				// get photo specs
				$photoDetails = exif_read_data($photo);
				$primaryDetails = array('FileName', 'DateTimeOriginal', 'Make', 'Model', 'MimeType', 'ExposureTime', 'FNumber', 'ISOSpeedRatings', 'ShutterSpeedValue', 'Flash');

				// create ajax response
				$ajaxResponse = '';
				$ajaxResponse .= '<div>';
					$ajaxResponse .= '<div class="entered-photo" style="display: table-cell; width: 50%; vertical-align: top; padding: 3rem;">';
						$ajaxResponse .= '<img src="'.$photo.'" style="max-width: 100%;" />';
					$ajaxResponse .= '</div>';
					$ajaxResponse .= '<div class="details-photo" style="display: table-cell; width: 50%; vertical-align: top; padding: 3rem;">';
						$ajaxResponse .= '<p>Filename: '.$photoDetails['FileName'].'</p>';
						$ajaxResponse .= '<p>Dimensions: '.$photoDetails['COMPUTED']['Width'].'px x '.$photoDetails['COMPUTED']['Height'].'px</p>';
						$ajaxResponse .= '<p>Original DateTime: '.$photoDetails['DateTimeOriginal'].'</p>';
						$ajaxResponse .= '<p>Upload DateTime: '.$dbPhoto->upload_date.'</p>';
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


				// return ajax response and terminate
				die($ajaxResponse);
			}


			/**
			 * CALLBACK FUNCTION TO SEND TEST ADMIT EMAIL
			 */
			public function testAdmitEmail()
			{
				$currentUser = wp_get_current_user();

				// connect to SendGrid API
				$sendgrid = new SendGrid(get_option('sendgrid_user'), get_option('sendgrid_pwd'));

				// create new email
				$email = new SendGrid\Email();

				// add recipient email
				$email->addTo($currentUser->user_email, $currentUser->user_firstname.' '.$currentUser->user_lastname)
					  ->setFrom("wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']))
					  ->setFromName(get_bloginfo())
					  ->setSubject(esc_attr($_POST['subject']))
					  ->setHtml(wp_kses($_POST['body'], $this->expanded_alowed_tags()));

				// send email to user
				$sendgrid->send($email);
				
				// ajax response
				$ajaxResponse = ' Email sent to '.$currentUser->user_email;
				
				// return ajax response and terminate
				die($ajaxResponse);
			}
		}

	endif;

?>