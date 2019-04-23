<?php
if (!isset($_SESSION))
{
//	session_id($_GET["session"])
	session_start();
}
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * ompl.php - exports subscription list as OPML
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

header("Content-Type: application/octet-stream; charset=utf-8");
header('Content-Disposition: attachment; filename="Feed-on-Feeds feeds.opml"');
$data .= "<opml version=\"1.1\">
  <head>
    <title>Feed on Feeds Subscriptions</title>
    <dateCreated>".date("D, d M Y H:i:s e")."</dateCreated>
  </head>
  <body>\n";

$result = fof_db_get_subscriptions(fof_current_user());

while($row = fof_db_get_row($result))
{
	$data .= "    <outline title=\"".htmlspecialchars($row['feed_title'])."\">\n";
	$data .= "      <outline type=\"rss\"";
	$data .= " text=\"".htmlspecialchars($row['feed_title'])."\"";
	$data .= " title=\"".htmlspecialchars($row['feed_title'])."\"";
	$data .= " htmlUrl=\"".htmlspecialchars($row['feed_link'])."\"";
	$data .= " xmlUrl=\"".htmlspecialchars($row['feed_url'])."\"";
	$data .= "/>\n";
	$data .= "    </outline>\n";
}
$data .= "  </body>
</opml>\n";
print ($data);

function fof_forceDownload($filename, $type = "application/octet-stream")
{
    header('Content-Type: '.$type.'; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
}

?>

