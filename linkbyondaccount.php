<?php


define('FROM_MEDIAWIKI', true); //to hook into the phpbbSSO wiki extension

//stuff phpbb wants defined.
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
define('IN_PHPBB', true);
$phpEx = substr(strrchr(__FILE__, '.'), 1);

include_once($phpbb_root_path . 'common.' . $phpEx); //we include the phpbb frame work

$user->session_begin(); //now we let phpbb do all the fancy work of figuring out who the fuck this are.
$userid = (int)$user->data['user_id'];
$usertype = $user->data['user_type'];

if ($userid <= 1 || $usertype == 1 || $usertype == 2) {
    header("location: ucp.php?mode=login&redirect=" . urlencode("linkbyondaccount.php?" . $_SERVER["QUERY_STRING"]));
    //print_r($user);
    die();
}

$token = $request->variable('token', '');
if (strlen($token) == 128 && preg_match('/^[a-f0-9]+$/', $token)) {
    $sql = "SELECT `key` FROM `tg_byond_oauth_tokens` WHERE token = '" . $db->sql_escape($token) . "' AND timestamp > DATE_SUB(CURDATE(),INTERVAL 30 MINUTE)";
    $result = $db->sql_query($sql);
    $key = $db->sql_fetchfield('key');
    $db->sql_freeresult($result);


    if (!$key) {
        print("Invalid token or unknown error linking byond account<br><a href='linkbyondaccount.php?redirect=" . htmlspecialchars(urlencode($redirect)) . "'>Retry?</a>");
        die();
    }

    $sql = "DELETE FROM `tg_byond_oauth_tokens` WHERE token = '" . $db->sql_escape($token) . "' OR timestamp < DATE_SUB(CURDATE(),INTERVAL 5 MINUTE)";
    $db->sql_freeresult($db->sql_query($sql));

    $bannedusernames = array();

    $sql = "SELECT u.username AS username FROM `" . BANLIST_TABLE . "` AS b LEFT JOIN `" . PROFILE_FIELDS_DATA_TABLE . "` AS f ON (b.ban_userid = f.user_id) LEFT JOIN `phpbb_users` AS u on (u.user_id = b.ban_userid) WHERE b.ban_userid > 0 AND f.pf_byond_username IS NOT NULL AND ban_exclude <= 0 AND (ban_end = 0 OR ban_end > UNIX_TIMESTAMP()) AND f.pf_byond_username = '" . $db->sql_escape($key) . "'";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result))
        $bannedusernames[] = $row['username'];

    if (count($bannedusernames) > 0) {
        print("You can not link this byond account while it is banned on another forum account.<br>");
        print("The following forum accounts are registered to this byond account and forum banned:<br>");
        foreach ($bannedusernames as $bannedusername)
            print($bannedusername . "<br>");
        die();
    }

    $sql = "INSERT INTO " . PROFILE_FIELDS_DATA_TABLE . " (user_id,pf_byond_username) VALUES (" . $userid . ", '" . $db->sql_escape($key) . "') ON DUPLICATE KEY UPDATE pf_byond_username='" . $db->sql_escape($key) . "'";
    $db->sql_freeresult($db->sql_query($sql));

    $sql = "INSERT INTO " . USER_GROUP_TABLE . " (group_id,user_id,user_pending) VALUES (11, " . $userid . ", 0) ON DUPLICATE KEY UPDATE user_pending=0";
    $db->sql_freeresult($db->sql_query($sql));

    $auth->acl_clear_prefetch($userid);

    $redirect = "memberlist.php?mode=viewprofile&u=" . $userid;
    header("location: " . $redirect);
    die();
}
print(<<<EOD
<h1>Validate/Link Byond Account</h1>
<h2>Game Server Verb <font color="green">(Preferred)</font></h2>
	Simply connect to any of the normal /tg/ game servers and press the "Link forum account" button in the ooc tab (or type link-forum-account in the input bar), then login to your forum account.
	<br>
	You will know this worked correctly if it opens a new window with your user profile page on the forums, with your byond info displayed.
	<br>
    <br>
	See <a href="https://tgstation13.org" target="_blank">tgstation13.org</a> for a list of our available servers.

EOD
);
die();
