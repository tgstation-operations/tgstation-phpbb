<?php
namespace tgstation\bypasspasswordpermission\migrations;

class install_permissions extends \phpbb\db\migration\migration {
	public function update_data() {
		return array(
			array('permission.add', array('f_bypass_forum_password', false)),
		);
	}
}
