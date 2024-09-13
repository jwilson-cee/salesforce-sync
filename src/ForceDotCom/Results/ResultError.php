<?php

namespace CEE\Salesforce\ForceDotCom\Results;

use stdClass;

class ResultError {

	public array $fields;
	public string $message;
	public string $statusCode;

	public function __construct( stdClass $response ) {
		$this->fields = $response->fields;
		$this->message = $response->message;
		$this->statusCode = $response->statusCode;
	}
}