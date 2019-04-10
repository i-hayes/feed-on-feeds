<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * update-single.php - updates a single feed
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

$feed = $_GET['feed'];
$feed = $feed + 0;

list ($count, $error) = fof_update_feed($feed);
	
if($count)
{
    print "<b><font color=red>$count new items</font></b>";
}

if($error)
{
    print " $error <br>";
}
else
{
    print " Done.<br>";
}

?>
