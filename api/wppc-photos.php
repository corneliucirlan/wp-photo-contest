<?php

	// SECURITY CHECK
	if (!defined('ABSPATH')) die;

	// PRE-REQUIREMENTS
	if (!class_exists('WP_List_Table'))
	    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
	
	if (!class_exists('WPPCPhotos')):
		class WPPCPhotos extends WP_List_Table
		{
			/**
			 * PHOTOS FOLDERS
			 */
			private $folders;	

			/**
			 * CONSTRUCTOR
			 */
			public function __construct()
			{
				global $status, $page, $wpdb;

				$wpDir = wp_upload_dir();

				// initialize params
				$contestID = isset($_GET['contest']) ? $_GET['contest'] : $wpdb->get_var("SELECT id FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE 1 ORDER BY id DESC LIMIT 1");
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
					'ajax'		=> true,
				));

				// load admin scripts
				add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));				 
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
					'nonce' 	=> wp_create_nonce(WPPC_NONCE),
					'photoURL'	=> urlencode($this->folders['raw']),
				));

				// Google Maps JavaScript API
				wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBiYR3nxRN3-a8t6o2ZSxM6_4UTv6lo41g', '', '', true);
			}


			/**
			 * SET PAGE TITLE
			 */
			public function setTitle()
			{
				global $wpdb;

				echo '<h2><strong>'.$wpdb->get_var("SELECT contest_name FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest']).'</strong> Photos</h2>';
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
				
				$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE visible=0 AND contest_id=".$_GET['contest']);

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

				$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE visible=".WPPC_PHOTO_NEW." AND contest_id=".$_GET['contest']);
				$trashedItems = $wpdb->get_var("SELECT COUNT(visible) FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE visible=".WPPC_PHOTO_REJECTED." AND contest_id=".$_GET['contest']);
				$publishedItems = $wpdb->get_var("SELECT COUNT(visible) FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE visible=".WPPC_PHOTO_APPROVED." AND contest_id=".$_GET['contest']);

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
		    					$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_APPROVED), array('photo_id' => $_GET['photo'], 'contest_id' => $_GET['contest']));
		    				
								// get contact information
								$contactInfo = $wpdb->get_row("SELECT competitor_name, competitor_email FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$_GET['contest']." AND photo_id=".$_GET['photo'], ARRAY_A);

		    					// get email subject and body
								$contestEmails = unserialize($wpdb->get_var("SELECT contest_emails FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest']));

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
		    						$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_APPROVED), array('photo_id' => $photo, 'contest_id' => $_GET['contest']));

		    						// get contact information
									$contactInfo = $wpdb->get_row("SELECT competitor_name, competitor_email FROM WPPC_TABLE_CONTESTS_ENTRIES WHERE contest_id=".$_GET['contest']." AND photo_id=".$photo, ARRAY_A);

			    					// get email subject and body
									$contestEmails = unserialize($wpdb->get_var("SELECT contest_emails FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest']));

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
		    					$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_REJECTED), array('photo_id' => $_GET['photo'], 'contest_id' => $_GET['contest']));			
		    				else foreach ($_GET['photo'] as $photo)
		    					$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_REJECTED), array('photo_id' => $photo, 'contest_id' => $_GET['contest']));			
		    			break;

		    		// RESTORE PHOTO
		    		case 'restore':
						if (!is_array($_GET['photo']))
								$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_NEW), array('photo_id' => $_GET['photo']));
							else foreach ($_GET['photo'] as $photo)
								$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_NEW), array('photo_id' => $photo));
		    			break;

		    		// DELETE PHOTO
		    		case 'delete':
		    			if (!is_array($_GET['photo'])):
				    			$wpdb->delete(WPPC_TABLE_CONTESTS_ENTRIES, array('photo_id' => $_GET['photo']));
							
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
		    	if (!isset($_GET['contest'])) $_GET['contest'] = $wpdb->get_var("SELECT id FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE 1 ORDER BY id DESC LIMIT 1");

		    	// get order params for the SQL query
				$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id'; //If no sort, default to title
				$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC'; //If no order, default to asc

		    	$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE visible=0 AND contest_id=".$_GET['contest']);
				
				if ($newItems) $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'new');
					else $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

				$where = 'contest_id=';
		    	$where .= isset($_GET['contest']) ? $_GET['contest'] : $wpdb->get_var("SELECT id FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE 1 ORDER BY id DESC LIMIT 1");
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

		    	return $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE $where ORDER BY $orderby $order", ARRAY_A);
		    }
		}
	endif;


	function addWPPCContestPhotos()
	{
		$hook = add_submenu_page('wppc-all-contests', 'Contest Photos', 'Photos', 'manage_options', 'wppc-photos', 'renderContestPhotos');
		add_action('load-'.$hook, 'addWPPCContestPhotosOptions');
	}


	function addWPPCContestPhotosOptions()
	{
		global $contestPhotos;

		$option = 'per_page';

		$args = array(
			'label'		=> 'Photos',
			'default'	=> 20,
			'option'	=> 'photos_per_page',
		);
		add_screen_option($option, $args);
		$contestPhotos = new WPPCPhotos();
	}
	add_action('admin_menu', 'addWPPCContestPhotos');


	function renderContestPhotos()
	{
		global $contestPhotos;

		//Fetch, prepare, sort, and filter our data...
	    $contestPhotos->prepare_items();
		?>
		<div class="wrap">
			<?php $contestPhotos->setTitle() ?>

			<div id="modal-content" title="" class="hidden"></div>

			<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
			<form id="photos" method="get" action="">

				<!-- separate photos by new | approved | rejected -->
				<?php $contestPhotos->views() ?>

				<!-- search box -->
	        	<?php $contestPhotos->search_box('search', 'photo') ?>

				<!-- For plugins, we also need to ensure that the form posts back to our current page -->
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

				<!-- Now we can render the completed list table -->
				<?php $contestPhotos->display() ?>
			</form>
		</div>
		<?php
	}



	// Get voters action
	add_action('wp_ajax_view-photo-voters', 'viewPhotoVoters');
	function viewPhotoVoters()
	{
		global $wpdb;

		// get photo id;
		$photoID = $_GET['photoid'];
		
		// get voters
		$votes = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_VOTES." WHERE photo_id=".$photoID);

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

	// Get photo specs action
	add_action('wp_ajax_view-photo-specs', 'viewPhotoSpecs');
	function viewPhotoSpecs()
	{
		global $wpdb;

		// security check
		if (!wp_verify_nonce($_GET['nonce'], WPPC_NONCE))
			die(__("Security check failed"));

		// get photo url
		$photo = $_GET['photo'];
		
		// get photo upload date from db
		$dbPhoto = $wpdb->get_row("SELECT upload_date, contest_id, competitor_photo FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE photo_id=$photo");

		$photo = urldecode($_GET['photoURL']).$dbPhoto->competitor_photo;

		// get photo specs
		$photoDetails = exif_read_data($photo);
		$primaryDetails = array('FileName', 'DateTimeOriginal', 'Make', 'Model', 'MimeType', 'ExposureTime', 'FNumber', 'ISOSpeedRatings', 'ShutterSpeedValue', 'Flash');

		// create ajax response
		$ajaxResponse = '<div>';
			$ajaxResponse .= '<div class="entered-photo" style="display: table-cell; width: 50%; vertical-align: top; padding: 3rem;">';
				$ajaxResponse .= '<img src="'.$photo.'" style="max-width: 100%;" />';
			$ajaxResponse .= '</div>';
			$ajaxResponse .= '<div class="details-photo" style="display: table-cell; width: 50%; vertical-align: top; padding: 3rem;">';
				if (array_key_exists('FileName', $photoDetails)) $ajaxResponse .= '<p>Filename: '.$photoDetails['FileName'].'</p>';
				if (array_key_exists('Width', $photoDetails['COMPUTED'])) $ajaxResponse .= '<p>Dimensions: '.$photoDetails['COMPUTED']['Width'].'px x '.$photoDetails['COMPUTED']['Height'].'px</p>';
				if (array_key_exists('DateTimeOriginal', $photoDetails)) $ajaxResponse .= '<p>Original DateTime: '.$photoDetails['DateTimeOriginal'].'</p>';
				$ajaxResponse .= '<p>Upload DateTime: '.$dbPhoto->upload_date.'</p>';
				if (array_key_exists('Make', $photoDetails)) $ajaxResponse .= '<p>Camera: '.$photoDetails['Make'].'</p>';
				if (array_key_exists('Model', $photoDetails)) $ajaxResponse .= '<p>Model: '.$photoDetails['Model'].'</p>';
				if (array_key_exists('MimeType', $photoDetails)) $ajaxResponse .= '<p>File Type: '.$photoDetails['MimeType'].'</p>';
				if (array_key_exists('ExposureTime', $photoDetails)) $ajaxResponse .= '<p>Exposure: '.$photoDetails['ExposureTime'].'</p>';
				if (array_key_exists('FNumber', $photoDetails)) $ajaxResponse .= '<p>FNumber: '.$photoDetails['FNumber'].'</p>';
				if (array_key_exists('ISOSpeedRatings', $photoDetails)) $ajaxResponse .= '<p>ISO: '.$photoDetails['ISOSpeedRatings'].'</p>';
				if (array_key_exists("ShutterSpeedValue", $photoDetails)) $ajaxResponse .= '<p>Shutter: '.$photoDetails['ShutterSpeedValue'].'</p>';
				if (array_key_exists('Flash', $photoDetails)) $ajaxResponse .= '<p>Flash: '.$photoDetails['Flash'].'</p>';
			$ajaxResponse .= '</div>';
		$ajaxResponse .= '</div>';

		// return ajax response and terminate
		die($ajaxResponse);
	}

?>