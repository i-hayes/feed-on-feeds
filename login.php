<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * login.php - username / password entry
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/

 * Copyright (C) 2019 Ian Hayes
 * ihayes@earthling.net - https://github.com/i-hayes/feed-on-feeds
 *
 * Distributed under the GPL - see LICENSE
 *
 */

ob_start();

$fof_no_login = true;
$failed = false;

include_once("fof-main.php");
include_once("upgrade.php");

fof_set_content_type();

if (isset($_COOKIE["fof_remember"]))
{
	$result = fof_safe_query("SELECT * FROM `".FOF_USER_TABLE."` WHERE `auto_login` = \"%s\"", $_COOKIE["fof_remember"]);
	$user = $result->fetch_assoc();
	if ($user["user_id"])
	{
		fof_set_session($user);
		Header("Location: ./");
		exit();
	}
}

if(isset($_POST["user_name"]) && isset($_POST["user_password"]))
{
	if (!isset($_POST["remember"])) $_POST["remember"] = 0;
	if(fof_authenticate($_POST['user_name'], $_POST['user_password'], $_POST["remember"]))
	{
		Header("Location: ./");
		exit();
	}
	else
	{
		$failed = true;
	}
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Feed on Feeds - Log on</title>

    <style>
    body
    {
      font-family: georgia;
      font-size: 16px;
    }

    div
    {
      background: #eee;
      border: 1px solid black;
      width: 20em;
      margin: 5em auto;
      padding: 1.5em;
    }
    </style>
  </head>

  <body>
    <div>
      <form action="?login=1" method="POST" style="display: inline">
        <center><a href="<?php print (SOURSE_WEB_SITE); ?>" style="font-size: 20px; font-family: georgia;">Feed on Feeds</a></center><br />
        User name:<br /><input type="string" name="user_name" style='font-size: 16px' /><br /><br />
        Password:<br /><input type="password" name="user_password" style='font-size: 16px' /><br /><br />
        <input type="checkbox" name="remember" value="1"<?php if (isset($_POST["remember"]) and $_POST["remember"]) print (" checked=\"checked\""); ?> /> Remember me.<br /><br />
        <input type="submit" value="Log on!" style='font-size: 16px; float: right;' /><br />
        <?php if($failed)
		{
			echo "<br><center><font color=red><b>"; 
			if (defined("ACCOUNT_LOCKED"))
			{
				echo "Account locked."; 
			}
			else
			{
				echo "Incorrect user name or password"; 
			}
			echo "</b></font></center>"; 

		}
		 ?>
      </form>
    </div>
  </body>
</html>
