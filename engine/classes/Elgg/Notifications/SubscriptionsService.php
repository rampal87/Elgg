<?php

/**
 * WARNING: API IN FLUX. DO NOT USE DIRECTLY.
 *
 * @access private
 *
 * @package    Elgg.Core
 * @subpackage Notifications
 * @since      1.9.0
 */
class Elgg_Notifications_SubscriptionsService {

	/**
	 *  Elgg has historically stored subscriptions as relationships with the prefix 'notify'
	 */
	const RELATIONSHIP_PREFIX = 'notify';

	/**
	 *  @var array Array of strings. Delivery names as registered with 
	 *             elgg_register_notification_method()
	 */
	public $methods;

	/** @var Elgg_Database */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param Elgg_Database $db      Database object
	 * @param array         $methods Notification delivery method names
	 */
	public function __construct(Elgg_Database $db, array $methods = array()) {
		$this->db = $db;
		$this->methods = $methods;
	}

	/**
	 * Get the subscriptions for this notification event
	 *
	 * The return array is of the form:
	 *
	 * array(
	 *     <user guid> => array('email', 'sms', 'ajax'),
	 * );
	 *
	 * @param Elgg_Notifications_Event $event Notification event
	 * @return array
	 */
	public function getSubscriptions(Elgg_Notifications_Event $event) {

		$subscriptions = array();

		if (!$this->methods) {
			return $subscriptions;
		}

		$object = $event->getObject();
		if (!$object) {
			return $subscriptions;
		}

		$prefixLength = strlen(self::RELATIONSHIP_PREFIX);
		$records = $this->getSubscriptionRecords($object->getContainerGUID());
		foreach ($records as $record) {
			$deliveryMethods = explode(',', $record->methods);
			$subscriptions[$record->guid] = substr_replace($deliveryMethods, '', 0, $prefixLength);
		}

		return $subscriptions;
	}

	/**
	 * Subscribe a user to notifications about a target entity
	 * 
	 * This method will return false if the subscription already exists.
	 * 
	 * @param int    $userGuid   The GUID of the user to subscribe to notifications
	 * @param string $method     The delivery method of the notifications
	 * @param int    $targetGuid The entity to receive notifications about
	 * @return boolean
	 */
	public function addSubscription($userGuid, $method, $targetGuid) {
		if (!in_array($method, $this->methods)) {
			return false;
		}
		$prefix = self::RELATIONSHIP_PREFIX;
		return add_entity_relationship($userGuid, "$prefix$method", $targetGuid);
	}

	/**
	 * Unsubscribe a user to notifications about a target entity
	 * 
	 * @param int    $userGuid   The GUID of the user to unsubscribe to notifications
	 * @param string $method     The delivery method of the notifications to stop
	 * @param int    $targetGuid The entity to stop receiving notifications about
	 * @return boolean
	 */
	public function removeSubscription($userGuid, $method, $targetGuid) {
		$prefix = self::RELATIONSHIP_PREFIX;
		return remove_entity_relationship($userGuid, "$prefix$method", $targetGuid);
	}

	/**
	 * Get subscription records from the database
	 *
	 * Records are an object with two vars: guid and methods with the latter
	 * being a comma-separated list of subscription relationship names.
	 *
	 * @param int $container_guid The GUID of the subscription target
	 * @return array
	 */
	protected function getSubscriptionRecords($container_guid) {

		$container_guid = $this->db->sanitizeInt($container_guid);

		// create IN clause
		$rels = $this->getMethodRelationships();
		if (!$rels) {
			return array();
		}
		array_walk($rels, array($this->db, 'sanitizeString'));
		$methods_string = "'" . implode("','", $rels) . "'";

		$db_prefix = $this->db->getTablePrefix();
		$query = "SELECT guid_one AS guid, GROUP_CONCAT(relationship SEPARATOR ',') AS methods
			FROM {$db_prefix}entity_relationships
			WHERE guid_two = $container_guid AND
					relationship IN ($methods_string) GROUP BY guid_one";
		return $this->db->getData($query);
	}

	/**
	 * Get the relationship names for notifications
	 * 
	 * @return array
	 */
	protected function getMethodRelationships() {
		$prefix = self::RELATIONSHIP_PREFIX;
		$names = array();
		foreach ($this->methods as $method) {
			$names[] = "$prefix$method";
		}
		return $names;
	}
}
