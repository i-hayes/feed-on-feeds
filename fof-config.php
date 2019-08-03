<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * config.php - modify this file with your database settings
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

// Difference, in hours, between your server and your local time zone.
define("SOURSE_WEB_SITE", "https://github.com/i-hayes/feed-on-feeds");
define('FOF_TIME_OFFSET', 0);

// Database connection information.  Host, username, password, database name.

define('FOF_DB_HOST', "localhost");
define('FOF_DB_USER', "feedonfeed");
define('FOF_DB_PASS', "bVTPBbR0lxmAU2Ro");
define('FOF_DB_DBNAME', "feedonfeed");

// The rest you should not need to change

// DB table names

define('FOF_DB_PREFIX', "fof_");

define('FOF_FEED_TABLE', FOF_DB_PREFIX . "feed");
define('FOF_ITEM_TABLE', FOF_DB_PREFIX . "item");
define('FOF_ITEM_TAG_TABLE', FOF_DB_PREFIX . "item_tag");
define('FOF_SUBSCRIPTION_TABLE', FOF_DB_PREFIX . "subscription");
define('FOF_TAG_TABLE', FOF_DB_PREFIX . "tag");
define('FOF_USER_TABLE', FOF_DB_PREFIX . "user");


// Find ourselves and the cache dir

if (!defined('DIR_SEP')) {
	define('DIR_SEP', DIRECTORY_SEPARATOR);
}

if (!defined('FOF_DIR')) {
    define('FOF_DIR', dirname(__FILE__) . DIR_SEP);
}
if (!defined('FOF_BASEDIR')) 
{
	define('FOF_BASEDIR', str_replace($_SERVER["DOCUMENT_ROOT"], "", FOF_DIR));
}

// Set the time interval for the sidebar to reload automatically. Default is 10 minutes.
$reload_interval = 10;
//define("COOKIE_LIFE_TIME", 60*60*24*365*10); // Set to 10 years
define("COOKIE_LIFE_TIME", 60*60*24*10); // Set to 10 days
define("MAX_FAILED_LOGIN", 3);

?>
