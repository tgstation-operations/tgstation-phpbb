<?php

namespace bakasura\xforwardedfor\acp;

class x_forwarded_for_info
{
    public function module()
    {
        return [
            'filename' => '\bakasura\xforwardedfor\acp\x_forwarded_for_module',
            'title' => 'XFF_ACP_TITLE',
            'modes' => [
                'settings' => [
                    'title' => 'XFF_ACP',
                    'auth' => 'ext_bakasura/xforwardedfor && acl_a_board',
                    'cat' => ['XFF_ACP_TITLE'],
                ],
            ],
        ];
    }
}

