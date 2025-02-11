<?php
/**
 *
 * Spam bot honeypot. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace tgstation\spambothoneypot\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Spam Bot Honeypot Event listener.
 */
class main_listener implements EventSubscriberInterface
{	
	private $auth;
	private $db;
	private $user;

	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\factory $db, \phpbb\user $user) {
		$this->auth = $auth;
		$this->db = $db;
		$this->user = $user;
	}
	public static function getSubscribedEvents()
	{
		return [
			'core.modify_posting_auth' => 'modify_posting_auth',
		];
	}

	public function modify_posting_auth($event) {
		global $phpbb_root_path, $phpEx;
		$forum_id = $event["forum_id"];
		$submit = $event["submit"];
		$mode = $event["mode"];
		$error = $event["error"];

		
		if ($submit && $forum_id == 77 && ($mode == 'post' || $mode == 'reply') && $this->user->data['is_registered'] && !$this->auth->acl_get('m_delete', $forum_id)) {
			
			//deactivate account, ban ip.
			include_once($phpbb_root_path . 'includes/functions_user.' . $phpEx);
			$user_id = (int)$this->user->data['user_id'];
			user_active_flip('deactivate', $user_id);
			user_ban('ip', $this->user->ip, 525600, null, false, '[AUTOMATIC] Posting in The board at the end of the universe (spam bot honeypot)', 'Spambot.');
			
			
			//delete all posts in queue
			$sql = 'SELECT post_id
					FROM ' . POSTS_TABLE . '
					WHERE poster_id = ' . $user_id . '
						AND ' . $this->db->sql_in_set('post_visibility', array(ITEM_UNAPPROVED, ITEM_REAPPROVE));
			$result = $this->db->sql_query($sql);

			if ($row = $this->db->sql_fetchrow($result)) {
				if (!function_exists('delete_posts')) {
					include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
				}
				
				$post_ids = array();
				
				do {
					$post_ids[] = (int) $row['post_id'];
				} while ($row = $this->db->sql_fetchrow($result));

				$this->db->sql_freeresult($result);

				delete_posts('post_id', array_keys($post_ids));
			}
			
			
			//delete all pms in their outbox
			
			$sql = 'SELECT msg_id
				FROM ' . PRIVMSGS_TO_TABLE . '
				WHERE author_id = ' . $user_id . '
					AND folder_id = ' . PRIVMSGS_OUTBOX;
			$result = $this->db->sql_query($sql);

			if ($row = $this->db->sql_fetchrow($result)) {
				if (!function_exists('delete_pm')) {
					include($phpbb_root_path . 'includes/functions_privmsgs.' . $phpEx);
				}
				
				$msg_ids = array();
				
				do {
					$msg_ids[] = (int) $row['msg_id'];
				} while ($row = $this->db->sql_fetchrow($result));

				$this->db->sql_freeresult($result);

				delete_pm($user_id, $msg_ids, PRIVMSGS_OUTBOX);
			}
			
			$error = 'You didn\'t say the magic word.';
			die('You didn\'t say the magic word.');
		}
		
		$event["error"] = $error;
		
	}
}
