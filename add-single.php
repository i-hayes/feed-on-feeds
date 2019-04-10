<?php
if (!isset($_SESSION)) session_start();
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add-single.php - adds a single feed
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$fof_installer = false;

include_once("fof-main.php");

$url = $_REQUEST['url'];
$unread = (isset($_REQUEST['unread']) ? $_REQUEST['unread'] : 1);
print(fof_subscribe(fof_current_user(), $url, $unread));
?>
