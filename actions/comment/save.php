<?php
/**
 * Action for adding and editing comments
 *
 * @package Elgg.Core
 * @subpackage Comments
 */

$entity_guid = (int) get_input('entity_guid', 0, false);
$comment_guid = (int) get_input('comment_guid', 0, false);
$comment_text = get_input('generic_comment');

if (empty($comment_text)) {
	return elgg_error_response(elgg_echo('generic_comment:blank'));
}

$result = '';

if ($comment_guid) {
	// Edit an existing comment
	$comment = get_entity($comment_guid);

	if (!elgg_instanceof($comment, 'object', 'comment')) {
		return elgg_error_response(elgg_echo('generic_comment:notfound'));
	}
	if (!$comment->canEdit()) {
		return elgg_error_response(elgg_echo('actionunauthorized'));
	}

	$comment->description = $comment_text;
	if (!$comment->save()) {
		return elgg_error_response(elgg_echo('generic_comment:failure'));
	}
	

	if (elgg_is_xhr()) {
		// @todo move to its own view object/comment/content in 1.x
		$result = elgg_view('output/longtext', [
			'value' => $comment->description,
			'class' => 'elgg-inner',
			'data-role' => 'comment-text',
		]);
	}
	
	$success_message = elgg_echo('generic_comment:updated');
	
} else {
	// Create a new comment on the target entity
	$entity = get_entity($entity_guid);
	if (!$entity) {
		return elgg_error_response(elgg_echo('generic_comment:notfound'));
	}

	$user = elgg_get_logged_in_user_entity();

	$comment = new ElggComment();
	$comment->description = $comment_text;
	$comment->owner_guid = $user->getGUID();
	$comment->container_guid = $entity->getGUID();
	$comment->access_id = $entity->access_id;
	$guid = $comment->save();

	if (!$guid) {
		return elgg_error_response(elgg_echo('generic_comment:failure'));
	}

	// Add to river
	elgg_create_river_item([
		'view' => 'river/object/comment/create',
		'action_type' => 'comment',
		'subject_guid' => $user->guid,
		'object_guid' => $guid,
		'target_guid' => $entity_guid,
	]);

	$success_message = elgg_echo('generic_comment:posted');
}

$forward = $comment->getURL();

// return to activity page if posted from there
// this can be removed once saving new comments is ajaxed
if (!empty($_SERVER['HTTP_REFERER'])) {
	// don't redirect to URLs from client without verifying within site
	$site_url = preg_quote(elgg_get_site_url(), '~');
	if (preg_match("~^{$site_url}activity(/|\\z)~", $_SERVER['HTTP_REFERER'], $m)) {
		$forward = "{$m[0]}#elgg-object-{$comment->guid}";
	}
}

return elgg_ok_response($result, $success_message, $forward);
