<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace tgstation\securepasswords\auth\provider;

//use phpbb\captcha\factory;
//use phpbb\captcha\plugins\captcha_abstract;
//use phpbb\config\config;
//use phpbb\db\driver\driver_interface;
//use phpbb\passwords\manager;
//use phpbb\request\request_interface;
//use phpbb\user;
use tgstation\securepasswords\auth\validator\common_password as common_password_validator;
use tgstation\securepasswords\auth\validator\hacked_password as hacked_password_validator;

/**
 * Secure Database authentication provider for phpBB3
 * This is for Secure:tm: authentication
 */
class securedb extends \phpbb\auth\provider\db
{
	/**
	 * {@inheritdoc}
	 */
	public function login($username, $password)
	{
		$argusername = $username;
		$argpassword = $password;
		// Auth plugins get the password untrimmed.
		// For compatibility we trim() here.
		$password = trim($password);

		// do not allow empty password
		if (!$password)
		{
			return array(
				'status'	=> LOGIN_ERROR_PASSWORD,
				'error_msg'	=> 'NO_PASSWORD_SUPPLIED',
				'user_row'	=> array('user_id' => ANONYMOUS),
			);
		}

		if (!$username)
		{
			return array(
				'status'	=> LOGIN_ERROR_USERNAME,
				'error_msg'	=> 'LOGIN_ERROR_USERNAME',
				'user_row'	=> array('user_id' => ANONYMOUS),
			);
		}
		$errors = array();
		if (($msg = common_password_validator::is_invalid($password)) !== false) {
			$errors[] = $msg;
		}
	
		if (($msg = hacked_password_validator::is_invalid($password)) !== false) {
			$errors[] = $msg;
		}	
		if (count($errors)) {
			return array(
				'status'	=> LOGIN_BREAK,
				'error_msg'	=> '<B>Password FAILED Validation</B><br>The password you have supplied has been rejected as being an insecure password. <b>If</b> this is indeed your actual password (this has not been checked), in order to gain access to your account you will need to reset your password using the I forgot my password link on the login page. The error provided by the password checking system is below:<br>'. implode('<br>', $errors),
				'user_row'	=> array('user_id' => ANONYMOUS),
			);
		}
		// redirect to parent...
		return parent::login($argusername, $argpassword);
	}
}

