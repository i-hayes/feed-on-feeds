<?php
if (!isset($_SESSION)) session_start();
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add-tag.php - adds (or removes) a tag to an item
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

if (isset($_GET['tag'])) $tags = explode(" ", $_GET['tag']);
$item = $_GET['item'];
$remove = $_GET['remove'];

if (isset($tags))
{
	foreach($tags as $tag)
	{
		if(isset($remove) and $remove == 'true')
		{
			fof_untag_item(fof_current_user(), $item, $tag);
		}
		else
		{
			fof_tag_item(fof_current_user(), $item, $tag);
		}
	}
}
?>
