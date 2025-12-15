<?php

namespace bakasura\xforwardedfor\acp;

class x_forwarded_for_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    public function main($id, $mode)
    {
        global $language, $template, $request, $phpbb_container;

        /* @var $config_text \phpbb\config\db_text */
        $config_text = $phpbb_container->get('config_text');

        /* @var $config \phpbb\config\config */
        $config = $phpbb_container->get('config');

        /* @var $cloudflare_fetcher \bakasura\xforwardedfor\service\cloudflare_ip_fetcher */
        $cloudflare_fetcher = $phpbb_container->get('bakasura.xforwardedfor.cloudflare_ip_fetcher');

        $this->tpl_name = 'acp_x_forwarded_for';
        $this->page_title = $language->lang('XFF_ACP_TITLE');

        add_form_key('xff_settings');

        $fetch_message = '';
        $fetch_success = null;

        if ($request->is_set_post('submit')) {
            if (!check_form_key('xff_settings')) {
                trigger_error('FORM_INVALID');
            }

            $auto_fetch = $request->variable('xff_auto_fetch_cloudflare', 0);
            $manual_ips = $request->variable('xff_trusted_ips', '');

            // Save auto-fetch setting
            $config->set('xff_auto_fetch_cloudflare', $auto_fetch);

            // If auto-fetch is enabled, fetch IPs now
            if ($auto_fetch) {
                $result = $cloudflare_fetcher->fetch_cloudflare_ips();
                $fetch_success = $result['success'];
                $fetch_message = $result['message'];
            } else {
                // Manual IPs - save them
                $config_text->set('xff_trusted_ips', $manual_ips);
            }

            trigger_error($language->lang('XFF_ACP_SETTING_SAVED') . ($fetch_message ? '<br>' . $fetch_message : '') . adm_back_link($this->u_action));
        }

        // Check if auto-fetch is enabled and IPs need refresh
        $auto_fetch_enabled = $config->offsetGet('xff_auto_fetch_cloudflare');
        if ($auto_fetch_enabled && $cloudflare_fetcher->should_refresh()) {
            $result = $cloudflare_fetcher->fetch_cloudflare_ips();
            if ($result['success']) {
                $fetch_message = $language->lang('XFF_AUTO_FETCH_REFRESHED');
            }
        }

        $last_fetch_time = $config->offsetGet('xff_last_fetch_time');

        $template->assign_vars([
            'XFF_TRUSTED_IPS' => $config_text->get('xff_trusted_ips'),
            'XFF_AUTO_FETCH_CLOUDFLARE' => $auto_fetch_enabled,
            'XFF_LAST_FETCH_TIME' => $last_fetch_time ? $language->lang('XFF_LAST_FETCH_TIME', date('Y-m-d H:i:s', $last_fetch_time)) : '',
            'XFF_FETCH_MESSAGE' => $fetch_message,
            'U_ACTION' => $this->u_action,
        ]);
    }
}