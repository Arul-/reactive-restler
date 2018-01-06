<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\UriTemplate;
use Luracast\Restler\Data\Text;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Rest context.
 *
 * @category   Framework
 * @package    restler
 * @author     R.Arul Kumaran <arul@luracast.com>
 * @copyright  2010 Luracast
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://luracast.com/products/restler/
 * @version    3.0.0
 */
class RestContext implements Context
{

    private $_request_debug_stream = null;
    private $_startTime = null;
    private $_restObject = null;
    private $_headers = array();
    private $_restObjectType = null;
    private $_restObjectMethod = 'get';
    private $_client = null;
    private $_response = null;
    private $_request = null;
    private $_requestBody = null;
    private $_requestUrl = null;
    private $_type = null;
    private $_charset = null;
    private $_language = null;
    private $_data = null;

    protected $baseUrl = '';

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     */
    public function __construct($baseUrl)
    {
        // Initialize your context here

        $this->_restObject = new stdClass();
        $this->baseUrl = $baseUrl;
        $handler = HandlerStack::create();
        $handler->push(Middleware::mapRequest(function (RequestInterface $request) {
            // Notice that we have to return a request object
            $this->_request = $request;
            return $request;
        }));
        $this->_client = new Client(['base_uri' => $baseUrl, 'handler' => $handler]);
        /*
        //suppress few errors
        $this->_client
            ->getEventDispatcher()
            ->addListener('request.error',
                function (\Guzzle\Common\Event $event) {
                    switch ($event['response']->getStatusCode()) {
                        case 400:
                        case 401:
                        case 404:
                        case 405:
                        case 406:
                            $event->stopPropagation();
                    }
                });
        */
        $timezone = ini_get('date.timezone');
        if (empty($timezone)) {
            date_default_timezone_set('UTC');
        }
        global $argv;
        $options = $argv;
    }

    /**
     * ============ json array ===================
     * @Given /^that I send (\[[^]]*\])$/
     *
     * ============ json object ==================
     * @Given /^that I send (\{(?>[^\{\}]+|(?1))*\})$/
     *
     * ============ json string ==================
     * @Given /^that I send ("[^"]*")$/
     *
     * ============ json int =====================
     * @Given /^that I send ([-+]?[0-9]*\.?[0-9]+)$/
     *
     * ============ json null or boolean =========
     * @Given /^that I send (null|true|false)$/
     */
    public function thatISend($data)
    {
        $this->_restObject = json_decode($data);
        $this->_restObjectMethod = 'post';
    }

    /**
     * @Given /^that I send:/
     * @param PyStringNode $data
     */
    public function thatISendPyString(PyStringNode $data)
    {
        $this->thatISend($data);
    }

    /**
     * ============ json array ===================
     * @Given /^the response contains (\[[^]]*\])$/
     *
     * ============ json object ==================
     * @Given /^the response contains (\{(?>[^\{\}]+|(?1))*\})$/
     *
     * ============ json string ==================
     * @Given /^the response contains ("[^"]*")$/
     *
     * ============ json int =====================
     * @Given /^the response contains ([-+]?[0-9]*\.?[0-9]+)$/
     *
     * ============ json null or boolean =========
     * @Given /^the response contains (null|true|false)$/
     */
    public function theResponseContains($response)
    {
        $data = json_encode($this->_data);
        if (!Text::contains($data, $response)) {
            throw new Exception("Response value does not contain '$response' only\n\n"
                . $this->echoLastResponse());
        }
    }

    /**
     * ============ json array ===================
     * @Given /^the response equals (\[[^]]*\])$/
     *
     * ============ json object ==================
     * @Given /^the response equals (\{(?>[^\{\}]+|(?1))*\})$/
     *
     * ============ json string ==================
     * @Given /^the response equals ("[^"]*")$/
     *
     * ============ json int =====================
     * @Given /^the response equals ([-+]?[0-9]*\.?[0-9]+)$/
     *
     * ============ json null or boolean =========
     * @Given /^the response equals (null|true|false)$/
     */
    public function theResponseEquals($response)
    {
        $data = json_encode($this->_data);
        if ($data !== $response) {
            throw new Exception("Response value does not match '$response'\n\n"
                . $this->echoLastResponse());
        }
    }

    /**
     * @Given /^the response equals:/
     * @param PyStringNode $data
     */
    public function theResponseEqualsPyString(PyStringNode $response)
    {
        $this->theResponseEquals($response);
    }

    /**
     * @Given /^that I want to make a new "([^"]*)"$/
     */
    public function thatIWantToMakeANew($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'post';
    }

    /**
     * @Given /^that I want to update "([^"]*)"$/
     * @Given /^that I want to update an "([^"]*)"$/
     * @Given /^that I want to update a "([^"]*)"$/
     */
    public function thatIWantToUpdate($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'put';
    }


    /**
     * @Given /^that I want to find a "([^"]*)"$/
     */
    public function thatIWantToFindA($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'get';
    }

    /**
     * @Given /^that I want to delete a "([^"]*)"$/
     * @Given /^that I want to delete an "([^"]*)"$/
     * @Given /^that I want to delete "([^"]*)"$/
     */
    public function thatIWantToDeleteA($objectType)
    {
        $this->_restObjectType = ucwords(strtolower($objectType));
        $this->_restObjectMethod = 'delete';
    }

    /**
     * @Given /^that "([^"]*)" header is set to "([^"]*)"$/
     * @Given /^that "([^"]*)" header is set to (\d+)$/
     */
    public function thatHeaderIsSetTo($header, $value)
    {
        $this->_headers[$header] = $value;
    }


    /**
     * @Given /^that its "([^"]*)" is "([^"]*)"$/
     * @Given /^that his "([^"]*)" is "([^"]*)"$/
     * @Given /^that her "([^"]*)" is "([^"]*)"$/
     * @Given /^its "([^"]*)" is "([^"]*)"$/
     * @Given /^his "([^"]*)" is "([^"]*)"$/
     * @Given /^her "([^"]*)" is "([^"]*)"$/
     * @Given /^that "([^"]*)" is set to "([^"]*)"$/
     * @Given /^"([^"]*)" is set to "([^"]*)"$/
     */
    public function thatItsStringPropertyIs($propertyName, $propertyValue)
    {
        $this->_restObject->$propertyName = $propertyValue;
    }

    /**
     * @Given /^that its "([^"]*)" is (\d+)$/
     * @Given /^that his "([^"]*)" is (\d+)$/
     * @Given /^that her "([^"]*)" is (\d+)$/
     * @Given /^its "([^"]*)" is (\d+)$/
     * @Given /^his "([^"]*)" is (\d+)$/
     * @Given /^her "([^"]*)" is (\d+)$/
     * @Given /^that "([^"]*)" is set to (\d+)$/
     * @Given /^"([^"]*)" is set to (\d+)$/
     */
    public function thatItsNumericPropertyIs($propertyName, $propertyValue)
    {
        $this->_restObject->$propertyName = is_float($propertyValue)
            ? floatval($propertyValue)
            : intval($propertyValue);
    }

    /**
     * @Given /^that its "([^"]*)" is (true|false)$/
     * @Given /^that his "([^"]*)" is (true|false)$/
     * @Given /^that her "([^"]*)" is (true|false)$/
     * @Given /^its "([^"]*)" is (true|false)$/
     * @Given /^his "([^"]*)" is (true|false)$/
     * @Given /^her "([^"]*)" is (true|false)$/
     * @Given /^that "([^"]*)" is set to (true|false)$/
     * @Given /^"([^"]*)" is set to (true|false)$/
     */
    public function thatItsBooleanPropertyIs($propertyName, $propertyValue)
    {
        $this->_restObject->$propertyName = $propertyValue == 'true';
    }

    /**
     * @Given /^the request is sent as JSON$/
     * @Given /^the request is sent as Json$/
     */
    public function theRequestIsSentAsJson()
    {
        $this->_headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->_requestBody = json_encode(
            is_object($this->_restObject)
                ? (array)$this->_restObject
                : $this->_restObject
        );
    }

    /**
     * @When /^I request "([^"]*)"$/
     */
    public function iRequest($pageUrl)
    {
        try {
            $this->_startTime = microtime(true);
            $this->_requestUrl = $this->baseUrl . $pageUrl;
            $url = false !== strpos($pageUrl, '{')
                ? (new UriTemplate)->expand($pageUrl, (array)$this->_restObject)
                : $pageUrl;

            $method = strtoupper($this->_restObjectMethod);

            $this->_request_debug_stream = fopen('php://temp/', 'r+');

            $options = array(
                'headers' => $this->_headers,
                'http_errors' => false,
                'decode_content' => false,
                'debug' => $this->_request_debug_stream,
                //'curl' => array(CURLOPT_VERBOSE => true),
            );
            if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
                if (empty($this->_requestBody)) {
                    $postFields = is_object($this->_restObject)
                        ? (array)$this->_restObject
                        : $this->_restObject;
                    $options['form_params'] = $postFields;
                } else {
                    $options['body'] = $this->_requestBody;
                }
            }
            $this->_response = $this->_client->request($method, $url, $options);
        } catch (ClientException $ce) {
            $this->_request = $ce->getRequest();
            $this->_response = $ce->getResponse();
        }
        //detect type, extract data
        $this->_language = $this->_response->getHeaderLine('Content-Language');

        $cType = explode('; ', $this->_response->getHeaderLine('Content-type'));
        if (count($cType) > 1) {
            $charset = $cType[1];
            $this->_charset = substr($charset, strpos($charset, '=') + 1);
        }
        $cType = $cType[0];
        switch ($cType) {
            case 'application/json':
                $this->_type = 'json';
                $this->_data = json_decode($this->_response->getBody(true));
                switch (json_last_error()) {
                    case JSON_ERROR_NONE :
                        return;
                    case JSON_ERROR_DEPTH :
                        $message = 'maximum stack depth exceeded';
                        break;
                    case JSON_ERROR_STATE_MISMATCH :
                        $message = 'underflow or the modes mismatch';
                        break;
                    case JSON_ERROR_CTRL_CHAR :
                        $message = 'unexpected control character found';
                        break;
                    case JSON_ERROR_SYNTAX :
                        $message = 'malformed JSON';
                        break;
                    case JSON_ERROR_UTF8 :
                        $message = 'malformed UTF-8 characters, possibly ' .
                            'incorrectly encoded';
                        break;
                    default :
                        $message = 'unknown error';
                        break;
                }
                throw new Exception ('Error parsing JSON, ' . $message
                    . "\n\n" . $this->echoLastResponse());
                break;
            case 'application/xml':
                $this->_type = 'xml';
                libxml_use_internal_errors(true);
                libxml_disable_entity_loader(true);
                $this->_data = @simplexml_load_string(
                    $this->_response->getBody(true));
                if (!$this->_data) {
                    $message = '';
                    foreach (libxml_get_errors() as $error) {
                        $message .= $error->message . PHP_EOL;
                    }
                    throw new Exception ('Error parsing XML, ' . $message);
                }
                break;
        }
    }

    /**
     * @Then /^the response is JSON$/
     * @Then /^the response should be JSON$/
     */
    public function theResponseIsJson()
    {
        if ($this->_type != 'json') {
            throw new Exception("Response was not JSON\n\n" . $this->echoLastResponse());
        }
    }

    /**
     * @Then /^the response is XML$/
     * @Then /^the response should be XML$/
     */
    public function theResponseIsXml()
    {
        if ($this->_type != 'xml') {
            throw new Exception("Response was not XML\n\n" . $this->echoLastResponse());
        }
    }

    /**
     * @Then /^the response charset is "([^"]*)"$/
     */
    public function theResponseCharsetIs($charset)
    {
        if ($this->_charset != $charset) {
            throw new Exception("Response charset was not $charset\n\n" . $this->echoLastResponse());
        }
    }

    /**
     * @Then /^the response language is "([^"]*)"$/
     */
    public function theResponseLanguageIs($language)
    {
        if ($this->_language != $language) {
            throw new Exception("Response Language was not $language\n\n"
                . $this->echoLastResponse());
        }
    }

    /**
     * @Then /^the response "([^"]*)" header should be "([^"]*)"$/
     */
    public function theResponseHeaderShouldBe($header, $value)
    {
        if (!$this->_response->hasHeader($header)) {
            throw new Exception("Response header $header was not found\n\n"
                . $this->echoLastResponse());
        }
        if ((string)$this->_response->getHeaderLine($header) !== $value) {
            throw new Exception("Response header $header ("
                . (string)$this->_response->getHeaderLine($header)
                . ") does not match `$value`\n\n"
                . $this->echoLastResponse());
        }
    }

    /**
     * @Then /^the response "Expires" header should be Date\+(\d+) seconds$/
     */
    public function theResponseExpiresHeaderShouldBeDatePlusGivenSeconds($seconds)
    {
        $server_time = strtotime($this->_response->getHeaderLine('Date')) + $seconds;
        $expires_time = strtotime($this->_response->getHeaderLine('Expires'));
        if ($expires_time === $server_time || $expires_time === $server_time + 1) {
            return;
        }
        return $this->theResponseHeaderShouldBe(
            'Expires',
            gmdate('D, d M Y H:i:s \G\M\T', $server_time)
        );
    }

    /**
     * @Then /^the response time should at least be (\d+) milliseconds$/
     */
    public function theResponseTimeShouldAtLeastBeMilliseconds($milliSeconds)
    {
        usleep(1);
        $diff = 1000 * (microtime(true) - $this->_startTime);
        if ($diff < $milliSeconds) {
            throw new Exception("Response time $diff is "
                . "quicker than $milliSeconds\n\n"
                . $this->echoLastResponse());
        }
    }


    /**
     * @Given /^the type is "([^"]*)"$/
     */
    public function theTypeIs($type)
    {
        $data = $this->_data;

        switch ($type) {
            case 'bool':
            case 'boolean':
                if (is_bool($data)) {
                    return;
                }
            case 'string':
                if (is_string($data)) {
                    return;
                }
            case 'int':
                if (is_int($data)) {
                    return;
                }
            case 'float':
                if (is_float($data)) {
                    return;
                }
            case 'array' :
                if (is_array($data)) {
                    return;
                }
            case 'object' :
                if (is_object($data)) {
                    return;
                }
            case 'null' :
                if (is_null($data)) {
                    return;
                }
        }

        throw new Exception("Response is not of type '$type'\n\n" .
            $this->echoLastResponse());
    }

    /**
     * @Given /^the value equals "([^"]*)"$/
     */
    public function theValueEquals($sample)
    {
        $data = $this->_data;
        if ($data !== $sample) {
            throw new Exception("Response value does not match '$sample'\n\n"
                . $this->echoLastResponse());
        }
    }

    /**
     * @Given /^the value equals (\d+)$/
     */
    public function theNumericValueEquals($sample)
    {
        $sample = is_float($sample) ? floatval($sample) : intval($sample);
        return $this->theValueEquals($sample);
    }

    /**
     * @Given /^the value equals (true|false)$/
     */
    public function theBooleanValueEquals($sample)
    {
        $sample = $sample == 'true';
        return $this->theValueEquals($sample);
    }

    /**
     * @Then /^the response is JSON "([^"]*)"$/
     */
    public function theResponseIsJsonWithType($type)
    {
        if ($this->_type != 'json') {
            throw new Exception("Response was not JSON\n\n" . $this->echoLastResponse());
        }

        $data = $this->_data;

        switch ($type) {
            case 'string':
                if (is_string($data)) {
                    return;
                }
            case 'int':
                if (is_int($data)) {
                    return;
                }
            case 'float':
                if (is_float($data)) {
                    return;
                }
            case 'array' :
                if (is_array($data)) {
                    return;
                }
            case 'object' :
                if (is_object($data)) {
                    return;
                }
            case 'null' :
                if (is_null($data)) {
                    return;
                }
        }

        throw new Exception("Response was JSON\n but not of type '$type'\n\n" .
            $this->echoLastResponse());
    }


    /**
     * @Given /^the response has a "([^"]*)" property$/
     * @Given /^the response has an "([^"]*)" property$/
     * @Given /^the response has a property called "([^"]*)"$/
     * @Given /^the response has an property called "([^"]*)"$/
     */
    public function theResponseHasAProperty($propertyName)
    {
        $data = $this->_data;

        if (!empty($data)) {
            if (!isset($data->$propertyName)) {
                throw new Exception("Property '"
                    . $propertyName . "' is not set!\n\n"
                    . $this->echoLastResponse());
            }
        }
    }

    /**
     * @Then /^the "([^"]*)" property equals "([^"]*)"$/
     */
    public function thePropertyEquals($propertyName, $propertyValue)
    {
        $data = $this->_data;

        if (!empty($data)) {
            if (!isset($data->$propertyName)) {
                throw new Exception("Property '"
                    . $propertyName . "' is not set!\n\n"
                    . $this->echoLastResponse());
            }
            if ($data->$propertyName != $propertyValue) {
                throw new \Exception('Property value mismatch! (given: '
                    . $propertyValue . ', match: '
                    . $data->$propertyName . ")\n\n"
                    . $this->echoLastResponse());
            }
        } else {
            throw new Exception("Response was not JSON\n\n"
                . $this->_response->getBody(true));
        }
    }

    /**
     * @Then /^the "([^"]*)" property equals (\d+)$/
     */
    public function thePropertyEqualsNumber($propertyName, $propertyValue)
    {
        $propertyValue = is_float($propertyValue)
            ? floatval($propertyValue) : intval($propertyValue);
        return $this->thePropertyEquals($propertyName, $propertyValue);
    }

    /**
     * @Then /^the "([^"]*)" property equals (true|false)$/
     */
    public function thePropertyEqualsBoolean($propertyName, $propertyValue)
    {
        return $this->thePropertyEquals($propertyName, $propertyValue == 'true');
    }

    /**
     * @Given /^the type of the "([^"]*)" property is ([^"]*)$/
     */
    public function theTypeOfThePropertyIs($propertyName, $typeString)
    {
        $data = $this->_data;

        if (!empty($data)) {
            if (!isset($data->$propertyName)) {
                throw new Exception("Property '"
                    . $propertyName . "' is not set!\n\n"
                    . $this->echoLastResponse());
            }
            // check our type
            switch (strtolower($typeString)) {
                case 'numeric':
                    if (!is_numeric($data->$propertyName)) {
                        throw new Exception("Property '"
                            . $propertyName . "' is not of the correct type: "
                            . $typeString . "!\n\n"
                            . $this->echoLastResponse());
                    }
                    break;
            }

        } else {
            throw new Exception("Response was not JSON\n"
                . $this->_response->getBody(true));
        }
    }

    /**
     * @Then /^the response status code should be (\d+)$/
     */
    public function theResponseStatusCodeShouldBe($httpStatus)
    {
        if ((string)$this->_response->getStatusCode() !== $httpStatus) {
            throw new \Exception('HTTP code does not match ' . $httpStatus .
                ' (actual: ' . $this->_response->getStatusCode() . ")\n\n"
                . $this->echoLastResponse());
        }
    }

    /**
     * @Then /^echo last response$/
     */
    public function echoLastResponse()
    {
        global $argv;
        $level = 1;
        if (in_array('-v', $argv) || in_array('--verbose=1', $argv)) {
            $level = 2;
        } elseif (in_array('-vv', $argv) || in_array('--verbose=2', $argv)) {
            $level = 3;
        } elseif (in_array('-vvv', $argv) || in_array('--verbose=3', $argv)) {
            $level = 4;
        }
        //echo "$this->_request\n$this->_response";
        if ($level >= 2 && is_resource($this->_request_debug_stream)) {
            rewind($this->_request_debug_stream);
            echo stream_get_contents($this->_request_debug_stream) . PHP_EOL . PHP_EOL;
        }
        if ($level >= 1) {
            /** @var RequestInterface $req */
            $req = $this->_request;
            echo $req->getMethod() . ' ' . $req->getUri() . ' HTTP/' . $req->getProtocolVersion() . PHP_EOL;
            foreach ($req->getHeaders() as $k => $v) {
                echo ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL;
            }
            echo PHP_EOL;
            echo urldecode((string)$req->getBody()) . PHP_EOL . PHP_EOL;
        }
        /** @var ResponseInterface $res */
        $res = $this->_response;
        echo 'HTTP/' . $res->getProtocolVersion() . ' ' . $res->getStatusCode() . ' ' . $res->getReasonPhrase() . PHP_EOL;
        foreach ($res->getHeaders() as $k => $v) {
            echo ucwords($k) . ': ' . implode(', ', $v) . PHP_EOL;
        }
        echo PHP_EOL;
        echo (string)$res->getBody();
    }
}
