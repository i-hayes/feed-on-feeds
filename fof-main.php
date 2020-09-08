<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-main.php - initializes FoF, and contains functions used from other scripts
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

fof_repair_drain_bamage();

if ( !file_exists( dirname(__FILE__) . '/fof-config.php') )
{
    echo "You will first need to create a fof-config.php file.  Please copy fof-config-sample.php to fof-config.php and then update the values to match your database settings.";
    die();
}

require_once("fof-config.php");
require_once("fof-db.php");
require_once("classes/fof-prefs.php");

$_SESSION["fof_connection"] = fof_db_connect();

if(!isset($fof_installer))
{
    if(!isset($fof_no_login))
    {
		include_once("upgrade.php");
        require_user();
        $FoF_Prefs = new FoF_Prefs("");
        $fof_prefs_obj = $FoF_Prefs->instance();
    }
    else
    {
        $fof_user_id = 1;
        $FoF_Prefs = new FoF_Prefs("");
        $fof_prefs_obj = $FoF_Prefs->instance();
    }

    ob_start();
    fof_init_plugins();
    ob_end_clean();
}
$collapse = (isset($_COOKIE["fof_collapse"]) ? $_COOKIE["fof_collapse"] : ((isset($prefs['fof_collapse']) and $prefs['fof_collapse']) ? "hidden" : "shown"));

//require_once('simplepie/simplepie.inc');
require_once('simplepie/autoloader.php');

function fof_set_content_type()
{
    static $set;
    if(!$set)
    {
        header("Content-Type: text/html; charset=utf-8");
        $set = true;
    }
}

function fof_log($message, $topic="debug")
{
    global $fof_prefs_obj;
    
    if(!$fof_prefs_obj) return;
    
    $p = $fof_prefs_obj->admin_prefs;
    if(!$p['logging']) return;
    
    static $log;
    if(!isset($log)) $log = @fopen("fof.log", 'a');
    
    if(!$log) return;
    
    $message = str_replace ("\n", "\\n", $message); 
    $message = str_replace ("\r", "\\r", $message); 
    
    fwrite($log, date('r') . " [$topic] $message\n");
}

function require_user()
{
	if(!isset($_SESSION["user_name"]))
	{
		include("login.php");
		exit();
	}
}

function fof_authenticate($user_name, $user_password, $remember = 0)
{
	$login = false;
	$users = fof_db_get_users($user_name);
	if (!count($users)) return $login;
	foreach ($users as $key=>$value)
	{
		foreach ($value as $k=>$v)
		{
			$user[$k] = $v;
		} 
	}
	if ($user["login_no"] <= MAX_FAILED_LOGIN)
	{
		$prefs = new FoF_Prefs($user["user_id"]);
		if (null == $prefs->get("auth_type") or $prefs->get("auth_type") == "md5")
		{
			if ($user["user_password_hash"] == md5($user_password.$user_name))
			{
				$prefs->set("auth_type", "md5");
				$prefs->set("remember", $remember);
				$prefs->save();
				fof_set_session($user);
				$login = true;
			}
		}
		elseif (null != $prefs->get("auth_type") and $prefs->get("auth_type") == "crypt")
		{
			if (password_verify($user_password, $user["user_password_hash"]))
			{
				$prefs->set("remember", $remember);
				$prefs->save();
				fof_set_session($user);
				$login = true;
			}
		}
		if($login)
		{
			if ($prefs->get("auth_type") == "md5")
			{
				$result = fof_db_query("SHOW FIELDS FROM `".FOF_USER_TABLE."` LIKE 'user_password_hash'");
				$row = $result->fetch_assoc();
				if ($row["Type"] == "varchar(254)")
				{
					fof_db_change_password(fof_current_user(), $user_password);
					$prefs->set('auth_type', "crypt");
					$prefs->save();
				}
			}
		}
	}
	if (!$login)
	{
		$user["login_no"]++;
		fof_db_query("UPDATE `".FOF_USER_TABLE."` SET `login_no` = \"".$user["login_no"]."\" WHERE `user_id` = \"".$user["user_id"]."\"");
		if ($user["login_no"] > MAX_FAILED_LOGIN) define("ACCOUNT_LOCKED", true);
	}
	return $login;
}

function fof_set_session($data)
{
	$prefs = new FoF_Prefs($data["user_id"]);

	foreach($data AS $k=>$v)
	{
		if (strlen($v)) $_SESSION[$k] = $v; 
	}

	if (null != $prefs->get("collapse"))
	{
		setcookie("fof_collapse", ($prefs->get("collapse") ? "hidden" : "shown"));
	}
	$autologin = "";
	if (null != $prefs->get("remember") and $prefs->get("remember"))
	{
		$salt = Salt();
		setcookie("fof_remember", $salt, (time() + COOKIE_LIFE_TIME));
		$autologin = "`auto_login` = \"".addslashes($salt)."\", ";
	}
	fof_db_query("UPDATE `".FOF_USER_TABLE."` SET $autologin`login_no` = '0' WHERE `user_id` = \"".$data["user_id"]."\"");

} // end function set_session()

function RandomToken($length = 32)
{
	if(!isset($length) || intval($length) <= 8 )
	{
		$length = 32;
	}
	if (function_exists('random_bytes')) 
	{
		return bin2hex(random_bytes($length));
	}
	if (function_exists('mcrypt_create_iv')) 
	{
		return bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
	}
	if (function_exists('openssl_random_pseudo_bytes')) 
	{
		return bin2hex(openssl_random_pseudo_bytes($length));
	}
}

function Salt()
{
    return substr(strtr(base64_encode(hex2bin(RandomToken(32))), '+', '.'), 0, 44);
}

if (!function_exists('password_hash'))
{
	function password_hash($var)
	{
		return crypt($var, Salt());
	} // end function password_hash()
}

if (!function_exists('password_verify'))
{
	$output = false;
	function password_verify($user_input, $password)
	{
		if ($password == crypt(urlencode($user_input), $password))  $output = true;
	}
	return $output;
} // end function password_verify ()

function fof_logout()
{
	$prefs = new FoF_Prefs(fof_current_user());
	$prefs->set("remember", 0);
	$prefs->save(fof_current_user());
	fof_db_query("UPDATE `".FOF_USER_TABLE."` SET `auto_login` = \"\" WHERE `user_id` = \"".fof_current_user()."\"");
	setcookie("fof_remember", "", 1);
	session_unset();
	session_destroy();
	header("Location: ./");
	exit;
}

function fof_current_user()
{
    return (isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 0);
}

function fof_username()
{
    return (isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "");
}

function fof_get_users()
{
    return fof_db_get_users();
}

function fof_prefs()
{
	$prefs = new FoF_Prefs("");
	$p = $prefs->instance();
    return $p->prefs;
}

function fof_is_admin()
{
    return $_SESSION["user_level"] == "admin";
}

function fof_get_unread_count($user_id)
{
    return fof_db_get_unread_count($user_id);
}

function fof_get_tags($user_id)
{
    $tags = array();
    
    $result = fof_db_get_tags($user_id);
    
    $counts = fof_db_get_tag_unread($user_id);
    
    while($row = fof_db_get_row($result))
    {
        if(isset($counts[$row['tag_id']]))
            $row['unread'] = $counts[$row['tag_id']];
        else
            $row['unread'] = 0;
            
        $tags[] = $row;
    }
    
    return $tags;
}

function fof_get_item_tags($user_id, $item_id)
{
	$result = fof_db_get_item_tags($user_id, $item_id);
   
	$tags = array();
   
	while($row = fof_db_get_row($result))
	{
    	$tags[] = $row['tag_name'];
    }
    
	return $tags;
}

function fof_tag_feed($user_id, $feed_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    if($tag_id == NULL)
    {
        $tag_id = fof_db_create_tag($user_id, $tag);
    }
    
    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_tag_items($user_id, $tag_id, $items);
    
    fof_db_tag_feed($user_id, $feed_id, $tag_id);
}

function fof_untag_feed($user_id, $feed_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    if($tag_id == NULL)
    {
        $tag_id = fof_db_create_tag($user_id, $tag);
    }
    
    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_untag_items($user_id, $tag_id, $items);
    
    fof_db_untag_feed($user_id, $feed_id, $tag_id);
}

function fof_tag_item($user_id, $item_id, $tag)
{
	if(is_array($tag)) $tags = $tag; else $tags[] = $tag;
	
	foreach($tags as $tag)
	{
		$tag_id = fof_db_get_tag_by_name($user_id, $tag);
		if($tag_id == NULL)
		{
			$tag_id = fof_db_create_tag($user_id, $tag);
		}
		
		fof_db_tag_items($user_id, $tag_id, $item_id);   
	}
}

function fof_untag_item($user_id, $item_id, $tag)
{
   $tag_id = fof_db_get_tag_by_name($user_id, $tag);
   fof_db_untag_items($user_id, $tag_id, $item_id);   
}

function fof_untag($user_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);

    $result = fof_db_get_items($user_id, $feed_id, $tag, NULL, NULL, NULL);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_nice_time_stamp($age)
{
      $age = time() - $age;

      if($age == 0)
      {
         $agestr = "never";
         $agestrabbr = "&infin;";
      }
      else
      {
         $seconds = $age % 60;
         $minutes = $age / 60 % 60;
         $hours = $age / 60 / 60 % 24;
         $days = floor($age / 60 / 60 / 24);

         if($seconds)
         {
            $agestr = "$seconds second";
            if($seconds != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $seconds . "s";
         }

         if($minutes)
         {
            $agestr = "$minutes minute";
            if($minutes != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $minutes . "m";
         }

         if($hours)
         {
            $agestr = "$hours hour";
            if($hours != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $hours . "h";
         }

         if($days)
         {
            $agestr = "$days day";
            if($days != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $days . "d";
         }
      }
      
      return array($agestr, $agestrabbr);
}

function fof_get_feeds($user_id, $order = 'feed_title', $direction = 'asc')
{
	$feeds = array();
	$result = fof_db_get_subscriptions($user_id, $order, $direction);

	while($row = fof_db_get_row($result))
	{
		$id = $row['feed_id'];
		$age = $row['feed_cache_date'];

		$feeds[$id]['feed_id'] = $id;
		$feeds[$id]['feed_url'] = $row['feed_url'];
		$feeds[$id]['feed_title'] = $row['feed_title'];
		$feeds[$id]['feed_link'] = $row['feed_link'];
		$feeds[$id]['feed_description'] = $row['feed_description'];
		$feeds[$id]['feed_image'] = $row['feed_image'];
		$feeds[$id]['prefs'] = unserialize($row['subscription_prefs']);
		$feeds[$id]['feed_age'] = $age;
		$feeds[$id]['feed_starred'] = 0;

		list($agestr, $agestrabbr) = fof_nice_time_stamp($age);

		$feeds[$id]['agestr'] = $agestr;
		$feeds[$id]['agestrabbr'] = $agestrabbr;
	}
	if (!count($feeds)) return array();
	$tags = fof_db_get_tag_id_map();

	foreach($feeds as $feed)
	{
		$id = $feed['feed_id'];
		$feeds[$id]['tags'] = array();
		if(!is_bool($feeds[$id]['prefs']))
		{
			foreach($feeds[$id]['prefs']['tags'] as $tag)
			{
				$feeds[$id]['tags'][] = $tags[$tag];
			}
		}
	}

	$result = fof_db_get_item_count($user_id);

	while($row = fof_db_get_row($result))
	{
		$feeds[$row['id']]['feed_items'] = $row['count'];
		$feeds[$row['id']]['feed_read'] = $row['count'];
		$feeds[$row['id']]['feed_unread'] = 0;
	}

	$result = fof_db_get_unread_item_count($user_id);

	while($row = fof_db_get_row($result))
	{
		$feeds[$row['id']]['feed_unread'] = $row['count'];
	}

	$result = fof_db_get_starred_item_count($user_id);

	while($row = fof_db_get_row($result))
	{
		$feeds[$row['id']]['feed_starred'] = $row['count'];
	}

	$result = fof_db_get_latest_item_age($user_id);

	while($row = fof_db_get_row($result))
	{
		if (isset($feeds[$row['id']]))
		{
			$feeds[$row['id']]['max_date'] = $row['max_date'];
			list($agestr, $agestrabbr) = fof_nice_time_stamp($row['max_date']);

			$feeds[$row['id']]['lateststr'] = $agestr;
			$feeds[$row['id']]['lateststrabbr'] = $agestrabbr;
		}
	}

   $feeds = fof_multi_sort($feeds, $order, $direction != "asc");

   return $feeds;
}

function fof_view_title($feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $search=NULL)
{
    //$prefs = fof_prefs();
    $title = "feed on feeds";
    
    if(!is_null($when) && $when != "")
    {
        $title .= ' - for ' . $when ;
    }
    if(!is_null($feed) && $feed)
    {
        $r = fof_db_get_feed_by_id($feed);
        $title .=' - ' . $r['feed_title'];
    }
    if(is_numeric($start))
    {
        if(!is_numeric($limit)) $limit = $prefs["howmany"];
        $title .= " - items $start to " . ($start + $limit);
    }
    if (strpos($what, " ")) $tag = explode(" ", $what); else $tag[] = $what;
    if($tag[0] == "unread")
    {
        $title .=' - new items';
        array_shift($tag);
    }
    else
    {
        $title .= ' - all items';
        if ($tag[0] == "all") array_shift($tag);
    }
	if (isset($tag[0])) $title .= " tagged ". implode($tag, ", ");

	if(isset($search) and strlen($search))
    {
        $title .= " - <a href='javascript:toggle_highlight()'>matching <i class='highlight'>$search</i></a>";
    }
    
    return $title;
}

function fof_get_items($user_id, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL)
{
   global $fof_item_filters;

   $items = fof_db_get_items($user_id, $feed, $what, $when, $start, $limit, $order, $search);

   for($i=0; $i<count($items); $i++)
   {
   	  foreach($fof_item_filters as $filter)
   	  {
		  $items[$i]['item_content'] = $filter($items[$i]['item_content']);
      }
   }
   
   return $items;
}

function fof_get_item($user_id, $item_id)
{   
   global $fof_item_filters;

   $item = fof_db_get_item($user_id, $item_id);
   
   foreach($fof_item_filters as $filter)
   {
      $item['item_content'] = $filter($item['item_content']);
   }
   
   return $item;
}

function fof_mark_read($user_id, $items)
{
    fof_db_mark_read($user_id, $items);
}

function fof_mark_unread($user_id, $items)
{
    fof_db_mark_unread($user_id, $items);
}

function fof_delete_subscription($user_id, $feed_id)
{
    fof_db_delete_subscription($user_id, $feed_id);
    $r = fof_get_subscribed_users($feed_id);
    if($r->num_rows == 0)
    {
    	fof_db_delete_feed($feed_id);
    }
}

function fof_get_nav_links($feed=NULL, $what="new", $when=NULL, $start=NULL, $how=NULL, $limit=NULL)
{
    $prefs = fof_prefs();
    $string = "";
    
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
        
        $begin = strtotime($whendate);
        
        $tomorrow = date( $prefs["dsformat"], $begin + (24 * 60 * 60) );
        $yesterday = date( $prefs["dsformat"], $begin - (24 * 60 * 60) );
        
        $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$yesterday&amp;how=$how&amp;howmany=".$prefs["howmany"]."\">&laquo; $yesterday</a> ";
        if($whendate != fof_todays_date())
        {
			$string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=today&amp;how=$how&amp;howmany=".$prefs["howmany"]."\">Today</a> ";
			if (fof_todays_date() != $tomorrow) $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=".$tomorrow."&amp;how=$how&amp;howmany=".$prefs["howmany"]."\">$tomorrow &raquo;</a> ";
		}
    }
    
    if(is_numeric($start))
    {
        if(!is_numeric($limit)) $limit = $prefs["howmany"];
        
        $earlier = $start + $limit;
        $later = $start - $limit;
        
        $string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;which=$earlier&amp;howmany=$limit\">[&laquo; previous $limit]</a> ";
        if($later >= 0)
        {
			$string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;howmany=$limit\">[current items]</a> ";
			$string .= "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;which=$later&amp;howmany=$limit\">[next $limit &raquo;]</a> ";
		}
    }
    
    return $string;
}

function fof_render_feed_link($row)
{
   $link = $row['feed_link'];
   $description = $row['feed_description'];
   $title = $row['feed_title'];
   $url = $row['feed_url'];

   $s = "<b><a href=\"$link\" title=\"$description\">$title</a></b> ";
   $s .= "<a href=\"$url\">(rss)</a>";

   return $s;
}

function fof_opml_to_array($opml)
{
   $rx = "/xmlurl=\"(.*?)\"/mi";

   if (preg_match_all($rx, $opml, $m))
   {
      for($i = 0; $i < count($m[0]) ; $i++)
      {
         $r[] = $m[1][$i];
      }
  }

  return $r;
}

function fof_prepare_url($url)
{
   $url = trim($url);

   if(substr($url, 0, 7) == "feed://") $url = substr($url, 7);

   if(substr($url, 0, 7) != 'http://' && substr($url, 0, 8) != 'https://')
   {
     $url = 'http://' . $url;
   }

    return $url;
}

function fof_subscribe($user_id, $url, $unread="today")
{
    if(!$url) return false;

    $url = fof_prepare_url($url);    
    $feed = fof_db_get_feed_by_url($url);
    
    if(fof_is_subscribed($user_id, $url))
    {
        return "You are already subscribed to " . fof_render_feed_link($feed) . "<br>";
    }
    
    if(fof_feed_exists($url))
    {
        fof_db_add_subscription($user_id, $feed['feed_id']);
        fof_apply_plugin_tags($feed['feed_id'], NULL, $user_id);
        fof_update_feed($feed['feed_id']);
        
        if($unread != "no") fof_db_mark_feed_unread($user_id, $feed['feed_id'], $unread);
        return '<font color="green"><b>Subscribed.</b></font><br>';
    }
    
    $rss = fof_parse($url);
       
    if (isset($rss->error))
    {
        return "Error: <B>" . $rss->error . "</b> <a target=\"_blank\" href=\"https://validator.w3.org/feed/check.cgi?url=$url\">try to validate it?</a><br>";
    }
    else
    {
        $url = html_entity_decode($rss->subscribe_url(), ENT_QUOTES);
        $self = $rss->get_link(0, 'self');
        if($self) $url = html_entity_decode($self, ENT_QUOTES);

        if(fof_feed_exists($url))
        {
            $feed = fof_db_get_feed_by_url($url);
            
            if(fof_is_subscribed($user_id, $url))
            {
                return "You are already subscribed to " . fof_render_feed_link($feed) . "<br>";
            }
            
            fof_db_add_subscription($user_id, $feed['feed_id']);
            if($unread != "no") fof_db_mark_feed_unread($user_id, $feed['feed_id'], $unread);

            return '<font color="green"><b>Subscribed.</b></font><br>';
        }
        
        $id = fof_add_feed($url, $rss->get_title(), $rss->get_link(), $rss->get_description() );
		
        fof_update_feed($id);
        fof_db_add_subscription($user_id, $id);
        if($unread != "no") fof_db_mark_feed_unread($user_id, $id, $unread);
        
        fof_apply_plugin_tags($id, NULL, $user_id);
 
       return '<font color="green"><b>Subscribed.</b></font><br>';
    }
}

function fof_add_feed($url, $title, $link, $description)
{
   if($title == "") $title = "[no title]";

   $id = fof_db_add_feed($url, $title, $link, $description);
   
   return $id;
}

function fof_is_subscribed($user_id, $url)
{
   return(fof_db_is_subscribed($user_id, $url));
}

function fof_feed_exists($url)
{
   $feed = fof_db_get_feed_by_url($url);

   return $feed;
}

function fof_get_subscribed_users($feed_id)
{
   return(fof_db_get_subscribed_users($feed_id));
}

function fof_mark_item_unread($feed_id, $id)
{
   $result = fof_get_subscribed_users($feed_id);
   $users = array();
   while($row = fof_db_get_row($result))
   {
      $users[] = $row['user_id'];
   }
   
   fof_db_mark_item_unread($users, $id);
}

function fof_parse($url)
{
	$FoF_Prefs = new FoF_Prefs("");
	$p = $FoF_Prefs->instance();
    $admin_prefs = $p->admin_prefs;
    
    $pie = new SimplePie();
    $pie->set_cache_duration($admin_prefs["manualtimeout"] * 60);
//    $pie->set_favicon_handler("favicon.php");
	$pie->set_feed_url($url);
//	$pie->set_javascript(false);
	$pie->remove_div(false);
	$pie->init();
	return $pie;
}

function fof_apply_tags($feed_id, $item_id)
{
	global $fof_subscription_to_tags;

	if(!isset($fof_subscription_to_tags))
	{
		$fof_subscription_to_tags = fof_db_get_subscription_to_tags();
	}

	if (isset($fof_subscription_to_tags[$feed_id]))
	{
		foreach((array)$fof_subscription_to_tags[$feed_id] as $user_id => $tags)
		{
			if(is_array($tags))
			{
				foreach($tags as $tag)
				{
					fof_db_tag_items($user_id, $tag, $item_id);
				}
			}
		}
	}
}

function fof_update_feed($id)
{
    if(!$id) return 0;

    $feed = fof_db_get_feed_by_id($id);
    $url = $feed['feed_url'];
    fof_log("Updating $url");
    
    fof_db_feed_mark_attempted_cache($id);
    
    $rss = fof_parse($feed['feed_url']);

    if ($rss->error())
    {
        fof_log("feed update failed: " . $rss->error(), "update");
        return array(0, "Error: <b>" . $rss->error() . "</b> <a target=\"_blank\" href=\"https://validator.w3.org/feed/check.cgi?url=$url\">try to validate it?</a>");
    }
        
    $sub = html_entity_decode($rss->subscribe_url(), ENT_QUOTES);
    $self_link = $rss->get_link(0, 'self');
    if($self_link) $sub = html_entity_decode($self_link, ENT_QUOTES);
    
    fof_log("subscription url is $sub");
    
    $image = $feed['feed_image'];
    $image_cache_date = $feed['feed_image_cache_date'];
    
    if($feed['feed_image_cache_date'] < (time() - (7*24*60*60)))
    {
        $image = ""; //$rss->get_favicon();
        $image_cache_date = time();
    }
	
	$title =  $rss->get_title();
	if($title == "") $title = "[no title]";
	
    fof_db_feed_update_metadata($id, $sub, $title, $rss->get_link(), $rss->get_description(), $image, $image_cache_date );
    
    $feed_id = $feed['feed_id'];
    $n = 0;
    
    if($rss->get_items())
    {
        foreach($rss->get_items() as $item)
        {
            $link = $item->get_permalink();
            $title = $item->get_title();
            $content = $item->get_content();
            $date = $item->get_date('U');
            if(!$date) $date = time();
            $item_id = $item->get_id();
            
            if(!$item_id)
            {
                $item_id = $link;
            }
            
            $id = fof_db_find_item($feed_id, $item_id);

            if($id == NULL)
            {
                global $fof_item_prefilters;
                if ($fof_item_prefilters)
                {
					foreach($fof_item_prefilters as $filter)
					{
						list($link, $title, $content) = $filter($item, $link, $title, $content);
					}
                }
                $id = fof_db_add_item($feed_id, $item_id, $link, $title, $content, time(), $date, $date);
                if ($id) $n++;
                fof_apply_tags($feed_id, $id);

                $republished = false;
                
                // this was a failed attempt to avoid duplicates when subscribing to
                // a "planet" type feed when you already have some of the feeds in the
                // planet subscribed.  in the end there were just too many cases where
                // dupes still got through (like the 'source' feed url being just slightly
                // different from the subscribed url).
                //
                // maybe a better approach would be simply using the Atom GUID as a
                // true *GU* ID.
                
                /*
                $source = $item->get_item_tags(SIMPLEPIE_NAMESPACE_ATOM_10, 'source');
                $links = $source[0]['child'][SIMPLEPIE_NAMESPACE_ATOM_10]['link'];
                
                if(is_array($links))
                {                    
                    foreach($links as $link)
                    {
                        if($link['attribs']['']['rel'] == 'self')
                        {
                            $feed_url = $link['attribs']['']['href'];
                                                        
                            $feed = fof_db_get_feed_by_url($feed_url);
                            
                            if($feed)
                            {
                                fof_log("was repub from $feed_url");
                                
                                $republished = true;
                                
                                $result = fof_get_subscribed_users($feed_id);
                                
                                $repub_subscribers = array();
                                while($row = fof_db_get_row($result))
                                {
                                   $repub_subscribers[] = $row['user_id'];
                                   fof_log("repub_sub: " . $row['user_id']);
                                }
                                
                                $result = fof_get_subscribed_users($feed['feed_id']);
                                
                                $original_subscribers = array();
                                while($row = fof_db_get_row($result))
                                {
                                   $original_subscribers[] = $row['user_id'];
                                   fof_log("orig_sub: " . $row['user_id']);
                                }
                                
                                $new_subscribers = array_diff($repub_subscribers, $original_subscribers);
                                
                                fof_db_mark_item_unread($new_subscribers, $id);
                                
                                $old_subscribers = array_intersect($original_subscribers, $repub_subscribers);

                                foreach($old_subscribers as $user)
                                {
                                    fof_tag_item($user, $id, 'republished');
                                }
                            }
                        }
                    }
                }
                */

                if(!$republished)
                {
                    fof_mark_item_unread($feed_id, $id);
                }

				fof_apply_plugin_tags($feed_id, $id, NULL);
            }
            
            $ids[] = $id;
        }
    }

    // optionally purge old items -  if 'purge' is set we delete items that are not
    // unread or starred, not currently in the feed or within sizeof(feed) items
    // of being in the feed, and are over 'purge' many days old
    
	$FoF_Prefs = new FoF_Prefs("");
	$p = $FoF_Prefs->instance();
    $admin_prefs = $p->admin_prefs;
    $ndelete = 0;
    if($admin_prefs['purge'] != "")
    {
        fof_log('purge is ' . $admin_prefs['purge']);
        $count = count($ids);
        fof_log('items in feed: ' . $count);

        if(count($ids) != 0)
        {
            $in = implode ( ", ", $ids );

            $sql = "select item_id, item_cached from `".FOF_ITEM_TABLE."` where feed_id = $feed_id and item_id not in ($in) order by item_cached desc limit $count, 1000000000";
            $result = fof_db_query($sql);
            
            while($row = fof_db_get_row($result))
            {
                if($row['item_cached'] < (time() - ($admin_prefs['purge'] * 24 * 60 * 60)))
                {
                    if(!fof_item_has_tags($row['item_id']))
                    {		      
                        $delete[] = $row['item_id'];
                    }
                }
            }

            $ndelete = count($delete);
            if(count($delete) != 0)
            {
                $in = implode(", ", $delete); 
                fof_db_query( "delete from `".FOF_ITEM_TABLE."` where item_id in ($in)" );
                fof_db_query( "delete from `".FOF_ITEM_TAG_TABLE."` where item_id in ($in)" );
            }
        }
    }
    
    unset($rss);
    
    fof_db_feed_mark_cached($feed_id);
    
    $log = "feed update complete, $n new items, $ndelete items purged";
    if($admin_prefs['purge'] == "")
    {
        $log .= " (purging disabled)";
    }
    fof_log($log, "update");

    return array($n, "");
}

function fof_apply_plugin_tags($feed_id, $item_id = NULL, $user_id = NULL)
{
    $users = array();

    if($user_id)
    {
        $users[] = $user_id;
    }
    else
    {
        $result = fof_get_subscribed_users($feed_id);
        
        while($row = fof_db_get_row($result))
        {
            $users[] = $row['user_id'];
        }
    }
    
    $items = array();
    if($item_id)
    {
        $items[] = fof_db_get_item($user_id, $item_id);
    }
    else
    {
        $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
        
        foreach($result as $r)
        {
            $items[] = $r;
        }
    }
    
    $userdata = fof_get_users();
    
    foreach($users as $user)
    {
        fof_log("tagging for $user");
                
        global $fof_tag_prefilters;
        if ($fof_tag_prefilters)
        {
			foreach($fof_tag_prefilters as $plugin => $filter)
			{
				fof_log("considering $plugin $filter");

				if(!$userdata[$user]['prefs']['plugin_' . $plugin])
				{
					foreach($items as $item)
					{
						$tags = $filter($item['item_link'], $item['item_title'], $item['item_content']);
						fof_tag_item($user, $item['item_id'], $tags);
					}
				}
			}
		}
	}
}

function fof_item_has_tags($item_id)
{
	return fof_db_item_has_tags($item_id);
}

function fof_init_plugins()
{
	global $fof_item_filters, $fof_item_prefilters, $fof_tag_prefilters, $fof_plugin_prefs;
    
    $fof_item_filters = array();
    $fof_item_prefilters = array();
    $fof_plugin_prefs = array();
	$fof_tag_prefilters = array();

	$FoF_Prefs = new FoF_Prefs("");
	$p = $FoF_Prefs->instance();
    
    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file=readdir($dirlist))
    {
    	fof_log("considering " . $file);
        if(preg_match('/\.php$/',$file) && !$p->get('plugin_' . substr($file, 0, -4)))
        {
        	fof_log("including " . $file);

            include(FOF_DIR . "/plugins/" . $file);
        }
    }

    closedir();
}

function fof_add_tag_prefilter($plugin, $function)
{
    global $fof_tag_prefilters;
    
    $fof_tag_prefilters[$plugin] = $function;
}

function fof_add_item_filter($function)
{
    global $fof_item_filters;
    
    $fof_item_filters[] = $function;
}

function fof_add_item_prefilter($function)
{
    global $fof_item_prefilters;
    
    $fof_item_prefilters[] = $function;
}

function fof_add_pref($name, $key, $type="string")
{
    global $fof_plugin_prefs;
    
    $fof_plugin_prefs[] = array($name, $key, $type);
}

function fof_add_item_widget($function)
{
    global $fof_item_widgets;
    
    $fof_item_widgets[] = $function;
}

function fof_get_widgets($item)
{
    global $fof_item_widgets;

    if (!is_array($fof_item_widgets))
    {
		return false;
	}

    foreach($fof_item_widgets as $widget)
    {
        $w = $widget($item);
        if($w) $widgets[] = $w;
    }
     
    return $widgets;
}

function fof_get_plugin_prefs()
{
    global $fof_plugin_prefs;
    
    return $fof_plugin_prefs;
}

function fof_multi_sort($tab, $key, $rev)
{
	if($rev)
	{
//		$compare = function() {'if (strtolower($a["'.$key.'"]) == strtolower($b["'.$key.'"])) {return 0;}else {return ((strtolower($a["'.$key.'"]) > strtolower($b["'.$key.'"])) ? -1 : 1)};';};
		uasort($tab, fof_comparer([$key, SORT_DESC, "fof_natural_sort"]));
	}
	else
	{
//		$compare = function() {'if (strtolower($a["'.$key.'"]) == strtolower($b["'.$key.'"])) {return 0;}else {return (strtolower($a["'.$key.'"]) < strtolower($b["'.$key.'"])) ? -1 : 1;};';};
		uasort($tab, fof_comparer([$key, SORT_ASC, "fof_natural_sort"]));
	}

	return $tab;
}

/**
 * This function modifies the sort in the function fof_compare to makes it
 * a natural sort (Aa)-(Zz), rather than A-Z then a-z.
 */
function fof_natural_sort($val)
{
	return (is_string($val) ? strtoupper($val) : $val);
}
/**
 * Thanks to Jon (Rocket Internet) at "talegame" at Gmail.com via Stack Overflow 
 * https://stackoverflow.com/questions/96759/how-do-i-sort-a-multidimensional-array-in-php/16788610#16788610
 * for the following sort function. 
 */
function fof_comparer() 
{
	// Normalize criteria up front so that the comparer finds everything tidy
	$criteria = func_get_args();
	foreach ($criteria as $index => $criterion) 
	{
		$criteria[$index] = is_array($criterion)
			? array_pad($criterion, 3, null)
			: array($criterion, SORT_ASC, null);
	}

	return function($first, $second) use (&$criteria) 
	{
		foreach ($criteria as $criterion) 
		{
			// How will we compare this round?
			list($column, $sortOrder, $projection) = $criterion;
			$sortOrder = ($sortOrder === SORT_DESC ? -1 : 1);

			// If a projection was defined project the values now
			if ($projection and isset($first[$column]) and isset($second[$column]))
			{
				$lhs = call_user_func($projection, $first[$column]);
				$rhs = call_user_func($projection, $second[$column]);
			}
			elseif (isset($first[$column]) and isset($second[$column]))
			{
				$lhs = $first[$column];
				$rhs = $second[$column];
			}

            // Do the actual comparison; do not return if equal
			if (isset($lhs) and isset($rhs))
			{
				if ($lhs < $rhs) 
				{
					return -1 * $sortOrder;
				}
				else if ($lhs > $rhs) 
				{
					return 1 * $sortOrder;
				}
			}
		}

		return 0; // tiebreakers exhausted, so $first == $second
	};
}

function fof_view_action()
{
	foreach ($_POST AS $key=>$val)
	{
		$first = false;

		if($val == "checked")
		{
			$key = substr($key, 1);
			$items[] = $key;
		}
	}

	if(isset($_POST['deltag']))
	{
		fof_untag(fof_current_user(), $_POST['deltag']);
	}
	else if(isset($_POST['feed']))
	{
		fof_db_mark_feed_read(fof_current_user(), $_POST['feed']);
	}
	else
	{
		if($items)
		{
			if($_POST['action'] == 'read')
			{
				fof_db_mark_read(fof_current_user(), $items);
			}

			if($_POST['action'] == 'unread')
			{
				fof_db_mark_unread(fof_current_user(), $items);
			}
		}
		header("Location: ./");
	}

}

function fof_todays_date()
{
    $prefs = fof_prefs();
    $offset = $prefs['tzoffset'];
    
    return gmdate( $prefs['dsformat'], time() + ($offset * 60 * 60) );
}

function fof_repair_drain_bamage()
{
	if (ini_get('register_globals'))
	{
		foreach($_REQUEST as $k=>$v)
		{
			unset($GLOBALS[$k]);
		}
	}
	// thanks to submitter of http://bugs.php.net/bug.php?id=39859
/*	if (get_magic_quotes_gpc()) 
	{
		function undoMagicQuotes($array, $topLevel=true) 
		{
			$newArray = array();
			foreach($array as $key => $value) 
			{
				if (!$topLevel) $key = stripslashes($key);
				if (is_array($value))
				{
					$newArray[$key] = undoMagicQuotes($value, false);
				}
				else 
				{
					$newArray[$key] = stripslashes($value);
				}
			}
			return $newArray;
		}
		$_GET = undoMagicQuotes($_GET);
		$_POST = undoMagicQuotes($_POST);
		$_COOKIE = undoMagicQuotes($_COOKIE);
		$_REQUEST = undoMagicQuotes($_REQUEST);
	}
*/
}

// for PHP 4 compatibility

if(!function_exists('str_ireplace'))
{
	function str_ireplace($search,$replace,$subject)
	{
		$search = preg_quote($search, "/");
		return preg_replace("/".$search."/i", $replace, $subject);
	}
}
?>
