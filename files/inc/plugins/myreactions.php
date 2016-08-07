<?php
/**
 * MyReactions 0.1

 * Copyright 2016 Matthew Rogowski

 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at

 ** http://www.apache.org/licenses/LICENSE-2.0

 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.

 * Idea inspired by https://facepunch.com/ and more recently Slack

 * Twitter Emoji licenced under CC-BY 4.0
 * http://twitter.github.io/twemoji/
 * https://github.com/twitter/twemoji
**/

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook('showthread_start', 'myreactions_showthread');
$plugins->add_hook('postbit', 'myreactions_postbit');
$plugins->add_hook('misc_start', 'myreactions_react');

function myreactions_info()
{
	return array(
		"name" => "MyReactions",
		"description" => "Add emoji reactions to posts",
		"website" => "https://github.com/MattRogowski/MyReactions",
		"author" => "Matt Rogowski",
		"authorsite" => "https://matt.rogow.ski",
		"version" => "0.1",
		"compatibility" => "18*",
		"guid" => ""
	);
}

function myreactions_install()
{
	global $db;

	myreactions_uninstall();

	if(!$db->table_exists('myreactions'))
	{
		$db->write_query('CREATE TABLE `'.TABLE_PREFIX.'myreactions` (
		  `reaction_id` int(11) NOT NULL AUTO_INCREMENT,
		  `reaction_name` varchar(255) NOT NULL,
		  `reaction_path` varchar(255) NOT NULL,
		  PRIMARY KEY (`reaction_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1;');

		$reactions = array("angry","anguished","awesome","balloon","broken_heart","clap","confounded","confused","crossed_fingers","disappointed","disappointed_relieved","disapproval","dizzy_face","expressionless","eyes","face_with_rolling_eyes","facepalm","fearful","fire","flushed","grimacing","grin","grinning","hear_no_evil","heart","heart_eyes","ill","information_desk_person","innocent","joy","laughing","mask","nerd_face","neutral_face","ok_hand","open_mouth","pensive","persevere","poop","pray","rage","raised_hands","rofl","scream","see_no_evil","shrug","sleeping","slightly_frowning_face","slightly_smiling_face","smile","smiling_imp","smirk","sob","speak_no_evil","star","stuck_out_tongue","stuck_out_tongue_closed_eyes","stuck_out_tongue_winking_eye","sunglasses","suspicious","sweat","sweat_smile","tada","thinking_face","thumbsdown","thumbsup","tired_face","triumph","unamused","upside_down_face","v","whatever","white_frowning_face","wink","worried","zipper_mouth_face");

		foreach($reactions as $reaction)
		{
			$insert = array(
				'reaction_name' => ucwords(str_replace('_', ' ', $reaction)),
				'reaction_path' => 'images/reactions/'.$reaction.'.png'
			);
			$db->insert_query('myreactions', $insert);
		}
	}
	if(!$db->table_exists('post_reactions'))
	{
		$db->write_query('CREATE TABLE `'.TABLE_PREFIX.'post_reactions` (
		  `post_reaction_id` int(11) NOT NULL AUTO_INCREMENT,
		  `post_reaction_pid` int(11) NOT NULL,
		  `post_reaction_rid` int(11) NOT NULL,
		  `post_reaction_uid` int(11) NOT NULL,
		  `post_reaction_date` int(11) NOT NULL,
		  PRIMARY KEY (`post_reaction_id`),
		  KEY `post_reaction_pid` (`post_reaction_pid`),
		  KEY `post_reaction_rid` (`post_reaction_rid`),
		  KEY `post_reaction_uid` (`post_reaction_uid`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1;');
	}
	myreactions_cache();
}

function myreactions_is_installed()
{
	global $db;

	return $db->table_exists('myreactions') && $db->table_exists('post_reactions');
}

function myreactions_uninstall()
{
	global $db;

	if($db->table_exists('myreactions'))
	{
		$db->drop_table('myreactions');
	}
	if($db->table_exists('post_reactions'))
	{
		$db->drop_table('post_reactions');
	}

	$db->delete_query('datacache', 'title = \'myreactions\'');
}

function myreactions_activate()
{
	global $mybb, $db;
	
	myreactions_deactivate();

	$settings_group = array(
		"name" => "myreactions",
		"title" => "MyReactions Settings",
		"description" => "Settings for the MyReactions plugin.",
		"disporder" => "28",
		"isdefault" => 0
	);
	$db->insert_query("settinggroups", $settings_group);
	$gid = $db->insert_id();
	
	$settings = array();
	$settings[] = array(
		"name" => "myreactions_type",
		"title" => "Display Type",
		"description" => "<strong>Grouped</strong> - each reaction is only displayed once per post, in its own button with a count of the number of times it has been given to that post, ordered by number of times given<br /><strong>Linear</strong> - lists each individual reaction given in the order it was given",
		"optionscode" => "radio
grouped=Grouped
linear=Linear",
		"value" => "grouped"
	);
	$settings[] = array(
		"name" => "myreactions_size",
		"title" => "Display Size",
		"description" => "The size of the reaction emojis",
		"optionscode" => "radio
16=16px x 16px
20=20px x 20px
24=24px x 24px
28=28px x 28px
32=32px x 32px",
		"value" => "16"
	);
	$settings[] = array(
		"name" => "myreactions_multiple",
		"title" => "Allow multiple reactions",
		"description" => "Whether users can add more than one reaction to a post (regardless of setting the same reaction can never be given by the same user on the same post)",
		"optionscode" => "yesno",
		"value" => "1"
	);
	$settings[] = array(
		"name" => "myreactions_profile",
		"title" => "Display on profiles",
		"description" => "Display the most given and most received reactions on user profiles",
		"optionscode" => "yesno",
		"value" => "1"
	);
	$i = 1;
	foreach($settings as $setting)
	{
		$insert = array(
			"name" => $db->escape_string($setting['name']),
			"title" => $db->escape_string($setting['title']),
			"description" => $db->escape_string($setting['description']),
			"optionscode" => $db->escape_string($setting['optionscode']),
			"value" => $db->escape_string($setting['value']),
			"disporder" => intval($i),
			"gid" => intval($gid),
		);
		$db->insert_query("settings", $insert);
		$i++;
	}
	
	rebuild_settings();
	
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

	find_replace_templatesets("postbit", "#".preg_quote('<div class="post_controls">')."#i", '{$post[\'myreactions\']}<div class="post_controls">');
	find_replace_templatesets("postbit_classic", "#".preg_quote('<div class="post_controls">')."#i", '{$post[\'myreactions\']}<div class="post_controls">');
	
	$templates = array();
	$templates[] = array(
		"title" => "myreactions_container",
		"template" => "<div style=\"clear:both\"></div>
<div class=\"myreactions-container reactions-{\$size}\">
  {\$post_reactions}
  <div class=\"myreactions-reacted\">{\$reacted_with}</div>
</div>"
	);
	$templates[] = array(
		"title" => "myreactions_reactions",
		"template" => "<div class=\"myreactions-reactions\">
  {\$reactions}<span>{\$lang->myreactions_add}</span>
  <div style=\"clear:both\"></div>
</div>"
	);
	$templates[] = array(
		"title" => "myreactions_reaction",
		"template" => "<div class=\"myreactions-reaction{\$class}\"{\$onclick}>
  {\$reaction_image} <span>{\$count}</span>
</div>"
	);
	$templates[] = array(
		"title" => "myreactions_reaction_image",
		"template" => "<img src=\"/{\$reaction['reaction_path']}\"{\$class}{\$onclick} />{\$remove}"
	);
	$templates[] = array(
		"title" => "myreactions_react",
		"template" => "<div class=\"modal\">
	<div class=\"myreactions-react\">
		<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">
			<tr>
				<td class=\"thead\"><strong>{\$lang->myreactions_add}</strong></td>
			</tr>
			<tr>
				<td class=\"trow1\">{\$post_preview}</td>
			</tr>
			{\$recent}
			<tr>
				<td class=\"tcat\">{\$lang->myreactions_all}</td>
			</tr>
			<tr>
				<td class=\"trow1\" align=\"left\">
					{\$reactions}
				</td>
			</tr>
		</table>
	</div>
</div>"
	);
	$templates[] = array(
		"title" => "myreactions_react_recent",
		"template" => "<tr>
	<td class=\"tcat\">{\$lang->myreactions_recent}</td>
</tr>
<tr>
	<td class=\"trow1\" align=\"left\">
		{\$recent_reactions}
	</td>
</tr>"
	);
	
	foreach($templates as $template)
	{
		$insert = array(
			"title" => $db->escape_string($template['title']),
			"template" => $db->escape_string($template['template']),
			"sid" => "-1",
			"version" => "1800",
			"status" => "",
			"dateline" => TIME_NOW
		);
		
		$db->insert_query("templates", $insert);
	}

	myreactions_cache();
}

function myreactions_deactivate()
{
	global $mybb, $db;
	
	$db->delete_query("settinggroups", "name = 'myreactions'");
	
	$settings = array(
		"myreactions_type",
		"myreactions_size",
		"myreactions_multiple",
		"myreactions_profile"
	);
	$settings = "'" . implode("','", $settings) . "'";
	$db->delete_query("settings", "name IN ({$settings})");
	
	rebuild_settings();
	
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

	find_replace_templatesets("postbit", "#".preg_quote('{$post[\'myreactions\']}')."#i", '', 0);
	find_replace_templatesets("postbit_classic", "#".preg_quote('{$post[\'myreactions\']}')."#i", '', 0);
	
	$db->delete_query("templates", "title IN ('myreactions_container','myreactions_reactions','myreactions_reaction','myreactions_reaction_image','myreactions_react','myreactions_react_recent')");
}

function myreactions_cache()
{
	global $db, $cache;
	
	$query = $db->simple_select('myreactions');
	$myreactions = array();
	while($myreaction = $db->fetch_array($query))
	{
		$myreactions[$myreaction['reaction_id']] = $myreaction;
	}
	$cache->update('myreactions', $myreactions);
}

function myreactions_showthread()
{
	global $mybb, $db, $thread_reactions;

	if($mybb->input['pid'])
	{
		$post = get_post($mybb->input['pid']);
		$tid = $post['tid'];
	}
	else
	{
		$tid = intval($mybb->input['tid']);
	}

	$reactions = $db->query('
		SELECT '.TABLE_PREFIX.'post_reactions.*
		FROM '.TABLE_PREFIX.'post_reactions
		JOIN '.TABLE_PREFIX.'posts ON (pid = post_reaction_pid)
		WHERE tid = \''.$tid.'\'
		ORDER BY post_reaction_date ASC
	');
	$thread_reactions = array();
	while($reaction = $db->fetch_array($reactions))
	{
		$thread_reactions[$reaction['post_reaction_pid']][] = $reaction;
	}
}

function myreactions_postbit(&$post)
{
	global $mybb, $lang, $cache, $templates, $thread_reactions;

	if($mybb->input['action'] == 'do_myreactions')
	{
		myreactions_showthread();
	}

	$all_reactions = $cache->read('myreactions');
	$lang->load('myreactions');

	$received_reactions = $reacted = array();
	if(array_key_exists($post['pid'], $thread_reactions))
	{
		$received_reactions = $thread_reactions[$post['pid']];
		foreach($received_reactions as $reaction)
		{
			if($reaction['post_reaction_uid'] == $mybb->user['uid'])
			{
				$reacted[] = $reaction;
			}
		}
	}

	$size = $mybb->settings['myreactions_size'];

	switch($mybb->settings['myreactions_type'])
	{
		case 'linear':
			$reactions = '';
			foreach($received_reactions as $received_reaction)
			{
				$reaction = $all_reactions[$received_reaction['post_reaction_rid']];
				eval("\$reactions .= \"".$templates->get('myreactions_reaction_image')."\";");
			}

			if($post['uid'] != $mybb->user['uid'] && !($reacted && !$mybb->settings['myreactions_multiple']))
			{
				$reaction = array('reaction_path' => 'images/reactions/plus.png');
				$class = ' class="reaction-add'.(!$number?' reaction-add-force':'').'"';
				$onclick = ' onclick="MyReactions.reactions('.$post['pid'].');"';
				eval("\$reactions .= \"".$templates->get('myreactions_reaction_image')."\";");
			}

			eval("\$post_reactions = \"".$templates->get('myreactions_reactions')."\";");
			break;
		case 'grouped':
			$post_reactions = '';
			$grouped_reactions = array();
			foreach($received_reactions as $received_reaction)
			{
				if(!array_key_exists($received_reaction['post_reaction_rid'], $grouped_reactions))
				{
					$grouped_reactions[$received_reaction['post_reaction_rid']] = 0;
				}
				$grouped_reactions[$received_reaction['post_reaction_rid']]++;
			}
			arsort($grouped_reactions);
			foreach($grouped_reactions as $rid => $count)
			{
				$reaction = $all_reactions[$rid];
				eval("\$reaction_image = \"".$templates->get('myreactions_reaction_image')."\";");
				eval("\$post_reactions .= \"".$templates->get('myreactions_reaction')."\";");
			}

			if($post['uid'] != $mybb->user['uid'] && !($reacted && !$mybb->settings['myreactions_multiple']))
			{
				$reaction = array('reaction_path' => 'images/reactions/plus.png');
				$count = $lang->myreactions_add;
				eval("\$reaction_image = \"".$templates->get('myreactions_reaction_image')."\";");
				$class = ' reaction-add'.(!$number?' reaction-add-force':'');
				$onclick = ' onclick="MyReactions.reactions('.$post['pid'].');"';
				eval("\$post_reactions .= \"".$templates->get('myreactions_reaction')."\";");
			}
			break;
	}

	if($reacted)
	{
		$reacted_with = $lang->myreactions_you_reacted_with;
		foreach($reacted as $r)
		{
			$reaction = $all_reactions[$r['post_reaction_rid']];
			$class = $onclick = '';
			$remove = ' ('.$lang->myreactions_remove.')';
			eval("\$reacted_with .= \"".$templates->get('myreactions_reaction_image')."\";");
		}
	}

	eval("\$post['myreactions'] = \"".$templates->get('myreactions_container')."\";");
}

function myreactions_react()
{
	global $mybb, $lang, $cache, $templates, $theme;

	if($mybb->input['action'] == 'myreactions')
	{
		$all_reactions = $cache->read('myreactions');
		$lang->load('myreactions');

		$post = get_post($mybb->input['pid']);
		$post_preview = $post['message'];
		if(my_strlen($post['message']) > 100)
		{
			$post_preview = my_substr($post['message'], 0, 140).'...';
		}

		$reactions = $recent_reactions = '';

		foreach($all_reactions as $reaction)
		{
			$onclick = ' onclick="MyReactions.react('.$reaction['reaction_id'].','.$post['pid'].');"';
			eval("\$reactions .= \"".$templates->get('myreactions_reaction_image', 1, 0)."\";");
		}
		
		shuffle($all_reactions);
		$number = rand(0, 10);
		if($number)
		{
			for($i = 1; $i <= $number; $i++)
			{
				$k = $i - 1;
				$reaction = $all_reactions[$k];
				$onclick = ' onclick="MyReactions.react('.$reaction['reaction_id'].','.$post['pid'].');"';
				eval("\$recent_reactions .= \"".$templates->get('myreactions_reaction_image', 1, 0)."\";");
			}
			eval("\$recent = \"".$templates->get('myreactions_react_recent', 1, 0)."\";");
		}

		eval("\$myreactions = \"".$templates->get('myreactions_react', 1, 0)."\";");
		echo $myreactions;
		exit;
	}
	elseif($mybb->input['action'] == 'do_myreactions')
	{
		$post = get_post($mybb->input['pid']);
		myreactions_postbit($post);
		echo $post['myreactions'];
		exit;
	}
}

/*
.myreactions-container {
  padding: 10px;
  border-top: 1px solid #ccc;
}
.myreactions-reaction {
  display: inline-block;
  margin: 2px;
  padding: 5px;
}
.myreactions-reactions, .myreactions-reaction {
  background: #f5f5f5;
  border: 1px solid #ccc;
  display: inline-block;
  border-radius: 6px;
}
.myreactions-reaction span {
  float: right;
  margin-left: 5px;
}
.myreactions-reactions .reaction-add, .myreactions-reaction.reaction-add {
  display: none;
}
.myreactions-container:hover .reaction-add, .reaction-add.reaction-add-force {
  display: inline-block;
}
.myreactions-reaction.reaction-add span, .myreactions-reactions .reaction-add + span, .myreactions-reactions > span {
  display: none;
}
.myreactions-reactions .reaction-add + span {
  margin-right: 5px;
}
.myreactions-reaction.reaction-add.reaction-add-force span, .myreactions-reactions .reaction-add.reaction-add-force + span {
  display: inline;
}
.myreactions-reactions img {
  margin: 5px;
  float: left;
  display: inline-block;
}
.myreactions-container .myreactions-reacted img {
  position: relative;
}
.myreactions-container.reactions-16 img {
  width: 16px;
  height: 16px;
}
.myreactions-container.reactions-16 .myreactions-reaction span, .myreactions-container.reactions-16 .myreactions-reacted {
  font-size: 12px;
  line-height: 16px;
}
.myreactions-container.reactions-16 .myreactions-reactions .reaction-add + span {
  font-size: 12px;
  line-height: 26px;
}
.myreactions-container.reactions-16 .myreactions-reacted img {
  top: 4px;
}
.myreactions-container.reactions-20 img {
  width: 20px;
  height: 20px;
}
.myreactions-container.reactions-20 .myreactions-reaction span, .myreactions-container.reactions-20 .myreactions-reacted {
  font-size: 13px;
  line-height: 20px;
}
.myreactions-container.reactions-20 .myreactions-reactions .reaction-add + span {
  font-size: 13px;
  line-height: 30px;
}
.myreactions-container.reactions-20 .myreactions-reacted img {
  top: 6px;
}
.myreactions-container.reactions-24 img {
  width: 24px;
  height: 24px;
}
.myreactions-container.reactions-24 .myreactions-reaction span, .myreactions-container.reactions-24 .myreactions-reacted {
  font-size: 14px;
  line-height: 24px;
}
.myreactions-container.reactions-24 .myreactions-reactions .reaction-add + span {
  font-size: 14px;
  line-height: 34px;
}
.myreactions-container.reactions-24 .myreactions-reacted img {
  top: 7px;
}
.myreactions-container.reactions-28 img {
  width: 28px;
  height: 28px;
}
.myreactions-container.reactions-28 .myreactions-reaction span, .myreactions-container.reactions-28 .myreactions-reacted {
  font-size: 15px;
  line-height: 28px;
}
.myreactions-container.reactions-28 .myreactions-reactions .reaction-add + span {
  font-size: 15px;
  line-height: 38px;
}
.myreactions-container.reactions-28 .myreactions-reacted img {
  top: 7px;
}
.myreactions-container.reactions-32 img {
  width: 32px;
  height: 32px;
}
.myreactions-container.reactions-32 .myreactions-reaction span, .myreactions-container.reactions-32 .myreactions-reacted {
  font-size: 16px;
  line-height: 32px;
}
.myreactions-container.reactions-32 .myreactions-reactions .reaction-add + span {
  font-size: 16px;
  line-height: 42px;
}
.myreactions-container.reactions-32 .myreactions-reacted img {
  top: 8px;
}
.myreactions-react img {
	width: 24px;
	height: 24px;
	padding: 5px;
}
.reaction-add, .myreactions-react img {
	cursor: pointer;
}
*/