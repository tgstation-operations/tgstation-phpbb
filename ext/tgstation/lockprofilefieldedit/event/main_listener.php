<?php
/**
 *
 * Anti-Birthday Spam. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2021
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace tgstation\lockprofilefieldedit\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Anti-Birthday Spam Event listener.
 */
class main_listener implements EventSubscriberInterface {
	public static function getSubscribedEvents() {
		return [
			'core.ucp_profile_info_modify_sql_ary' => 'strip_cp_data',
			'core.acp_users_profile_modify_sql_ary' => 'strip_cp_data',
		];
	}
						
	public function strip_cp_data($event) {
		$remove = ['pf_byond_username', 'pf_github', 'pf_reddit'];

		$cp_array = $event["cp_data"];
		$cp_array = array_diff_key($cp_array, array_flip($remove));
		//print_r($cp_array);
		$event["cp_data"] = $cp_array;
	}
}
