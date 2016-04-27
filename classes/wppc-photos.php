<?php

	// Security check
	if (!defined('ABSPATH')) die;

	// Pre-requirements
	if (!class_exists('WP_List_Table'))
	    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
	
	if (!class_exists('WPPCPhotos')):
		class WPPCPhotos extends WP_List_Table
		{
			/**
			 * Photos folders
			 */
			private $folders;	

			/**
			 * Class constructor
			 *
			 * @since 1.0
			 */
			public function __construct()
			{
				global $status, $page, $wpdb;

				$wpDir = wp_upload_dir();

				// Initialize params
				$contestID = isset($_GET['contest']) ? $_GET['contest'] : $wpdb->get_var("SELECT id FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE 1 ORDER BY id DESC LIMIT 1");
				$this->folders = array(
					'raw'		=> $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contestID.'/raw/',
					'full'		=> $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contestID.'/full/',
					'medium'	=> $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contestID.'/medium/',
					'thumb'		=> $wpDir['baseurl'].'/wppc-photos/wppc-photos-'.$contestID.'/thumbs/',
				);

				// Set parent defaults
				parent::__construct(array(
					'singular'	=> 'photo',
					'plural'	=> 'photos',
					'ajax'		=> true,
				));

				// Load admin scripts
				add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));				 
			}


			/**
			 * Enqueue admin scripts
			 */
			public function enqueueAdminScripts()
			{
				// Custom CSS
				wp_enqueue_style('admin-wppc-contests', WPPC_URI.'css/wppc-photos.css', '', WPPC_VERSION);

				// jQuery
				wp_enqueue_script('jquery', '', '', '', true);
				
				// jQuery UI core
				wp_enqueue_script('jquery-ui-core', array('jquery'), '', '', true);
				
				// jQuery UI Tabs
				wp_enqueue_script('jquery-ui-tabs', array('jquery-ui-core'), '', '', true);

				// jQuery UI Dialog
	    		wp_enqueue_style('wp-jquery-ui-dialog');
				wp_enqueue_script('jquery-ui-dialog');
				
				// Custom JS script
				wp_enqueue_script('wppc-photos-js', WPPC_URI.'js/wppc-photos.js', array('jquery'), WPPC_VERSION, true);

				// Load AJAX PHP FILE - TO BE DELETED
				wp_localize_script('wppc-photos-js', 'wppc', array(
					'nonce' 	=> wp_create_nonce(WPPC_NONCE),
					'photoURL'	=> urlencode($this->folders['raw']),
				));

				// Google Maps JavaScript API
				wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBiYR3nxRN3-a8t6o2ZSxM6_4UTv6lo41g', '', '', true);
			}


			/**
			 * Set page title
			 *
			 * @since 1.0
			 */
			public function setTitle()
			{
				global $wpdb;

				echo '<h2><strong>'.$wpdb->get_var("SELECT contest_name FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest']).'</strong> Photos</h2>';
			}


			/**
			 * Set columns
			 *
			 * @since 1.0
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
			 * Fallback function to render columns
			 *
			 * @since 1.0
			 */
			public function column_default($item, $column_name)
			{
				return $item[$column_name];
			}


			/**
			 * Checkbox column
			 *
			 * @since 1.0
			 */
			public function column_cb($item)
			{
				return sprintf('<input type="checkbox" name="%1$s[]" value="%2$s" />', $this->_args['singular'], $item['id']);
			}


			/**
			 * Photo thumb column
			 *
			 * @since 1.0
			 */
			public function column_photo_id($item)
			{
				return sprintf('<a target="_blank" href="%s"><img style="width: 60px" src="%s" /></a>', $this->folders['raw'].$item['competitor_photo'], $this->folders['thumb'].$item['competitor_photo']);
			}


			/**
			 * Photo column
			 *
			 * @since 1.0
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
				
				// Set actions based on view
				if ($newItems && !isset($_GET['status'])):
						$actions = array(
							'publish'	=> sprintf('<a href="?page=wppc-photos&contest=%s&photo=%s&action=%s">Approve</a>', $_GET['contest'], $item['photo_id'], 'approve'),
							'trash'		=> sprintf('<a href="?page=wppc-photos&contest=%s&photo=%s&action=%s">Reject</a>', $_GET['contest'], $item['photo_id'], 'trash'),
						);
					elseif ((!$newItems && !isset($_GET['status']) || ($newItems && isset($_GET['status']) && $_GET['status'] == 'publish'))):
							$actions = array(
								'download-raw'	=> sprintf('<a download href="%s">Download raw</a>', $this->folders['raw'].$item['competitor_photo']),
								'download-copy'	=> sprintf('<a download href="%s">Download &copy;</a>', $this->folders['medium'].$item['competitor_photo']),
								'voters'		=> sprintf('<a href="#" data-photo-id="%s">View IPs</a>', $item['photo_id']),
							);
						else:
							$actions = array(
								'restore'	=> sprintf('<a href="?page=wppc-photos&contest=%s&photo=%s&action=%s">Restore</a>', $_GET['contest'], $item['photo_id'], 'restore'),
								'delete'	=> sprintf('<a href="?page=wppc-photos&contest=%s&photo=%s&action=%s&file=%s">Delete permanelty</a>', $_GET['contest'], $item['photo_id'], 'delete', $item['competitor_photo']),
							);
				endif;

		        return sprintf('%1$s %2$s', $photo, $this->row_actions($actions));
			}


			/**
			 * Competitor name column
			 *
			 * @since 1.0
			 */
			public function column_competitor_name($item)
			{
				return sprintf('<a target="_blank" href="mailto:%s">%s</a>', $item['competitor_email'], $item['competitor_name']);
			}


			/**
		     * Set sortable columns
		     *
		     * @since 1.0
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
			 * Set views
			 *
			 * @since 1.0
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
			 * Set bulk actions
			 *
			 * @since 1.0
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
		     * Process bulk actions
		     *
		     * @since 1.0
		     */
		    public function processActions()
		    {
		    	global $wpdb;

		    	switch ($this->current_action()):

		    		// Approve photos
		    		case "approve":
		    			if (!is_array($_GET['photo'])):
								
								// Update the database to add the photo to the contest
		    					$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_APPROVED), array('photo_id' => $_GET['photo'], 'contest_id' => $_GET['contest']));
		    				
								// Get contact information
								$contactInfo = $wpdb->get_row("SELECT competitor_name, competitor_email FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE contest_id=".$_GET['contest']." AND photo_id=".$_GET['photo'], ARRAY_A);

		    					// Get email subject and body
								$contestEmails = unserialize($wpdb->get_var("SELECT contest_emails FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest']));

								// Create email
								$to = $contactInfo['competitor_email'];
								$subject = $contestEmails['admitted-body'];
								$message = $contestEmails['admitted-body'];
								$headers   = array();
								$headers[] = "MIME-Version: 1.0";
								$headers[] = "Content-type: text/html; charset=utf-8";
								$headers[] = "From: {get_bloginfo()} <wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']).">";
								$headers[] = "Subject: {$subject}";
								$headers[] = "X-Mailer: PHP/".phpversion();

								// Send email
								$emailResponse = wp_mail($to, $subject, $message, $headers);
		    				else:
		    					foreach ($_GET['photo'] as $photo):
	
									// Update the database to add the photo to the contest
		    						$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_APPROVED), array('photo_id' => $photo, 'contest_id' => $_GET['contest']));

		    						// Get contact information
									$contactInfo = $wpdb->get_row("SELECT competitor_name, competitor_email FROM WPPC_TABLE_CONTESTS_ENTRIES WHERE contest_id=".$_GET['contest']." AND photo_id=".$photo, ARRAY_A);

			    					// Get email subject and body
									$contestEmails = unserialize($wpdb->get_var("SELECT contest_emails FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE id=".$_GET['contest']));

									// Create email
									$to = $contactInfo['competitor_email'];
									$subject = $contestEmails['admitted-body'];
									$message = $contestEmails['admitted-body'];
									$headers   = array();
									$headers[] = "MIME-Version: 1.0";
									$headers[] = "Content-type: text/html; charset=utf-8";
									$headers[] = "From: {get_bloginfo()} <wppc@".str_replace('www.', '', $_SERVER['SERVER_NAME']).">";
									$headers[] = "Subject: {$subject}";
									$headers[] = "X-Mailer: PHP/".phpversion();

									// Send email
									$emailResponse = wp_mail($to, $subject, $message, $headers);
		    					endforeach;
		    			endif;
		    			break;

		    		// Reject photos
		    		case "trash":
		    			if (!is_array($_GET['photo']))
		    					$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_REJECTED), array('photo_id' => $_GET['photo'], 'contest_id' => $_GET['contest']));			
		    				else foreach ($_GET['photo'] as $photo)
		    					$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_REJECTED), array('photo_id' => $photo, 'contest_id' => $_GET['contest']));			
		    			break;

		    		// Restore photos
		    		case 'restore':
						if (!is_array($_GET['photo']))
								$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_NEW), array('photo_id' => $_GET['photo']));
							else foreach ($_GET['photo'] as $photo)
								$wpdb->update(WPPC_TABLE_CONTESTS_ENTRIES, array('visible' => WPPC_PHOTO_NEW), array('photo_id' => $photo));
		    			break;

		    		// Delete photos
		    		case 'delete':
		    			if (!is_array($_GET['photo'])):
			    			$wpdb->delete(WPPC_TABLE_CONTESTS_ENTRIES, array('photo_id' => $_GET['photo']));
						
							// Change file permissions
							chmod($contestPath.'raw/'.$_GET['file'], 0755);
							chmod($contestPath.'full/'.$_GET['file'], 0755);
							chmod($contestPath.'medium/'.$_GET['file'], 0755);
							chmod($contestPath.'thumbs/'.$_GET['file'], 0755);
							
							// Delete all photo's versions
							if (is_writable($contestPath.'raw/'.$_GET['file'])):
								if (file_exists($contestPath.'raw/'.$_GET['file'])) unlink($contestPath.'raw/'.$_GET['file']);
								if (file_exists($contestPath.'full/'.$_GET['file'])) unlink($contestPath.'full/'.$_GET['file']);					
								if (file_exists($contestPath.'medium/'.$_GET['file'])) unlink($contestPath.'medium/'.$_GET['file']);
								if (file_exists($contestPath.'thumbs/'.$_GET['file'])) unlink($contestPath.'thumbs/'.$_GET['file']);
							endif;

							// Delete database entry
							$wpdb->delete($tableName, array('contest_id' => $_GET['contest'], 'photo_id' => $_GET['photoid']));
		    			endif;
		    			break;

		    	endswitch;
		    }


			/**
		     * Prepare data for rendering
		     *
		     * @since 1.0
		     */
		    public function prepare_items()
		    {
		        // How many records are to be shown on page
				$per_page = 20;

				// Columns array to be displayed
		        $columns = $this->get_columns();

		        // Columns array to be hidden
		        $hidden = array();

		        // List of sortable columns
		        $sortable = $this->get_sortable_columns();
		        
		        // Create the array that is used by the class
		        $this->_column_headers = array($columns, $hidden, $sortable);
		        
		        // Process single actions
		        $this->processActions();

		      	// Current page
		        $current_page = $this->get_pagenum();

		        // Get contests
		        $data = $this->getData();
		        
		        // Total number of items
		        $total_items = count($data);
		        
		        // Slice data for pagination
		        $data = array_slice($data, (($current_page-1)*$per_page), $per_page);
		        
		        // Send processed data to the items property to be used
		        $this->items = $data;
		        
		        // Register pagination options & calculations
		        $this->set_pagination_args(array(
		            'total_items' => $total_items,                  //WE have to calculate the total number of items
		            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
		            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
		        ));
		    }


		    /**
		     * Get photos
		     *
		     * @since 1.0
		     */
		    private function getData()
		    {
		    	global $wpdb;

		    	// Set default contest as last contest
		    	if (!isset($_GET['contest'])) $_GET['contest'] = $wpdb->get_var("SELECT id FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE 1 ORDER BY id DESC LIMIT 1");

		    	// Get order params for the SQL query
				$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id'; //If no sort, default to title
				$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC'; //If no order, default to asc

		    	$newItems = $wpdb->get_var("SELECT COUNT(visible) FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE visible=0 AND contest_id=".$_GET['contest']);
				
				if ($newItems) $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'new');
					else $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

				$where = 'contest_id=';
		    	$where .= isset($_GET['contest']) ? $_GET['contest'] : $wpdb->get_var("SELECT id FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE 1 ORDER BY id DESC LIMIT 1");
		    	$where .= ' AND ';

		    	// Search
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


	/**
	 * Create menu page
	 */
	function addWPPCContestPhotos()
	{
		$hook = add_submenu_page('wppc-all-contests', 'Contest Photos', 'Photos', 'manage_options', 'wppc-photos', 'renderContestPhotos');
		add_action('load-'.$hook, 'addWPPCContestPhotosOptions');
	}


	/**
	 * Create options screen
	 */
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


	/**
	 * Render photos
	 */
	function renderContestPhotos()
	{
		global $contestPhotos;

		// Fetch, prepare, sort, and filter the photos
	    $contestPhotos->prepare_items();
		?>

		<div class="wrap">
			<?php $contestPhotos->setTitle() ?>

			<div id="modal-content" title="" class="hidden"></div>

			<form id="photos" method="get" action="">

				<!-- Separate photos by new | approved | rejected -->
				<?php $contestPhotos->views() ?>

				<!-- Search box -->
	        	<?php $contestPhotos->search_box('search', 'photo') ?>

				<!-- For plugins, we also need to ensure that the form posts back to our current page -->
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

				<!-- Now we can render the completed list table -->
				<?php $contestPhotos->display() ?>
			</form>
		</div>
		<?php
	}


	/**
	 * Get voters Ajax callback
	 */
	add_action('wp_ajax_view-photo-voters', 'viewPhotoVoters');
	function viewPhotoVoters()
	{
		global $wpdb;

		// Get photo id;
		$photoID = $_GET['photoid'];
		
		// Get voters
		$votes = $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_CONTESTS_VOTES." WHERE photo_id=".$photoID);

		// Create ajax response
		$ajaxResponse = array();

		foreach ($votes as $vote)
			$ajaxResponse[] = array(
				'ip'	=> long2ip($vote->vote_ip),
				'votes'	=> $vote->vote_number
			);

		// Return ajax response and terminate
		die(json_encode($ajaxResponse));
	}

	/**
	 * Get photo specs Ajax callback
	 */
	add_action('wp_ajax_view-photo-specs', 'viewPhotoSpecs');
	function viewPhotoSpecs()
	{
		global $wpdb;

		// Security check
		if (!wp_verify_nonce($_GET['nonce'], WPPC_NONCE))
			die(__("Security check failed"));

		// Get photo url
		$photo = $_GET['photo'];
		
		// Get photo upload date from db
		$dbPhoto = $wpdb->get_row("SELECT upload_date, contest_id, competitor_photo FROM ".WPPC_TABLE_CONTESTS_ENTRIES." WHERE photo_id=$photo");

		$photo = urldecode($_GET['photoURL']).$dbPhoto->competitor_photo;

		// Get photo specs
		$photoDetails = exif_read_data($photo);
		$primaryDetails = array('FileName', 'DateTimeOriginal', 'Make', 'Model', 'MimeType', 'ExposureTime', 'FNumber', 'ISOSpeedRatings', 'ShutterSpeedValue', 'Flash');

		// Create ajax response
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

		// Return ajax response and terminate
		die($ajaxResponse);
	}

?>