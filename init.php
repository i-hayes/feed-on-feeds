<?php

if (!isset($_GET['what'])) $what = $_GET['what'] = "";
if (!isset($_GET["when"])) $when = $_GET["when"] = "";
if (!isset($_GET['howmany'])) $_GET['howmany'] = 0;
if (!isset($_GET['search'])) $_GET['search'] = "";
if (!isset($_GET['how'])) $how = $_GET['how'] = "";
if (!isset($_GET['which'])) $_GET['which'] = "";
if (!isset($_GET['noedit'])) $_GET['noedit'] = 0;
if (!isset($_GET['feed'])) $_GET["feed"] = 0;
$feed = $_GET['feed'] + 0;
$fof_user_id = 0;

if($_GET['how'] == 'paged' && !isset($_GET['which']))
{
	$which = 0;
}
else
{
	$which = $_GET['which'];
}
if (strpos($_GET["what"], " ")) $_GET["what"] = substr($_GET["what"], 0, strpos($_GET["what"], " "));
if ($_GET['what'] == "star")
{
    $what = "star";
    $how = "paged";
    $when = "";
}
elseif ($_GET['what'] == "today")
{
    $what = "all";
    $how = "paged";
    $when = "today";
}
elseif ($_GET['what'] == "all")
{
    $what = "all";
    $how = "paged";
    $when = $_GET["when"];
}
elseif ($_GET['what'] == "unread")
{
    $what = "unread";
    $how = "paged";
    $when = "";
}
else
{
    $what = "unread";
    $how = "paged";
    $when = "";
}

if (isset($_GET["tag"]))
{
	$fields = fof_db_fetch_fields(FOF_TAG_TABLE);
	$alltags = array_flip(fof_db_get_tag_id_map());

	if (strpos($_GET["tag"], " ")) $urltag = explode(" ", $_GET["tag"]); else $urltag[] = $_GET["tag"];

	foreach ($urltag as $v)
	{
		$atag[substr($v, 0, $fields["tag_name"]["length"])] = 1;
	}
    unset($urltag);

	foreach ($alltags AS $k => $v)
	{
		if (isset($atag[$k]))
		{
			$what .= " ".$k;
			if (isset($tag)) $urltag .= " ". $k; else  $urltag = $k;
		}
	}
    $when = "";
    unset($fields, $alltags, $atag);
}
if(isset($_GET['order']))
{
	$order = substr($_GET['order'], 0, 4);
	$_SESSION["order"] = $order;
}
elseif (isset($_SESSION["order"]))
{
	$order = $_SESSION["order"];
}
else
{
	$order = $fof_prefs_obj->get("order");
}

if(!isset($order))
{
	if (isset($_GET['order'])) $order = $_GET['order']; else $order = "title";
}

if(!isset($direction))
{
   if (isset($_GET['direction'])) $direction = $_GET['direction']; else $direction = "asc";
}

//$how = $_GET['how'];
//$when = $_GET['when'];
$howmany = $_GET['howmany'];
$direction = $fof_prefs_obj->get('feed_direction');
$feedorder = $fof_prefs_obj->get('feed_order');

if (!isset($unread)) $unread = 0;
if (!isset($starred)) $starred = 0;
if (!isset($total)) $total = 0;

?> 
