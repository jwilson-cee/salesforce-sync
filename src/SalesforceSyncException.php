<?php

namespace CEE\Salesforce;

use Exception;

class SalesforceSyncException extends Exception
{
	public $result;

	public function __construct($result) {
		$this->result = $result;
		parent::__construct('Salesforce Sync Failure: '.$this->getErrorMessages());
	}

	public function getErrorMessages() {
		$errorMessages = '';
		if(is_array($this->result)) {
			foreach($this->result as $result) {
				$errorMessages .= isset($result->id) ? 'Id: '.$result->id : '';
				if(isset($result->errors) && is_array($result->errors)) {
					foreach($result->errors as $error) {
						$fields = isset($error->fields) && is_array($error->fields) && count($error->fields) ? ' (Fields: '.implode(', ', $error->fields).')' : '';
						$errorMessages .= isset($error->message) ? $error->message.$fields.'; ' : '';
					}
				}
			}
		}
		return $errorMessages;
	}

}