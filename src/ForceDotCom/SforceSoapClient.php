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
     * SforceSoapClient class.
     *
     * @package SalesforceSoapClient
     */
// When parsing partner WSDL, when PHP SOAP sees NewValue and OldValue, since
// the element has a xsi:type attribute with value 'string', it drops the
// string content into the parsed output and loses the tag name. Removing the
// xsi:type forces PHP SOAP to just leave the tags intact
class SforceSoapClient extends \SoapClient
{
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $response = parent::__doRequest($request, $location, $action, $version, $one_way);

        if (strpos($response, '<sf:OldValue') === false && strpos($response, '<sf:NewValue') === false) {
            return $response;
        }

        $dom = new \DOMDocument();
        $dom->loadXML($response);

        $nodeList = $dom->getElementsByTagName('NewValue');
        foreach ($nodeList as $key => $node) {
            $node->removeAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'type');
        }

        $nodeList = $dom->getElementsByTagName('OldValue');
        foreach ($nodeList as $key => $node) {
            $node->removeAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'type');
        }

        return $dom->saveXML();
    }
}

?>
