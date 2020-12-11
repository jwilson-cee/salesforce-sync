<?php

namespace CEE\Salesforce;

use Exception;

class SalesforceSyncException extends Exception
{
	public $result;
	public $syncObject;
	public $objects;
	public $attempts;

	/**
	 * SalesforceSyncException constructor
	 *
	 * @param array|\stdClass $result
	 * @param SyncObject $syncObject
	 * @param array $objects
	 * @param array $attempts
	 */
	public function __construct($result, $syncObject=null, $objects=null, $attempts=null) {
		$this->result = $result;
		$this->syncObject = $syncObject;
		$this->objects = $objects;
		$this->attempts = $attempts;
		parent::__construct('Salesforce Sync Failure: '.$this->getErrorMessage());
	}

	/**
	 * Compile error message from result responses and attempts
	 *
	 * @return string
	 */
	public function getErrorMessage() {
		$errorMessage = '';
		$results = is_array($this->result) ? $this->result : [$this->result];
		foreach($results as $result) {
			$errorMessage .= isset($result->message) ? $result->message.PHP_EOL : '';
			$errorMessage .= isset($result->id) ? 'Id: '.$result->id.' ' : '';
			if(isset($result->errors) && is_array($result->errors)) {
				foreach($result->errors as $error) {
					$fields = isset($error->fields) && is_array($error->fields) && count($error->fields) ? ' (Fields: '.implode(', ', $error->fields).')' : '';
					$errorMessage .= isset($error->statusCode) ? 'Code: '.$error->statusCode.' - ' : '';
					$errorMessage .= isset($error->message) ? $error->message.$fields.'; ' : '';
				}
			}
			$errorMessage .= PHP_EOL;
		}
		if(count($this->attempts)) {
			$errorMessage .= count($this->attempts).' Attempts:'.PHP_EOL;
			foreach($this->attempts as $attempt) {
				if(is_array($attempt)) {
					foreach($attempt as $result) {
						$errorMessage .= isset($result->id) ? 'Id: '.$result->id.' ' : '';
						if(isset($result->errors) && is_array($result->errors)) {
							foreach($result->errors as $error) {
								$errorMessage .= isset($error->statusCode) ? 'Code: '.$error->statusCode.'; ' : '';
							}
						}
					}
				}
				$errorMessage .= PHP_EOL;
			}
		}
		return $errorMessage;
	}

}