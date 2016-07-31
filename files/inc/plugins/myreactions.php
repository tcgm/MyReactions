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

$plugins->add_hook('postbit', 'myreactions_postbit');

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

		$reactions = array("angry","anguished","balloon","broken_heart","clap","confounded","confused","crossed_fingers","disappointed","disappointed_relieved","dizzy_face","expressionless","eyes","face_with_rolling_eyes","facepalm","fearful","fire","flushed","grimacing","grin","grinning","hear_no_evil","heart","heart_eyes","ill","information_desk_person","innocent","joy","laughing","mask","nerd_face","neutral_face","ok_hand","open_mouth","pensive","persevere","poop","pray","rage","raised_hands","rofl","scream","see_no_evil","shrug","sleeping","slightly_frowning_face","slightly_smiling_face","smile","smiling_imp","smirk","sob","speak_no_evil","star","stuck_out_tongue","stuck_out_tongue_closed_eyes","stuck_out_tongue_winking_eye","sunglasses","sweat","sweat_smile","tada","thinking_face","thumbsdown","thumbsup","tired_face","triumph","unamused","upside_down_face","v","white_frowning_face","wink","worried","zipper_mouth_face");

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
	
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	
	$templates = array();
	$templates[] = array(
		"title" => "myreactions_container",
		"template" => "<div class=\"myreactions-container reactions-{\$size}\">
  {\$post_reactions}
</div>"
	);
	$templates[] = array(
		"title" => "myreactions_reactions",
		"template" => "<div class=\"myreactions-reactions\">
  {\$reactions}
  <div style=\"clear:both\"></div>
</div>"
	);
	$templates[] = array(
		"title" => "myreactions_reaction",
		"template" => "<div class=\"myreactions-reaction{\$class}\">
  {\$reaction_image} <span>{\$count}</span>
</div>"
	);
	$templates[] = array(
		"title" => "myreactions_reaction_image",
		"template" => "<img src=\"/{\$reaction['reaction_path']}\"{\$class} />"
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
}

function myreactions_deactivate()
{
	global $mybb, $db;
	
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	
	$db->delete_query("templates", "title IN ('myreactions_container','myreactions_reactions','myreactions_reaction','myreactions_reaction_image')");
}

function myreactions_cache()
{
	global $db, $cache;
	
	$query = $db->simple_select('myreactions');
	$myreactions = array();
	while($myreaction = $db->fetch_array($query))
	{
		$myreactions[] = $myreaction;
	}
	$cache->update('myreactions', $myreactions);
}

function myreactions_postbit(&$post)
{
	global $cache, $templates;

	$all_reactions = $cache->read('myreactions');

	shuffle($all_reactions);
	$number = rand(1, 20);
	$type = rand(0, 1);
	$sizes = array(16,20,24,28,32);
	$size = $sizes[0];

	switch($type)
	{
		case 0:
			$reactions = '';
			for($i = 1; $i <= $number; $i++)
			{
				$k = $i - 1;
				$reaction = $all_reactions[$k];
				eval("\$reactions .= \"".$templates->get('myreactions_reaction_image')."\";");
			}
			$reaction = array('reaction_path' => 'images/reactions/plus.png');
			$class = ' class="reaction-add"';
			eval("\$reactions .= \"".$templates->get('myreactions_reaction_image')."\";");
			eval("\$post_reactions = \"".$templates->get('myreactions_reactions')."\";");
			eval("\$post['myreactions'] = \"".$templates->get('myreactions_container')."\";");
			break;
		case 1:
			$post_reactions = '';
			for($i = 1; $i <= $number; $i++)
			{
				$k = $i - 1;
				$reaction = $all_reactions[$k];
				eval("\$reaction_image = \"".$templates->get('myreactions_reaction_image')."\";");
				$count = rand(1, 100);
				eval("\$post_reactions .= \"".$templates->get('myreactions_reaction')."\";");
			}
			$reaction = array('reaction_path' => 'images/reactions/plus.png');
			$class = ' reaction-add';
			$count = '';
			eval("\$reaction_image = \"".$templates->get('myreactions_reaction_image')."\";");
			eval("\$post_reactions .= \"".$templates->get('myreactions_reaction')."\";");
			eval("\$post['myreactions'] = \"".$templates->get('myreactions_container')."\";");
			break;
	}
}

/*
.myreactions-container {
  padding: 10px;
  border-top: 1px solid #ccc;
}
.myreactions-reactions {
  padding: 5px;
}
.myreactions-reaction {
  display: inline-block;
  margin: 2px;
  padding: 5px;
}
.myreactions-reactions, .myreactions-reaction {
  background: #f5f5f5;
  border: 1px solid #ccc;
}
.myreactions-reaction span {
  float: right;
  margin-left: 10px;
}
.myreactions-container .reaction-add {
  display: none;
}
.myreactions-container:hover .reaction-add {
  display: inline-block;
}
.myreactions-reaction.reaction-add span {
  display: none;
}
.myreactions-reactions img {
  margin: 5px;
  float: left;
  display: inline-block;
}
.myreactions-container.reactions-16 img {
  width: 16px;
  height: 16px;
}
.myreactions-container.reactions-16 .myreactions-reaction span {
  font-size: 12px;
  line-height: 16px;
}
.myreactions-container.reactions-20 img {
  width: 20px;
  height: 20px;
}
.myreactions-container.reactions-20 .myreactions-reaction span {
  font-size: 13px;
  line-height: 20px;
}
.myreactions-container.reactions-24 img {
  width: 24px;
  height: 24px;
}
.myreactions-container.reactions-24 .myreactions-reaction span {
  font-size: 14px;
  line-height: 24px;
}
.myreactions-container.reactions-28 img {
  width: 28px;
  height: 28px;
}
.myreactions-container.reactions-28 .myreactions-reaction span {
  font-size: 15px;
  line-height: 28px;
}
.myreactions-container.reactions-32 img {
  width: 32px;
  height: 32px;
}
.myreactions-container.reactions-32 .myreactions-reaction span {
  font-size: 16px;
  line-height: 32px;
}
*/