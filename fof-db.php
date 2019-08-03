<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-db.php - (nearly) all of the DB specific code
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

////////////////////////////////////////////////////////////////////////////////
// Utilities
////////////////////////////////////////////////////////////////////////////////

function fof_db_connect($type = "read")
{
	$dbc = new mysqli(FOF_DB_HOST, FOF_DB_USER, FOF_DB_PASS, FOF_DB_DBNAME) or die("<br><br>Cannot connect to database.  Please update configuration in <b>fof-config.php</b>.  Mysql says: <i>" . mysqli_error() . "</i>");
	return $dbc;
}

function fof_db_optimize()
{
	//$tables = $fof_connection->query("SHOW TABLES");

	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE;

	fof_db_query("optimize table $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE");
}

function fof_safe_query(/* $query, [$args...]*/)
{
	$args  = func_get_args();
	$query = array_shift($args);
	if(isset($args[0]) and is_array($args[0])) $args = $args[0];
	$args  = array_map('fof_real_escape_string', $args);
	$query = vsprintf($query, $args);
	return fof_db_query($query);
}

function fof_real_escape_string($a)
{
	return $_SESSION["fof_connection"]->real_escape_string($a);
}

function fof_db_query($sql, $live=0)
{
    list($usec, $sec) = explode(" ", microtime()); 
    $t1 = (float)$sec + (float)$usec;

    $result = $_SESSION["fof_connection"]->query($sql);

    if(is_resource($result)) $num = $result->num_rows; else $num = 0;
    if(is_resource($result)) $affected = $result->affected_rows; else $affected = 0;

    list($usec, $sec) = explode(" ", microtime()); 
    $t2 = (float)$sec + (float)$usec;
    $elapsed = $t2 - $t1;
    $logmessage = sprintf("%.3f: [%s] (%d / %d)", $elapsed, $sql, $num, $affected);
    fof_log($logmessage, "query");
	if ($_SESSION["fof_connection"]->insert_id)
	{
		$result = $_SESSION["fof_connection"]->insert_id;
//		var_dump($result);
	}
    if($live)
    {
        return $result;
    }
    else
    {
        if(is_resource($result) and $result->errno)
        {
            //echo "<pre>";
            //print_r(debug_backtrace());
            //echo "</pre>";
            die("Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? MySQL says: <b>". $result->errno . "</b></br>");
        }
        return $result;
    }
}

function fof_db_get_row($result)
{
	return $result->fetch_assoc();
}


////////////////////////////////////////////////////////////////////////////////
// Feed level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_feed_mark_cached($feed_id)
{

	$result = fof_safe_query("update `".FOF_FEED_TABLE."` set `feed_cache_date` = %d where `feed_id` = %d", time(), $feed_id);

	return $result;
}

function fof_db_feed_mark_attempted_cache($feed_id)
{

	$result = fof_safe_query("update `".FOF_FEED_TABLE."` set `feed_cache_attempt_date` = %d where `feed_id` = %d", time(), $feed_id);

	return $result;
}

function fof_db_feed_update_metadata($feed_id, $url, $title, $link, $description, $image, $image_cache_date)
{

    $sql = "update `".FOF_FEED_TABLE."` set `feed_url` = '%s', `feed_title` = '%s', `feed_link` = '%s', `feed_description` = '%s'";
    $args = array($url, $title, $link, $description);

	if($image)
	{
		$sql .= ", `feed_image` = '%s' ";
		$args[] = $image;
	}
	else
	{
		$sql .= ", `feed_image` = NULL ";
	}

	$sql .= ", `feed_image_cache_date` = %d ";
	$args[] = $image_cache_date;

	$sql .= "where `feed_id` = %d";
	$args[] = $feed_id;

	$result = fof_safe_query($sql, $args);

	return $result;
}

function fof_db_get_latest_item_age($user_id)
{
	$sql = "SELECT max( item_cached ) AS \"max_date\", ".FOF_ITEM_TABLE.".feed_id as \"id\" FROM ".FOF_ITEM_TABLE." GROUP BY ".FOF_ITEM_TABLE.".feed_id";
	$result = fof_db_query($sql);
	return $result;
}

function fof_db_get_subscriptions($user_id)
{
	$sql = "select * from `".FOF_FEED_TABLE."`, `".FOF_SUBSCRIPTION_TABLE."` where `".FOF_SUBSCRIPTION_TABLE."`.`user_id` = %d and `".FOF_FEED_TABLE."`.`feed_id` = `".FOF_SUBSCRIPTION_TABLE."`.`feed_id` order by `feed_title`";
	return(fof_safe_query($sql, $user_id));
}

function fof_db_get_all_subscriptions()
{
	$sql = "select * from `".FOF_FEED_TABLE."` where `public_feed` = '1' order by `feed_title`";
	return(fof_db_query($sql));
}

function fof_db_get_feeds()
{
	return(fof_db_query("select * from `".FOF_FEED_TABLE."` order by `feed_title`"));
}

function fof_db_get_item_count($user_id)
{
	$sql = "select count(*) as count, ".FOF_ITEM_TABLE.".feed_id as id from ".FOF_ITEM_TABLE.", ".FOF_SUBSCRIPTION_TABLE." where ".FOF_SUBSCRIPTION_TABLE.".user_id = %d and ".FOF_ITEM_TABLE.".feed_id = ".FOF_SUBSCRIPTION_TABLE.".feed_id group by id";
//	print ($sql);
	return(fof_safe_query($sql, $user_id));
}

function fof_db_get_unread_item_count($user_id)
{
	return(fof_safe_query("select count(*) as count, ".FOF_ITEM_TABLE.".feed_id as id from ".FOF_ITEM_TABLE.", ".FOF_SUBSCRIPTION_TABLE.", ".FOF_ITEM_TAG_TABLE.", ".FOF_FEED_TABLE." where ".FOF_ITEM_TABLE.".item_id = ".FOF_ITEM_TAG_TABLE.".item_id and ".FOF_SUBSCRIPTION_TABLE.".user_id = $user_id and ".FOF_ITEM_TAG_TABLE.".tag_id = 1 and ".FOF_ITEM_TAG_TABLE.".user_id = %d and ".FOF_FEED_TABLE.".feed_id = ".FOF_SUBSCRIPTION_TABLE.".feed_id and ".FOF_ITEM_TABLE.".feed_id = ".FOF_FEED_TABLE.".feed_id group by id", $user_id));
}

function fof_db_get_starred_item_count($user_id)
{
	$sql = "select count(*) as count, ". 
				FOF_ITEM_TABLE.".feed_id as id 
			from ".FOF_ITEM_TABLE.", 
				".FOF_SUBSCRIPTION_TABLE.", 
				".FOF_ITEM_TAG_TABLE.", 
				".FOF_FEED_TABLE." 
			where ".FOF_ITEM_TABLE.".item_id = ".FOF_ITEM_TAG_TABLE.".item_id 
				and ".FOF_SUBSCRIPTION_TABLE.".user_id = $user_id 
				and ".FOF_ITEM_TAG_TABLE.".tag_id = 2 
				and ".FOF_ITEM_TAG_TABLE.".user_id = $user_id 
				and ".FOF_FEED_TABLE.".feed_id = ".FOF_SUBSCRIPTION_TABLE.".feed_id 
				and ".FOF_ITEM_TABLE.".feed_id = ".FOF_FEED_TABLE.".feed_id 
			group by id";

	return(fof_safe_query($sql , $user_id));
}

function fof_db_get_subscribed_users($feed_id)
{
	return(fof_safe_query("select user_id from `".FOF_SUBSCRIPTION_TABLE."` where `".FOF_SUBSCRIPTION_TABLE."`.feed_id = %d", $feed_id));
}

function fof_db_is_subscribed($user_id, $feed_url)
{
	$result = fof_safe_query("select `".FOF_SUBSCRIPTION_TABLE."`.feed_id from `".FOF_FEED_TABLE."`, `".FOF_SUBSCRIPTION_TABLE."` where feed_url='%s' and `".FOF_SUBSCRIPTION_TABLE."`.feed_id = `".FOF_FEED_TABLE."`.feed_id and `".FOF_SUBSCRIPTION_TABLE."`.user_id = %d", $feed_url, $user_id);

	if($result->num_rows == 0)
	{
		return false;
	}

	return true;
}

function fof_db_get_feed_by_url($feed_url)
{
	$result = fof_safe_query("select * from `".FOF_FEED_TABLE."` where feed_url='%s'", $feed_url);

	if($result->num_rows == 0)
	{
		return NULL;
	}

	return $result->fetch_assoc();
}

function fof_db_get_feed_by_id($feed_id)
{
	$result = fof_safe_query("select * from `".FOF_FEED_TABLE."` where `feed_id` = %d", $feed_id);

	return $result->fetch_assoc();
}

function fof_db_add_feed($url, $title, $link, $description)
{
	$result = fof_safe_query("insert into `".FOF_FEED_TABLE."` (feed_url,feed_title,feed_link,feed_description) values ('%s', '%s', '%s', '%s')", $url, $title, $link, $description);

	return $result;
}

function fof_db_add_subscription($user_id, $feed_id)
{
	$result = fof_safe_query("insert into ".FOF_SUBSCRIPTION_TABLE." (feed_id, user_id) values (%d, %d)", $feed_id, $user_id);

	return $result;
}

function fof_db_delete_subscription($user_id, $feed_id)
{
	$result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);

	foreach($result as $r)
	{
		$items[] = $r['item_id'];
	}

	fof_safe_query("delete from `".FOF_SUBSCRIPTION_TABLE."` where `feed_id` = %d and `user_id` = %d", $feed_id, $user_id);

	if (isset($items))
	{
		$itemclause = join(", ", $items);
		fof_safe_query("delete from `".FOF_ITEM_TAG_TABLE."` where `user_id` = %d and `item_id` in ($itemclause)", $user_id);
	}
}

function fof_db_delete_feed($feed_id)
{
	fof_safe_query("delete from `".FOF_FEED_TABLE."` where feed_id = %d", $feed_id);
	fof_safe_query("delete from `".FOF_ITEM_TABLE."` where feed_id = %d", $feed_id);
}


////////////////////////////////////////////////////////////////////////////////
// Item level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_find_item($feed_id, $item_guid)
{
	$result = fof_safe_query("select `item_id` from `".FOF_ITEM_TABLE."` where `feed_id` = %d and `item_guid` = '%s'", $feed_id, $item_guid);
	$row = $result->fetch_assoc();

	if($result->num_rows == 0)
	{
		return NULL;
	}
	else
	{
		return($row['item_id']);
	}
}

function fof_db_add_item($feed_id, $guid, $link, $title, $content, $cached, $published, $updated)
{
	$result = fof_safe_query("insert into ".FOF_ITEM_TABLE." (feed_id, item_link, item_guid, item_title, item_content, item_cached, item_published, item_updated) values (%d, '%s', '%s' ,'%s', '%s', %d, %d, %d)", $feed_id, $link, $guid, $title, $content, $cached, $published, $updated);

	return $result;
}

function fof_db_get_items($user_id=1, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL)
{
    $prefs = fof_prefs();
    $offset = (isset($prefs['tzoffset']) ? $prefs['tzoffset'] : 0);
    $limit_clause = "";
	$select = $from = $where = $group = $order_by = "";    
    if(!is_null($when) && $when != "")
    {
        if($when == "today")
        {
            $whendate = fof_todays_date();
        }
        else
        {
            $whendate = $when;
        }
        
        $td = strtotime($whendate);
        $begin = gmmktime(0, 0, 0, date("n", $td), date("j", $td), date("Y", $td)) - ($offset * 60 * 60);
        $end = $begin + (24 * 60 * 60);
    }
    
    if(is_numeric($start))
    {
        if(!is_numeric($limit))
        {
            $limit = $prefs["howmany"];
        }
        
        $limit_clause = " limit $start, $limit ";
    }
    
    $args = array();
    $select = "SELECT i.* , f.* ";
    $from = "FROM `".FOF_FEED_TABLE."` f, `".FOF_ITEM_TABLE."` i, `".FOF_SUBSCRIPTION_TABLE."` s ";
    $where = sprintf("WHERE s.user_id = %d AND s.feed_id = f.feed_id AND f.feed_id = i.feed_id ", $user_id);
 
    if(!is_null($feed) && $feed)
    {
        $where .= sprintf("AND f.feed_id = %d ", $feed);
    }
    
    if(!is_null($when) && $when != "")
    {
        $where .= sprintf("AND i.item_published > %d and i.item_published < %d ", $begin, $end);
    }
    
    if($what != "all")
    {
        if (strpos($what, " ")) $tags = explode(" ", $what); else $tags[] = $what;
        if ($tags[0] == "all") array_shift($tags);
        foreach($tags As $k => $v) $tags[$k] = "'".trim($v)."'";
        $in = implode($tags, ", ");
        $from .= ", `".FOF_TAG_TABLE."` t, `".FOF_ITEM_TAG_TABLE."` it ";
        $where .= sprintf("AND it.user_id = %d ", $user_id);
		$where .= "AND it.tag_id = t.tag_id AND ( t.tag_name IN ( ".$in." ) ) AND i.item_id = it.item_id ";
        $group = sprintf("GROUP BY i.item_id HAVING COUNT( i.item_id ) = %d ", count($tags));
        $args = array_merge($args, $tags);
    }
    
    if(!is_null($search) && $search != "")
    {
        $where .= "AND (i.item_title like '%%%s%%'  or i.item_content like '%%%s%%' )";
//        $args[] = $search;
        $args[] = $search;
    }

    $order_by = "ORDER BY i.item_published $order $limit_clause ";
    
    $query = $select . $from . $where . $group . $order_by;
    $result = fof_safe_query($query, $args);

    if($result->num_rows == 0)
    {
        return array();
    }
    	
    while($row = $result->fetch_assoc())
    {
        $array[] = $row;
    }
    
	$array = fof_multi_sort($array, 'item_published', $order != "asc");
    
    $i = 0;
    foreach($array as $item)
    {
        $ids[] = $item['item_id'];
        $lookup[$item['item_id']] = $i;
        $array[$i]['tags'] = array();
        
        $i++;
    }
    
    $items = join($ids, ", ");
    
    $result = fof_safe_query("select `".FOF_TAG_TABLE."`.tag_name, `".FOF_ITEM_TAG_TABLE."`.item_id from `".FOF_TAG_TABLE."`, `".FOF_ITEM_TAG_TABLE."` where `".FOF_TAG_TABLE."`.tag_id = `".FOF_ITEM_TAG_TABLE."`.tag_id and `".FOF_ITEM_TAG_TABLE."`.item_id in (%s) and `".FOF_ITEM_TAG_TABLE."`.user_id = %d", $items, $user_id);
    
    while($row = $result->fetch_assoc())
    {
        $item_id = $row['item_id'];
        $tag = $row['tag_name'];
        
        $array[$lookup[$item_id]]['tags'][] = $tag;
    }

    return $array;
}

function fof_db_get_item($user_id, $item_id)
{
    $query = "select `".FOF_FEED_TABLE."`.feed_image as feed_image, 
					`".FOF_FEED_TABLE."`.feed_title as feed_title, 
					`".FOF_FEED_TABLE."`.feed_link as feed_link, 
					`".FOF_FEED_TABLE."`.feed_description as feed_description, 
					`".FOF_ITEM_TABLE."`.item_id as item_id, 
					`".FOF_ITEM_TABLE."`.item_link as item_link, 
					`".FOF_ITEM_TABLE."`.item_title as item_title, 
					`".FOF_ITEM_TABLE."`.item_cached, 
					`".FOF_ITEM_TABLE."`.item_published, 
					`".FOF_ITEM_TABLE."`.item_updated, 
					`".FOF_ITEM_TABLE."`.item_content as item_content 
				from `".FOF_FEED_TABLE."`, `".FOF_ITEM_TABLE."` 
				where `".FOF_ITEM_TABLE."`.feed_id = `".FOF_FEED_TABLE."`.feed_id 
					and `".FOF_ITEM_TABLE."`.item_id = %d";
    
    $result = fof_safe_query($query, $item_id);
    
    $item = $result->fetch_assoc();
    
    $item['tags'] = array();
    
	if($user_id)
	{
		$sql = "select `".FOF_TAG_TABLE."`.tag_name 
				from `".FOF_TAG_TABLE."`, `".FOF_ITEM_TAG_TABLE."` 
				where `".FOF_TAG_TABLE."`.tag_id = `".FOF_ITEM_TAG_TABLE."`.tag_id 
					and `".FOF_ITEM_TAG_TABLE."`.item_id = %d 
					and `".FOF_ITEM_TAG_TABLE."`.user_id = %d";

		$result = fof_safe_query($sql, $item_id, $user_id);

		while($row = fof_db_get_row($result))
		{
			$item['tags'][] = $row['tag_name'];
		}
	}
    
    return $item;
}

////////////////////////////////////////////////////////////////////////////////
// Tag stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_get_subscription_to_tags()
{
	$r = array();
	$result = fof_safe_query("select * from `".FOF_SUBSCRIPTION_TABLE."`");
	while($row = fof_db_get_row($result))
	{
		$prefs = unserialize($row['subscription_prefs']);
		$tags = $prefs['tags'];
		if(!isset($r[$row['feed_id']]) or !is_array($r[$row['feed_id']])) $r[$row['feed_id']] = array();
		$r[$row['feed_id']][$row['user_id']] = $tags;
	}

	return $r;
}

function fof_db_tag_feed($user_id, $feed_id, $tag_id)
{
    $result = fof_safe_query("select subscription_prefs from `".FOF_SUBSCRIPTION_TABLE."` where feed_id = %d and user_id = %d", $feed_id, $user_id);
    $row = fof_db_get_row($result);
    $prefs = unserialize($row['subscription_prefs']);
    
    if(!is_array($prefs['tags']) || !in_array($tag_id, $prefs['tags'])) $prefs['tags'][] = $tag_id;
    
    fof_safe_query("update `".FOF_SUBSCRIPTION_TABLE."` set subscription_prefs = '%s' where feed_id = %d and user_id = %d", serialize($prefs), $feed_id, $user_id);
}

function fof_db_untag_feed($user_id, $feed_id, $tag_id)
{
    $result = fof_safe_query("select subscription_prefs from `".FOF_SUBSCRIPTION_TABLE."` where feed_id = %d and user_id = %d", $feed_id, $user_id);
    $row = fof_db_get_row($result);
    $prefs = unserialize($row['subscription_prefs']);
    
    if(is_array($prefs['tags']))
    {
        $prefs['tags'] = array_diff($prefs['tags'], array($tag_id));
    }
    
    fof_safe_query("update `".FOF_SUBSCRIPTION_TABLE."` set subscription_prefs = '%s' where feed_id = %d and user_id = %d", serialize($prefs), $feed_id, $user_id);
}

function fof_db_get_item_tags($user_id, $item_id)
{
	$result = fof_safe_query("select `".FOF_TAG_TABLE."`.tag_name from `".FOF_TAG_TABLE."`, `".FOF_ITEM_TAG_TABLE."` where `".FOF_TAG_TABLE."`.tag_id = `".FOF_ITEM_TAG_TABLE."`.tag_id and `".FOF_ITEM_TAG_TABLE."`.item_id = %d and `".FOF_ITEM_TAG_TABLE."`.user_id = %d", $item_id, $user_id);

	return $result;
}

function fof_db_item_has_tags($item_id)
{
	$result = fof_safe_query("select count(*) as \"count\" from `".FOF_ITEM_TAG_TABLE."` where item_id=%d and tag_id <= 2", $item_id);
	$row = $result->fetch_assoc();

	return $row["count"];
}

function fof_db_get_unread_count($user_id)
{
	$result = fof_safe_query("select count(*) as \"count\" from ".FOF_ITEM_TAG_TABLE." where tag_id = 1 and user_id = %d", $user_id); 
	$row = fof_db_get_row($result);

	return $row["count"];
}

function fof_db_get_tag_unread($user_id)
{
    $result = fof_safe_query("SELECT count(*) as count, it2.tag_id FROM `".FOF_ITEM_TABLE."` i, `".FOF_ITEM_TAG_TABLE."` it , `".FOF_ITEM_TAG_TABLE."` it2 where it.item_id = it2.item_id and it.tag_id = 1 and i.item_id = it.item_id and i.item_id = it2.item_id and it.user_id = %d and it2.user_id = %d group by it2.tag_id", $user_id, $user_id);
    
    $counts = array();
    while($row = fof_db_get_row($result))
    {
        $counts[$row['tag_id']] = $row['count'];
    }

    return $counts;
}

function fof_db_get_tags($user_id)
{

    $sql = "SELECT `".FOF_TAG_TABLE."`.`tag_id`, `".FOF_TAG_TABLE."`.`tag_name`, count( `".FOF_ITEM_TAG_TABLE."`.`item_id` ) as count
			FROM `".FOF_TAG_TABLE."`
			LEFT JOIN `".FOF_ITEM_TAG_TABLE."` ON `".FOF_TAG_TABLE."`.`tag_id` = `".FOF_ITEM_TAG_TABLE."`.`tag_id`
			WHERE `".FOF_ITEM_TAG_TABLE."`.`user_id` = %d
			GROUP BY `".FOF_TAG_TABLE."`.`tag_id` order by `".FOF_TAG_TABLE."`.`tag_name`";

    $result = fof_safe_query($sql, $user_id);

    return $result;
}

function fof_db_get_tag_id_map()
{
    $sql = "select * from `".FOF_TAG_TABLE."`";

    $result = fof_safe_query($sql);

    $tags = array();

    while($row = fof_db_get_row($result))
    {
        $tags[$row['tag_id']] = $row['tag_name'];
    }

    return $tags;
}

function fof_db_create_tag($user_id, $tag)
{
	$result = fof_safe_query("insert into `".FOF_TAG_TABLE."` (tag_name) values ('%s')", $tag);

	return $result;
}

function fof_db_get_tag_by_name($user_id, $tag)
{

    $result = fof_safe_query("select `".FOF_TAG_TABLE."`.`tag_id` from `".FOF_TAG_TABLE."` where `".FOF_TAG_TABLE."`.`tag_name` = '%s'", $tag);
    
    if($result->num_rows == 0)
    {
        return NULL;
    }
    
    $row = $result->fetch_assoc();
    
    return $row['tag_id'];
}

function fof_db_mark_unread($user_id, $items)
{
    fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_read($user_id, $items)
{
    fof_db_untag_items($user_id, 1, $items);
}

function fof_db_mark_feed_read($user_id, $feed_id)
{
	$result = fof_db_get_items($user_id, $feed_id, $what="all");

	foreach($result as $r)
	{
		$items[] = $r['item_id'];
	}

	fof_db_untag_items($user_id, 1, $items);
}

function fof_db_mark_feed_unread($user_id, $feed, $what)
{
	fof_log("fof_db_mark_feed_unread($user_id, $feed, $what)");

	if($what == "all")
	{
		$result = fof_db_get_items($user_id, $feed, "all");
	}
	if($what == "today")
	{
		$result = fof_db_get_items($user_id, $feed, "all", "today");
	}
	$items = array();
	foreach((array)$result as $r)
	{
		$items[] = $r['item_id'];
	}

	fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_item_unread($users, $id)
{
    if(count($users) == 0) return;
    
    foreach($users as $user)
    {
        $sql[] = sprintf("(%d, 1, %d)", $user, $id);
    }
    
    $values = implode ( ",", $sql );
    
	$sql = "insert into `".FOF_ITEM_TAG_TABLE."` (`user_id`, `tag_id`, `item_id`) values " . $values;
	
	$result = fof_db_query($sql, 1);

    if(!$result && ($_SESSION["fof_connection"]->errno != 1062))
    {
        die("Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? MySQL says: <b>". $fof_connection->error . "</b>");
    }
}

function fof_db_tag_items($user_id, $tag_id, $items)
{
    if(!$items) return;
    
    if(!is_array($items)) $items = array($items);

    foreach($items as $item)
    {
        $sql[] = sprintf("(%d, %d, %d)", $user_id, $tag_id, $item);
    }
    
    $values = implode ( ",", $sql );
    
	$sql = "insert into `".FOF_ITEM_TAG_TABLE."` (`user_id`, `tag_id`, `item_id`) values " . $values;
	
	$result = fof_db_query($sql, 1);
    
    if(!$result && ($_SESSION["fof_connection"]->errno != 1062))
    {
        die("Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? MySQL says: <b>". $fof_connection->error . "</b>");
    }
}

function fof_db_untag_items($user_id, $tag_id, $items)
{
    if(!$items) return;
    
    if(!is_array($items)) $items = array($items);
    
    foreach($items as $item)
    {
        $sql[] = " item_id = %d ";
        $args[] = $item;
    }
    
    $values = implode ( " or ", $sql );
    
    $sql = "delete from `".FOF_ITEM_TAG_TABLE."` where `user_id` = %d and `tag_id` = %d and ( $values )";
    
    array_unshift($args, $tag_id);
    array_unshift($args, $user_id);

    fof_safe_query($sql, $args);
}


////////////////////////////////////////////////////////////////////////////////
// User stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_get_users($username = NULL)
{
	if ($username != NULL and strlen($username))
	{
		$result = fof_safe_query("select * from `".FOF_USER_TABLE."` WHERE `user_name` = '%s'", $username);
	}
	else
	{
		$result = fof_safe_query("select * from `".FOF_USER_TABLE."`");
	}
	while($row = fof_db_get_row($result))
	{
		$users[$row['user_id']]['user_id'] = $row['user_id'];
		$users[$row['user_id']]['user_name'] = $row['user_name'];
		$users[$row['user_id']]['user_password_hash'] = $row['user_password_hash'];
		$users[$row['user_id']]['user_level'] = $row['user_level'];
		$users[$row['user_id']]["login_no"] = $row['login_no'];
//		$users[$row['user_id']]['cookie'] = $row['cookie'];
	}
	return (isset($users) ? $users : array());
}

function fof_db_add_user($username, $password)
{
	$password_hash = password_hash($password, PASSWORD_BCRYPT);

	$id = fof_safe_query("insert into `".FOF_USER_TABLE."` (`user_name`, `user_password_hash`) values ('%s', '%s')", $username, $password_hash);

	$prefs = new FoF_Prefs($id);
	if ($prefs->get("auth_type") == "md5")
	{
		$prefs->set("auth_type", "crypt");
		$prefs->save();
	}
}

function fof_db_change_password($userid, $password)
{
	$password_hash = password_hash($password, PASSWORD_BCRYPT);
	fof_safe_query("update `".FOF_USER_TABLE."` set `user_password_hash` = '%s' where `user_id` = '%d'", $password_hash, $userid);
	$prefs = new FoF_Prefs($userid);
	if ($prefs->get("auth_type") == "md5")
	{
		$prefs->set("auth_type", "crypt");
		$prefs->save();
	}

	return $password_hash;
}

function fof_db_get_user_id($username)
{
	$result = fof_safe_query("select `user_id` from `".FOF_USER_TABLE."` where `user_name` = '%s'", $username);
	$row = $result->fetch_assoc();

	return $row['user_id'];
}

function fof_db_delete_user($username)
{
	$user_id = fof_db_get_user_id($username);

	fof_safe_query("delete from `".FOF_SUBSCRIPTION_TABLE."` where `user_id` = %d", $user_id);
	fof_safe_query("delete from `".FOF_ITEM_TAG_TABLE."` where `user_id` = %d", $user_id);
	fof_safe_query("delete from `".FOF_USER_TABLE."` where `user_id` = %d", $user_id);
}

function fof_db_save_prefs($user_id, $prefs)
{
	$prefs = serialize($prefs);

	fof_safe_query("update `".FOF_USER_TABLE."` set `user_prefs` = '%s' where `user_id` = %d", $prefs, $user_id);
}

function fof_db_authenticate($user_name, $user_password_hash)
{

	$fof["user_name"] = "";
	$fof["user_id"] = "";
	$fof["user_level"] = "";
	$fof["user_password_hash"] = "";
	
	$result = fof_safe_query("select * from `".FOF_USER_TABLE."` where `user_name` = '%s' and `user_password_hash` = '%s'", $user_name, $user_password_hash);

	$row = fof_db_get_row($result);

	if($result->num_rows)
	{
		$fof["user_name"] = $row['user_name'];
		$fof["user_id"] = $row['user_id'];
		$fof["user_level"] = $row['user_level'];
		$fof["user_password_hash"] = $row['user_password_hash'];
	}

	return $fof;
}

function fof_db_fetch_fields($db)
{
	$result = $_SESSION["fof_connection"]->query("SELECT * FROM `".$db."` LIMIT 1");
	$r = $result->fetch_fields();
	foreach ($r AS $v)
	{
		$return[$v->name]["name"] = $v->name;
		$return[$v->name]["table"] = $v->table;
		$return[$v->name]["max_length"] = $v->max_length;
		if ($v->type == 254 and $v->charsetnr == 45)
		{
			$return[$v->name]["length"] = $v->length/4;
		}
		else
		{
			$return[$v->name]["length"] = $v->length;
		}
		$return[$v->name]["charsetnr"] = $v->charsetnr;
		$return[$v->name]["flags"] = $v->flags;
		$return[$v->name]["type"] = $v->type;
	}

	if (!isset($return)) $return[] = "";

	return $return;
}
?>
