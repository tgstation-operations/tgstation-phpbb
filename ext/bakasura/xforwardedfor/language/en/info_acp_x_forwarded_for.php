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
    'XFF_AUTO_FETCH_CLOUDFLARE' => 'Automatically fetch Cloudflare IP ranges',
    'XFF_AUTO_FETCH_CLOUDFLARE_EXPLAIN' => 'When enabled, Cloudflare IP ranges will be automatically fetched from their public endpoints and refreshed daily. Manual IP list will be disabled.',
    'XFF_LAST_FETCH_TIME' => 'Last fetch: %s',
    'XFF_AUTO_FETCH_REFRESHED' => 'Cloudflare IPs automatically refreshed.',
]);