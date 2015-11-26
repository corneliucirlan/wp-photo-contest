<?php

	// SECURITY CHECK
	if (!defined('ABSPATH')) die;
	
	// PRE-REQUIREMENTS
	require_once(ABSPATH.'wp-admin/includes/template.php');
	if (!class_exists('WP_List_Table'))
	    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
	if (!class_exists('WP_Screen'))
		require_once( ABSPATH.'wp-admin/includes/screen.php');

	if (!class_exists('WPPCAllContests')):
		class WPPCAllContests extends WP_List_Table
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
					'singular'	=> 'Contest',
					'plural'	=> 'Contests',
					'screen'	=> 'contests-list',
					'ajax'		=> false,
				));

				// CREATE WPPC MENU
				add_action('admin_menu', array($this, 'renderMenuItems'));
			}


			/**
			 * CALLBACK FUNCTION TO RENDER MENU ITEMS
			 */
			public function renderMenuItems()
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
				//Fetch, prepare, sort, and filter our data...
			    $this->prepare_items();
				?>
				<div class="wrap">
					<h2>
						All Contests 
						<a class="add-new-h2" href="?page=wppc-contest" title="Add New">Add New</a>
					</h2>

					<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
			        <form id="bus-schedule-form" method="get" action="">
			        	<?php $this->views() ?>
			        	<!-- search box -->
			        	<?php $this->search_box('search', 'schedule') ?>
			            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
			            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
			            <!-- Now we can render the completed list table -->
			            <?php $this->display() ?>
			        </form>
				</div>
				<?php
			}


			/**
			 * SET COLUMNS AND TITLES
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
			 * CONTEST NAME COLUMN
			 */
			public function column_contest_name($item)
			{
				$title = $item['contest_name'];
		    	if (isset($_GET['status']) && $_GET['status'] == "trash")
						$actions = array(
							'restore'		=> sprintf('<a href="?page=%s&status=trashcontest=%s&action=%s">Restore</a>', $_REQUEST['page'], $item['id'], 'restore'),
							'delete'		=> sprintf('<a href="?page=%s&status=trashcontest=%s&action=%s">Delete permanently</a>', $_REQUEST['page'], $item['id'], 'delete'),
						); 
					else
						$title = sprintf('<a class="row-title" href="?page=%s&contest=%s&activity=%s" title="Edit %s">%s</a>', 'wppc-contest', $item['id'], 'edit', $item['contest_name'], $item['contest_name']);
						$actions = array(
							'edit'		=> sprintf('<a href="?page=%s&contest=%s&activity=%s">Edit</a>', 'wppc-contest', $item['id'], 'edit'),
							'view'		=> sprintf('<a href="?page=%s&contest=%s">Photos</a>', 'wppc-photos', $item['id']),
							'trash'		=> sprintf('<a href="?page=%s&contest=%s&action=%s">Trash</a>', $_REQUEST['page'], $item['id'], 'trash'),
							'stats'		=> sprintf('<a href="?page=%s&contest=%s&activity=%s">Stats</a>', 'wppc-contest', $item['id'], 'stats'),
						);

		        return sprintf('%1$s %2$s', $title, $this->row_actions($actions));
			}


			/**
			 * SHORTCODE COLUMN
			 */
			public function column_shortcode($item)
			{
				return sprintf('<code>[wphotocontest id=%s]</code>', $item['id']);
			}


			/**
		     * SET SORTABLE COLUMNS
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
		     * GET VIEWS
		     */
		    public function get_views()
		    {
		    	global $wpdb;

		    	$views = array();
				$current = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

				$publishedItems = $wpdb->get_var("SELECT COUNT(status) FROM $this->contestsTable WHERE status=1");
				$trashedItems = $wpdb->get_var("SELECT COUNT(status) FROM $this->contestsTable WHERE status=0");

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
		     * GET BULK ACTIONS
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
		     * PROCESS BULK ACTIONS
		     */
		    public function process_bulk_action()
		    {
		    	global $wpdb;

				
				switch ($this->current_action()):

					// TRASH CONTESTS
		    		case 'trash':
		    			if (!is_array($_GET['contest']))
								$wpdb->update($this->contestsTable, array('status' => 0), array('id' => $_GET['contest']));
							else foreach ($_GET['contest'] as $contest)
								$wpdb->update($this->contestsTable, array('status' => 0), array('id' => $contest));
						break;

					// RESTORE CONTESTS
					case 'restore':
			    		if (!is_array($_GET['contest'])) $wpdb->update($this->contestsTable, array('status' => 1), array('id' => $_GET['contest']));
			   				else foreach ($_GET['contest'] as $contest)
			   					$wpdb->update($this->contestsTable, array('status' => 1), array('id' => $contest));
						break;

					// DELETE CONTESTS
					case 'delete':
						if (!is_array($_GET['contest'])):
								// DELETE CONTEST
								$wpdb->delete($this->contestsTable, array('id' => $_GET['contest']));

								// DELETE CONTEST ENTRIES
								$wpdb->delete($this->contestEntriesTable, array('contest_id' => $_GET['contest']));

								// DELETE CONTEST VOTES
								$wpdb->delete($this->contestVotesTable, array('contest_id' => $_GET['contest']));
							else:
								foreach ($_GET['contest'] as $contest):
		    			
									// DELETE CONTEST
									$wpdb->delete($this->contestsTable, array('id' => $contest));

									// DELETE CONTEST ENTRIES
									$wpdb->delete($this->contestEntriesTable, array('contest_id' => $contest));

									// DELETE CONTEST VOTES
									$wpdb->delete($this->contestVotesTable, array('contest_id' => $contest));
		    					endforeach;
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
		        
		        // process bulk actions
		        $this->process_bulk_action();

		       	// current page
		        $current_page = $this->get_pagenum();

		        // get contests
		        $data = $this->getContests();
		        
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
		     * GET CONTESTS
		     */
		    private function getContests()
		    {
		    	global $wpdb;

		    	// get item status
		        $status = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'publish');

		        // get order params for the SQL query
				$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id'; //If no sort, default to title
				$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC'; //If no order, default to asc

		        // set where SQL
		        $where = 'WHERE ';
		        if ($status == 'publish') $where .= 'status=1 AND ';
		        	else $where .= 'status=0 AND ';

		        // search SQL
		        if (isset($_GET['s']))
		        	$where .= 'contest_name LIKE "%'.esc_attr($_GET['s']).'%" AND';

		        $where = rtrim($where, ' AND ');
		        
		        // return data from the db
		      	return $wpdb->get_results("SELECT * FROM $this->contestsTable $where ORDER BY $orderby $order", ARRAY_A);
		    }

		} // END CLASS
	endif;

?>