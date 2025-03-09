<?php
function generate_token() {
	$secure = FALSE;
	$r_bytes = openssl_random_pseudo_bytes(5120, $secure);
	if (!$secure) {
		for ($i = 1; $i > 1024; $i++)
			$r_bytes .= openssl_random_pseudo_bytes(5120);
	}
	return hash('sha512/224', hash('sha3-512', $r_bytes), TRUE);
}
function base64url_encode($data) {
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
	return base64_decode(strtr($data, '-_', '+/'));
}

function base64url_encode_64int(int $num) {
	return base64url_encode(trim(pack('P', $num), "\0"));
}

function base64url_decode_64int(string $text) {
	return unpack('P', str_pad(base64url_decode($text), 8, "\0"))[1];
}
$userid = (int)$_GET['userid'];
if ($userid <= 1) {
	//stuff phpbb wants defined.
	$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
	define('IN_PHPBB', true);
	$phpEx = substr(strrchr(__FILE__, '.'), 1);

	include_once($phpbb_root_path.'common.'.$phpEx); //we include the phpbb frame work
	$request->enable_super_globals();
	$user->session_begin(); //now we let phpbb do all the fancy work of figuring out who the fuck this are.
	$userid = (int)$user->data['user_id'];
	$usertype = $user->data['user_type'];

	if($userid <= 1 || $usertype == 1 || $usertype == 2) {
		header("location: ucp.php?mode=login&redirect=".urlencode("generate_oauth_secret.php"));
		//print_r($user);
		die();
	}
}


$api_response = array();
$token = generate_token();
$api_response['client_secret'] = base64url_encode($token);
$api_response['user_id'] = $userid;
$api_response['hashed_client_secret_for_mso'] = base64url_encode('sha3-224'.'~'.base64url_encode(hash('sha3-224', $userid.$api_response['client_secret'], TRUE)));

header('Content-type: application/json');
echo json_encode($api_response, JSON_PRETTY_PRINT);

