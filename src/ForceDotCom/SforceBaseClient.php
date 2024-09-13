<?php namespace CEE\Salesforce\ForceDotCom;

    /*
     * Copyright (c) 2007, salesforce.com, inc.
     * All rights reserved.
     *
     * Redistribution and use in source and binary forms, with or without modification, are permitted provided
     * that the following conditions are met:
     *
     *    Redistributions of source code must retain the above copyright notice, this list of conditions and the
     *    following disclaimer.
     *
     *    Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
     *    the following disclaimer in the documentation and/or other materials provided with the distribution.
     *
     *    Neither the name of salesforce.com, inc. nor the names of its contributors may be used to endorse or
     *    promote products derived from this software without specific prior written permission.
     *
     * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED
     * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
     * PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
     * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
     * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
     * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
     * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
     * POSSIBILITY OF SUCH DAMAGE.
     */

    /**
     * This file contains one class.
     * @package SalesforceSoapClient
     */

	use CEE\Salesforce\ForceDotCom\Results\BasicResult;
	use CEE\Salesforce\ForceDotCom\Results\QueryResult;
	use Exception;
	use SoapClient;
	use SoapHeader;
	use SoapVar;
	use stdClass;

	/**
 * SalesforceSoapClient
 * @package SalesforceSoapClient
 */
class SforceBaseClient
{
    protected $sforce;
    protected $sessionId;
    protected $location;
    protected $version = '27.0';

    protected $namespace;

    // Header Options
    protected $callOptions;
    protected $assignmentRuleHeader;
    protected $emailHeader;
    protected $loginScopeHeader;
    protected $mruHeader;
    protected $queryHeader;
    protected $userTerritoryDeleteHeader;
    protected $sessionHeader;

    // new headers
    protected $allowFieldTruncationHeader;
    protected $localeOptions;
    protected $packageVersionHeader;

    protected function getSoapClient($wsdl, $options)
    {
        return new SoapClient($wsdl, $options);
    }

    public function getNamespace()
    {
        return $this->namespace;
    }


    // clientId specifies which application or toolkit is accessing the
    // salesforce.com API. For applications that are certified salesforce.com
    // solutions, replace this with the value provided by salesforce.com.
    // Otherwise, leave this value as 'phpClient/1.0'.
    protected $client_id;

    public function printDebugInfo()
    {
        echo 'PHP Toolkit Version: ' . $this->version . PHP_EOL;
        echo 'Current PHP version: ' . PHP_VERSION;
        echo PHP_EOL;
        echo 'SOAP enabled: ';
        if (extension_loaded('soap')) {
            echo 'True';
        } else {
            echo 'False';
        }
        echo PHP_EOL;
        echo 'OpenSSL enabled: ';
        if (extension_loaded('openssl')) {
            echo 'True';
        } else {
            echo 'False';
        }
    }

    /**
     * Connect method to www.salesforce.com
     *
     * @param string $wsdl Salesforce.com Partner WSDL
     * @param object $proxy (optional) proxy settings with properties host, port,
     *                       login and password
     * @param array $soap_options (optional) Additional options to send to the
     *                       SoapClient constructor. @see
     *                       http://php.net/manual/en/soapclient.soapclient.php
     */
    public function createConnection($wsdl, $proxy = null, $soap_options = array())
    {
        // use correct version comparison
        if (version_compare(PHP_VERSION, '5.3', '<')) {
            throw new Exception ('PHP versions older than 5.3 are no longer supported. Please upgrade!');
        }

        $soapClientArray = array_merge(array(
            'user_agent' => 'salesforce-toolkit-php/' . $this->version,
            'encoding' => 'utf-8',
            'trace' => 1,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP
        ), $soap_options);


        if ($proxy != null) {
            $proxySettings = array();
            $proxySettings['proxy_host'] = $proxy->host;
            $proxySettings['proxy_port'] = $proxy->port; // Use an integer, not a string
            $proxySettings['proxy_login'] = $proxy->login;
            $proxySettings['proxy_password'] = $proxy->password;
            $soapClientArray = array_merge($soapClientArray, $proxySettings);
        }

        $this->sforce = $this->getSoapClient($wsdl, $soapClientArray);

        return $this->sforce;
    }

    public function setCallOptions($header)
    {
        if ($header != null) {
            $this->callOptions = new SoapHeader($this->namespace, 'CallOptions', array(
                'client' => $header->client,
                'defaultNamespace' => $header->defaultNamespace
            ));
        } else {
            $this->callOptions = null;
        }
    }

    /**
     * Login to Salesforce.com and starts a client session.
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @return stdClass LoginResult
     */
    public function login($username, $password)
    {
        $this->sforce->__setSoapHeaders(null);
        if ($this->callOptions != null) {
            $this->sforce->__setSoapHeaders(array($this->callOptions));
        }
        if ($this->loginScopeHeader != null) {
            $this->sforce->__setSoapHeaders(array($this->loginScopeHeader));
        }
        $result = $this->sforce->login(array(
            'username' => $username,
            'password' => $password
        ));
        $result = $result->result;
        $this->_setLoginHeader($result);

        return $result;
    }

    /**
     * log outs from the salseforce system`
     *
     * @return stdClass LogoutResult
     */
    public function logout()
    {
        $this->setHeaders("logout");
        $arg = new stdClass();

        return $this->sforce->logout();
    }

    /**
     *invalidate Sessions from the salseforce system`
     *
     * @return stdClass InvalidateSessionsResult
	 * @property array<stdClass> $errors Error
	 * @property bool $success
     */
    public function invalidateSessions()
    {
        $this->setHeaders("invalidateSessions");
        $arg = new stdClass();
        $this->logout();

        return $this->sforce->invalidateSessions();
    }

    /**
     * Specifies the session ID returned from the login server after a successful
     * login.
     */
    protected function _setLoginHeader($loginResult)
    {
        $this->sessionId = $loginResult->sessionId;
        $this->setSessionHeader($this->sessionId);
        $serverURL = $loginResult->serverUrl;
        $this->setEndPoint($serverURL);
    }

    /**
     * Set the endpoint.
     *
     * @param string $location Location
     */
    public function setEndpoint($location)
    {
        $this->location = $location;
        $this->sforce->__setLocation($location);
    }

    private function setHeaders($call = null)
    {
        $this->sforce->__setSoapHeaders(null);

        $header_array = array(
            $this->sessionHeader
        );

        $header = $this->callOptions;
        if ($header != null) {
            array_push($header_array, $header);
        }

        if ($call == "create" ||
            $call == "merge" ||
            $call == "update" ||
            $call == "upsert"
        ) {
            $header = $this->assignmentRuleHeader;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        if ($call == "login") {
            $header = $this->loginScopeHeader;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        if ($call == "create" ||
            $call == "resetPassword" ||
            $call == "update" ||
            $call == "upsert"
        ) {
            $header = $this->emailHeader;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        if ($call == "create" ||
            $call == "merge" ||
            $call == "query" ||
            $call == "retrieve" ||
            $call == "update" ||
            $call == "upsert"
        ) {
            $header = $this->mruHeader;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        if ($call == "delete") {
            $header = $this->userTerritoryDeleteHeader;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        if ($call == "query" ||
            $call == "queryMore" ||
            $call == "retrieve"
        ) {
            $header = $this->queryHeader;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        // try to add allowFieldTruncationHeader
        $allowFieldTruncationHeaderCalls = array(
            'convertLead',
            'create',
            'merge',
            'process',
            'undelete',
            'update',
            'upsert',
        );
        if (in_array($call, $allowFieldTruncationHeaderCalls)) {
            $header = $this->allowFieldTruncationHeader;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        // try to add localeOptions
        if ($call == 'describeSObject' || $call == 'describeSObjects') {
            $header = $this->localeOptions;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        // try to add PackageVersionHeader
        $packageVersionHeaderCalls = array(
            'convertLead',
            'create',
            'delete',
            'describeGlobal',
            'describeLayout',
            'describeSObject',
            'describeSObjects',
            'describeSoftphoneLayout',
            'describeTabs',
            'merge',
            'process',
            'query',
            'retrieve',
            'search',
            'undelete',
            'update',
            'upsert',
        );

        if (in_array($call, $packageVersionHeaderCalls)) {
            $header = $this->packageVersionHeader;
            if ($header != null) {
                array_push($header_array, $header);
            }
        }

        $this->sforce->__setSoapHeaders($header_array);
    }

    public function setAssignmentRuleHeader($header)
    {
        if ($header != null) {
            $this->assignmentRuleHeader = new SoapHeader($this->namespace, 'AssignmentRuleHeader', array(
                'assignmentRuleId' => $header->assignmentRuleId,
                'useDefaultRule' => $header->useDefaultRuleFlag
            ));
        } else {
            $this->assignmentRuleHeader = null;
        }
    }

    public function setEmailHeader($header)
    {
        if ($header != null) {
            $this->emailHeader = new SoapHeader($this->namespace, 'EmailHeader', array(
                'triggerAutoResponseEmail' => $header->triggerAutoResponseEmail,
                'triggerOtherEmail' => $header->triggerOtherEmail,
                'triggerUserEmail' => $header->triggerUserEmail
            ));
        } else {
            $this->emailHeader = null;
        }
    }

    public function setLoginScopeHeader($header)
    {
        if ($header != null) {
            $this->loginScopeHeader = new SoapHeader($this->namespace, 'LoginScopeHeader', array(
                'organizationId' => $header->organizationId,
                'portalId' => $header->portalId
            ));
        } else {
            $this->loginScopeHeader = null;
        }
    }

    public function setMruHeader($header)
    {
        if ($header != null) {
            $this->mruHeader = new SoapHeader($this->namespace, 'MruHeader', array(
                'updateMru' => $header->updateMruFlag
            ));
        } else {
            $this->mruHeader = null;
        }
    }

    public function setSessionHeader($id)
    {
        if ($id != null) {
            $this->sessionHeader = new SoapHeader($this->namespace, 'SessionHeader', array(
                'sessionId' => $id
            ));
            $this->sessionId = $id;
        } else {
            $this->sessionHeader = null;
            $this->sessionId = null;
        }
    }

    public function setUserTerritoryDeleteHeader($header)
    {
        if ($header != null) {
            $this->userTerritoryDeleteHeader = new SoapHeader($this->namespace, 'UserTerritoryDeleteHeader  ', array(
                'transferToUserId' => $header->transferToUserId
            ));
        } else {
            $this->userTerritoryDeleteHeader = null;
        }
    }

    public function setQueryOptions($header)
    {
        if ($header != null) {
            $this->queryHeader = new SoapHeader($this->namespace, 'QueryOptions', array(
                'batchSize' => $header->batchSize
            ));
        } else {
            $this->queryHeader = null;
        }
    }

    public function setAllowFieldTruncationHeader($header)
    {
        if ($header != null) {
            $this->allowFieldTruncationHeader = new SoapHeader($this->namespace, 'AllowFieldTruncationHeader', array(
                    'allowFieldTruncation' => $header->allowFieldTruncation
                )
            );
        } else {
            $this->allowFieldTruncationHeader = null;
        }
    }

    public function setLocaleOptions($header)
    {
        if ($header != null) {
            $this->localeOptions = new SoapHeader($this->namespace, 'LocaleOptions',
                array(
                    'language' => $header->language
                )
            );
        } else {
            $this->localeOptions = null;
        }
    }

    /**
     * @param $header
     */
    public function setPackageVersionHeader($header)
    {
        if ($header != null) {
            $headerData = array('packageVersions' => array());

            foreach ($header->packageVersions as $key => $hdrElem) {
                $headerData['packageVersions'][] = array(
                    'majorNumber' => $hdrElem->majorNumber,
                    'minorNumber' => $hdrElem->minorNumber,
                    'namespace' => $hdrElem->namespace,
                );
            }

            $this->packageVersionHeader = new SoapHeader($this->namespace,
                'PackageVersionHeader',
                $headerData
            );
        } else {
            $this->packageVersionHeader = null;
        }
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function getConnection()
    {
        return $this->sforce;
    }

    public function getFunctions()
    {
        return $this->sforce->__getFunctions();
    }

    public function getTypes()
    {
        return $this->sforce->__getTypes();
    }

    public function getLastRequest()
    {
        return $this->sforce->__getLastRequest();
    }

    public function getLastRequestHeaders()
    {
        return $this->sforce->__getLastRequestHeaders();
    }

    public function getLastResponse()
    {
        return $this->sforce->__getLastResponse();
    }

    public function getLastResponseHeaders()
    {
        return $this->sforce->__getLastResponseHeaders();
    }

    protected function _convertToAny($fields)
    {
        $anyString = '';
        foreach ($fields as $key => $value) {
            $anyString = $anyString . '<' . $key . '>' . $value . '</' . $key . '>';
        }

        return $anyString;
    }

	/**
	 * @return array<BasicResult>
	 */
	protected function _create($arg): array
    {
        $this->setHeaders("create");

        return BasicResult::createMultiple( $this->sforce->create($arg)->result );
    }

    protected function _merge($arg)
    {
        $this->setHeaders("merge");

        return $this->sforce->merge($arg)->result;
    }

    protected function _process($arg)
    {
        $this->setHeaders();

        return $this->sforce->process($arg)->result;
    }

	/**
	 * @return array<BasicResult>
	 */
	protected function _update($arg): array
    {
        $this->setHeaders("update");

        return BasicResult::createMultiple( $this->sforce->update($arg)->result );
    }

    protected function _upsert($arg)
    {
        $this->setHeaders("upsert");

        return $this->sforce->upsert($arg)->result;
    }

    public function sendSingleEmail($request)
    {
        if (is_array($request)) {
            $messages = array();
            foreach ($request as $r) {
                $email = new SoapVar($r, SOAP_ENC_OBJECT, 'SingleEmailMessage', $this->namespace);
                array_push($messages, $email);
            }
            $arg = new stdClass();
            $arg->messages = $messages;

            return $this->_sendEmail($arg);
        } else {
            $backtrace = debug_backtrace();
            error_log('Please pass in array to this function:  ' . $backtrace[0]['function']);
            return false;
        }
    }

    public function sendMassEmail($request)
    {
        if (is_array($request)) {
            $messages = array();
            foreach ($request as $r) {
                $email = new SoapVar($r, SOAP_ENC_OBJECT, 'MassEmailMessage', $this->namespace);
                array_push($messages, $email);
            }
            $arg = new stdClass();
            $arg->messages = $messages;

            return $this->_sendEmail($arg);
        } else {
            $backtrace = debug_backtrace();
            error_log('Please pass in array to this function:  ' . $backtrace[0]['function']);
            return false;
        }
    }

    protected function _sendEmail($arg)
    {
        $this->setHeaders();

        return $this->sforce->sendEmail($arg)->result;
    }

    /**
     * Converts a Lead into an Account, Contact, or (optionally) an Opportunity.
     *
     * @param array $leadConverts Array of LeadConvert
     *
     * @return stdClass LeadConvertResult
     */
    public function convertLead($leadConverts)
    {
		$this->setHeaders("convertLead");
        $arg = new stdClass();
        
        foreach ($leadConverts as $k => $lc) {
            if (isset($lc->contactRecord) && !$lc->contactRecord instanceof SoapVar) {
                $lc->contactRecord = new SoapVar($lc->contactRecord, SOAP_ENC_OBJECT, 'Contact', $this->namespace);
            }

            if (isset($lc->opportunityRecord) && !$lc->opportunityRecord instanceof SoapVar) {
                $lc->opportunityRecord = new SoapVar($lc->opportunityRecord, SOAP_ENC_OBJECT, 'Opportunity', $this->namespace);
            }

            if (isset($lc->accountRecord) && !$lc->accountRecord instanceof SoapVar) {
                $lc->accountRecord = new SoapVar($lc->accountRecord, SOAP_ENC_OBJECT, 'Account', $this->namespace);
            }

            $leadConverts[$k] = $lc;
        }
		
        $arg->leadConverts = $leadConverts;
        
		return $this->sforce->convertLead($arg);
	}

    /**
     * Deletes one or more new individual objects to your organization's data.
	 *
	 * @return array<BasicResult>
     */
	public function delete( array $ids ): array {
		$this->setHeaders( "delete" );
		$arg = new stdClass();
		$arg->ids = $ids;

		return BasicResult::createMultiple( $this->sforce->delete( $arg )->result );
	}


	/**
	 * @return array<BasicResult>
	 */
	public function undelete( array $ids ): array {
		$this->setHeaders( "undelete" );
		$arg = new stdClass();
		$arg->ids = $ids;

		return BasicResult::createMultiple( $this->sforce->undelete( $arg )->result );
	}

	/**
	 * @return array<BasicResult>
	 */
	public function emptyRecycleBin(array $ids): array {
        $this->setHeaders();
        $arg = new stdClass();
        $arg->ids = $ids;

        return BasicResult::createMultiple( $this->sforce->emptyRecycleBin($arg)->result );
    }

    /**
     * Process Submit Request for Approval
     *
     * @param array $processRequestArray
     * @return stdClass|bool (ProcessResult)
     */
    public function processSubmitRequest($processRequestArray)
    {
        if (is_array($processRequestArray)) {
            foreach ($processRequestArray as &$process) {
                $process = new SoapVar($process, SOAP_ENC_OBJECT, 'ProcessSubmitRequest', $this->namespace);
            }
            $arg = new stdClass();
            $arg->actions = $processRequestArray;

            return $this->_process($arg);
        } else {
            $backtrace = debug_backtrace();
            error_log('Please pass in array to this function:  ' . $backtrace[0]['function']);
            return false;
        }
    }

    /**
     * Process Work Item Request for Approval
     *
     * @param array $processRequestArray
     * @return stdClass|bool (ProcessResult)
     */
    public function processWorkitemRequest($processRequestArray)
    {
        if (is_array($processRequestArray)) {
            foreach ($processRequestArray as &$process) {
                $process = new SoapVar($process, SOAP_ENC_OBJECT, 'ProcessWorkitemRequest', $this->namespace);
            }
            $arg = new stdClass();
            $arg->actions = $processRequestArray;

            return $this->_process($arg);
        } else {
            $backtrace = debug_backtrace();
            error_log('Please pass in array to this function:  ' . $backtrace[0]['function']);
            return false;
        }
    }

    /**
     * Retrieves a list of available objects for your organization's data.
     *
     * @return stdClass DescribeGlobalResult
     */
    public function describeGlobal()
    {
        $this->setHeaders("describeGlobal");

        return $this->sforce->describeGlobal()->result;
    }

    /**
     * Use describeLayout to retrieve information about the layout (presentation
     * of data to users) for a given object type. The describeLayout call returns
     * metadata about a given page layout, including layouts for edit and
     * display-only views and record type mappings. Note that field-level security
     * and layout editability affects which fields appear in a layout.
     *
     * @param string $type   Object Type
     * @return stdClass DescribeLayoutResult
     */
    public function describeLayout($type, array $recordTypeIds = null)
    {
        $this->setHeaders("describeLayout");
        $arg = new stdClass();
        $arg->sObjectType = new SoapVar($type, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');
        if (isset($recordTypeIds) && count($recordTypeIds)) {
            $arg->recordTypeIds = $recordTypeIds;
        }

        return $this->sforce->describeLayout($arg)->result;
    }

    /**
     * Describes metadata (field list and object properties) for the specified
     * object.
     *
     * @param string $type Object type
     * @return stdClass DescribeSObjectResult
     */
    public function describeSObject($type)
    {
        $this->setHeaders("describeSObject");
        $arg = new stdClass();
        $arg->sObjectType = new SoapVar($type, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');

        return $this->sforce->describeSObject($arg)->result;
    }

    /**
     * An array-based version of describeSObject; describes metadata (field list
     * and object properties) for the specified object or array of objects.
     *
     * @param array $arrayOfTypes Array of object types.
     * @return stdClass DescribeSObjectResult
     */
    public function describeSObjects($arrayOfTypes)
    {
        $this->setHeaders("describeSObjects");

        return $this->sforce->describeSObjects($arrayOfTypes)->result;
    }

    /**
     * The describeTabs call returns information about the standard apps and
     * custom apps, if any, available for the user who sends the call, including
     * the list of tabs defined for each app.
     *
     * @return stdClass DescribeTabSetResult
     */
    public function describeTabs()
    {
        $this->setHeaders("describeTabs");

        return $this->sforce->describeTabs()->result;
    }

    /**
     * To enable data categories groups you must enable Answers or Knowledge Articles module in
     * admin panel, after adding category group and assign it to Answers or Knowledge Articles
     *
     * @param string $sObjectType sObject Type
     * @return stdClass DescribeDataCategoryGroupResult
     */
    public function describeDataCategoryGroups($sObjectType)
    {
        $this->setHeaders('describeDataCategoryGroups');
        $arg = new stdClass();
        $arg->sObjectType = new SoapVar($sObjectType, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');

        return $this->sforce->describeDataCategoryGroups($arg)->result;
    }

    /**
     * Retrieves available category groups along with their data category structure for objects specified in the request.
     *
     * @param array<stdClass> $pairs DataCategoryGroupSobjectTypePair
     * @param bool $topCategoriesOnly Object Type
     * @return stdClass DescribeLayoutResult
     */
    public function describeDataCategoryGroupStructures(array $pairs, $topCategoriesOnly)
    {
        $this->setHeaders('describeDataCategoryGroupStructures');
        $arg = new stdClass();
        $arg->pairs = $pairs;
        $arg->topCategoriesOnly = new SoapVar($topCategoriesOnly, XSD_BOOLEAN, 'boolean',
            'http://www.w3.org/2001/XMLSchema');

        return $this->sforce->describeDataCategoryGroupStructures($arg)->result;
    }

    /**
     * Retrieves the list of individual objects that have been deleted within the
     * given timespan for the specified object.
     *
     * @param string $type Ojbect type
     * @param string $startDate Start date
     * @param string $endDate End Date
     * @return stdClass GetDeletedResult
     */
    public function getDeleted($type, $startDate, $endDate)
    {
        $this->setHeaders("getDeleted");
        $arg = new stdClass();
        $arg->sObjectType = new SoapVar($type, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');
        $arg->startDate = $startDate;
        $arg->endDate = $endDate;

        return $this->sforce->getDeleted($arg)->result;
    }

    /**
     * Retrieves the list of individual objects that have been updated (added or
     * changed) within the given timespan for the specified object.
     *
     * @param string $type Ojbect type
     * @param string $startDate Start date
     * @param string $endDate End Date
     * @return stdClass GetUpdatedResult
     */
    public function getUpdated($type, $startDate, $endDate)
    {
        $this->setHeaders("getUpdated");
        $arg = new stdClass();
        $arg->sObjectType = new SoapVar($type, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');
        $arg->startDate = $startDate;
        $arg->endDate = $endDate;

        return $this->sforce->getUpdated($arg)->result;
    }

    /**
     * Executes a query against the specified object and returns data that matches
     * the specified criteria.
     *
     * @param string $query Query String
     * @param QueryOptions $queryOptions Batch size limit.  OPTIONAL
     * @return QueryResult
     */
    public function query($query)
    {
        $this->setHeaders("query");
        $raw = $this->sforce->query(array(
            'queryString' => $query
        ))->result;
        $QueryResult = new QueryResult($raw);
        $QueryResult->setSf($this); // Dependency Injection
        return $QueryResult;
    }

    /**
     * Retrieves the next batch of objects from a query.
     *
     * @param string $queryLocator Represents the server-side cursor that tracks the current processing location in the query result set.
     * @param QueryOptions $queryOptions Batch size limit.  OPTIONAL
     * @return QueryResult
     */
    public function queryMore($queryLocator)
    {
        $this->setHeaders("queryMore");
        $arg = new stdClass();
        $arg->queryLocator = $queryLocator;
        $raw = $this->sforce->queryMore($arg)->result;
        $QueryResult = new QueryResult($raw);
        $QueryResult->setSf($this); // Dependency Injection
        return $QueryResult;
    }

    /**
     * Retrieves data from specified objects, whether or not they have been deleted.
     *
     * @param String $query Query String
     * @param QueryOptions $queryOptions Batch size limit.  OPTIONAL
     * @return QueryResult
     */
    public function queryAll($query, $queryOptions = null)
    {
        $this->setHeaders("queryAll");
        $raw = $this->sforce->queryAll(array(
            'queryString' => $query
        ))->result;
        $QueryResult = new QueryResult($raw);
        $QueryResult->setSf($this); // Dependency Injection
        return $QueryResult;
    }


    /**
     * Retrieves one or more objects based on the specified object IDs.
     *
     * @param string $fieldList One or more fields separated by commas.
     * @param string $sObjectType Object from which to retrieve data.
     * @param array $ids Array of one or more IDs of the objects to retrieve.
     * @return sObject[]
     */
    public function retrieve($fieldList, $sObjectType, $ids)
    {
        $this->setHeaders("retrieve");
        $arg = new stdClass();
        $arg->fieldList = $fieldList;
        $arg->sObjectType = new SoapVar($sObjectType, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');
        $arg->ids = $ids;

        return $this->sforce->retrieve($arg)->result;
    }

    /**
     * Executes a text search in your organization's data.
     *
     * @param string $searchString Search string that specifies the text expression to search for.
     * @return SforceSearchResult SearchResult
     */
    public function search($searchString)
    {
        $this->setHeaders("search");
        $arg = new stdClass();
        $arg->searchString = new SoapVar($searchString, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');

        return new SforceSearchResult($this->sforce->search($arg)->result);
    }

    /**
     * Retrieves the current system timestamp (GMT) from the Web service.
     *
     * @return stdClass GetServerTimestampResult
	 * @property string $timestamp
     */
    public function getServerTimestamp()
    {
        $this->setHeaders("getServerTimestamp");

        return $this->sforce->getServerTimestamp()->result;
    }

    public function getUserInfo()
    {
        $this->setHeaders("getUserInfo");

        return $this->sforce->getUserInfo()->result;
    }

    /**
     * Sets the specified user's password to the specified value.
     *
     * @param string $userId ID of the User.
     * @param string $password New password
     */
    public function setPassword($userId, $password)
    {
        $this->setHeaders("setPassword");
        $arg = new stdClass();
        $arg->userId = new SoapVar($userId, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');
        $arg->password = $password;

        return $this->sforce->setPassword($arg);
    }

    /**
     * Changes a user's password to a system-generated value.
     *
     * @param string $userId Id of the User
     * @return stdClass ResetPasswordResult
	 * @property string $password
     */
    public function resetPassword($userId)
    {
        $this->setHeaders("resetPassword");
        $arg = new stdClass();
        $arg->userId = new SoapVar($userId, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema');

        return $this->sforce->resetPassword($arg)->result;
    }
}
