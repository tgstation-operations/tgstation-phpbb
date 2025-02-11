<?php
/**
 *
 * Display Last Post extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2013 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace aurelienazerty\displaylastpost\migrations;

class release_0_1_0 extends \phpbb\db\migration\migration 
{
	
	public function effectively_installed() 
	{
		return !empty($this->config['display_last_post_show']);
	}

	public function update_data() 
	{
		return array(
			array(
				'config.add', array('display_last_post_show', 1)
			)
		);
	}
	
	static public function depends_on()
	{
		 return array(
			'\phpbb\db\migration\data\v32x\v321',
		);
	}
}
