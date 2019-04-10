<?php
session_start();
//var_dump($_SESSION);
//var_dump($_REQUEST);
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * index.php - main viewer page
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once("fof-main.php");
include("init.php");

if (isset($_GET["logout"])) fof_logout();
if (isset($_GET["view-action"])) fof_view_action();

include("header.php");
include("sidebar.php");
?>
<div id="handle" onmousedown="startResize(event)"></div>
<?php 
if (isset($_GET["update"]))
{
	include("update.php");
}
elseif(isset($_GET["add"]))
{
	include("add.php");
}
elseif(isset($_GET["rss_url"]))
{
	$_REQUEST['url'] = $_GET["rss_url"];
	include("add-single.php");
	include("items.php");
}
elseif(isset($_GET["delete"]))
{
	include("delete.php");
}
elseif(isset($_GET["prefs"]))
{
	include("prefs.php");
}
else
{
	include("items.php");
}

include("footer.php");
?>
