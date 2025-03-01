<?php
/**
 *
 * @package phpBB Extension - Add User
 * @author RMcGirr83  (Rich McGirr) rmcgirr83@rmcgirr83.org
 * @copyright (c) 2015 phpbbmodders.net
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\adduser\migrations\v104;

class version_104 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\phpbbmodders\adduser\migrations\v103\version_103');
	}

	public function update_data()
	{
		return array(
			array('config.remove', array('adduser_version')),
		);
	}
}
