<?php

/**
 * Limit Topics Length (LTL)
 *
 * @package LTL
 * @author emanuele
 * @copyright 2011 emanuele
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 *
 * Hooks
 *
 */

function limit_topic_length_add_modsettings (&$config_vars)
{
	$config_vars[] = array('text', 'max_length_for_topics');
	$config_vars[] = array('text', 'locking_bot_name');
	$config_vars[] = array('text', 'locking_bot_email');
	$config_vars[] = array('large_text', 'ltl_default_lock_message');
	$config_vars[] = array('large_text', 'ltl_default_new_message');

	if (isset($_POST['save']))
		$_POST['max_length_for_topics'] = (int) $_POST['max_length_for_topics'];
}


/**
 *
 * Functions
 *
 */

function ltl_lockOldTopic ($old_topic_id)
{
	global $smcFunc, $modSettings, $context, $board_info, $board, $scripturl, $txt;

	$old_topic_id = (int) $old_topic_id;

	if (empty($old_topic_id))
		return false;

	$new_topic_id = ltl_createNewTopic($old_topic_id);
	ltl_updateNotifications($old_topic_id, $new_topic_id);
	if (!empty($context['ltl_id_new_topic']))
		return $new_topic_id;

	if (empty($new_topic_id))
	{
		$context['override_topic_length'] = true;
		return false;
	}

	if (empty($modSettings['ltl_default_lock_message']))
		$modSettings['ltl_default_lock_message'] = $txt['ltl_default_text_lock_msg'];

	$message = str_replace(
		array(
			'{NEXT_TOPIC_URL}',
			'{NEXT_TOPIC_SUBJECT}',
			'{NEXT_TOPIC_LINK}',
		),
		array(
			$scripturl . '?topic=' . $new_topic_id . '.0',
			$scripturl . '?topic=' . $new_topic_id . '.0',
			'[iurl=' . $scripturl . '?topic=' . $new_topic_id . '.0][' . $context['ltl_num_topics'] . '] ' . $context['ltl_subject'] . '[/iurl]',
		),
		$modSettings['ltl_default_lock_message']
	);

	// Collect all parameters for the creation the locking post.
	$msgOptions = array(
		'id' => 0,
		'subject' => $txt['response_prefix'] . $context['ltl_subject'],
		'body' => $message,
		'smileys_enabled' => true,
		'attachments' => array(),
		'approved' => true,
	);
	$topicOptions = array(
		'id' => $old_topic_id,
		'board' => $board,
		'lock_mode' => 2,
		'mark_as_read' => true,
		'is_approved' => !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']),
	);
	$posterOptions = array(
		'id' => 0,
		// Just want to be sure there is actually something to throw in the db. ;)
		'name' => (!empty($modSettings['locking_bot_name']) ? $modSettings['locking_bot_name'] : 'Locking Bot'),
		'email' => (!empty($modSettings['locking_bot_email']) ? $modSettings['locking_bot_email'] : 'lockingbot@email.filler'),
		'update_post_count' => false,
	);

	createPost($msgOptions, $topicOptions, $posterOptions);
	return $new_topic_id;
}

function ltl_createNewTopic ($topic_id)
{
	global $smcFunc, $modSettings, $txt, $board, $context, $scripturl;

	$topic_id = (int) $topic_id;
	if(empty($topic_id))
		return false;

	$new_msg = ltl_getOriginalTopicInfo($topic_id);
	if (!empty($context['ltl_id_new_topic']))
		return $context['ltl_id_new_topic'];

	if (empty($modSettings['ltl_default_new_message']))
		$modSettings['ltl_default_new_message'] = $txt['ltl_default_text_new_msg'];

	$message = str_replace(
		array(
			'{PREV_TOPIC_URL}',
			'{PREV_TOPIC_SUBJECT}',
			'{PREV_TOPIC_LINK}',
		),
		array(
			$scripturl . '?topic=' . $topic_id . '.0',
			'[' . $new_msg['num_topics'] . '] ' . $new_msg['subject'],
			'[iurl=' . $scripturl . '?topic=' . $topic_id . '.0][' . $new_msg['num_topics'] . '] ' . $new_msg['subject'] . '[/iurl]',
		),
		$modSettings['ltl_default_new_message']
	);

	// Collect all parameters for the creation of the new topic.
	$msgOptions = array(
		'id' => 0,
		'subject' => $new_msg['subject'],
		// It's a placeholder so that it can be replaced at Display-time with a text in the appropriate language
		'body' => $message,
		'smileys_enabled' => true,
		'icon' => $new_msg['icon'],
		'attachments' => array(),
		'approved' => true,
	);
	$topicOptions = array(
		'id' => 0,
		'board' => $board,
		'poll' => !empty($new_msg['id_poll']) ? $new_msg['id_poll'] : null,
		'lock_mode' => !empty($context['ltl_locked']) ? $context['ltl_locked'] : null,
		'sticky_mode' => !empty($new_msg['is_sticky']) ? $new_msg['is_sticky'] : null,
		'mark_as_read' => true,
		'is_approved' => !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']),
	);
	$posterOptions = array(
		'id' => $new_msg['id_member_started'],
		'name' => $new_msg['poster_name'],
		'email' => $new_msg['poster_email'],
		'ip' => $new_msg['poster_ip'],
		'update_post_count' => false,
	);

	createPost($msgOptions, $topicOptions, $posterOptions);
	// Something went wrong, it's useless to continue.
	if(empty($topicOptions['id']))
		return false;

	$request = $smcFunc['db_query']('', '
		SELECT id_last_msg
		FROM {db_prefix}topics
		WHERE id_topic = {int:topic_id}',
		array(
			'topic_id' => $topicOptions['id'],
	));
	list($_REQUEST['last_msg']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_old_topic = {int:id_old_topic}, num_topics = {int:topic_number}
		WHERE id_topic = {int:id_topic}',
		array(
			'id_old_topic' => $topic_id,
			'id_topic' => $topicOptions['id'],
			'topic_number' => ($new_msg['num_topics']+1),
	));
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_new_topic = {int:id_new_topic}
		WHERE id_topic = {int:id_topic}',
		array(
			'id_new_topic' => $topicOptions['id'],
			'id_topic' => $topic_id,
	));

	return $topicOptions['id'];
}

function ltl_getOriginalTopicInfo ($topic_id)
{
	global $smcFunc, $context;

	$topic_id = (int) $topic_id;
	if(empty($topic_id))
		return false;

	// Retrieve the original information
	$request = $smcFunc['db_query']('', '
		SELECT m.subject, m.poster_name, m.poster_email, m.poster_ip, m.smileys_enabled, m.icon,
		t.id_member_started, t.id_poll, t.is_sticky, t.locked, t.id_new_topic, t.num_topics
		FROM {db_prefix}messages as m
		LEFT JOIN {db_prefix}topics as t ON (t.id_first_msg = m.id_msg)
		WHERE t.id_topic = {int:id_topic}
		LIMIT 1',
		array(
			'id_topic' => $topic_id,
	));
	$new_msg = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Set few variables we will need later
	$context['ltl_subject'] = $new_msg['subject'];
	$context['ltl_id_new_topic'] = $new_msg['id_new_topic'];
	$context['ltl_locked'] = $new_msg['locked'];
	$context['ltl_num_topics'] = $new_msg['num_topics']+1;

	return $new_msg;
}

function ltl_updateNotifications($old_topic_id, $new_topic_id)
{
	global $smcFunc;

	if (empty($old_topic_id) || empty($new_topic_id))
		return false;

	$request = $smcFunc['db_query']('', '
		UPDATE {db_prefix}log_notify
		SET id_topic = {int:new_topic_id}
		WHERE id_topic = {int:old_topic_id}',
		array(
			'old_topic_id' => $old_topic_id,
			'new_topic_id' => $new_topic_id,
	));
}
?>