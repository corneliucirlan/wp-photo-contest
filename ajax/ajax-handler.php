<?php

	define('DOING_AJAX', true);

	if (!isset($_POST['action']))
		die('-123');

	//relative to where your plugin is located
	require_once('../../../../wp-load.php'); 

	//Typical headers
	//header('Content-Type: text/html');
	send_nosniff_header();

	//Disable caching
	header('Cache-Control: no-cache');
	header('Pragma: no-cache');

	$action = esc_attr($_POST['action']);

	//A bit of security
	$allowed_actions = array(
		'wppc-submit-photo', // submit entry form
		'wppc-vote-photo', // vote photo
		'wppc-filter-photos', // filters
		);

	if (in_array($action, $allowed_actions)){
		if(is_user_logged_in())
			do_action('wppc_ajax_'.$action);
		else   
			do_action('wppc_ajax_nopriv_'.$action);
	}
	else{
		die('-12');
	}

?>