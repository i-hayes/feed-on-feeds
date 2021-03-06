<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add.php - displays form to add a feed
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

if (!isset($_POST['rss_url'])) $_POST['rss_url'] = "";
if (!isset($_POST['exist_feed'])) $_POST['exist_feed'] = "";
if (!isset($_GET['rss_url'])) $_GET['rss_url'] = "";
if (!isset($_POST['opml_url'])) $_POST['opml_url'] = "";
if (!isset($_POST['opml_file'])) $_POST['opml_file'] = "";
if (!isset($_POST['unread'])) $_POST['unread'] = "";

$url = $_POST['rss_url'];
if(!strlen($url)) $url = $_GET['rss_url'];
$opml = htmlentities($_POST['opml_url']);
$file = $_POST['opml_file'];
$unread = $_POST['unread'];

$feeds = array();

if(strlen($url)) $feeds[] = $url;
if(strlen($_POST['exist_feed'])) $feeds[] = $_POST['exist_feed'];

if(strlen($opml))
{
	$sfile = new SimplePie_File($opml);
	
	if(!$sfile->success)
	{
		echo "Cannot open $opml<br>";
		return false;
	}

	$content = $sfile->body;

	$feeds = fof_opml_to_array($content);
}

if(isset($_FILES['opml_file']) and $_FILES['opml_file']['tmp_name'])
{
	if(!$content_array = file($_FILES['opml_file']['tmp_name']))
	{
		echo "Cannot open uploaded file<br>";
	}
    else
    {
        $content = implode("", $content_array);
        $feeds = fof_opml_to_array($content);
    }
}

$add_feed_url = "http";
if(isset($_SERVER["HTTPS"]) and $_SERVER["HTTPS"] == "on")
{
  $add_feed_url = "https";
}
$add_feed_url .= "://" . $_SERVER["HTTP_HOST"] . $_SERVER["SCRIPT_NAME"];
$result = fof_db_get_all_subscriptions();
$select = "";
while ($row = $result->fetch_assoc())
{
	$select .= "<option value=\"".$row["feed_url"]."\">".$row["feed_title"]."</option>\n";
}
?>
<div id="items">

<div style="background: #eee; border: 1px solid black; padding: 1.5em; margin: 1.5em;">If your browser is cool, you can <a href='javascript:window.navigator.registerContentHandler("application/vnd.mozilla.maybe.feed", "<?php echo $add_feed_url ?>?rss_url=%s", "Feed on Feeds")'>register Feed on Feeds as a Feed Reader</a>.  If it is not cool, you can still use the <a href="javascript:void(location.href='<?php echo $add_feed_url ?>?rss_url='+escape(location))">FoF subscribe</a> bookmarklet to subscribe to any page with a feed.  Just add it as a bookmark and then click on it when you are at a page you'd like to subscribe to!</div>

<form method="post" action="opml.php">

<input type="submit" value="Export subscriptions as OPML">

</form>
<br>

<form method="post" name="addform" action="<?php print (FOF_BASEDIR); ?>?add=1" enctype="multipart/form-data">

When adding feeds, mark <select name="unread"><option value=today <?php if($unread == "today") echo "selected" ?> >today's</option><option value=all <?php if($unread == "all") echo "selected" ?> >all</option><option value=no <?php if($unread == "no") echo "selected" ?> >no</option></select> items as unread<br/><br/>

RSS or weblog URL: <input type="text" name="rss_url" size="40" value="<?php echo $url ?>"><input type="Submit" value="Add a feed"><br/><br/>

OPML URL: <input type="hidden" name="MAX_FILE_SIZE" value="100000">

<input type="text" name="opml_url" size="40" value="<?php echo $opml ?>"><input type="Submit" value="Add feeds from OPML file on the Internet"><br/><br/>

<input type="hidden" name="MAX_FILE_SIZE" value="100000">
OPML filename: <input type="file" name="opml_file" size="40" value="<?php echo $file ?>"><input type="Submit" value="Upload an OPML file">
<br/><br/>
Subscribe do public feed already in system<br/><br/>
<select name="exist_feed"><?php print ($select); ?></select> <input type="Submit" value="Add a feed">
</form>

<?php
if(count($feeds))
{
	print("<script>\nwindow.onload = ajaxadd;\n");
	print ("  feedslist = [");

	foreach($feeds as $feed)
	{
		$feedjson[] = "{'url': '" . addslashes($feed) . "'}";
	}

	print(join($feedjson, ", "));
	print("];\n");
	print ("</script>");
}
print("<br>");

?>
