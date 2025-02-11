<?php
namespace mothblocks\playerlounge\migrations;

class install_permissions extends \phpbb\db\migration\migration {
	public function update_data() {
		return array(
			array('permission.add', array('f_limit_to_players')),
			array('permission.add', array('u_bypass_player_limits')),
		);
	}
}
