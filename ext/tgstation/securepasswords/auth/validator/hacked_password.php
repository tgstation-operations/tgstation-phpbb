<?php

namespace tgstation\securepasswords\auth\validator;

/**
* HaveIBeenPwned.com Hacked Passwords k-Anonymity checker.
*/
class hacked_password implements validator_interface
{
	/**
	 * {@inheritdoc}
	 */
	public function is_invalid($password) {
		if (!$password) 
			return "Empty Password";
		
		$hash = sha1($password);

		$range = substr($hash, 0, 5);
		$rest = substr($hash, 5);
		$scontext = array('http' => array(
			'ignore_errors' => true,
			'user_agent'	=> 'tgstation13.org-Register-Login-PWN-Check/v2.0 email pwnpasswordapi@tgstation13.org with inqueries'
		));

		$api_result = file_get_contents('https://api.pwnedpasswords.com/range/'.$range, false, stream_context_create($scontext));
		$result_array = explode("\n", $api_result);
		foreach ($result_array as $result_string) {
			$result_pair = explode(':', $result_string);
			if ($result_pair[0] == strtoupper($rest)) {
				return '<B>HACKED PASSWORD DETECTED</B> - The password of "'.htmlspecialchars($password).'" has been found '.$result_pair[1].' times in hacked and leaked databases.';
			}
		}
		return false;

	}
}