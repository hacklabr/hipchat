<?php
/*
Plugin Name: HipChat
Plugin URI: http://www.hipchat.com
Description: Send a message to a HipChat room whenever a post is published.
Version: 1.2
Author: HipChat
Author URI: http://www.hipchat.com
License: GNU GPL v2
*/

include_once('lib/HipChat.php');

function hipchat_send_publish_notification($post) {
  
    $message = get_userdata($post->post_author)->display_name.
               " just posted <a href='".get_permalink($post->ID)."'>".
               $post->post_title."</a>";
    
    hipchat_send_notification($message);
    
    return $post;
}

function hipchat_send_comment_notification($comment_ID, $comment_data) {

    
    $comment = get_comment($comment_ID);
    $comment_link = get_comment_link($comment_ID);
    $post_link = get_permalink($comment->comment_post_ID);
    $post_title = get_the_title($comment->comment_post_ID);
    
    $message = get_userdata($comment->user_id)->display_name . 
            " just <a href='$comment_link'>replied</a> to <a href='$post_link'>$post_title</a>";
    
    if (get_option('hipchat_notify_comments_content'))
        $message .=  '<br/>' . $comment->comment_content;
        
    $auth_token = get_option('hipchat_auth_token');
    
    hipchat_send_notification($message);
    
}

function hipchat_send_notification($message) {
    
    try {
        $auth_token = get_option('hipchat_auth_token');
        // do nothing if plugin is not configured
        if (!$auth_token) {
          return;
        }

        $room = get_option('hipchat_room');
        $from = get_option('hipchat_from');

        $hc = new HipChat($auth_token);
        $r = $hc->message_room($room, $from, $message);
        if (!$r) {
          // something went wrong! what do we do with an error in WP?
        }
    } catch (HipChat_Exception $e) {
        // something went wrong! what do we do with an error in WP?
    }
}

function hipchat_menu() {
  add_options_page('HipChat', 'HipChat', 10, basename(__FILE__),
                   'hipchat_settings_page');
}

/**
 * Add a 'Settings' link below the plugin name on the plugins page
 */
function hipchat_plugin_action_links($links, $file) {
  if (basename($file) == basename(__FILE__)) {
    $l = '<a href="options-general.php?page=hipchat.php">Settings</a>';
    array_unshift($links, $l);
  }
  return $links;
}

function hipchat_settings_page() {
  $updated = null;
  $error = null;

  if ($_POST) {
    $auth_token = trim($_POST['auth_token']);
    $from = trim($_POST['from']);
    $room = trim($_POST['room']);
    $comments = $_POST['comments'];
    $comments_content = $_POST['comments_content'];

    // make sure token is valid and room exists
    $hc = new HipChat($auth_token);
    $successful = false;
    try {
      $r = $hc->message_room($room, $from, "Plugin enabled successfully.");
      if ($r) {
        $successful = true;
      }
    } catch (HipChat_Exception $e) {
      // token must have failed
    }

    if (!$successful) {
      $error = 'Bad auth token or room name.';
    } else if (!$from) {
      $error = 'Please enter a "From Name"';
    } else if (!$room) {
      $error = 'Please enter a "Room Name"';
    } else if (strlen($from) > 15) {
      $error = '"From Name" must be less than 15 characters.';
    } else {
      update_option('hipchat_auth_token', $auth_token);
      update_option('hipchat_from', $from);
      update_option('hipchat_room', $room);
      update_option('hipchat_notify_comments', $comments ? true : false);
      update_option('hipchat_notify_comments_content', $comments_content ? true : false);
      $updated = 'Settings saved! Auth token is valid and room exists.';
    }
  } else {
    $auth_token = get_option('hipchat_auth_token');
    $from = get_option('hipchat_from');
    $room = get_option('hipchat_room');
    $comments = get_option('hipchat_notify_comments');
    $comments_content = get_option('hipchat_notify_comments_content');

    if (!$from) {
      $from = 'WordPress';
    }
  }

  include 'hipchat-settings-template.php';
}

function hipchat_setup_options() {
  add_option('hipchat_auth_token');
  add_option('hipchat_from', 'WordPress');
  add_option('hipchat_room');
  add_option('hipchat_notify_comments');
  add_option('hipchat_notify_comments_content');
}

// Attach stuff
add_action('admin_menu', 'hipchat_menu');
add_action('draft_to_publish', 'hipchat_send_publish_notification');
add_action('pending_to_publish', 'hipchat_send_publish_notification');
add_action('new_to_publish', 'hipchat_send_publish_notification');
add_action('future_to_publish', 'hipchat_send_publish_notification');
add_filter('plugin_action_links', 'hipchat_plugin_action_links', 10, 2);

if (get_option('hipchat_notify_comments'))
    add_action('comment_post', 'hipchat_send_comment_notification', 10, 2);

register_activation_hook(__FILE__, 'hipchat_setup_options');

?>
