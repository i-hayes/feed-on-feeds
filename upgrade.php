<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

$fof_no_login = true;
$fof_installer = true;

include_once("fof-main.php");

$Upgrade = false;
$db_upgrade["public_feed"]["db"] = FOF_FEED_TABLE;
$db_upgrade["public_feed"]["upgrade"]["type"] = "add";
$db_upgrade["public_feed"]["field"]["name"] = "public_feed";
$db_upgrade["public_feed"]["field"]["type"] = "TINYINT";
$db_upgrade["public_feed"]["field"]["default"] = "DEFAULT '0'";
$db_upgrade["public_feed"]["field"]["after"] = "feed_link";

$db_upgrade["auto_login"]["db"] = FOF_USER_TABLE;
$db_upgrade["auto_login"]["upgrade"]["type"] = "add";
$db_upgrade["auto_login"]["field"]["name"] = "auto_login";
$db_upgrade["auto_login"]["field"]["type"] = "VARCHAR( 254 )";
$db_upgrade["auto_login"]["field"]["default"] = "DEFAULT ''";
$db_upgrade["auto_login"]["field"]["after"] = "user_password_hash";

$db_upgrade["login_attempt"]["db"] = FOF_USER_TABLE;
$db_upgrade["login_attempt"]["upgrade"]["type"] = "add";
$db_upgrade["login_attempt"]["field"]["name"] = "login_no";
$db_upgrade["login_attempt"]["field"]["type"] = "TINYINT";
$db_upgrade["login_attempt"]["field"]["default"] = "DEFAULT '0'";
$db_upgrade["login_attempt"]["field"]["after"] = "auto_login";

$db_upgrade["password_reset"]["db"] = FOF_USER_TABLE;
$db_upgrade["password_reset"]["upgrade"]["type"] = "add";
$db_upgrade["password_reset"]["field"]["name"] = "password_reset";
$db_upgrade["password_reset"]["field"]["type"] = "VARCHAR( 254 )";
$db_upgrade["password_reset"]["field"]["default"] = "DEFAULT ''";
$db_upgrade["password_reset"]["field"]["after"] = "login_no";

$db_upgrade["reset_time_out"]["db"] = FOF_USER_TABLE;
$db_upgrade["reset_time_out"]["upgrade"]["type"] = "add";
$db_upgrade["reset_time_out"]["field"]["name"] = "reset_time_out";
$db_upgrade["reset_time_out"]["field"]["type"] = "INT";
$db_upgrade["reset_time_out"]["field"]["default"] = "DEFAULT '0'";
$db_upgrade["reset_time_out"]["field"]["after"] = "password_reset";

$db_upgrade["email"]["db"] = FOF_USER_TABLE;
$db_upgrade["email"]["upgrade"]["type"] = "delete";
$db_upgrade["email"]["field"]["name"] = "user_email";

fof_set_content_type();

if (isset($_POST["Upgrade"]) and $_POST["Upgrade"] == "Upgrade")
{
	$Upgrade = fof_db_password_field(1);
	foreach ($db_upgrade as $v)
	{
		$f = "fof_db_".$v["upgrade"]["type"]."_field";
		$r = $f($v, 1);
		if ($r) $Upgrade = true;
	}
	$button = "Done";
}
else
{
	$Upgrade = fof_db_password_field(0);
	foreach ($db_upgrade as $v)
	{
		$f = "fof_db_".$v["upgrade"]["type"]."_field";
		$r = $f($v, 0);
		if ($r) $Upgrade = true;
	}
	$button = "Upgrade";
}

if ($Upgrade)
{
?>
  <form action="upgrade.php" method="post">
    <input type="submit" name="Upgrade" value="<?php print ($button) ?>" />
    <?php if ($button == "Upgrade") print ("<input type=\"submit\" name=\"Cancel\" value=\"cancel\" />"); ?>
  </form>
<?php
	exit;
}
if (isset($_POST["Upgrade"]) and $_POST["Upgrade"] == "Done")
{
	unset($_POST["Upgrade"]);
	header("Location: ./");
	exit;
}
function fof_db_password_field($upgrade = 0)
{
	$result = fof_db_query("show columns from `".FOF_USER_TABLE."` like 'user_password_hash'");
	$return = false;

	if($result->num_rows == 0)
	{
		if ($upgrade)
		{
			print "Upgrading schema changing field `user_password` to `user_password_hash`... ";
			fof_db_query("ALTER TABLE `".FOF_USER_TABLE."` CHANGE `user_password` `user_password_hash` VARCHAR( 254 ) NOT NULL");
			print "Done.<br/>\n";
			print "Hashing all passwords... ";
			$result = fof_db_query("SELECT * FROM `".FOF_USER_TABLE."`");
			while ($row = $result->fetch_assoc())
			{
				fof_db_query("update `".FOF_USER_TABLE."` set `user_password_hash` = \"".password_hash($row["user_password_hash"])."\"");
			}
			print "Done.<br/>\n";
			$return = true;
		}
		else
		{
			print ("<div class=\"upgrade\">User table needs to be upgraded changing field `user_password` to `user_password_hash` with </div>\n");
			$return = true;
		}
	}
	else
	{
		$row = $result->fetch_assoc();
		if ($row["Type"] == "varchar(32)")
		{
			if ($upgrade)
			{
				print "Upgrading schema extending `user_password_hash` from 32 characters to 254 characters... ";
				fof_db_query("ALTER TABLE `".FOF_USER_TABLE."` CHANGE `user_password_hash` `user_password_hash` VARCHAR( 254 ) NOT NULL");
				print "Done.<br/>\n";
				$return = true;
			}
			else
			{
				print ("<div class=\"upgrade\">User table needs to be upgraded changing field `user_password_hash` to 254 characters</div>\n");
				$return = true;
			}
		}
	}
	return $return;
}

function fof_db_delete_field($params, $upgrade = 0)
{
	$result = fof_db_query("show columns from `".$params["db"]."` like '".$params["field"]["name"]."'");
	$return = false;

	if($result->num_rows > 0)
	{
		if ($upgrade)
		{
			print "Upgrading schema deleting field `".$params["field"]["name"]."`... ";
			fof_db_query("ALTER TABLE `".FOF_USER_TABLE."` DROP `".$params["field"]["name"]."`");
			print "Done.<br/>\n";
		}
		else
		{
				print ("<div class=\"upgrade\">'".$params["db"]."' table needs to be upgraded removing field `".$params["field"]["name"]."`</div>\n");
		}
		$return = true;
	}
	return $return;
}

function fof_db_add_field($params, $upgrade = 0)
{
	$result = fof_db_query("show columns from `".$params["db"]."` like '".$params["field"]["name"]."'");
	$return = false;
	if($result->num_rows == 0)
	{
		if ($upgrade)
		{
			print "Upgrading schema adding field `".$params["field"]["name"]."`... ";
			fof_db_query("ALTER TABLE `".$params["db"]."` ADD `".$params["field"]["name"]."` ".$params["field"]["type"]." NOT NULL ".$params["field"]["default"]." AFTER `".$params["field"]["after"]."`");
			print "Done.<br/>\n";
		}
		else
		{
				print ("<div class=\"upgrade\">'".$params["db"]."' table needs to be upgraded adding field `".$params["field"]["name"]."`</div>\n");
		}
		$return = true;
	}
	return $return;
}

?>