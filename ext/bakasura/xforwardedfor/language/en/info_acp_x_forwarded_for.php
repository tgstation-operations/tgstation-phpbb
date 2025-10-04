<?php

if (!defined('IN_PHPBB')) {
    exit;
}

if (empty($lang) || !is_array($lang)) {
    $lang = [];
}

$lang = array_merge($lang, [
    'XFF_ACP_TITLE' => 'X-Forwarded-For',
    'XFF_ACP' => 'Settings',
    'XFF_ACP_TRUSTED_IPS' => 'Comma separated list of trusted IPs for use the header X-Forwarded-For',
    'XFF_ACP_SETTING_SAVED' => 'Settings have been saved successfully!',
]);