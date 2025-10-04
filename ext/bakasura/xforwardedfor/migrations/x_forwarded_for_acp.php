<?php

namespace bakasura\xforwardedfor\migrations;

class x_forwarded_for_acp extends \phpbb\db\migration\container_aware_migration
{
    /**
     * If our config variable already exists in the db
     * skip this migration.
     */
    public function effectively_installed()
    {
        $config_text = $this->container->get('config_text');

        return $config_text->get('xff_trusted_ips') !== null;
    }

    /**
     * This migration depends on phpBB's v314 migration
     * already being installed.
     */
    static public function depends_on()
    {
        return ['\phpbb\db\migration\data\v31x\v314'];
    }

    public function update_data()
    {
        return [

            // Add the config variable we want to be able to set
            ['config_text.add', [
                'xff_trusted_ips',
                '173.245.48.0/20, 103.21.244.0/22, 103.22.200.0/22, 103.31.4.0/22, 141.101.64.0/18, 108.162.192.0/18, 190.93.240.0/20, 188.114.96.0/20, 197.234.240.0/22, 198.41.128.0/17, 162.158.0.0/15, 104.16.0.0/13, 104.24.0.0/14, 172.64.0.0/13, 131.0.72.0/22, 2400:cb00::/32, 2606:4700::/32, 2803:f800::/32, 2405:b500::/32, 2405:8100::/32, 2a06:98c0::/29, 2c0f:f248::/32'
            ]],

            // Add a parent module (XFF_ACP_TITLE) to the Extensions tab (ACP_CAT_DOT_MODS)
            ['module.add', [
                'acp',
                'ACP_CAT_DOT_MODS',
                'XFF_ACP_TITLE'
            ]],

            // Add our main_module to the parent module (XFF_ACP_TITLE)
            ['module.add', [
                'acp',
                'XFF_ACP_TITLE',
                [
                    'module_basename' => '\bakasura\xforwardedfor\acp\x_forwarded_for_module',
                    'modes' => ['settings'],
                ],
            ]],
        ];
    }
}