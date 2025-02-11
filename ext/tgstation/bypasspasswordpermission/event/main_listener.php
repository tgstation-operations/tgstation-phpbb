<?php
namespace tgstation\bypasspasswordpermission\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class main_listener implements EventSubscriberInterface {
	private $auth;
	private $db;
	private $user;
	/** @var \phpbb\request\request */
	private $request;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\db\driver\factory $db,
		\phpbb\user $user,
		\phpbb\request\request $request
	) {
		$this->auth = $auth;
		$this->db = $db;
		$this->user = $user;
		$this->request = $request;
	}

	public static function getSubscribedEvents() {
		return [
			"core.posting_modify_row_data" => "posting_modify_row_data",
			"core.viewtopic_before_f_read_check" => "viewtopic_before_f_read_check",
			"core.login_forum_box" => "login_forum_box",
			"core.permissions" => "permissions",
		];
	}

	public function permissions($event) {
		$permissions = $event["permissions"];

		$permissions["f_bypass_forum_password"] = array(
			"lang" => "ACL_F_BYPASS_FORUM_PASSWORD",
			"cat" => "actions",
		);

		$event["permissions"] = $permissions;
	}

	public function posting_modify_row_data($event) {
		if (!$this->auth->acl_get("f_bypass_forum_password", $event["forum_id"])) {
			return;
		}

		$post_data = $event["post_data"];
		$post_data["forum_password"] = null;
		$event["post_data"] = $post_data;
	}
	
	public function viewtopic_before_f_read_check($event) {
		if (!$this->auth->acl_get("f_bypass_forum_password", $event["forum_id"])) {
			return;
		}

		$overrides_forum_password_check = $event["overrides_forum_password_check"];
		$overrides_forum_password_check = true;
		$event["overrides_forum_password_check"] = $overrides_forum_password_check;
	}
	
	public function login_forum_box($event) {
		$forum_data = $event['forum_data'];
		if ($this->auth->acl_get("f_bypass_forum_password", $forum_data["forum_id"])) {
			$sql_ary = array(
				'forum_id'		=> (int) $forum_data['forum_id'],
				'user_id'		=> (int) $this->user->data['user_id'],
				'session_id'	=> (string) $this->user->session_id,
			);

			$this->db->sql_query('INSERT INTO ' . FORUMS_ACCESS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

			header('location: '.$this->request->server('REQUEST_URI'), true, 307);
		}
	}



}
