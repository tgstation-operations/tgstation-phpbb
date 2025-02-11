<?php
/**
 *
 * Anti-Birthday Spam. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2021
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace mothblocks\antibirthdayspam\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Anti-Birthday Spam Event listener.
 */
class main_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			'core.index_modify_birthdays_sql' => 'index_modify_birthdays_sql',
		];
	}

	public function index_modify_birthdays_sql($event) {
		$sql_array = $event["sql_ary"];
		$sql_array["WHERE"] .= " AND u.user_posts > 0";
		$event["sql_ary"] = $sql_array;
	}
}
