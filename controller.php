<?php
//defined('_VALID_ACCESS') or die("Invalid Access");

$component = isset($_GET['c']) ? $_GET['c'] : '';
$task = isset($_GET['task']) ? $_GET['task'] : '';

// functions.php = data layer; view.php = shared helpers + list page.
// The heavier detail / calendar renderers live in their own files and are
// pulled in only for the request that needs them (see the dispatch below).
$component_dir = PATH . FOLDER_ADMIN . "components/" . $component . "/";
require_once($component_dir . "functions.php");
require_once($component_dir . "view.php");

$sqlmenuid = "SELECT * FROM tbl_components WHERE component_option = '" . $database->filter($component) . "'";
$getmenuid = $database->get_results($sqlmenuid);
$menuid = is_array($getmenuid) && isset($getmenuid[0]) ? $getmenuid[0] : array('component_id' => 0);

$sqlpermission = "
	SELECT *
	FROM tbl_rights_groups
	WHERE find_in_set(rights_group_id, '" . $database->filter($_SESSION['admin_groupid']) . "')
	AND rights_menu_id = '" . $database->filter($menuid['component_id']) . "'
";

$permissions = $database->get_results($sqlpermission);

if (is_array($permissions) && count($permissions) > 0) {
	if ($task === 'dashboard') {
		require_once($component_dir . "view_dashboard.php");
		showDashboard();
	} elseif ($task === 'view' && isset($_GET['id']) && $_GET['id'] !== '') {
		require_once($component_dir . "view_detail.php");
		showDeviceDetailsPage($_GET['id']);
	} elseif ($task === 'calendar' && isset($_GET['view']) && $_GET['view'] === 'chart') {
		require_once($component_dir . "view_chart.php");
		showCalendarChart();
	} elseif ($task === 'calendar') {
		require_once($component_dir . "view_calendar.php");
		showCalendarPage();
	} else {
		showList();
	}
} else {
	echo 'Access Denied';
}
?>
