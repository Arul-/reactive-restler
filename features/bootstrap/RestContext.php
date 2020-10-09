<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Testwork\Hook\Scope\AfterSuiteScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RedirectMiddleware;
use Luracast\Restler\Utils\Text;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Rize\UriTemplate\UriTemplate;
use Symfony\Component\Console\Input\ArgvInput;

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
    public const COOKIE_FILE = 'behat-guzzle-cookie-data.json';
    protected $baseUrl = '';
    private $_request_debug_stream = null;
    private $_startTime = null;
    private $_restObject = null;
    private $_headers = [
        'Accept-Language' => 'en'
    ];
    private $_restObjectType = null;
    private $_restObjectMethod = 'get';
    /** @var Client */
    private $_client = null;
    /** @var \GuzzleHttp\Psr7\Response */
    private $_response = null;
    /** @var \GuzzleHttp\Psr7\Request */
    private $_request = null;
    private $_requestBody = null;
    private $_requestUrl = null;
    private $_type = null;
    private $_charset = null;
    private $_language = null;
    private $_data = null;

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
        $handler->push(
            Middleware::mapRequest(
                function (RequestInterface $request) {
                    // Notice that we have to return a request object
                    $this->_request = $request;
                    return $request;
                }
            )
        );
        $cookieJar = new FileCookieJar(self::COOKIE_FILE, true);
        $this->_client = new Client(
            [
                'base_uri' => $baseUrl,
                'handler' => $handler,
                'allow_redirects' => ['track_redirects' => true],
                'cookies' => $cookieJar
            ]
        );
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
    }

    /**
     * @BeforeSuite
     *
     * @param NotBeforeSuiteScope $scope
     */
    public static function prepare(BeforeSuiteScope $scope)
    {
        $environment = $scope->getEnvironment();
        $contexts = $environment->getContextClassesWithArguments();
        $baseUrl = $contexts[static::class][0];
        // prepare system for test suite
        // before it runs
        $client = new Client(['base_uri' => $baseUrl]);
        $result = $client->delete('examples/-storage-/all');
    }

    /**
     * @AfterSuite
     *
     * @param NotAfterSuiteScope $scope
     */
    public static function package(AfterSuiteScope $scope)
    {
        $input = new ArgvInput($_SERVER['argv']);
        $profile = $input->getParameterOption(['--profile', '-p']) ?: 'default';
        if ('default' !== $profile) {
            return;
        }
        $environment = $scope->getEnvironment();
        $contexts = $environment->getContextClassesWithArguments();
        $baseUrl = $contexts[static::class][0];
        // package all loaded files in cache folder
        // after all tests completed
        $client = new Client(['base_uri' => $baseUrl]);
        //load explorer dependencies
        $client->get('explorer/docs.json');
        $result = $client->get('examples/-storage-/pack');
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
            throw new Exception(
                "Response value does not contain '$response' only\n\n"
                . $this->echoLastResponse()
            );
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

    /**
     * @Given /^the response equals:/
     * @param PyStringNode $data
     */
    public function theResponseEqualsPyString(PyStringNode $response)
    {
        $this->theResponseEquals($response);
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
            throw new Exception(
                "Response value does not match '$response'\n\n"
                . $this->echoLastResponse()
            );
        }
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
     * @When /^request "([^"]*)"$/
     */
    public function iRequest($path)
    {
        try {
            $parts = explode(' ', $path);
            if (2 === count($parts)) {
                $this->_restObjectMethod = $parts[0];
                $path = $parts[1];
            }
            $this->_startTime = microtime(true);
            $this->_requestUrl = $this->baseUrl . $path;
            $url = false !== strpos($path, '{')
                ? (new UriTemplate)->expand($path, (array)$this->_restObject)
                : $path;

            $method = strtoupper($this->_restObjectMethod);

            $this->_request_debug_stream = fopen('php://temp/', 'r+');

            $options = [
                'headers' => $this->_headers,
                'http_errors' => false,
                'decode_content' => false,
                'debug' => $this->_request_debug_stream,
                //'curl' => array(CURLOPT_VERBOSE => true),
            ];
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
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

        $cType = explode(';', $this->_response->getHeaderLine('Content-type'));
        if (count($cType) > 1) {
            $charset = trim($cType[1]);
            $this->_charset = substr($charset, strpos($charset, '=') + 1);
        }
        $cType = $cType[0];
        if (strpos($cType, '+') > 0) {
            //look for vendor mime
            //example 'application/vnd.SomeVendor-v1+json','application/vnd.SomeVendor-v2+json'
            list($app, $vendor, $extension) = [strtok($cType, '/'), strtok('+'), strtok('')];
            $cType = "$app/$extension";
        }
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
                throw new Exception (
                    'Error parsing JSON, ' . $message
                    . "\n\n" . $this->echoLastResponse()
                );
                break;
            case 'application/xml':
                $this->_type = 'xml';
                libxml_use_internal_errors(true);
                libxml_disable_entity_loader(true);
                $this->_data = @simplexml_load_string(
                    $this->_response->getBody(true)
                );
                if (!$this->_data) {
                    $message = '';
                    foreach (libxml_get_errors() as $error) {
                        $message .= $error->message . PHP_EOL;
                    }
                    throw new Exception ('Error parsing XML, ' . $message);
                }
                break;
            case 'text/html':
                $this->_type = 'html';
                break;
        }
    }

    /**
     * @When I accept :header
     * @param $header
     */
    public function accept($header)
    {
        $this->_headers['Accept'] = $header;
    }

    /**
     * @When accept language :language
     * @param $language
     */
    public function acceptLanguage($language)
    {
        $this->_headers['Accept-Language'] = $language;
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
     * @Then /^the response is HTML$/
     * @Then /^the response should be HTML$/
     */
    public function theResponseIsHtml()
    {
        if ($this->_type != 'html') {
            throw new Exception("Response was not Html\n\n" . $this->echoLastResponse());
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
            throw new Exception(
                "Response Language was not $language\n\n"
                . $this->echoLastResponse()
            );
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
     * @Then /^the response "([^"]*)" header should be "([^"]*)"$/
     * @Then /^the response "([^"]*)" header should be '([^']*)'$/
     */
    public function theResponseHeaderShouldBe($header, $value)
    {
        if (!$this->_response->hasHeader($header)) {
            throw new Exception(
                "Response header $header was not found\n\n"
                . $this->echoLastResponse()
            );
        }
        if (strcasecmp((string)$this->_response->getHeaderLine($header), $value)) {
            throw new Exception(
                "Response header $header ("
                . (string)$this->_response->getHeaderLine($header)
                . ") does not match `$value`\n\n"
                . $this->echoLastResponse()
            );
        }
    }

    /**
     * @Then /^the response time should at least be (\d+) milliseconds$/
     */
    public function theResponseTimeShouldAtLeastBeMilliseconds($milliSeconds)
    {
        usleep(1);
        $diff = 1000 * (microtime(true) - $this->_startTime);
        if ($diff < $milliSeconds) {
            throw new Exception(
                "Response time $diff is "
                . "quicker than $milliSeconds\n\n"
                . $this->echoLastResponse()
            );
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
                break;
            case 'string':
                if (is_string($data)) {
                    return;
                }
                break;
            case 'int':
            case 'integer':
                if (is_int($data)) {
                    return;
                }
                break;
            case 'float':
                if (is_float($data)) {
                    return;
                }
                break;
            case 'array' :
                if (is_array($data) || is_object($data)) {
                    return;
                }
                break;
            case 'object' :
                if (is_object($data)) {
                    return;
                }
                break;
            case 'null' :
                if (is_null($data)) {
                    return;
                }
        }

        throw new Exception(
            "Response is not of type '$type'\n\n" .
            $this->echoLastResponse()
        );
    }

    /**
     * @Given /^the value equals (\d+)$/
     */
    public function theNumericValueEquals($value)
    {
        $value = is_float($value) ? floatval($value) : intval($value);
        return $this->theValueEquals($value);
    }

    /**
     * @Given /^the value equals "([^"]*)"$/
     */
    public function theValueEquals($value)
    {
        $data = $this->_data;
        if ($data !== $value) {
            throw new Exception(
                sprintf(
                    "Response value does not match %s\n\n%s",
                    $this->typeFormat($value),
                    $this->echoLastResponse()
                )
            );
        }
    }

    private function typeFormat($value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        return $value = '"' . $value . '"';
    }

    /**
     * @Given /^the value equals (true|false)$/
     */
    public function theBooleanValueEquals($value)
    {
        $value = $value == 'true';
        return $this->theValueEquals($value);
    }

    /**
     * @Given /^the value equals (null)$/
     * @Given /^the value is (null)$/
     */
    public function theValueIsNull($value)
    {
        return $this->theValueEquals(null);
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

        throw new Exception(
            "Response was JSON\n but not of type '$type'\n\n" .
            $this->echoLastResponse()
        );
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
                throw new Exception(
                    "Property '"
                    . $propertyName . "' is not set!\n\n"
                    . $this->echoLastResponse()
                );
            }
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
     * @Then /^the "([^"]*)" property equals "([^"]*)"$/
     * @Then /^the "([^"]*)" property equals (null)$/
     * @Then /^the "([^"]*)" property is (null)$/
     */
    public function thePropertyEquals($propertyName, $propertyValue = null)
    {
        $data = $this->_data;

        if ('null' === $propertyValue) {
            $propertyValue = null;
        }

        if (!empty($data)) {
            $p = $data;
            $properties = explode('.', $propertyName);
            foreach ($properties as $property) {
                if (!isset($p->$property) && !is_null($propertyValue)) {
                    throw new Exception(
                        "Property '"
                        . $propertyName . "' is not set!\n\n"
                        . $this->echoLastResponse()
                    );
                }
                $p = $p->$property;
            }
            if ($p != $propertyValue) {
                throw new \Exception(
                    sprintf(
                        "Property value mismatch! (given: %s, expected: %s)\n\n%s",
                        $this->typeFormat($p),
                        $this->typeFormat($propertyValue),
                        $this->echoLastResponse()
                    )
                );
            }
        } else {
            throw new Exception(
                "Response was not JSON\n\n"
                . $this->_response->getBody(true)
            );
        }
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
                throw new Exception(
                    "Property '"
                    . $propertyName . "' is not set!\n\n"
                    . $this->echoLastResponse()
                );
            }
            // check our type
            switch (strtolower($typeString)) {
                case 'numeric':
                    if (!is_numeric($data->$propertyName)) {
                        throw new Exception(
                            "Property '"
                            . $propertyName . "' is not of the correct type: "
                            . $typeString . "!\n\n"
                            . $this->echoLastResponse()
                        );
                    }
                    break;
            }
        } else {
            throw new Exception(
                "Response was not JSON\n"
                . $this->_response->getBody(true)
            );
        }
    }

    /**
     * @Then /^the response status code should be (\d+)$/
     */
    public function theResponseStatusCodeShouldBe($httpStatus)
    {
        if ((string)$this->_response->getStatusCode() !== $httpStatus) {
            throw new \Exception(
                'HTTP code does not match ' . $httpStatus .
                ' (actual: ' . $this->_response->getStatusCode() . ")\n\n"
                . $this->echoLastResponse()
            );
        }
    }

    /**
     * @Given /^the value equals "([^"]*)" or "([^"]*)"$/
     */
    public function theValueEqualsOr($value1, $value2)
    {
        try {
            $this->theValueEquals($value1);
        } catch (Exception $exception) {
            try {
                $this->theValueEquals($value2);
            } catch (Exception $exception2) {
                throw new Exception(
                    sprintf(
                        "Response value does not match both %s and %s\n\n",
                        $this->typeFormat($value1),
                        $this->typeFormat($value2)
                    )
                    . $this->echoLastResponse()
                );
            }
        }
    }

    /**
     * @Then the response redirects to :expectedPath
     */
    public function theResponseRedirectsTo($expectedPath)
    {
        $redirects = $this->_response->getHeaderLine(RedirectMiddleware::HISTORY_HEADER);
        if (empty($redirects)) {
            throw new Exception("Response was not Redirected\n");
        }
        $actual = ltrim(str_replace($this->baseUrl, '', $redirects), '/');
        if ($expectedPath !== $actual) {
            throw new Exception("Redirect did not go to '$expectedPath'\n(actual: '$actual')\n");
        }
    }

}
