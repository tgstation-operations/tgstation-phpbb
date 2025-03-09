<?php
define('FROM_MEDIAWIKI', true); //to hook into the phpbbSSO wiki extension
function api_error($error) {
	echo json_encode(array('status' => 'error', 'error' => $error));
	die();
}

function keytockey($key) {
	return strtolower(preg_replace('/[^a-zA-Z0-9@]/', '', $key));
}

if (empty($_GET['site_private_token']))
	api_error('No site_private_token found in $_GET array');

$site_private_token = $_GET['site_private_token'];

$site_private_token_decoded = base64_decode($site_private_token, TRUE);

if ($site_private_token_decoded === false)
	api_error('site_private_token not a valid base64 encoded string');

if (strlen($site_private_token_decoded) < 32)
	api_error('Decoded site_private_token too small. was: '.strlen($site_private_token_decoded).'bytes. Min required: 32bytes');
if (strlen($site_private_token_decoded) > 255)
	api_error('Decoded site_private_token too large. was: '.strlen($site_private_token_decoded).'bytes. Max allowed: 255bytes');


if (empty($_GET['session_private_token']))
	api_error('No session_private_token found in $_GET array');

$session_private_token = $_GET['session_private_token'];

$session_private_token_decoded = base64_decode($session_private_token, TRUE);

if ($session_private_token_decoded === false)
	api_error('session_private_token not a valid base64 encoded string');

if (strlen($session_private_token_decoded) != 64)
	api_error('Decoded session_private_token wrong size. was: '.strlen($session_private_token_decoded).'bytes. Expected 64bytes');



//stuff phpbb wants defined.
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
define('IN_PHPBB', true);
$phpEx = substr(strrchr(__FILE__, '.'), 1);

include_once($phpbb_root_path.'common.'.$phpEx); //we include the phpbb frame work
$request->enable_super_globals();
$sql = "
	SELECT 
		u.username AS phpbb_username,
		p.pf_byond_username AS byond_key,
		p.pf_github AS github_username
		FROM tg_oauth_sessions AS o LEFT JOIN
			".PROFILE_FIELDS_DATA_TABLE." AS p ON (o.phpbb_user_id = p.user_id)
		LEFT JOIN
			".USERS_TABLE." as u ON (o.phpbb_user_id = u.user_id)
		WHERE 
			o.session_private_token = FROM_BASE64('".$db->sql_escape(base64_encode($session_private_token_decoded))."')
		AND
			o.site_private_token = FROM_BASE64('".$db->sql_escape(base64_encode($site_private_token_decoded))."') 
		AND
			o.last_access > DATE_SUB(CURDATE(),INTERVAL 30 DAY)
		AND
			o.phpbb_password = u.user_password
		";
$result = $db->sql_query($sql) OR api_error('Unknown SQL error');
if (!($row = $db->sql_fetchrow($result))) {
	api_error('oauth session not found');
}
$phpbb_username = $row['phpbb_username'];
$byond_key = $row['byond_key'];
$github_username = $row['github_username'];
$byond_ckey = keytockey($byond_key);
$db->sql_freeresult($result);

if (!$phpbb_username) {
	api_error('Unknown oauth session');
}

$api_response = array();
$api_response['status'] = 'OK';
$api_response['phpbb_username'] = $phpbb_username;
$api_response['byond_key'] = $byond_key;
$api_response['byond_ckey'] = $byond_ckey;
$api_response['github_username'] = $github_username;
echo json_encode($api_response);
