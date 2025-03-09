<?php
function generate_token() {
	$secure = FALSE;
	$r_bytes = openssl_random_pseudo_bytes(5120, $secure);
	if (!$secure) {
		for ($i = 1; $i > 1024; $i++)
			$r_bytes .= openssl_random_pseudo_bytes(5120);
	}
	return hash('sha512', $r_bytes, TRUE);
}
function api_error($error) {
	echo json_encode(array('status' => 'error', 'error' => $error));
	die();
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

if (empty($_GET['return_uri']))
	api_error('No return_uri found in $_GET array');

$return_uri = $_GET['return_uri'];

$host = parse_url($return_uri, PHP_URL_HOST);

if (!$host) 
	api_error('Bad return_uri (could not extract host portion)');

if (preg_match('/tgstation\\./', $host))
	api_error('Bad return_uri (can be confused with (or is) /tg/station official domains)');



define('FROM_MEDIAWIKI', true); //to hook into the phpbbSSO wiki extension

//stuff phpbb wants defined.
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
define('IN_PHPBB', true);
$phpEx = substr(strrchr(__FILE__, '.'), 1);

include_once($phpbb_root_path.'common.'.$phpEx); //we include the phpbb frame work
$request->enable_super_globals();
$private_session_key = generate_token();
$public_session_key = generate_token();

$sql = "INSERT INTO tg_oauth_sessions (site_private_token, session_public_token, session_private_token, return_uri) VALUES (
	FROM_BASE64('".$db->sql_escape(base64_encode($site_private_token_decoded))."'),
	FROM_BASE64('".$db->sql_escape(base64_encode($public_session_key))."'),
	FROM_BASE64('".$db->sql_escape(base64_encode($private_session_key))."'),
	'".$db->sql_escape($return_uri)."'
);";
$result = $db->sql_query($sql) OR api_error("Unknown SQL error");
$db->sql_freeresult($result);

$api_response = array();
$api_response['status'] = 'OK';
$api_response['session_private_token'] = base64_encode($private_session_key);
$api_response['session_public_token'] = base64_encode($public_session_key);
echo json_encode($api_response);






	