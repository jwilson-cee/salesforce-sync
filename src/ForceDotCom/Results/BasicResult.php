<?php

namespace CEE\Salesforce\ForceDotCom\Results;

use stdClass;

class BasicResult {

	/**
	 * @var array<ResultError>
	 */
	public array $errors;
	public string $id;
	public bool $success;

	public function __construct( stdClass $response ) {
		$this->id = $response->id;
		$this->success = $response->success;
		foreach( $response->errors ?? [] as $error ) {
			$this->errors[] = new ResultError( $error );
		}
	}

	/**
	 * @param array<stdClass> $results
	 * @return array<BasicResult>
	 */
	public static function createMultiple( array $results ): array {
		return array_map( fn( $result ) => new static( $result ), $results );
	}
}