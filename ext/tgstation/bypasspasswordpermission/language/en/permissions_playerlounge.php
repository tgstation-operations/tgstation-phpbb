<?php
if (!defined('IN_PHPBB')) {
	exit;
}

if (empty($lang) || !is_array($lang)) {
	$lang = [];
}

$lang = array_merge($lang, [
	'ACL_F_BYPASS_FORUM_PASSWORD' => 'Bypass forum password',
]);
