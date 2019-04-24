<?php
if (!isset($_SESSION))
{
//	session_id($_GET["session"])
	session_start();
}
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * shared.php - display shared items for a user
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
include_once("fof-render.php");

$result = fof_get_items(fof_current_user(), NULL, "unread", NULL, 0, 10);

header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Feed on Feeds</title>
    <meta name = "viewport" content = "width=100%">

    <link rel="stylesheet" href="fof.css" media="screen" />
    <style>
      .box
      {
          font-family: georgia;
          background: #eee;
          border: 1px solid black;
          width: 30em;
          margin: 10px auto 20px;
          padding: 1em;
          text-align: center;
      }

      #items
      {
          font-size: .5em;
      }

      img.uparrow,
      img.downarrow
      {
          width: 16px;
          height: 16px;
      }

    </style>

    <script src="prototype/prototype.js" type="text/javascript"></script>
    <script src="fof.js" type="text/javascript"></script>

<script>
function toggle_favorite(id)
{
    var image = $('fav' + id);    
    
    var url = "add-tag.php?tag=star";
    var params = "&item=" + id;
    image.src = 'image/star-pending.gif';
	
    if(image.star)
    {
        params += "&remove=true";
        var complete = function()
		{
			image.src='image/star-off.gif';
			image.star = false;
		};
    }
    else
    {
        var complete = function()
		{
			image.src='image/star-on.gif';
			image.star = true;
		};
    }
    
    var options = { method: 'get', parameters: params, onComplete: complete };  
    new Ajax.Request(url, options);
    
    return false;
}

function newWindowIfy()
{
	a=document.getElementsByTagName('a');
	
	for(var i=0,j=a.length;i<j;i++){a[i].setAttribute('target','_blank')};
}
</script>
  </head>

    <body onload="newWindowIfy()">
      <form id="itemform" name="items" action="view-action.php" method="post" onSubmit="return false;">
        <input type="hidden" name="action" value="read" />
        <input type="hidden" name="return" />

        <div id="items">

<?php

$first = true;

foreach($result as $item)
{
	$item_id = $item['item_id'];
	print '<div class="item shown" id="i' . $item_id . '">';

	$feed_link = $item['feed_link'];
	$feed_title = $item['feed_title'];
	$feed_image = (($item['feed_image'] && $fof_prefs_obj->get('favicons')) ? $item['feed_image'] : "image/feed-icon.png");

	$feed_description = $item['feed_description'];

	$item_link = $item['item_link'];
	$item_id = $item['item_id'];
	$item_title = $item['item_title'];
	$item_content = $item['item_content'];
//	$item_read = $item['item_read'];

	$item_published = gmdate("Y-n-d g:ia", $item['item_published'] + $fof_prefs_obj->get('$offset')*60*60);
	$item_cached = gmdate("Y-n-d g:ia", $item['item_cached'] + $fof_prefs_obj->get('$offset')*60*60);
	$item_updated = gmdate("Y-n-d g:ia", $item['item_updated'] + $fof_prefs_obj->get('$offset')*60*60);

	if(!$item_title) $item_title = "[no title]";
	$tags = $item['tags'];
	$star = in_array("star", $tags) ? true : false;
	$star_image = $star ? "image/star-on.gif" : "image/star-off.gif";

?>

          <div class="header">
            <h2>
              <div>
                <img class='uparrow' height="16" width="16" src="image/u_arrow.gif" onClick="javascript:hide_body('<?php echo $item_id ?>')" title="Hide item" />
                <img class='downarrow' height="16" width="16" src="image/d_arrow.gif" onClick='javascript:show_body("<?php echo $item_id ?>")'  title="Expand item" />
                <img height="16" width="16" src="<?php echo $star_image ?>" id="fav<?php echo $item_id ?>" onclick="return toggle_favorite('<?php echo $item_id ?>')" />
                <script>
                  document.getElementById('fav<?php echo $item_id ?>').star = <?php if($star) echo 'true'; else echo 'false'; ?>;
                </script>
              </div>
              <div>
                <a href="<?php echo $item_link ?>"><?php echo $item_title ?></a>
                <br />
                <!--a href="<?php echo $feed_link ?>" title='<?php echo $feed_description ?>'><img src="<?php echo $feed_image ?>" height="16" width="16" border="0" /></a-->
                <a href="<?php echo $feed_link ?>" title='<?php echo $feed_description ?>'><?php echo $feed_title ?></a>
              </div>
            </h2>
          </div>
          <span class="meta"><?php echo $item_published ?> GMT</span>
          <div class="body"><?php echo $item_content ?></div>
          <div class="clearer"></div>
        </div>
        <input type="hidden" name="c<?php echo $item_id ?>" id="c<?php echo $item_id ?>" value="checked" />

<?php
}

if(count($result) == 0)
{
	echo "        <p><i>No new items.</i></p>";
}
else
{
	echo "        <center><a href='#' onclick='mark_read(); return false;'><b>Mark All Read</b></a></center>";
}

?>

      </div>
    </form>
  </body>
</html>

<script>
<?php
if ($collapse == "hidden") print ("show_hide_all('hidden');\n");
if (isset($_COOKIE["fof_sidebar_table-tags"]) and $_COOKIE["fof_sidebar_table-tags"] == "hide") print ("sideBar_show_hide_table('table-tags');\n");
?>
</script>
