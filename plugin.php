<?php
/**
 * WeMentions' plugins main file
 * 
 * @package Dragooon:WeMentions
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2013, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *      Licensed under "New BSD License (3-clause version)"
 *      http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

/**
 * Hook callback for create_post_before and modify_post_before
 * Parses a post, actually looks for mentions and issues notifications
 *
 * @param array &$msgOptions
 * @param array &$topicOptions
 * @param array &$posterOptions
 * @param bool $new_topic
 * @return void
 */
function wementions_post(&$msgOptions, &$topicOptions, &$posterOptions, $new_topic = false)
{
    // Attempt to match all the @<username> type mentions in the post
    preg_match_all('/\@(\w+)/', $msgOptions['body'], $matches);

    if (empty($matches[1]) || !allowedTo('mention_member'))
        return;

    // Attempt to fetch all the valid usernames
    $request = wesql::query('
        SELECT id_member, real_name, member_name
        FROM {db_prefix}members
        WHERE member_name IN ({array_string:names})
            OR real_name IN ({array_string:names})
        LIMIT {int:count}',
        array(
            'names' => $matches[1],
            'count' => count($matches[1]),
        )
    );
    $members = array();
    while ($row = wesql::fetch_assoc($request))
        $members[$row['id_member']] = array(
            'id' => $row['id_member'],
            'member_name' => $row['member_name'],
            'real_name' => $row['real_name'],
        );
    wesql::free_result($request);

    if (empty($members))
        return;

    // Replace all the tags with BBCode ([member=<id>]<username>[/member])
    $msgOptions['mentions'] = array();
    foreach ($members as $member)
    {
        $msgOptions['body'] = str_replace(array('@' . $member['member_name'], '@' . $member['real_name']), '[member=' . $member['id'] . ']' . $member['real_name'] . '[/member]', $msgOptions['body']);

        // Why would an idiot mention themselves?
        if (we::$id == $member['id'])
            continue;

        $msgOptions['mentions'][] = $member['id'];
    }

    // Issue the notifications now if we are not a new post 
    if (!empty($msgOptions['id']))
        wementions_create_post_after($msgOptions, $topicOptions, $posterOptions);
}

/**
 * Hook callback for create_post_after, in case we're to be creating a new post previously
 *
 * @param array &$msgOptions
 * @param array &$topicOptions
 * @param array &$posterOptions
 * @param bool $new_topic
 * @return void
 */
function wementions_create_post_after(&$msgOptions, &$topicOptions, &$posterOptions, $new_topic = false)
{
    if (empty($msgOptions['mentions']))
        return;

    // Issue the notifications
    Notification::issue($msgOptions['mentions'], WeNotif::getNotifiers('mentions'), $msgOptions['id'], array(
        'topic' => $topicOptions['id'],
        'subject' => $msgOptions['subject'],
        'member' => array(
            'id' => we::$id,
            'name' => we::$user['name'],
        ),
    ));
}

/**
 * Hook for notification_callback, registers the notifier
 *
 * @param array &$notifiers
 * @return void
 */
function wementions_notification_callback(array &$notifiers)
{
    $notifiers['mentions'] = new Mentions_Notifier();
}

/**
 * Hook callback for display_message_list, marks unread mentions as read for these messages
 *
 * @param array &$messages
 * @param array &$times
 * @param array &$all_posters
 * @return void
 */
function wementions_display_message_list(&$messages, &$times, &$all_posters)
{
    Notification::markReadForNotifier(we::$id, WeNotif::getNotifiers('mentions'), $messages);
}

/**
 * Notifier interface
 */
class Mentions_Notifier implements Notifier
{
    /**
     * Constructor, loads this plugin's language
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        loadPluginLanguage('Dragooon:WeMentions', 'plugin');
    }

    /**
     * Returns the URL for this notification
     *
     * @access public
     * @param Notification $notification
     * @return string
     */
    public function getURL(Notification $notification)
    {
        $data = $notification->getData();
        return 'topic=' . $data['topic'] . '.msg' . $notification->getObject() . '#msg' . $notification->getObject();
    }

    /**
     * Returns this notifier's identifier
     *
     * @access public
     * @return string
     */
    public function getName()
    {
        return 'mentions';
    }

    /**
     * Returns this notification's text to be displayed
     *
     * @access public
     * @param Notification $notification
     * @return string
     */
    public function getText(Notification $notification)
    {
        global $txt;

        $data = $notification->getData();

        return sprintf($txt['wementions_notification'], $data['member']['name'], $data['subject']);
    }

    /**
     * Callback for handling multiple notifications, we basically ignore this since the
     * mentions are per-post and we need no multiple mentions
     *
     * @access public
     * @param Notification $notification
     * @param array &$data
     * @return bool
     */
    public function handleMultiple(Notification $notification, array &$data)
    {
        return false;
    }

    /**
     * Returns elements for profile area
     *
     * @access public
     * @param int $id_member
     * @return array
     */
    public function getProfile($id_member)
    {
        global $txt;

        return array($txt['wementions_title'], $txt['wementions_desc'], array());
    }

    /**
     * Callback for profile save
     *
     * @access public
     * @param int $id_member The ID of the member whose profile is currently being accessed
     * @param array $settings A key => value pair of the fed settings
     * @return void
     */
    public function saveProfile($id_member, array $settings)
    {
    }

    /**
     * E-mail handler for instantanous notification
     *
     * @access public
     * @param Notification $notification
     * @param array $email_data
     * @return array(subject, body)
     */
    public function getEmail(Notification $notification, array $email_data)
    {
        global $txt;

        return array($txt['wementions_subject'], $this->getText($notification));
    }
}