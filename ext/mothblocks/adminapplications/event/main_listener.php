<?php
namespace mothblocks\adminapplications\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

const ADMIN_FACING_BOARD = 81;
const PLAYER_FACING_BOARD = 82;

class main_listener implements EventSubscriberInterface {
	private $auth;
	private $db;
	private $user;

	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\factory $db, \phpbb\user $user) {
		$this->auth = $auth;
		$this->db = $db;
		$this->user = $user;
	}

	public static function getSubscribedEvents() {
		return [
			'core.display_forums_modify_row' => 'display_forums_modify_row',
			'core.modify_submit_post_data' => 'modify_submit_post_data',
			'core.posting_modify_row_data' => 'posting_modify_row_data',
			'core.viewforum_get_topic_ids_data' => 'viewforum_get_topic_ids_data',
			'core.viewtopic_before_f_read_check' => 'viewtopic_before_f_read_check',
		];
	}

	function is_our_post($topic_id) {
		$sql = "SELECT topic_poster FROM " . TOPICS_TABLE . " WHERE topic_id = $topic_id";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return !is_null($row) && $row["topic_poster"] == $this->user->data["user_id"];
	}

	// Make it look like the board is populated with their post.
	public function display_forums_modify_row($event) {
		$row = $event["row"];
		$user_id = $this->user->data["user_id"];

		if (!$user_id || $row["forum_id"] != PLAYER_FACING_BOARD || $this->auth->acl_get("f_read", ADMIN_FACING_BOARD)) {
			return;
		}

		// Try to find a post made by us
		$sql = "SELECT topic_last_post_id, topic_last_poster_id, topic_last_poster_name, topic_last_poster_colour, topic_last_post_subject, topic_last_post_time"
			. " FROM " . TOPICS_TABLE
			. " WHERE forum_id = " . ADMIN_FACING_BOARD
			. " AND topic_poster = " . $this->user->data["user_id"]
			. " ORDER BY topic_last_post_id DESC";
		$result = $this->db->sql_query($sql);
		$most_recent_post_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$most_recent_post_row) {
			return;
		}

		$row["forum_last_post_id"] = $most_recent_post_row["topic_last_post_id"];
		$row["forum_last_poster_id"] = $most_recent_post_row["topic_last_poster_id"];
		$row["forum_last_post_subject"] = $most_recent_post_row["topic_last_post_subject"];
		$row["forum_last_post_time"] = $most_recent_post_row["topic_last_post_time"];
		$row["forum_last_poster_name"] = $most_recent_post_row["topic_last_poster_name"];
		$row["forum_last_poster_colour"] = $most_recent_post_row["topic_last_poster_colour"];

		$event["row"] = $row;
	}

	public function posting_modify_row_data($event) {
		$post_data = $event["post_data"];
		if ($post_data["forum_id"] == ADMIN_FACING_BOARD
			&& !$this->auth->acl_get("f_read", ADMIN_FACING_BOARD)
			&& $this->is_our_post($post_data["topic_id"])
		) {
			$post_data["forum_id"] = PLAYER_FACING_BOARD;
			$event["forum_id"] = PLAYER_FACING_BOARD;
			$event["post_data"] = $post_data;
		}
	}

	public function modify_submit_post_data($event) {
		$data = $event["data"];
		if ($data["forum_id"] == PLAYER_FACING_BOARD) {
			$data["force_approved_state"] = true;
			$data["forum_id"] = ADMIN_FACING_BOARD;
		}

		$event["data"] = $data;
	}

	public function viewtopic_before_f_read_check($event) {
		if ($event["forum_id"] != ADMIN_FACING_BOARD) {
			return;
		}

		$topic_id = $event["topic_id"];

		if ($this->is_our_post($topic_id)) {
			$event["overrides_f_read_check"] = true;
		}
	}

	public function viewforum_get_topic_ids_data($event) {
		$user_id = $this->user->data["user_id"];

		if (!$user_id || $event["forum_data"]["forum_id"] != PLAYER_FACING_BOARD || $this->auth->acl_get("f_read", ADMIN_FACING_BOARD)) {
			return;
		}

		$sql_ary = $event["sql_ary"];
		$sql_ary["WHERE"] = str_replace($sql_ary["WHERE"], "t.forum_id = " . PLAYER_FACING_BOARD, "t.forum_id = " . ADMIN_FACING_BOARD);
		$sql_ary["WHERE"] .= " AND t.topic_poster = $user_id ";
		$event["sql_ary"] = $sql_ary;
	}
}
