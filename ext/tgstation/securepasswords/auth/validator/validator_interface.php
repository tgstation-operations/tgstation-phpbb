<?php

namespace tgstation\securepasswords\auth\validator;

/**
* The interface authentication validator classes have to implement.
*/
interface validator_interface
{
	/**
	 * Validates passwords.
	 *
	 * @param	string	$password	The password to be provided.
	 * @return	string|false	False if the password is not invalid, a string error message if it is invalid.
	 */
	public function is_invalid($password);
}