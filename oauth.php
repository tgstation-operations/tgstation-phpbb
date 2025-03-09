<?php
//die();
function oauth_error($error) {
	trigger_error('OAUTH ERROR: '.$error);
	die();
}
function keytockey($key) {
	return strtolower(preg_replace('/[^a-zA-Z0-9@]/', '', $key));
}
//define('FROM_MEDIAWIKI', true); //to hook into the phpbbSSO wiki extension

//stuff phpbb wants defined.
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
define('IN_PHPBB', true);
$phpEx = substr(strrchr(__FILE__, '.'), 1);

include_once($phpbb_root_path.'common.'.$phpEx); //we include the phpbb frame work
$request->enable_super_globals();
$user->session_begin(); //now we let phpbb do all the fancy work of figuring out who the fuck this are.
$auth->acl($user->data);
$user->setup('');
$userid = (int)$user->data['user_id'];
$usertype = $user->data['user_type'];
if($userid <= 1 || $usertype == 1 || $usertype == 2) {
	header("location: ucp.php?mode=login&redirect=".urlencode("oauth.php?".http_build_query($_GET)));
	die();
}
if (empty($_GET['session_public_token']))
	oauth_error('No session_public_token found in $_GET array');

$session_public_token = $_GET['session_public_token'];

$session_public_token_decoded = base64_decode($session_public_token, TRUE);

if ($session_public_token_decoded === false)
	oauth_error('session_public_token not a valid base64 encoded string');

if (strlen($session_public_token_decoded) != 64)
	oauth_error('Decoded session_public_token wrong size. was: '.strlen($session_public_token_decoded).'bytes. Expected 64bytes');

$sql = "
	SELECT 
		u.username AS phpbb_username,
		p.pf_byond_username AS byond_key,
		o.return_uri,
		p.pf_github AS github_username
		FROM tg_oauth_sessions AS o LEFT JOIN
			".PROFILE_FIELDS_DATA_TABLE." AS p ON (".$userid." = p.user_id)
		LEFT JOIN
			".USERS_TABLE." as u ON (".$userid." = u.user_id)
		WHERE 
			o.session_public_token = FROM_BASE64('".$db->sql_escape(base64_encode($session_public_token_decoded))."')
		AND
			o.last_access > DATE_SUB(CURDATE(),INTERVAL 30 MINUTE)
		";
$result = $db->sql_query($sql) OR oauth_error('Unknown SQL error');
if (!($row = $db->sql_fetchrow($result))) {
	oauth_error('oauth session not found');
}
$phpbb_username = $row['phpbb_username'];
$return_uri = $row['return_uri'];
$byond_key = $row['byond_key'];
$github_username = $row['github_username'];
$byond_ckey = keytockey($byond_key);
$db->sql_freeresult($result);


if (!$return_uri) {
	oauth_error('Unknown oauth session');
}

if (!confirm_box(true)) {
	$host = parse_url($return_uri, PHP_URL_HOST);
	$api_response = array();
	$api_response['status'] = 'OK';
	$api_response['phpbb_username'] = $phpbb_username;
	$api_response['byond_key'] = $byond_key;
	$api_response['byond_ckey'] = $byond_ckey;
	$api_response['github_username'] = $github_username;
	$data = json_encode($api_response);
	$preview = <<<CDATA
	<div style="margin:20px; margin-top:5px"><div class="quotetitle"><b>Preview data that will be sent</b> <input type="button" value="Show" style="width:45px;font-size:10px;margin:0px;padding:0px;" onclick="if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = '';        this.innerText = ''; this.value = 'Hide'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerText = ''; this.value = 'Show'; }" /></div><div class="quotecontent"><div style="display: none;">$data</div></div></div>
CDATA;
	confirm_box(false, $host.' would like to know your forum username and linked byond account username (if any). <br>Grant access?<br>'.$preview, build_hidden_fields(array('session_public_token' => $session_public_token)));
	die();
}

$sql = "SELECT `user_password` FROM `".USERS_TABLE."` WHERE user_id = ".$userid;
$result = $db->sql_query($sql);
$phpbb_password = $db->sql_fetchfield('user_password');
$db->sql_freeresult($result);

if (!$phpbb_password) {
	oauth_error('Unknown error linking user account to oauth session');
}

$sql = "UPDATE tg_oauth_sessions SET phpbb_user_id = ".$userid.", phpbb_password = '".$db->sql_escape($phpbb_password)."' WHERE session_public_token = FROM_BASE64('".$db->sql_escape(base64_encode($session_public_token_decoded))."')";
$result = $db->sql_query($sql) OR oauth_error("Unknown SQL error linking user account to oauth session");
$db->sql_freeresult($result);

header("location: ".$return_uri);
die();


