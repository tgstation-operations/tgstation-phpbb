<?php
if (!defined('IN_PHPBB')) {
	exit;
}

if (empty($lang) || !is_array($lang)) {
	$lang = [];
}

$lang = array_merge($lang, [
	'ACL_F_LIMIT_TO_PLAYERS' => 'Limit to players',
	'ACL_U_BYPASS_PLAYER_LIMITS' => 'Bypass player limits',
]);
