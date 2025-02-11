<?php

namespace tgstation\oauthprovider\migrations;

class dbmigrations_v1 extends \phpbb\db\migration\migration {
    public function update_schema()	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'oauth_server_application'	=> array(
					'COLUMNS'		=> array(
						'application_id'		=> array('UINT', null, 'auto_increment'),
						'user_id'				=> array('ULINT', 0),
						'application_name'		=> array('VCHAR:160', ''),
						'application_website'	=> array('VCHAR:255', ''),
						'redirect_uri'			=> array('TEXT', ''),
						'client_secret'			=> array('VCHAR:255', ''),
						'application_flags'		=> array('USINT', 0),
						'refresh_ttl'			=> array('UINT', 0),
						'session_ttl'			=> array('UINT', 0),
						'registered'			=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY'	=> 'application_id',
					'KEYS'			=> array(
						'user_id'				=> array('INDEX', array('user_id')),
					),
				),
				$this->table_prefix . 'oauth_server_authorization'	=> array(
					'COLUMNS'		=> array(
						'authorization_id'		=> array('UINT', null, 'auto_increment'),
						'application_id'		=> array('UINT', 0),
						'user_id'				=> array('UINT', 0),
						'auth_code'				=> array('VCHAR:128', ''),
						'redirect_uri'			=> array('TEXT', ''),
						'challenge_token'		=> array('VCHAR:128', ''),
						'scopes'				=> array('TEXT', 0),
						'authorization_flags'	=> array('USINT', 0),
						'user_passchg'			=> array('TIMESTAMP', 0),
						'authorized'			=> array('TIMESTAMP', 0),
						'last_refreshed'		=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY'	=> 'authorization_id',
					'KEYS'			=> array(
						'user_id'				=> array('INDEX', array('user_id')),
						'application_id'		=> array('INDEX', array('application_id')),
					),
				),
				$this->table_prefix . 'oauth_server_refresh_token'	=> array(
					'COLUMNS'		=> array(
						'refresh_token'			=> array('VCHAR:128', ''),
						'authorization_id'		=> array('UINT', 0),
						'refresh_token_flags'	=> array('USINT', 0),
						'consumed_on'			=> array('TIMESTAMP', 0),
						'created'				=> array('TIMESTAMP', 0),
						'expires'				=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY'	=> 'refresh_token',
					'KEYS'			=> array(
						'authorization_id'		=> array('INDEX', array('authorization_id')),
					),
				),
				$this->table_prefix . 'oauth_server_session_token'	=> array(
					'COLUMNS'		=> array(
						'session_token'			=> array('VCHAR:128', ''),
						'authorization_id'		=> array('UINT', 0),
						'session_token_flags'	=> array('USINT', 0),
						'created'				=> array('TIMESTAMP', 0),
						'last_used'				=> array('TIMESTAMP', 0),
						'expires'				=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY'	=> 'session_token',
					'KEYS'			=> array(
						'authorization_id'		=> array('INDEX', array('authorization_id')),
					),
				),
			),
		);
	}

	public function revert_schema() {
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'oauth_server_application',
				$this->table_prefix . 'oauth_server_authorization',
				$this->table_prefix . 'oauth_server_refresh_token',
				$this->table_prefix . 'oauth_server_session_token',
			),
		);
	}
}