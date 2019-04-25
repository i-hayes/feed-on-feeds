<?php
if (!isset($_SESSION))
{
//	session_id($_GET["session"])
	session_start();
}
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * sidebar.php - sidebar for all pages
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */
include_once("fof-main.php");
include_once("init.php");

?>
  <img id="throbber" src="image/throbber.gif" align="left" style="position: fixed; left: 0; top: 0; display: none;">

  <center id="welcome">Welcome <b><?php echo fof_username() ?></b>! <a href="./?prefs=1">prefs</a> | <a href="./?logout=1">log out</a> | <a href="http://feedonfeeds.com/">about</a></center>
  <br>
  <center><a href="?add=1"><b>Add Feeds</b></a> / <a href="?update=1"><b>Update Feeds</b></a></center>

  <ul id="nav">

<?php

$n = $starred = 0;

$search = $_GET['search'];

echo "<script>what='$what'; when='$when'; session='".session_id()."'</script>";

$feeds = fof_get_feeds(fof_current_user(), $feedorder, $direction);

foreach($feeds as $row)
{
	$n++;
	if (isset($row['feed_unread'])) $unread += $row['feed_unread'];
	$starred += (isset($row['feed_starred']) ? $row['feed_starred']: 0);
	if (isset($row['feed_items'])) $total += $row['feed_items'];
}

if($unread)
{
	echo "<script>document.title = 'Feed on Feeds ($unread)';</script>";
}
else
{
	echo "<script>document.title = 'Feed on Feeds';</script>";
}

echo "<script>starred = $starred;</script>";

?>
        
    <li <?php if($what == "unread") echo "style='background: #ddd'" ?> ><a href=".?what=unread"><font color=red><b>Unread <?php if($unread) echo "($unread)" ?></b></font></a></li>
    <li <?php if($what == "star") echo "style='background: #ddd'" ?> ><a href=".?what=star"><img src="image/star-on.gif" border="0" height="10" width="10"> Starred <span id="starredcount"><?php if($starred) echo "($starred)" ?></span></a></li>
    <li <?php if($what == "all" && isset($when)) echo "style='background: #ddd'" ?> ><a href=".?what=today">&lt; Today</a></li>
    <li <?php if($what == "all" && !isset($when)) echo "style='background: #ddd'" ?> ><a href=".?what=all">All Items <?php if($total) echo "($total)" ?></a></li>
    <li <?php if(isset($search)) echo "style='background: #ddd'" ?> ><a href="javascript:Element.toggle('search'); Field.focus('searchfield');void(0);">Search</a>
      <form action="." id="search" <?php if(!isset($search)) echo 'style="display: none"' ?>>
        <input id="searchfield" name="search" value="<?php echo $search?>" />
<?php
	if($what == "unread")
		echo "        <input type='hidden' name='what' value='all' />\n";
	else
		echo "        <input type='hidden' name='what' value='$what' />\n";
?>
<?php if(isset($_GET['when'])) echo "        <input type='hidden' name='what' value='${_GET['when']}' />\n" ?>
      </form>
    </li>
  </ul>

<?php

$tags = fof_get_tags(fof_current_user());

$n = 0;

foreach($tags as $tag)
{
    $tag_id = $tag['tag_id'];
    if($tag_id == 1 || $tag_id == 2) continue;
    $n++;
}

if($n)
{
?>

  <div id="tags">
    <table cellspacing="0" cellpadding="1" border="0" id="taglist" class="taglist">
      <tr class="heading" onClick="toggle_show_sideBar_table('table-tags');">
        <th class="number"><span class="unread">#</span></th>
		<th class="all"></th>
		<th class="tag-name">Tag Name</th>
		<th class="delete-tag">Untag</th>
      </tr>

<?php
$t = 0;
foreach($tags as $tag)
{   
	$tag_name = $tag['tag_name'];
	$tag_id = $tag['tag_id'];
	$count = $tag['count'];
	$unread = $tag['unread'];
 
	if($tag_id == 1 || $tag_id == 2) continue;
	if(++$t % 2) $tr_class = " odd-row"; else $tr_class = "";

	print "      <tr class=\"table-tags".$tr_class."\">\n";
	print "        <td class=\"number\">\n";
	if($unread) print "          <a class='unread' href='.?what=unread&tag=$tag_name'>$unread</a>\n";
	print "        </td>\n";
	print "        <td class=\"all\"><a href='.?what=all&tag=$tag_name'>$count</a></td>\n";
	print "        <td class=\"tag-name\"><b><a href='.?what=all&tag=$tag_name'>$tag_name</a></b></td>\n";
//	print "      <td><a href=\"#\" title=\"untag all items\" onclick=\"if(confirm('Untag all [$tag_name] items --are you SURE?')) { delete_tag('$tag_name'); return false; }  else { return false; }\">[x]</a></td>\n";
	print "        <td class=\"delete-tag\"><a href=\"#\" title=\"untag all items\" onclick=\"delete_tag('$tag_name'); return false;\">[x]</a></td>\n";
	print "      </tr>\n";
}

?>

    </table>
  </div>
  <br>

<?php } ?>

  <div id="feeds">
    <div id="feedlist">
      <table cellspacing="0" cellpadding="1" border="0">
        <tr class="heading">

<?php

$title["feed_age"] = "sort by last update time";
$title["max_date"] = "sort by last new item";
$title["feed_unread"] = "sort by number of unread items";
$title["feed_url"] = "sort by feed URL";
$title["feed_title"] = "sort by feed title";

$name["feed_age"] = "age";
$name["max_date"] = "latest";
$name["feed_unread"] = "#";
$name["feed_url"] = "feed";
$name["feed_title"] = "title";
$cols = array("feed_age", "feed_unread", "feed_url", "feed_title"); //, "max_date"

foreach ($cols as $col)
{
    if($col == $feedorder)
    {
		if ($direction == "asc") $sort = "desc"; else $sort = "asc";
	}
    else
    {
		$sort = "asc";
    }
    
    echo "          <th><a href=\"#\" title=\"".$title[$col]."\" onclick=\"return change_feed_order('".$col."', '".$sort."')\">";
    
    if($col == "feed_unread")
    {
        echo "<span class=\"unread\">#</span>";
    }
    else
    {
        echo $name[$col];
    }
    
    if($col == $feedorder)
    {
        echo ($direction == "asc") ? "&darr;" : "&uarr;";
    }
    
    echo "</a></th>\n";
}

?>
          <td>
	      </td>
        </tr>

<?php
$t = 0;

foreach($feeds as $row)
{
   $id = $row['feed_id'];
   $url = $row['feed_url'];
   $title = $row['feed_title'];
   $link = $row['feed_link'];
   $description = $row['feed_description'];
   $age = $row['feed_age'];
   if (isset($row['feed_unread'])) $unread = $row['feed_unread']; else $unread = "";
   $starred = (isset($row['feed_starred']) ? $row['feed_starred'] : 0);
   if (isset($row['feed_items'])) $items = $row['feed_items']; else $items = "";
   $agestr = $row['agestr'];
   $agestrabbr = $row['agestrabbr'];
   if (isset($row['lateststr'])) $lateststr = $row['lateststr']; else  $lateststr = "";
   if (isset($row['lateststrabbr'])) $lateststrabbr = $row['lateststrabbr']; else $lateststrabbr = "";
   if(++$t % 2) $class = " odd-row"; else $class = "";

   $u = ".?feed=$id&amp;what=unread";
   $u2 = ".?feed=$id&amp;what=all&amp;how=paged";


	print "        <tr class=\"table-feeds".$class."\">\n";
//	print "          <td><span title=\"$agestr\" id=\"${id}-agestr\">$agestrabbr</span></td>\n";
	print "          <td><span title=\"$lateststr\" id=\"${id}-lateststr\">$lateststrabbr</span></td>\n";
	print "          <td class=\"nowrap\" id=\"${id}-items\">\n";

   if($unread)
   {
      print "            <a class=\"unread\" title=\"new items\" href=\"$u\">$unread</a>/\n";
   }

   print "            <a href=\"$u2\" title=\"all items\">$items</a>\n";

	print "          </td>\n";

	print "          <td align='center'>\n";
	if($row['feed_image'] && $fof_prefs_obj->get('favicons'))
	{
		$image = $row['feed_image'];
	}
	else
	{
		$image = "image/feed-icon.png";
	}
	print "        <a href=\"$url\" title=\"feed\"><img src='$image' width='16' height='16' border='0' /></a>\n";
	print "          </td>\n";
	print "          <td>\n";
	if ($fof_prefs_obj->get('new_page')) $target = " target=\"_blank\""; else $target = "";
	print "            <a href=\"$link\" title=\"home page\"$target><b>$title</b></a>\n";
	print "          </td>\n";
	print "          <td><nobr>\n";
	print "            <a href=\"?update=1&feed=$id\" title=\"update\">u</a>\n";
	$stitle = htmlspecialchars(addslashes($title));
//	print "            <a href=\"#\" title=\"mark all read\" onclick=\"if(confirm('Mark all [$stitle] items as read --are you SURE?')) { mark_feed_read($id); return false; }  else { return false; }\">m</a>\n";
	print "            <a href=\"./#\" title=\"mark all read\" onclick=\"mark_feed_read($id); return false;\">m</a>\n";
	print "            <a href=\"?delete=1&feed=$id\" title=\"delete\" onclick=\"return confirm('Unsubscribe [$stitle] --are you SURE?')\">d</a>\n";
	print "            </nobr>\n";
	print "          </td>\n";
	print "        </tr>\n";
}

?>
      </table>
    </div>
  </div>



