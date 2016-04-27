<?php

	// Security check
	if (!defined('ABSPATH')) die;
	
	// Pre-requirements
	if (!class_exists('WP_List_Table'))
	    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');

	// Define class
	if (!class_exists('WPPCAllContests')):
		class WPPCAllContests extends WP_List_Table
		{
			/**
			 * Class constructor
			 *
			 * @since 1.0
			 */
			public function __construct()
			{
				global $status, $page;

				// set parent defaults
				parent::__construct(array(
					'singular'	=> __('contest', 'text-domain'),
					'plural'	=> __('contests', 'text-domain'),
					'ajax'		=> false
				));
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
		            'contest_name'		=> __('Contest name'),
		            'start_date'		=> __('Start date'),
		            'end_date'			=> __('End date'),
		            'photos_allowed'	=> __('Photos allowed'),
		            'votes_allowed'		=> __('Votes allowed'),
		            'shortcode'			=> __('Shrotcode'),
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
			 * Contest name column
			 *
			 * @since 1.0
			 */
			public function column_contest_name($item)
			{
				$title = $item['contest_name'];
		    	if (isset($_GET['status']) && $_GET['status'] == "trash"):
						$actions = array(
							'restore'		=> sprintf('<a href="?page=%s&status=trash&contest=%s&action=%s">Restore</a>', $_REQUEST['page'], $item['id'], 'restore'),
							'delete'		=> sprintf('<a href="?page=%s&status=trash&contest=%s&action=%s">Delete permanently</a>', $_REQUEST['page'], $item['id'], 'delete'),
						); 
					else:
						$title = sprintf('<a class="row-title" href="?page=%s&contest=%s&activity=%s" title="Edit %s">%s</a>', 'wppc-contest', $item['id'], 'edit', $item['contest_name'], $item['contest_name']);
						$actions = array(
							'edit'		=> sprintf('<a href="?page=%s&contest=%s&activity=%s">Edit</a>', 'wppc-contest', $item['id'], 'edit'),
							'view'		=> sprintf('<a href="?page=%s&contest=%s">Photos</a>', 'wppc-photos', $item['id']),
							'trash'		=> sprintf('<a href="?page=%s&contest=%s&action=%s">Trash</a>', $_REQUEST['page'], $item['id'], 'trash'),
							'stats'		=> sprintf('<a href="?page=%s&contest=%s&activity=%s">Stats</a>', 'wppc-contest', $item['id'], 'stats'),
						);
				endif;

		        return sprintf('%1$s %2$s', $title, $this->row_actions($actions));
			}

			/**
			 * Shortcode column
			 *
			 * @since 1.0
			 */
			public function column_shortcode($item)
			{
				return sprintf('<code>[wphotocontest id=%s]</code>', $item['id']);
			}

			/**
		     * Set sortable columns
		     *
		     * @since 1.0
		     */
		    public function get_sortable_columns()
		    {
		        $sortable_columns = array(
		            'contest_name'		=> array('contest_name', false),
		            'start_date'		=> array('start_date', false),
		            'end_date'			=> array('end_date', false),
		            'photos_allowed'	=> array('photos_allowed', false),
		            'votes_allowed'		=> array('votes_allowed', false),
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
				$current = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

				$publishedItems = $wpdb->get_var("SELECT COUNT(status) FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE status=1");
				$trashedItems = $wpdb->get_var("SELECT COUNT(status) FROM ".WPPC_TABLE_ALL_CONTESTS." WHERE status=0");

				// Publish link
				if ($publishedItems):
					$class = ($current == 'publish' ? ' class="current"' :'');
					$publishedURL = remove_query_arg('status');
					$views['publish'] = "<a href='{$publishedURL }' {$class} >Publish ({$publishedItems})</a>";
				endif;

				// Trash link
				if ($trashedItems):
					$class = ($current == 'trash' ? ' class="current"' :'');
					$trashedURL = add_query_arg('status','trash');
					$views['trash'] = "<a href='{$trashedURL}' {$class} >Trash ({$trashedItems})</a>";
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
		    	if (isset($_GET['status']) && $_GET['status'] == "trash")
		    			$actions = array('restore' => __('Restore'), 'delete' => __('Delete permanently'));
		    		else
		    			$actions = array('trash' => __('Trash'));

		    	return $actions;
		    }

		    /**
		     * Process bulk actions
		     *
		     * @since 1.0
		     */
		    public function process_bulk_action()
		    {
		    	global $wpdb;
				
				switch ($this->current_action()):

					// Trash contests
		    		case 'trash':
		    			if (!is_array($_GET['contest']))
								$wpdb->update(WPPC_TABLE_ALL_CONTESTS, array('status' => 0), array('id' => $_GET['contest']));
							else foreach ($_GET['contest'] as $contest)
								$wpdb->update(WPPC_TABLE_ALL_CONTESTS, array('status' => 0), array('id' => $contest));
						break;

					// Restore contests
					case 'restore':
			    		if (!is_array($_GET['contest'])) $wpdb->update(WPPC_TABLE_ALL_CONTESTS, array('status' => 1), array('id' => $_GET['contest']));
			   				else foreach ($_GET['contest'] as $contest)
			   					$wpdb->update(WPPC_TABLE_ALL_CONTESTS, array('status' => 1), array('id' => $contest));
						break;

					// Delete contests
					case 'delete':
						if (!is_array($_GET['contest'])):
								// DELETE CONTEST
								$wpdb->delete(WPPC_TABLE_ALL_CONTESTS, array('id' => $_GET['contest']));

								// DELETE CONTEST ENTRIES
								$wpdb->delete(WPPC_TABLE_CONTESTS_ENTRIES, array('contest_id' => $_GET['contest']));

								// DELETE CONTEST VOTES
								$wpdb->delete(WPPC_TABLE_CONTESTS_VOTES, array('contest_id' => $_GET['contest']));
							else:
								foreach ($_GET['contest'] as $contest):
		    			
									// DELETE CONTEST
									$wpdb->delete(WPPC_TABLE_ALL_CONTESTS, array('id' => $contest));

									// DELETE CONTEST ENTRIES
									$wpdb->delete(WPPC_TABLE_CONTESTS_ENTRIES, array('contest_id' => $contest));

									// DELETE CONTEST VOTES
									$wpdb->delete(WPPC_TABLE_CONTESTS_VOTES, array('contest_id' => $contest));
		    					endforeach;
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
		        
		        // Process bulk actions
		        $this->process_bulk_action();

		       	// Current page
		        $current_page = $this->get_pagenum();

		        // Get contests
		        $data = $this->getContests();
		        
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
		     * Get contests
		     *
		     * @since 1.0
		     */
		    private function getContests()
		    {
		    	global $wpdb;

		    	// Get item status
		        $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

		        // Get order params for the SQL query
				$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id'; //If no sort, default to title
				$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC'; //If no order, default to asc

		        // Set where SQL
		        $where = 'WHERE ';
		        if ($status == 'publish') $where .= 'status=1 AND ';
		        	else $where .= 'status=0 AND ';

		        // Search SQL
		        if (isset($_GET['s']))
		        	$where .= 'contest_name LIKE "%'.esc_attr($_GET['s']).'%" AND';

		        $where = rtrim($where, ' AND ');
		        
		        // Return data from the db
		      	return $wpdb->get_results("SELECT * FROM ".WPPC_TABLE_ALL_CONTESTS." $where ORDER BY $orderby $order", ARRAY_A);
		    }

		}
	endif;

	/**
	 * Create menu page
	 */
	function addWPPCAllContests()
	{
		$hook = add_menu_page(__('WordPress Photo Contests'), __('Photo Contests'), 'manage_options', 'wppc-all-contests', 'displayWPPCAllContests', plugins_url('wp-photo-contest/img/icon_16.png'), 99);
		add_action('load-'.$hook, 'addWPPCAllContestsOptions');

		$hook = add_submenu_page("wppc-all-contests", __('All Contests'), __('All Contests'), "manage_options", "wppc-all-contests", 'displayWPPCAllContests');
		add_action('load-'.$hook, 'addWPPCAllContestsOptions');
	}

	/**
	 * Create options screen
	 */
	function addWPPCAllContestsOptions()
	{
		global $allWPPCContests;

		$option = 'per_page';

		$args = array(
			'label' 	=> 'Contests',
			'default' 	=> 20,
			'option'	=> 'contests_per_page',
		);
		add_screen_option($option, $args);
		$allWPPCContests = new WPPCAllContests();
	}
	add_action('admin_menu', 'addWPPCAllContests');

	/**
	 * Render all contests
	 */
	function displayWPPCAllContests()
	{
		global $allWPPCContests;

		// Fetch, prepare, sort, and filter the contests
	    $allWPPCContests->prepare_items();
		?>
		
		<div class="wrap">
			<h2>
				All Contests 
				<a class="add-new-h2" href="?page=wppc-contest" title="Add New">Add New</a>
			</h2>

			<form id="wppc-all-contests-form" method="get" action="">
	        	<?php $allWPPCContests->views() ?>
	        
	        	<!-- Search box -->
	        	<?php $allWPPCContests->search_box('search', 'search_id') ?>
	        
	            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
	            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
	        
	            <!-- Now we can render the completed list table -->
	            <?php $allWPPCContests->display() ?>
	        </form>
		</div>
		<?php
	}

?>