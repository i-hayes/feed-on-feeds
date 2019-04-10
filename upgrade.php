<?php
$fof_no_login = true;
$fof_installer = true;

include_once("fof-main.php");

fof_set_content_type();

if (isset($_POST["Upgrade"]) and $_POST["Upgrade"] == "Upgrade")
{
	$password = fof_db_password_field(1);
	$email = fof_db_email_field(1);
	$login = fof_db_auto_login_field(1);
	$button = "Done";
}
else
{
	$password = fof_db_password_field(0);
	$email = fof_db_email_field(0);
	$login = fof_db_auto_login_field(0);
	$button = "Upgrade";
}

if ($password or $email or $login)
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

function fof_db_email_field($upgrade = 0)
{
	$result = fof_db_query("show columns from `".FOF_USER_TABLE."` like 'user_email'");
	$return = false;
	if($result->num_rows == 0)
	{
		if ($upgrade)
		{
			print "Upgrading schema adding field `user_email`... ";
			fof_db_query("ALTER TABLE `".FOF_USER_TABLE."` ADD `user_email` VARCHAR( 254 ) NOT NULL AFTER `user_password_hash`");
			print "Done.<br/>\n";
		}
		else
		{
				print ("<div class=\"upgrade\">User table needs to be upgraded adding field `user_email`</div>\n");
		}
		$return = true;
	}
	return $return;
}

function fof_db_auto_login_field($upgrade = 0)
{
	$result = fof_db_query("show columns from `".FOF_USER_TABLE."` like 'auto_login'");
	$return = false;
	if($result->num_rows == 0)
	{
		if ($upgrade)
		{
			print "Upgrading schema adding field `auto_login`... ";
			fof_db_query("ALTER TABLE `".FOF_USER_TABLE."` ADD `auto_login` VARCHAR( 254 ) NOT NULL AFTER `user_password_hash`");
			print "Done.<br/>\n";
		}
		else
		{
				print ("<div class=\"upgrade\">User table needs to be upgraded adding field `auto_login`</div>\n");
		}
		$return = true;
	}
	return $return;
}

?>