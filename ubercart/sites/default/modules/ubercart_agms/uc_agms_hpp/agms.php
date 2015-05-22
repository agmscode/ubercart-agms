<?php
/**
 * Agms
 *
 * @package     agms_php_lite
 * @class 		Agms
 * @version		0.1.0
 * @author      Maanas Royy
 */

class Agms
{
    /*
     * Library Version
     */
    private static $version = "0.1.0";
    /**
     * Gateway Urls
     *
     */
    private static $transactionUrl = 'https://gateway.agms.com/roxapi/agms.asmx';
    private static $hostedPaymentUrl = 'https://gateway.agms.com/roxapi/AGMS_HostedPayment.asmx';

    /**
     * Gateway Variables
     */
    private static $url = NULL;
    private static $op = NULL;
    /**
     * Gateway Credentials
     *
     */
    private static $username;
    private static $password;

    /*
     * Verbose
     */
    private static $verbose = false;
    /**
     * Set the gateway username variable
     * @param $username
     * @return NULL
     */
    public static function setUsername($username)
    {
        self::$username = $username;
    }

    /**
     * Set the gateway password variable
     * @param $password
     * @return NULL
     */
    public static function setPassword($password)
    {
        self::$password = $password;
    }

    /**
     * Set the gateway verbose variable
     * @param $password
     * @return NULL
     */
    public static function setVerbose($verbose = false)
    {
        self::$verbose = $verbose;
    }
    /**
     * Process transaction request on gateway
     * @param $params
     * @return array
     */
    public static function process($params)
    {
        self::$url = self::$transactionUrl;
        self::$op = 'ProcessTransaction';
        $data = self::getGatewayCredentials();
        $params = array_merge($params, array('PaymentType' => 'creditcard'));
        $params = array_merge($data, $params);
        $header = self::buildRequestHeader(self::$op);
        $body = self::buildRequestBody($params, self::$op);
        $response = self::doPost(self::$url, $header, $body);
        return self::parseResponse($response, self::$op);
    }

    /**
     * Get Hosted Form Hash from gateway
     * @param $params
     * @return string
     */
    public static function hostedPayment($params)
    {
        self::$url = self::$hostedPaymentUrl;
        self::$op = 'ReturnHostedPaymentSetup';
        $data = self::getGatewayCredentials();
        $params = array_merge($data, $params);
        $header = self::buildRequestHeader(self::$op);
        $body = self::buildRequestBody($params, self::$op);
        $response = self::doPost(self::$url, $header, $body);
        return self::parseResponse($response, self::$op);
    }

    public static function doPost($url, $header, $body)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        if (self::$verbose) {
            curl_setopt($curl, CURLOPT_VERBOSE, true);
        }
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public static function getGatewayCredentials()
    {
        $data = array();
        $data['GatewayUserName'] = isset(self::$username)?self::$username:NULL;
        $data['GatewayPassword'] = isset(self::$password)?self::$password:NULL;
        return $data;
    }

    /**
     * Convert array to xml string
     * @param $request, $op
     * @return string
     */
    public static function buildRequestBody($request, $op='ProcessTransaction')
    {
        /*
         * Resolve object parameters
         */
        switch ($op) {
            case 'ProcessTransaction':
                $param = 'objparameters';
                break;
            case 'ReturnHostedPaymentSetup':
                $param = 'objparameters';
                break;
        }

        $xmlHeader = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <' . $op . ' xmlns="https://gateway.agms.com/roxapi/">
      <' . $param . '>';
        $xmlFooter = '</' . $param . '>
    </' . $op . '>
  </soap:Body>
</soap:Envelope>';

        $xmlBody = '';
        foreach ($request as $key => $value) {
            $xmlBody = $xmlBody . "<$key>$value</$key>";
        }
        $payload = $xmlHeader . $xmlBody . $xmlFooter;
        return $payload;

    }

    /**
     * Builds header for the Request
     * @param $op
     * @return array
     */
    public static function buildRequestHeader($op='ProcessTransaction')
    {
        return array(
            'Accept: application/xml',
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: https://gateway.agms.com/roxapi/' . $op,
            'User-Agent: AGMS PHP Library Lite (' . self::$version . ')',
            'X-ApiVersion: 3'
        );
    }

    /**
     * Parse response from Agms Gateway
     * @param $response, $op
     * @return array
     */
    public static function parseResponse($response, $op)
    {
        $xml = new SimpleXMLElement($response);
        $xml = $xml->xpath('/soap:Envelope/soap:Body');
        $xml = $xml[0];
        $data = json_decode(json_encode($xml));
        $opResponse = $op . 'Response';
        $opResult = $op . 'Result';
        $arr = Agms::object2array($data->$opResponse->$opResult);
        return $arr;
    }

    /**
     * Convert object to array
     * @param $data
     * @return array
     */
    private static function object2array($data)
    {
        if (is_array($data) || is_object($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = Agms::object2array($value);
            }
            return $result;
        }
        return $data;
    }
}