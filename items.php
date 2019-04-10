<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * items.php - displays right hand side "frame"
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");
include_once("fof-render.php");

$title = fof_view_title($_GET['feed'], $what, $when, $which, $_GET['howmany'], $_GET['search']);
$noedit = $_GET['noedit'];
?>
<div id="items">

<ul id="item-display-controls-spacer" class="inline-list">
	<li class="orderby">new to old</li>
	<li class="orderby">old to new</li>
	<li><a href="javascript:flag_all();mark_read()">Mark all read</a></li>
	<li><a href="javascript:flag_all()">Flag all</a></li>
	<li><a href="javascript:unflag_all()">Unflag all</a></li>
	<li><a href="javascript:toggle_all()">Toggle all</a></li>
	<li><a href="javascript:mark_read()">Mark flagged read</a></li>
	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>
	<li><a href="javascript:show_hide_all('shown')">Show all</a></li>
	<li><a href="javascript:show_hide_all('hidden')">Hide all</a></li>
</ul>

<br style="clear: both"><br>

<p><?php 

echo $title;
$new_to_old = 'New -> Old';
$old_to_new = 'Old -> New';

if ($order == "desc")
{
	print (" - Newest displayed first");
}
elseif ($order == "asc")
{
	print (" - Oldest displayed first");
}

?></p>



<ul id="item-display-controls" class="inline-list">
	<li class="orderby"><?php

	$url = "";
	if ($feed) $url .= "feed=".$feed;
	if ($what) $url .= (strlen($url) ? "&amp;" : "") . "what=" . (strpos($what, " ") ? substr($what, 0, strpos($what, " ")) : $what);
	if (isset($urltag) and strlen($urltag)) $url .= (strlen($url) ? "&amp;":""). "tag=".$urltag;
	if ($when) $url .= (strlen($url) ? "&amp;":""). "when=".$when;
	if ($how) $url .= (strlen($url) ? "&amp;":""). "how=".$how;
	if ($howmany) $url .= (strlen($url ? "&amp;":"")). "howmany=".$howmany;
	echo ($order == "desc") ? "<a href=\".?$url&amp;order=asc\" title=\"Display oldest first\">$old_to_new</a>" : "<a href=\".?$url&amp;order=desc\" title=\"Display newest first\">$new_to_old</a>" ;
	?></li>
	<li><a href="javascript:flag_all();mark_read()">Mark all read</a></li>
	<li><a href="javascript:flag_all()">Flag all</a></li>
	<li><a href="javascript:unflag_all()">Unflag all</a></li>
	<li><a href="javascript:toggle_all()">Toggle all</a></li>
	<li><a href="javascript:mark_read()">Mark flagged read</a></li>
	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>
	<li><a href="javascript:show_hide_all('shown')">Show all</a></li>
	<li><a href="javascript:show_hide_all('hidden')">Hide all</a></li>
</ul>



<!-- close this form to fix first item! -->

		<form id="itemform" name="items" action="?view-action=1" method="post" onSubmit="return false;">
		<input type="hidden" name="action" />
		<input type="hidden" name="return" />

<?php
$links = fof_get_nav_links($_GET['feed'], $what, $when, $which, $how, $_GET['howmany']);


if($links)
{
?>
		<div class="nav-links"><?php echo $links ?></div>

<?php

}

$result = fof_get_items(fof_current_user(), $_GET['feed'], $what, $when, $which, $_GET['howmany'], $order, $_GET['search']);

$first = true;
$date_item_published_saved = 0;
$prefs = fof_prefs();
$offset = $prefs['tzoffset'];
$colapse = (isset($_COOKIE["colapse"]) ? $_COOKIE["colapse"] : ((isset($prefs['colapse']) and $prefs['colapse']) ? "hidden" : "shown"));

foreach($result as $row)
{
	$item_id = $row['item_id'];
	if($first) print "<script>firstItem = 'i$item_id'; </script>";
	$date_item_published = date($prefs['dlformat'], ($row['item_published'] + $offset*60*60));

	$first = false;
	if ($date_item_published_saved !== $date_item_published) print ("<div class=\"date-item-published\">".$date_item_published."</div>");
	print '<div class="item shown" id="i' . $item_id . '"  onclick="return itemClicked(event)">';
	fof_render_item($row);
	print '</div>';
	$date_item_published_saved = $date_item_published;
}

if(count($result) == 0)
{
	echo "<p><i>No items found.</i></p>";
}

?>
		</form>
        
        <div id="end-of-items"></div>

<script>itemElements = $$('.item');</script>
<?php
if ($colapse == "hidden")
{
	print ("<script>\n");
	print ("javascript:show_hide_all('hidden');\n");
	print ("</script>\n");
}

?>