<?php

/**
 * Parse the SAML response and maintain the XML for it.
 */
class OneLogin_Saml_Response
{
    /**
     * @var OneLogin_Saml_Settings
     */
    private $_settings;

    /**
     * The decoded, unprocessed XML assertion provided to the constructor.
     * @var string
     */
    public $assertion;

    /**
     * A DOMDocument class loaded from the $assertion.
     * @var DomDocument
     */
    public $document;

    /**
     * Construct the response object.
     *
     * @param OneLogin_Saml_Settings $settings Settings containing the necessary X.509 certificate to decode the XML.
     * @param string $assertion A UUEncoded SAML assertion from the IdP.
     */
    public function __construct(OneLogin_Saml_Settings $settings, $assertion)
    {
        $this->_settings = $settings;
        $this->assertion = base64_decode($assertion);
        $this->document = new DOMDocument();
        $this->document->loadXML($this->assertion);
    }

    /**
     * Determine if the SAML Response is valid using the certificate.
     *
     * @throws Exception
     * @return bool Validate the document
     */
    public function isValid()
    {
        $xmlSec = new OneLogin_Saml_XmlSec($this->_settings, $this);
        return $xmlSec->isValid();
    }

    /**
     * Get the NameID provided by the SAML response from the IdP.
     */
    public function getNameId()
    {
        $entries = $this->_queryAssertion('/saml:Subject/saml:NameID');
        return $entries->item(0)->nodeValue;
    }

    public function getAttributes()
    {
        $entries = $this->_queryAssertion('/saml:AttributeStatement/saml:Attribute');

        $attributes = array();
        /** @var $entry DOMNode */
        foreach ($entries as $entry) {
            $attributeName = $entry->attributes->getNamedItem('Name')->nodeValue;

            $attributeValues = array();
            foreach ($entry->childNodes as $childNode) {
                if ($childNode->tagName === 'saml:AttributeValue'){
                    $attributeValues[] = $childNode->nodeValue;
                }
            }

            $attributes[$attributeName] = $attributeValues;
        }
        return $attributes;
    }

    /**
     * @param string $assertionXpath
     * @return DOMNodeList
     */
    private function _queryAssertion($assertionXpath)
    {
        $xpath = new DOMXPath($this->document);
        $xpath->registerNamespace('samlp'   , 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml'    , 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xpath->registerNamespace('ds'      , 'http://www.w3.org/2000/09/xmldsig#');

        $signatureQuery = '//ds:Reference[@URI]';
        $id = substr($xpath->query($signatureQuery)->item(0)->getAttribute('URI'), 1);

        $nameQuery = "/samlp:Response/saml:Assertion[@ID='$id']" . $assertionXpath;
        return $xpath->query($nameQuery);
    }
}
