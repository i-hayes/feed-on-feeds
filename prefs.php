<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * prefs.php - display and change preferences
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

$FoF_Prefs = new FoF_Prefs("");
$prefs = $FoF_Prefs->instance();
$message = "";
if(fof_is_admin() && isset($_POST['adminprefs']))
{
	$prefs->set('purge', $_POST['purge']);
	$prefs->set('manualtimeout', $_POST['manualtimeout']);
	$prefs->set('autotimeout', $_POST['autotimeout']);
	$prefs->set('logging', $_POST['logging']);

	$prefs->save();
    	
	$message .= ' Saved admin prefs.';
    
    if($prefs->get('logging') && !@fopen("fof.log", 'a'))
    {
        $message .= ' Warning: could not write to log file!';
    }
}

if(isset($_POST['tagfeed']))
{
    $tags = $_POST['tag'];
    $feed_id = $_POST['feed_id'];
    $title = $_POST['title'];
    
    foreach(explode(" ", $tags) as $tag)
    {
        fof_tag_feed(fof_current_user(), $feed_id, $tag);
        $message .= " Tagged '$title' as $tag.";
    }
}

if(isset($_GET['untagfeed']))
{
    $feed_id = $_GET['untagfeed'];
    $tags = $_GET['tag'];
    $title = $_GET['title'];
	
    foreach(explode(" ", $tags) as $tag)
    {
        fof_untag_feed(fof_current_user(), $feed_id, $tag);
        $message .= " Dropped $tag from '$title'.";
    }
}

if(isset($_POST['prefs']))
{
	$prefs->set('favicons', (isset($_POST['favicons']) ? 1: 0));
	$prefs->set('keyboard', (isset($_POST['keyboard']) ? 1: 0));
	$prefs->set('new_page', (isset($_POST['new_page']) ? 1: 0));
	$prefs->set('colapse', (isset($_POST['colapse']) ? 1 : 0));
	$prefs->set('tzoffset', intval($_POST['tzoffset']));
	$prefs->set('tformat', $_POST['tformat']);
	$prefs->set('dsformat', $_POST['dsformat']);
	$prefs->set('dlformat', $_POST['dlformat']);
	$prefs->set('howmany', intval($_POST['howmany']));
	$prefs->set('order', $_POST['order']);
	$prefs->set('sharing', $_POST['sharing']);
	$prefs->set('sharedname', $_POST['sharedname']);
	$prefs->set('sharedurl', $_POST['sharedurl']);

	$prefs->save(fof_current_user());
    $message = "";
    if($_POST['password'] && ($_POST['password'] == $_POST['password2']))
    {
        fof_db_change_password($fof_user_name, $_POST['password']);
        setcookie ( "user_password_hash",  md5($_POST['password'] . $fof_user_name), time()+60*60*24*365*10 );
        $message = "Updated password.";
    }
    else if($_POST['password'] || $_POST['password2'])
    {
        $message = "Passwords do not match!";
    }
	
	$message .= ' Saved prefs.';
}

if(isset($_POST['plugins']))
{
    foreach(fof_get_plugin_prefs() as $plugin_pref)
    {
        $key = $plugin_pref[1];   
        $prefs->set($key, $_POST[$key]);
    }
    
    $plugins = array();
    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file=readdir($dirlist))
    {
        if(strpos($file, '.php'))
        {
           $plugins[] = substr($file, 0, -4);
        }
    }

    closedir();
        
    foreach($plugins as $plugin)
    {
        $prefs->set("plugin_" . $plugin, (isset($_POST[$plugin]) ? 0 : 1));
    }

	$prefs->save(fof_current_user());

	$message .= ' Saved plugin prefs.';
}

if(fof_is_admin() && isset($_POST['changepassword'])) 
{
	if($_POST['password'] != $_POST['password2'])
	{
		$message = "Passwords do not match!";
	}
	else
	{
		fof_db_change_password(fof_db_get_user_id($_POST['username']), $_POST['password']);
		$message = "Changed password for '".$_POST['username']."'.";
	}
}

if(fof_is_admin() && isset($_POST['adduser']) && $_POST['username'] && $_POST['password']) 
{
	fof_db_add_user($_POST['username'], $_POST['password']);
	$message = "User '".$_POST['username']."' added.";
}


if(fof_is_admin() && isset($_POST['deleteuser']) && $_POST['username'])
{
	fof_db_delete_user($_POST['username']);
	$message = "User '".$_POST['username']."' deleted.";
}


?>
<div id="items">

<?php if(strlen($message)) { ?>
<script>
Document.onLoad = setTimeout(hideMessage, 10000);

function hideMessage() 
{
	document.getElementById("message").style.display = "none"; 
}

</script>
<div id="message" class="stared"><?php echo $message ?></div>

<?php }

?>

<div class="main-heading"><h1>Feed on Feeds</h1></div>
<div class="heading"><h2>Preferences</h2></div>
<div class="container">
  <form method="post" action="<?php print (FOF_BASEDIR); ?>?prefs=1">
    <div class="prefs-row">
      <div class="column-one">Default display order:</div>
      <div class="column-two">
        <select name="order">
          <option value=desc>new to old</option>
          <option value=asc <?php if($prefs->get('order') == "asc") echo "selected";?>>old to new</option>
        </select>
      </div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Condensed display by default</div>
      <div class="column-two"><input class="input checkbox" id="new_page" type="checkbox" name="colapse" value="1"<?php if($prefs->get('colapse')) echo " checked=checked";?> /></div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Number of items in paged displays:</div>
      <div class="column-two"><input class="input small-number" id="howmany" type="string" name="howmany" value="<?php echo $prefs->get('howmany') ?>"></div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Open website in new window or tab</div>
      <div class="column-two"><input class="input checkbox" id="new_page" type="checkbox" name="new_page" value="1"<?php if($prefs->get('new_page')) echo " checked=checked";?> /></div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Display custom feed favicons?</div>
      <div class="column-two"><input class="input checkbox" id="favicons" type="checkbox" name="favicons" value="1" <?php if($prefs->get('favicons')) echo " checked=checked";?> /></div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Use keyboard shortcuts?</div>
      <div class="column-two"><input class="input checkbox" id="keyboard" type="checkbox" name="keyboard" value="1" <?php if($prefs->get('keyboard')) echo " checked=checked";?> /></div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Time offset in hours:</div>
      <div class="column-two"><input class="input small-number" id="tzoffset" type=string name=tzoffset value="<?php echo $prefs->get('tzoffset')?>" /> (UTC time: <?php echo gmdate($prefs->get('dsformat')." ".$prefs->get('tformat')) ?>, local time: <?php echo gmdate($prefs->get('dsformat')." ".$prefs->get('tformat'), time() + $prefs->get("tzoffset")*60*60) ?>)</div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Time format:</div>
      <div class="column-two">
        <select name="tformat">
          <option value="g:ia"<?php print (($prefs->get('tformat') == "g:ia" ? " selected=\"selected\"" : "")); ?>>12 hour</option>
          <option value="G:i"<?php print (($prefs->get('tformat') == "G:i" ? " selected=\"selected\"" : "")); ?>>24 hour</option>
		</select>
      </div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Date short format</div>
      <div class="column-two">
<?php 
$date_format = array("Y-n-d", "d-n-Y", "Y/n/d", "d/n/Y", "Y.n.d", "d.n.Y");
print (date_select("dsformat", $prefs->get('dsformat'), $date_format, $script = NULL));
print (" current: ".date($prefs->get('dsformat')));
?>
	  </div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Date Long format</div>
      <div class="column-two">
<?php 
$date_format = array("dS M Y", "dS F Y", "l, jS F, Y", "D, jS F, Y", "l, F j, Y", "D, F j, Y");
print (date_select("dlformat", $prefs->get('dlformat'), $date_format, $script = NULL));
print (" current: ".date($prefs->get('dlformat')));
?>
      </div>
    </div>
    <div class="prefs-row table">
      <div class="prefs-row table-row">
        <div class="column-one table-cell">New password:</div>
        <div class="column-two table-cell"><input type=password name=password> (leave blank to not change)</div>
      </div>
      <div class="prefs-row table-row">
        <div class="column-one table-cell">Repeat new password:</div>
        <div class="column-two table-cell"><input type=password name=password2></div>
      </div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Share</div>
      <div class="column-two"> 
        <select name="sharing">
          <option value=no>no</option>
          <option value=all <?php if($prefs->get('sharing') == "all") echo "selected";?>>all</option>
          <option value=tagged <?php if($prefs->get('sharing') == "tagged") echo "selected";?>>tagged as "shared"</option>
        </select>
        items.
<?php if($prefs->get('sharing') != "no") echo "        <small><i>(your shared page is <a href='./shared.php?user=".fof_current_user()."'>here</a>)</i></small>\n";?>
      </div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Name to be shown on shared page:</div>
      <div class="column-two"><input type="string" name="sharedname" value="<?php echo $prefs->get('sharedname')?>"></div>
    </div>
    <div class="prefs-row">
      <div class="column-one">URL to be linked on shared page:</div>
      <div class="column-two"><input type="string" name="sharedurl" value="<?php echo $prefs->get('sharedurl')?>"></div>
	</div>
    <div class="prefs-row">
      <div class="column-one"><input type="submit" name="prefs" value="Save Preferences"></div>
      <div class="column-two"></div>
    </div>
  </form>
</div>

<div class="heading"><h2>Plugin Preferences</h2></div>
<div class="container">
  <form method="post" action="<?php print (FOF_BASEDIR); ?>?prefs=1">
<?php
    $plugins = array();
    $dirlist = opendir(FOF_DIR . "/plugins");
    while($file=readdir($dirlist))
    {
    	fof_log("considering " . $file);
        if(strpos($file, '.php'))
        {
           $plugins[] = substr($file, 0, -4);
        }
    }

    closedir();

?>
    <div class="prefs-row">
      <div class="column-one">
<?php foreach($plugins as $plugin) { ?>
        <div><input type=checkbox name=<?php echo $plugin ?> <?php if(!$prefs->get("plugin_" . $plugin)) echo "checked=\"checked\""; ?>> Enable plugin <tt><?php echo $plugin?></tt>?</div>
<?php } ?>
      </div>
      <div class="column-two"> </div>
	</div>

<?php foreach(fof_get_plugin_prefs() as $plugin_pref) { $name = $plugin_pref[0]; $key = $plugin_pref[1]; $type = $plugin_pref[2]; ?>
<?php echo $name ?>: 

<?php if($type == "boolean") { ?>
    <div class="prefs-row">
      <div class="column-one">
        <input name="<?php echo $key ?>" type="checkbox" <?php if($prefs->get($key)) echo "checked" ?>>
      </div>
      <div class="column-two"> </div>
	</div>
<?php } else { ?>
    <div class="prefs-row">
      <div class="column-one">
        <input name="<?php echo $key ?>" value="<?php echo $prefs->get($key)?>">
      </div>
      <div class="column-two"> </div>
	</div>
<?php } } ?>
    <div class="prefs-row">
      <div class="column-one">
        <input type=submit name=plugins value="Save Plugin Preferences">
      </div>
      <div class="column-two"> </div>
	</div>
  </form>
</div>


<div class="heading"><h2>Feeds and Tags</h2></div>
<div class="container">
  <div class="prefs-row table Feeds-and-Tags">
 
<?php
foreach($feeds as $row)
{
//   $id = $row['feed_id'];
//   $url = $row['feed_url'];
//   $title = $row['feed_title'];
//   $link = $row['feed_link'];
//   $description = $row['feed_description'];
//   $age = $row['feed_age'];
//   $unread = $row['feed_unread'];
//   $starred = $row['feed_starred'];
//   $items = $row['feed_items'];
//   $agestr = $row['agestr'];
//   $agestrabbr = $row['agestrabbr'];
//   $lateststr = $row['lateststr'];
//   $lateststrabbr = $row['lateststrabbr'];   
	$tags = $row['tags'];
    print ("    <div class=\"prefs-row table-row".(++$t % 2 ? " odd-row" : "")."\">\n");

    if($row['feed_image'] && $prefs->get('favicons'))
	{
		$image = $row['feed_image'];
	}
	else
	{
		$image = 'image/feed-icon.png';
	}
	print "      <div class=\"column-one table-cell\">\n";
	print "        <a href=\"".$row['feed_url']."\" title=\"feed\"><img src='$image' width='16' height='16' border='0' /></a>\n";
	print "      </div>\n";
	print "      <div class=\"column-two table-cell\">\n";
	print "        <a href=\"".$row['feed_link']."\" title=\"home page\">".$row['feed_title']."</a>\n";
	print "      </div>\n";
	print "      <div class=\"column-three table-cell align-right\">\n";

	if($row['tags'])
	{
		foreach($row['tags'] as $tag)
		{
			print "        $tag <a href=\"".FOF_BASEDIR."?prefs=1&untagfeed=".$row['feed_id']."&tag=". urlencode($tag)."&title=".urlencode($row['feed_title'])."\">[x]</a>\n ";
		}
	}
	print "      </div>\n";
	print "      <div class=\"column-four table-cell\">\n";
	print "        <form method=\"post\" action=\"".FOF_BASEDIR."?prefs=1\">\n";
	print "          <input type=\"hidden\" name=\"title\" value=\"".htmlspecialchars($row['feed_title'])."\">\n";
	print "          <input type=\"hidden\" name=\"feed_id\" value=".$row['feed_id'].">\n";
	print "          <input type=\"string\" name=\"tag\"><input type=\"submit\" name=\"tagfeed\" value=\"Tag Feed\">\n";
	print "        </form>\n";
	print "        <div><small><i>(separate tags with spaces)</i></small></div>\n";
	print "      </div>\n";
	print ("    </div>\n");
}
?>
  </div>
</div>


<?php if(fof_is_admin()) { ?>

<div class="heading"><h2>Admin Options</h2></div>
<div class="container">
  <form method="post" action="<?php print (FOF_BASEDIR);?>?prefs=1">
    <div class="prefs-row">
      <div class="column-one">Enable logging? </div>
      <div class="column-two"><input type=checkbox name=logging <?php if($prefs->get('logging')) echo "checked" ?>></div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Purge read items after </div>
      <div class="column-two"><input class="input small-number" id="purge" type=string name=purge value="<?php echo $prefs->get('purge')?>"> days (leave blank to never purge)</div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Allow automatic feed updates every </div>
      <div class="column-two"><input class="input small-number" id="autotimeout" type=string name=autotimeout value="<?php echo $prefs->get('autotimeout')?>"> minutes</div>
    </div>
    <div class="prefs-row">
      <div class="column-one">Allow manual feed updates every </div>
      <div class="column-two"><input class="input small-number" id="manualtimeout" type=string name=manualtimeout value="<?php echo $prefs->get('manualtimeout')?>"> minutes</div>
    </div>
    <div class="prefs-row">
      <div class="column-one"><input type=submit name=adminprefs value="Save Options"></div>
      <div class="column-two"> </div>
    </div>
  </form>
</div>

<div class="heading"><h2>Admin Options - Add User</h2></div>
<div class="container">
  <form method="post" action="<?php print (FOF_BASEDIR);?>?prefs=1">
    <div class="prefs-row">
      <div class="column-one">Username: <input type=string name=username></div>
      <div class="column-two">
      Password: <input type=string name=password>
      <input type=submit name=adduser value="Add user"></div>
	</div>
  </form>
</div>

<?php
	$result = fof_db_query("select user_name from `".FOF_USER_TABLE."` where user_id > 1");
	$delete_options = "";
	while($row = fof_db_get_row($result))
	{
		$username = $row['user_name'];
		$delete_options .= "        <option value=\"$username\">$username</option>\n";
	}

    if(strlen($delete_options))
    {
?>

<div class="heading"><h2>Admin Options - Delete User</h2></div>
<div class="container">
  <form method="post" action="<?php print (FOF_BASEDIR);?>?prefs=1" onsubmit="return confirm('Delete User - Are you sure?')">
    <div class="prefs-row">
      <div class="column-one">
        <select name="username">
<?php echo $delete_options ?>
        </select>
      </div>
      <div class="column-two"><input type=submit name=deleteuser value="Delete user"></div>
    </div>
  </form>
</div>

<div class="heading"><h2>Admin Options - Change User's Password</h2></div>
<div class="container">
  <form method="post" action="<?php print (FOF_BASEDIR);?>?prefs=1" onsubmit="return confirm('Change Password - Are you sure?')">
    <div class="prefs-row table">
      <div class="prefs-row table-row">
        <div class="column-one table-cell">Select user:</div>
        <div class="column-two table-cell">
          <select name=username>
<?php echo $delete_options ?>
          </select>
        </div>
      </div>
      <div class="prefs-row table-row">
        <div class="column-one table-cell">New password:</div>
        <div class="column-two table-cell"><input type="password" name="password"></div>
      </div>
      <div class="prefs-row table-row">
        <div class="column-one table-cell">Repeat new password:</div>
        <div class="column-two table-cell"><input type="password" name="password2"></div>
      </div>
      <div class="prefs-row table-row">
        <div class="column-one table-cell"><input type="submit" name="changepassword" value="Change"></div>
        <div class="column-two table-cell"></div>
      </div>
    </div>
  </form>
</div>

<?php } ?>

<div class="heading"><h2>Admin Options - Uninstall Feed on Feeds</h2></div>
<div class="container">
  <form method="get" action="uninstall.php" onsubmit="return confirm('Really?  This will delete all the database tables!')">
    <div class="prefs-row">
      <div class="column-one">
        <center><input type="submit" name="uninstall" value="Uninstall Feed on Feeds" style="background-color: #ff9999"></center>
      </div>
      <div class="column-two"></div>
    </div>
  </form>
</div>

<?php }

function date_select($name, $prefs, $value, $script = NULL)
{
	$return = "        <select name=\"$name\">\n";
	foreach ($value AS $v)
	{
		$return .="          <option value=\"$v\"";
		if ($prefs == $v) $return .=" selected=\"selected\"";
		$return .= ">".date($v)."</option>\n";
	}
	$return .= "        </select>\n";
	return $return;
}
?>

