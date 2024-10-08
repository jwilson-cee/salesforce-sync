<?php
/**
 * This is a class for syncing local data with remote Salesforce objects
 *
 * Classes that inherit this class can have functions for syncing (pushing and pulling) with Salesforce object fields.
 * The functions used for pushing and pulling Salesforce object fields must use this naming convention:
 *
 * public function push_<Salesforce field name>()
 *
 * public function pull_<Salesforce field name>($value)
 *
 * The 'push_...()' functions should return a value that is to be pushed to the corresponding <Salesforce field name> of the Salesforce object.
 * The 'pull_...($value)' functions will have an argument containing the value corresponding to the <Salesforce field name> of the Salesforce object that can be used to update local data.
 *
 * It is not required to have both a 'push_...()' and a 'pull_...()' function for a given Salesforce field. Either or both can be used according to what is needed for syncing in either direction.
 *
 *
 * This class can also be used on it's own using the chaining functions:
 *
 * SyncObject::objectName('Contact')->id('00A10000001aBCde')->pushFields(['FirstName' => 'John', 'LastName' => 'Doe'])->push();
 *
 * $salesforceContact = SyncObject::objectName('Contact')->id('00A10000001aBCde')->pullFields(['FirstName', 'LastName'])->pull();
 */

namespace CEE\Salesforce;

use CEE\Salesforce\ForceDotCom\Results\BasicResult;
use CEE\Salesforce\Laravel\Facades\Salesforce;

class SyncObject
{
	/**
	 * The name of the Salesforce object
	 *
	 * @var string
	 */
	public $objectName;

	/**
	 * Id of the remote Salesforce object
	 *
	 * @var string
	 */
	public $id;

	/**
	 * List of the Salesforce object fields that are required and will always be included when pushing a new object to Salesforce
	 * Note: There must be 'push_...()' field functions defined for these fields
	 *
	 * @var array
	 */
	public $requiredFields = [];

	/**
	 * Assoc array of the Salesforce object fields and corresponding values to be pushed
	 * Note: Populating this parameter will override all the 'push_...()' field functions
	 *
	 * @var array
	 */
	public $pushFields = [];

	/**
	 * List of the Salesforce object fields to be pulled
	 * Note: Populating this parameter will override all the 'pull...()' field functions
	 *
	 * @var array
	 */
	public $pullFields = [];

	/**
	 * List of Salesforce object fields that are generated by the 'push_...()' functions to be pushed - excluding all other fields
	 * Note: This will be reset to an empty array after $this->push() is called
	 *
	 * @var array
	 */
	public $pushOnlyFields = [];

	/**
	 * List of Salesforce object fields that are generated by the 'pull_...($value)' functions to be pulled - excluding all other fields
	 * Note: This will be reset to an empty array after $this->pull() is called
	 *
	 * @var array
	 */
	public $pullOnlyFields = [];

	/**
	 * List of Salesforce object field names that are to be nulled when pushed.
	 *
	 * @var array
	 */
	public $fieldsToNull = [];

	/**
	 * The number of tries the push action should take when failing
	 *
	 * @var int
	 */
	public $retry = 0;

	private $_remoteObject;

	/**
	 * This a random token that can be used for ignoring a Salesforce object field when pushing
	 *
	 * Return self::IGNORE_VALUE in a 'push_...()' function when you don't want a field to be pushed
	 * For example when a local value is null and you don't want the remote Salesforce object field to be set to null:
	 *     return $value === null ? self::IGNORE_VALUE : $value;
	 */
	const IGNORE_VALUE = '5c363d1509c452e88d954859fd6';

	/**
	 * Static function to create a SyncObject with a Salesforce object name
	 *
	 * @param string $objectName
	 * @return SyncObject
	 */
	public static function objectName($objectName) {
		$self = new static();
		$self->objectName = $objectName;
		return $self;
	}

	/**
	 * Chaining function to set the Salesforce object id
	 *
	 * @param string $id
	 * @return $this
	 */
	public function id($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * Chaining function to set the list of Salesforce object field names that are to be pushed only
	 * Note: $this->pushOnlyFields will be reset to an empty array after $this->push() is called
	 *
	 * @param array $fieldNames
	 * @return $this
	 */
	public function pushOnlyFields($fieldNames) {
		$this->pushOnlyFields = $fieldNames;
		return $this;
	}

	/**
	 * Chaining function to set the list of Salesforce object field names that are to pulled only
	 * Note: $this->pullOnlyFields will be reset to an empty array after $this->pull() is called
	 *
	 * @param array $fieldNames
	 * @return $this
	 */
	public function pullOnlyFields($fieldNames) {
		$this->pullOnlyFields = $fieldNames;
		return $this;
	}

	/**
	 * Chaining function to set the list of Salesforce object field names that are to be nulled when pushed
	 *
	 * @param array $fieldNames
	 * @return $this
	 */
	public function fieldsToNull($fieldNames) {
		$this->fieldsToNull = $fieldNames;
		return $this;
	}

	/**
	 * Chaining function to manually set the remote object to be pulled. The $pullOnlyFields will be set to the keys of $object.
	 *
	 * @param \stdClass|array $object
	 * @return $this
	 */
	public function withRemoteObject($object) {
		if(is_array($object)) {
			$this->pullOnlyFields = array_keys($object);
			$object = (object) $object;
		} else {
			$this->pullOnlyFields = array_keys(get_object_vars($object));
		}
		$this->_remoteObject = $object;
		return $this;
	}

	/**
	 * Chaining function to set the retry limit for the push actions
	 *
	 * @param int $limit
	 * @return $this
	 */
	public function retry($limit=10) {
		$this->retry = $limit;
		return $this;
	}

	/**
	 * The default 'push_...()' function for returning the local value of the Salesforce object id
	 * Note: returning null will create a new object in Salesforce
	 *
	 * @return string
	 */
	public function push_Id() {
		return $this->id;
	}

	/**
	 * Returns the list of Salesforce object field names that are to be pushed
	 * OR
	 * Can be used as a chaining function to set list of Salesforce object field names that are to be pushed
	 *
	 * @param array $pushFields
	 * @return $this|array
	 */
	public function pushFields($pushFields=[]) {
		if(count($pushFields)) {
			// set and return as a chaining function
			$this->pushFields = $pushFields;
			return $this;
		}
		if(count($this->pushFields)) {
			// return manually populated pushFields
			return $this->pushFields;
		}
		// return field list derived from 'push_...()' functions
		preg_match_all('/(?<=^|;)push_([^;]+?)(;|$)/', implode(';', get_class_methods(static::class)), $matches);
		if(count($this->pushOnlyFields)) {
			$pushOnlyFields = $this->push_Id() ? array_unique(array_merge($this->pushOnlyFields, ['Id'])) : array_unique(array_merge($this->pushOnlyFields, $this->requiredFields));
			return array_intersect($pushOnlyFields, $matches[1]);
		}
		return $matches[1];
	}

	/**
	 * Returns the list of Salesforce object field names that are to be pulled
	 * OR
	 * Can be used as a chaining function to set list of Salesforce object field names that are to be pulled
	 *
	 * @param array $pullFields
	 * @return $this|array
	 */
	public function pullFields($pullFields=[]) {
		if(count($pullFields)) {
			// set and return as a chaining function
			$this->pullFields = $pullFields;
			$this->pullFields[] = 'Id';
			return $this;
		}
		if(count($this->pullFields)) {
			// return manually populated pullFields
			return $this->pullFields;
		}
		// return field list derived from 'pull...()' functions
		preg_match_all('/(?<=^|;)pull_([^;]+?)(;|$)/', implode(';', get_class_methods(static::class)), $matches);
		return count($this->pullOnlyFields) ? array_intersect($this->pullOnlyFields, $matches[1]) : $matches[1];
	}

	/**
	 * Returns an object with it's values set to the local data, formatted to be used for a Salesforce push
	 *
	 * @return \stdClass
	 */
	public function localObject() {
		$object = new \stdClass();
		$object->fieldsToNull = $this->fieldsToNull;
		$object->Id = $this->push_Id();
		if(count($this->pushFields)) {
			foreach($this->pushFields as $fieldName => $value) {
				if($value !== self::IGNORE_VALUE) {
					if(isset($value)) {
						$object->{$fieldName} = $value;
					} else if(isset($object->Id)) {
						$object->fieldsToNull[] = $fieldName;
					}
				}
			}
		} else {
			foreach($this->pushFields() as $fieldName) {
				if($fieldName != 'Id') {
					$value = $this->{'push_'.$fieldName}();
					if($value !== self::IGNORE_VALUE) {
						if(!is_null($value)) {
							$object->{$fieldName} = $this->{'push_'.$fieldName}();
						} else {
							$object->fieldsToNull[] = $fieldName;
						}
					}
					if(!isset($object->Id)) {
						unset($object->fieldsToNull);
					}
				}
			}
		}
		return $object;
	}

	/**
	 * Returns an object fetched from the remote Salesforce object
	 *
	 * @param string $id
	 * @return null|\stdClass
	 */
	public function remoteObject($id=null) {
		if(!$this->_remoteObject && $this->objectName) {
			$id = $id ?: $this->push_Id();
			$pullFields = $this->pullFields();
			if($id && count($pullFields)) {
				$result = Salesforce::query("SELECT ".implode(', ', array_unique($pullFields))." FROM ".$this->objectName." WHERE Id = '".$id."'");
				$this->_remoteObject = count($result->records) > 0 ? $result->records[0] : null;
			}
		}
		return $this->_remoteObject;
	}

	/**
	 * Push local data to remote Salesforce object
	 *
	 * @param array $objects
	 * @return null|array
	 * @throws SalesforceSyncException
	 */
	public function push($objects=null) {
		if($this->objectName) {
			$objects = $objects ?: [$this->localObject()];
			$this->pushOnlyFields = [];
			if(count($objects)) {
				$objects = collect($objects);
				$updateObjects = $objects->filter(function($object) { return isset($object->Id); });
				$createObjects = collect();
				if($objects->count() != $updateObjects->count()) {
					$createObjects = $objects->filter(function($object) { return !isset($object->Id); });
				}
				$result = [];
				if($updateObjects->count()) {
					$result['updated'] = $this->attemptSalesforceSync('update', $updateObjects->toArray());
					if(!$createObjects->count()) {
						return $result['updated'];
					}
				}
				if($createObjects->count()) {
					$result['created'] = $this->attemptSalesforceSync('create', $createObjects->toArray());
					if(!$updateObjects->count()) {
						return $result['created'];
					}
				}
				return $result;
			}
		}
		return null;
	}

	/**
	 * Pull remote Salesforce object to write to local data
	 *
	 * @param string $id
	 * @return null|\stdClass
	 */
	public function pull($id=null) {
		$id = $id ?: $this->push_Id();
		if($id) {
			$object = $this->remoteObject($id);
			if($object && !count($this->pullFields)) {
				// Only execute 'pull_...($value)' methods if $this->pullFields wasn't manually populated
				foreach($this->pullFields() as $fieldName) {
					$this->{'pull_'.$fieldName}(property_exists($object, $fieldName) ? $object->{$fieldName} : null);
				}
			}
			$this->pullOnlyFields = [];
			$this->_remoteObject = null;
			return $object;
		}
		return null;
	}

	/**
	 * Delete remote Salesforce objects
	 *
	 * @param array $ids
	 * @return array|null
	 * @throws SalesforceSyncException
	 */
	public function delete($ids=null) {
		$ids = is_array($ids) ? $ids : [$this->push_Id()];
		return count($ids) ? static::validateResult(Salesforce::delete($ids)) : null;
	}

	/**
	 * Check if a Salesforce SaveResult has all successful responses
	 *
	 * @param array|\stdClass|BasicResult $result
	 * @return bool
	 */
	public static function isSuccessfulResult($result) {
		if(empty($result) || (is_array($result) && count($result) === 0)) {
			return false;
		}
		$results = is_array($result) ? $result : [$result];
		foreach($results as $response) {
			if(!isset($response->success) || !$response->success) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Throw an exception if a Salesforce SaveResult is not successful
	 *
	 * @param array|\stdClass|BasicResult $result
	 * @param SyncObject|null $syncObject
	 * @param array|null $objects
	 * @return array
	 * @throws SalesforceSyncException
	 */
	public static function validateResult($result, $syncObject=null, $objects=null) {
		if(!static::isSuccessfulResult($result)) {
			throw new SalesforceSyncException($result, $syncObject, $objects);
		}
		return $result;
	}
	/**
	 * Static version of attemptSalesforceSync()
	 *
	 * @param string $action
	 * @param string $objectName
	 * @param array $objects
	 * @param int $retryLimit
	 * @return array|BasicResult|\stdClass
	 * @throws SalesforceSyncException
	 */
	public static function attemptSync( string $action, string $objectName, array $objects, int $retryLimit=10) {
		return SyncObject::objectName($objectName)->retry($retryLimit)->attemptSalesforceSync($action, $objects);
	}

	/**
	 * Attempt a Salesforce sync action. Repeat the action if the error is an object lock or network issue.
	 *
	 * @param string $action
	 * @param array $objects
	 * @param array $attempts
	 * @return array|BasicResult|\stdClass
	 * @throws SalesforceSyncException
	 */
	public function attemptSalesforceSync( string $action, array $objects, array $attempts=[]) {
		try {
			$result = Salesforce::$action($objects, $this->objectName);
		} catch(\SoapFault $exception) {
			$error = new \stdClass();
			$error->statusCode = 'SOAP_FAULT';
			$result = new \stdClass();
			$result->success = false;
			$result->errors = [$error];
			$result->message = $exception->faultstring;
		}
		if(!$this->retry) {
			return static::validateResult($result, $this, $objects);
		}
		if(!static::isSuccessfulResult($result)) {
			if(count($attempts) < $this->retry) {
				if(static::resultHasErrorCode($result, ['UNABLE_TO_LOCK_ROW', 'REQUEST_RUNNING_TOO_LONG', 'SOAP_FAULT'])) {
					$attempts[] = $result;
					return $this->attemptSalesforceSync($action, $objects, $attempts);
				}
			}
			throw new SalesforceSyncException($result, $this, $objects, $attempts);
		}
		return $result;
	}

	/**
	 * Check to see if a Salesforce SaveResult has a specific error code or codes
	 *
	 * @param array|\stdClass|BasicResult $result
	 * @param string|array $code
	 * @return bool
	 */
	public static function resultHasErrorCode($result, $code) {
		$codes = is_array($code) ? $code : [$code];
		if(count($codes)) {
			$results = is_array($result) ? $result : [$result];
			foreach($results as $response) {
				if(isset($response->errors) && is_array($response->errors)) {
					foreach($response->errors as $error) {
						if(in_array($error->statusCode, $codes)) {
							return true;
						}
					}
				}
			}
		}
		return false;
	}

	/**
	 * Convert a value into a number format that Salesforce will accept
	 *
	 * @param mixed $value
	 * @param int $precision
	 * @return string
	 */
	public static function valueToNumber($value, $precision = 0) {
		return empty($value) && !is_numeric($value) ? null : number_format(floatval($value), $precision, '.', '');
	}

}
