<?php
/**
 * Notification Events
 * =====================
 * Elgg events registered with the function elgg_register_notification_event() 
 * trigger the notification system. See the documentation for that function for
 * more details.
 *  
 * 
 * Notification Methods
 * ======================
 * 
 *   Delivery methods
 *   -----------------
 *   Notification delivery methods are registered with the function 
 *   elgg_register_notification_method(). 
 * 
 *   Message content
 *   ----------------
 *   The message is determined through a plugin hook that is triggered for each
 *   notification: 'prepare', 'notification:[event name]'. The event name is of 
 *   the form [action]:[type]:[subtype]. For example, the publish event for a blog
 *   would be named 'publish:object:object'. The params for the plugin hook
 *   has the keys 'event', 'method', 'recipient', and 'language'.
 * 
 *   The plugin hook callback modifies a Elgg_Notifications_Notification object
 *   that holds the message content.
 * 
 *   Sending the notification
 *   ------------------------
 *   Plugins that register a delivery method should also register for the plugin
 *   hook for that method. The hook is named 'send', 'notification:[method name]'.
 *   It receives the notification object in the params array and should return 
 *   a boolean to indicate whether the message was sent.
 * 
 * 
 * Subscriptions
 * ================
 * Users subscribe to receive notifications based on container and delivery method.
 * 
 * 
 * @package Elgg.Core
 * @subpackage Notifications
 */

/**
 * Register a notification event
 *
 * Elgg sends notifications for the items that have been registered with this
 * function. For example, if you want notifications to be sent when a bookmark
 * has been created or updated, call the function like this:
 *
 * 	   elgg_register_notification_event('object', 'bookmarks', array('create', 'update'));
 *
 * If you want notifications sent when a user friends another user:
 *
 * 	   elgg_register_notification_event('relationship', 'friend');
 *
 * @param string $object_type    'object', 'user', 'group', 'site', 'annotation', 'relationship'
 * @param string $object_subtype The subtype or name of the entity, annotation or relationship
 * @param array  $actions        Array of actions or empty array for the action event.
 *                                An event is usually described by the first string passed
 *                                to elgg_trigger_event(). Examples include
 *                                'create', 'update', and 'publish'. The default is 'create'.
 * @return void
 * @since 1.9
 */
function elgg_register_notification_event($object_type, $object_subtype, array $actions = array()) {
	_elgg_services()->notifications->registerEvent($object_type, $object_subtype, $actions);
}

/**
 * Unregister a notification event
 *
 * @param string $object_type    'object', 'user', 'group', 'site', 'annotation', 'relationship'
 * @param string $object_subtype The type of the entity or the subtype of the annotation or relationship
 * @return bool
 * @since 1.9
 */
function elgg_unregister_notification_event($object_type, $object_subtype) {
	return _elgg_services()->notifications->unregisterEvent($object_type, $object_subtype);
}

/**
 * Register a delivery method for notifications
 * 
 * Register for the 'send', 'notification:[method name]' plugin hook to handle
 * sending a notification. A notification object is in the params array for the
 * hook with the key 'notification'. See Elgg_Notifications_Notification.
 *
 * @param string $name The notification method name
 * @return void
 * @see elgg_unregister_notification_method()
 * @since 1.9
 */
function elgg_register_notification_method($name) {
	_elgg_services()->notifications->registerMethod($name);
}

/**
 * Unregister a delivery method for notifications
 *
 * @param string $name The notification method name
 * @return bool
 * @see elgg_register_notification_method()
 * @since 1.9
 */
function elgg_unregister_notification_method($name) {
	return _elgg_services()->notifications->unregisterMethod($name);
}

/**
 * Queue a notification event for later handling
 *
 * Checks to see if this event has been registered for notifications.
 * If so, it adds the event to a notification queue.
 *
 * This function triggers the 'enqueue', 'notification' hook.
 *
 * @param string   $action The name of the action
 * @param ElggData $object The object of the event
 * @return void
 * @access private
 * @since 1.9
 */
function _elgg_enqueue_notification_event($action, $type, $object) {
	return _elgg_services()->notifications->enqueueEvent($action, $type, $object);
}

/**
 * @access private
 */
function _elgg_notifications_cron() {
	// calculate when we should stop
	// @todo make configurable?
	$stop_time = time() + 45;
	_elgg_services()->notifications->processQueue($stop_time);
}

/**
 * @access private
 */
function _elgg_notifications_init() {
	elgg_register_plugin_hook_handler('cron', 'minute', '_elgg_notifications_cron', 100);
	elgg_register_event_handler('all', 'all', '_elgg_enqueue_notification_event');
}

elgg_register_event_handler('init', 'system', '_elgg_notifications_init');





/**
 * Notifications
 * This file contains classes and functions which allow plugins to register and send notifications.
 *
 * There are notification methods which are provided out of the box
 * (see notification_init() ). Each method is identified by a string, e.g. "email".
 *
 * To register an event use register_notification_handler() and pass the method name and a
 * handler function.
 *
 * To send a notification call notify() passing it the method you wish to use combined with a
 * number of method specific addressing parameters.
 *
 * Catch NotificationException to trap errors.
 *
 * @package Elgg.Core
 * @subpackage Notifications
 */

/** Notification handlers */
global $NOTIFICATION_HANDLERS;
$NOTIFICATION_HANDLERS = array();

/**
 * This function registers a handler for a given notification type (eg "email")
 *
 * @param string $method  The method
 * @param string $handler The handler function, in the format
 *                        "handler(ElggEntity $from, ElggUser $to, $subject,
 *                        $message, array $params = NULL)". This function should
 *                        return false on failure, and true/a tracking message ID on success.
 * @param array  $params  An associated array of other parameters for this handler
 *                        defining some properties eg. supported msg length or rich text support.
 *
 * @return bool
 */
function register_notification_handler($method, $handler, $params = NULL) {
	global $NOTIFICATION_HANDLERS;

	if (is_callable($handler, true)) {
		$NOTIFICATION_HANDLERS[$method] = new stdClass;

		$NOTIFICATION_HANDLERS[$method]->handler = $handler;
		if ($params) {
			foreach ($params as $k => $v) {
				$NOTIFICATION_HANDLERS[$method]->$k = $v;
			}
		}

		return true;
	}

	return false;
}

/**
 * This function unregisters a handler for a given notification type (eg "email")
 *
 * @param string $method The method
 *
 * @return void
 * @since 1.7.1
 */
function unregister_notification_handler($method) {
	global $NOTIFICATION_HANDLERS;

	if (isset($NOTIFICATION_HANDLERS[$method])) {
		unset($NOTIFICATION_HANDLERS[$method]);
	}
}

/**
 * Notify a user via their preferences.
 *
 * @param mixed  $to               Either a guid or an array of guid's to notify.
 * @param int    $from             GUID of the sender, which may be a user, site or object.
 * @param string $subject          Message subject.
 * @param string $message          Message body.
 * @param array  $params           Misc additional parameters specific to various methods.
 * @param mixed  $methods_override A string, or an array of strings specifying the delivery
 *                                 methods to use - or leave blank for delivery using the
 *                                 user's chosen delivery methods.
 *
 * @return array Compound array of each delivery user/delivery method's success or failure.
 * @throws NotificationException
 */
function notify_user($to, $from, $subject, $message, array $params = NULL, $methods_override = "") {
	global $NOTIFICATION_HANDLERS;

	// Sanitise
	if (!is_array($to)) {
		$to = array((int)$to);
	}
	$from = (int)$from;
	//$subject = sanitise_string($subject);

	// Get notification methods
	if (($methods_override) && (!is_array($methods_override))) {
		$methods_override = array($methods_override);
	}

	$result = array();

	foreach ($to as $guid) {
		// Results for a user are...
		$result[$guid] = array();

		if ($guid) { // Is the guid > 0?
			// Are we overriding delivery?
			$methods = $methods_override;
			if (!$methods) {
				$tmp = (array)get_user_notification_settings($guid);
				$methods = array();
				foreach ($tmp as $k => $v) {
					// Add method if method is turned on for user!
					if ($v) {
						$methods[] = $k;
					}
				}
			}

			if ($methods) {
				// Deliver
				foreach ($methods as $method) {

					if (!isset($NOTIFICATION_HANDLERS[$method])) {
						continue;
					}

					// Extract method details from list
					$details = $NOTIFICATION_HANDLERS[$method];
					$handler = $details->handler;
					/* @var callable $handler */

					if ((!$NOTIFICATION_HANDLERS[$method]) || (!$handler) || (!is_callable($handler))) {
						error_log("No handler registered for the method $method");
					}

					elgg_log("Sending message to $guid using $method");

					// Trigger handler and retrieve result.
					try {
						$result[$guid][$method] = call_user_func($handler,
							$from ? get_entity($from) : NULL, 	// From entity
							get_entity($guid), 					// To entity
							$subject,							// The subject
							$message, 			// Message
							$params								// Params
						);
					} catch (Exception $e) {
						error_log($e->getMessage());
					}

				}
			}
		}
	}

	return $result;
}

/**
 * Get the notification settings for a given user.
 *
 * @param int $user_guid The user id
 *
 * @return stdClass
 */
function get_user_notification_settings($user_guid = 0) {
	$user_guid = (int)$user_guid;

	if ($user_guid == 0) {
		$user_guid = elgg_get_logged_in_user_guid();
	}

	// @todo: there should be a better way now that metadata is cached. E.g. just query for MD names, then
	// query user object directly
	$all_metadata = elgg_get_metadata(array(
		'guid' => $user_guid,
		'limit' => 0
	));
	if ($all_metadata) {
		$prefix = "notification:method:";
		$return = new stdClass;

		foreach ($all_metadata as $meta) {
			$name = substr($meta->name, strlen($prefix));
			$value = $meta->value;

			if (strpos($meta->name, $prefix) === 0) {
				$return->$name = $value;
			}
		}

		return $return;
	}

	return false;
}

/**
 * Set a user notification pref.
 *
 * @param int    $user_guid The user id.
 * @param string $method    The delivery method (eg. email)
 * @param bool   $value     On(true) or off(false).
 *
 * @return bool
 */
function set_user_notification_setting($user_guid, $method, $value) {
	$user_guid = (int)$user_guid;
	$method = sanitise_string($method);

	$user = get_entity($user_guid);
	if (!$user) {
		$user = elgg_get_logged_in_user_entity();
	}

	if (($user) && ($user instanceof ElggUser)) {
		$prefix = "notification:method:$method";
		$user->$prefix = $value;
		$user->save();

		return true;
	}

	return false;
}

/**
 * Send a notification via email.
 *
 * @param ElggEntity $from    The from user/site/object
 * @param ElggUser   $to      To which user?
 * @param string     $subject The subject of the message.
 * @param string     $message The message body
 * @param array      $params  Optional parameters (none taken in this instance)
 *
 * @return bool
 * @throws NotificationException
 * @access private
 */
function email_notify_handler(ElggEntity $from, ElggUser $to, $subject, $message,
array $params = NULL) {

	global $CONFIG;

	if (!$from) {
		$msg = "Missing a required parameter, '" . 'from' . "'";
		throw new NotificationException($msg);
	}

	if (!$to) {
		$msg = "Missing a required parameter, '" . 'to' . "'";
		throw new NotificationException($msg);
	}

	if ($to->email == "") {
		$msg = "Could not get the email address for GUID:" . $to->guid;
		throw new NotificationException($msg);
	}

	// To
	$to = $to->email;

	// From
	$site = elgg_get_site_entity();
	// If there's an email address, use it - but only if its not from a user.
	if (!($from instanceof ElggUser) && $from->email) {
		$from = $from->email;
	} else if ($site && $site->email) {
		// Use email address of current site if we cannot use sender's email
		$from = $site->email;
	} else {
		// If all else fails, use the domain of the site.
		$from = 'noreply@' . get_site_domain($CONFIG->site_guid);
	}

	return elgg_send_email($from, $to, $subject, $message);
}

/**
 * Send an email to any email address
 *
 * @param string $from    Email address or string: "name <email>"
 * @param string $to      Email address or string: "name <email>"
 * @param string $subject The subject of the message
 * @param string $body    The message body
 * @param array  $params  Optional parameters (none used in this function)
 *
 * @return bool
 * @throws NotificationException
 * @since 1.7.2
 */
function elgg_send_email($from, $to, $subject, $body, array $params = NULL) {
	global $CONFIG;

	if (!$from) {
		$msg = "Missing a required parameter, '" . 'from' . "'";
		throw new NotificationException($msg);
	}

	if (!$to) {
		$msg = "Missing a required parameter, '" . 'to' . "'";
		throw new NotificationException($msg);
	}
	
	$header_fields = array(
		"Content-Type" => "text/plain; charset=UTF-8; format=flowed",
		"MIME-Version" => "1.0",
		"Content-Transfer-Encoding" => "8bit",
	);

	// return TRUE/FALSE to stop elgg_send_email() from sending
	$mail_params = array(
		'to' => $to,
		'from' => $from,
		'subject' => $subject,
		'body' => $body,
		'headers' => $header_fields,
		'params' => $params,
	);

	// $mail_params is passed as both params and return value. The former is for backwards
	// compatibility. The latter is so handlers can now alter the contents/headers of
	// the email by returning the array
	$result = elgg_trigger_plugin_hook('email', 'system', $mail_params, $mail_params);
	if (is_array($result)) {
		foreach (array('to', 'from', 'subject', 'body', 'headers') as $key) {
			if (isset($result[$key])) {
				${$key} = $result[$key];
			}
		}
	} elseif ($result !== NULL) {
		return $result;
	}

	$header_eol = "\r\n";
	if (isset($CONFIG->broken_mta) && $CONFIG->broken_mta) {
		// Allow non-RFC 2822 mail headers to support some broken MTAs
		$header_eol = "\n";
	}

	// Windows is somewhat broken, so we use just address for to and from
	if (strtolower(substr(PHP_OS, 0, 3)) == 'win') {
		// strip name from to and from
		if (strpos($to, '<')) {
			preg_match('/<(.*)>/', $to, $matches);
			$to = $matches[1];
		}
		if (strpos($from, '<')) {
			preg_match('/<(.*)>/', $from, $matches);
			$from = $matches[1];
		}
	}

	$headers = "From: $from{$header_eol}";
	foreach ($header_fields as $header_field => $header_value) {
		$headers .= "$header_field: $header_value{$header_eol}";
	}

	// Sanitise subject by stripping line endings
	$subject = preg_replace("/(\r\n|\r|\n)/", " ", $subject);
	// this is because Elgg encodes everything and matches what is done with body
	$subject = html_entity_decode($subject, ENT_COMPAT, 'UTF-8'); // Decode any html entities
	if (is_callable('mb_encode_mimeheader')) {
		$subject = mb_encode_mimeheader($subject, "UTF-8", "B");
	}

	// Format message
	$body = html_entity_decode($body, ENT_COMPAT, 'UTF-8'); // Decode any html entities
	$body = elgg_strip_tags($body); // Strip tags from message
	$body = preg_replace("/(\r\n|\r)/", "\n", $body); // Convert to unix line endings in body
	$body = preg_replace("/^From/", ">From", $body); // Change lines starting with From to >From
	$body = wordwrap($body);

	return mail($to, $subject, $body, $headers);
}

/**
 * Correctly initialise notifications and register the email handler.
 *
 * @return void
 * @access private
 */
function notification_init() {
	// Register a notification handler for the default email method
	register_notification_handler("email", "email_notify_handler");

	// Add settings view to user settings & register action
	elgg_extend_view('forms/account/settings', 'core/settings/account/notifications');

	elgg_register_plugin_hook_handler('usersettings:save', 'user', 'notification_user_settings_save');
}

/**
 * Includes the action to save user notifications
 *
 * @return void
 * @todo why can't this call action(...)?
 * @access private
 */
function notification_user_settings_save() {
	global $CONFIG;
	//@todo Wha??
	include($CONFIG->path . "actions/notifications/settings/usersettings/save.php");
}

/**
 * Register an entity type and subtype to be eligible for notifications
 *
 * @param string $entity_type    The type of entity
 * @param string $object_subtype Its subtype
 * @param string $language_name  Its localized notification string (eg "New blog post")
 *
 * @return void
 */
function register_notification_object($entity_type, $object_subtype, $language_name) {
	global $CONFIG;

	if ($entity_type == '') {
		$entity_type = '__BLANK__';
	}
	if ($object_subtype == '') {
		$object_subtype = '__BLANK__';
	}

	if (!isset($CONFIG->register_objects)) {
		$CONFIG->register_objects = array();
	}

	if (!isset($CONFIG->register_objects[$entity_type])) {
		$CONFIG->register_objects[$entity_type] = array();
	}

	$CONFIG->register_objects[$entity_type][$object_subtype] = $language_name;
}

/**
 * Establish a 'notify' relationship between the user and a content author
 *
 * @param int $user_guid   The GUID of the user who wants to follow a user's content
 * @param int $author_guid The GUID of the user whose content the user wants to follow
 *
 * @return bool Depending on success
 */
function register_notification_interest($user_guid, $author_guid) {
	return add_entity_relationship($user_guid, 'notify', $author_guid);
}

/**
 * Remove a 'notify' relationship between the user and a content author
 *
 * @param int $user_guid   The GUID of the user who is following a user's content
 * @param int $author_guid The GUID of the user whose content the user wants to unfollow
 *
 * @return bool Depending on success
 */
function remove_notification_interest($user_guid, $author_guid) {
	return remove_entity_relationship($user_guid, 'notify', $author_guid);
}

/**
 * Automatically triggered notification on 'create' events that looks at registered
 * objects and attempts to send notifications to anybody who's interested
 *
 * @see register_notification_object
 *
 * @param string $event       create
 * @param string $object_type mixed
 * @param mixed  $object      The object created
 *
 * @return bool
 * @access private
 */
function object_notifications($event, $object_type, $object) {
	// We only want to trigger notification events for ElggEntities
	if ($object instanceof ElggEntity) {
		/* @var ElggEntity $object */

		// Get config data
		global $CONFIG, $SESSION, $NOTIFICATION_HANDLERS;

		$hookresult = elgg_trigger_plugin_hook('object:notifications', $object_type, array(
			'event' => $event,
			'object_type' => $object_type,
			'object' => $object,
		), false);
		if ($hookresult === true) {
			return true;
		}

		// Have we registered notifications for this type of entity?
		$object_type = $object->getType();
		if (empty($object_type)) {
			$object_type = '__BLANK__';
		}

		$object_subtype = $object->getSubtype();
		if (empty($object_subtype)) {
			$object_subtype = '__BLANK__';
		}

		if (isset($CONFIG->register_objects[$object_type][$object_subtype])) {
			$subject = $CONFIG->register_objects[$object_type][$object_subtype];
			$string = $subject . ": " . $object->getURL();

			// Get users interested in content from this person and notify them
			// (Person defined by container_guid so we can also subscribe to groups if we want)
			foreach ($NOTIFICATION_HANDLERS as $method => $foo) {
				$interested_users = elgg_get_entities_from_relationship(array(
					'site_guids' => ELGG_ENTITIES_ANY_VALUE,
					'relationship' => 'notify' . $method,
					'relationship_guid' => $object->container_guid,
					'inverse_relationship' => TRUE,
					'type' => 'user',
					'limit' => false
				));
				/* @var ElggUser[] $interested_users */

				if ($interested_users && is_array($interested_users)) {
					foreach ($interested_users as $user) {
						if ($user instanceof ElggUser && !$user->isBanned()) {
							if (($user->guid != $SESSION['user']->guid) && has_access_to_entity($object, $user)
							&& $object->access_id != ACCESS_PRIVATE) {
								$body = elgg_trigger_plugin_hook('notify:entity:message', $object->getType(), array(
									'entity' => $object,
									'to_entity' => $user,
									'method' => $method), $string);
								if (empty($body) && $body !== false) {
									$body = $string;
								}
								if ($body !== false) {
									notify_user($user->guid, $object->container_guid, $subject, $body,
										null, array($method));
								}
							}
						}
					}
				}
			}
		}
	}
}

// Register a startup event
elgg_register_event_handler('init', 'system', 'notification_init', 0);
elgg_register_event_handler('create', 'object', 'object_notifications');
