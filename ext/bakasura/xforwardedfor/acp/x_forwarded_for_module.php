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

        $this->tpl_name = 'acp_x_forwarded_for';
        $this->page_title = $language->lang('XFF_ACP_TITLE');

        add_form_key('xff_settings');

        if ($request->is_set_post('submit')) {
            if (!check_form_key('xff_settings')) {
                trigger_error('FORM_INVALID');
            }

            $config_text->set('xff_trusted_ips', $request->variable('xff_trusted_ips', ''));
            trigger_error($language->lang('XFF_ACP_SETTING_SAVED') . adm_back_link($this->u_action));
        }

        $template->assign_vars([
            'XFF_TRUSTED_IPS' => $config_text->get('xff_trusted_ips'),
            'U_ACTION' => $this->u_action,
        ]);
    }
}