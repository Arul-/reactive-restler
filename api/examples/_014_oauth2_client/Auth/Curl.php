<?php

namespace Auth;

class Curl
{
    private $options;

    public static $request;

    public static function __callStatic($name, $arguments)
    {
        if (property_exists(static::class, $name) && is_callable([static::class, $name])) {
            return static::$$name(...$arguments);
        }
    }

    public function __construct($options = array())
    {
        $this->options = array_merge(array(
            'debug' => false,
            'http_port' => '80',
            'user_agent' => 'PHP-curl-client (https://github.com/bshaffer/oauth2-server-demo)',
            'timeout' => 20,
            'curlopts' => null,
            'verifyssl' => true,
        ), $options);
    }

    /**
     * Send a request to the server, receive a response
     *
     * @param $url
     * @param  array $parameters Parameters
     * @param  string $httpMethod HTTP method to use
     *
     * @param array $options
     * @param callable|null $callback
     * @return void
     */
    public function requestOLD(
        $url,
        array $parameters = [],
        $httpMethod = 'GET',
        array $options = [],
        callable $callback = null
    ) {
        $options['http_port'] = parse_url($url, PHP_URL_PORT) ?? 80;
        $options = array_merge($this->options, $options);

        $curlOptions = array();
        $headers = array();

        if ('POST' === $httpMethod) {
            $curlOptions += array(
                CURLOPT_POST => true,
            );
        } elseif ('PUT' === $httpMethod) {
            $curlOptions += array(
                CURLOPT_POST => true, // This is so cURL doesn't strip CURLOPT_POSTFIELDS
                CURLOPT_CUSTOMREQUEST => 'PUT',
            );
        } elseif ('DELETE' === $httpMethod) {
            $curlOptions += array(
                CURLOPT_CUSTOMREQUEST => 'DELETE',
            );
        }

        if (!empty($parameters)) {
            if ('GET' === $httpMethod) {
                $queryString = utf8_encode($this->buildQuery($parameters));
                $url .= '?' . $queryString;
            } elseif ('POST' === $httpMethod) {
                $curlOptions += array(
                    CURLOPT_POSTFIELDS => $parameters,
                );
            } else {
                $curlOptions += array(
                    CURLOPT_POSTFIELDS => json_encode($parameters)
                );
                $headers[] = 'Content-Type: application/json';
            }
        } else {
            $headers[] = 'Content-Length: 0';
        }

        $this->debug('send ' . $httpMethod . ' request: ' . $url);

        $curlOptions += array(
            CURLOPT_URL => $url,
            CURLOPT_PORT => $options['http_port'],
            CURLOPT_USERAGENT => $options['user_agent'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $options['timeout'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $options['verifyssl'],
        );

        if (ini_get('open_basedir') == '' && ini_get('safe_mode') != 'On') {
            $curlOptions[CURLOPT_FOLLOWLOCATION] = true;
        }

        if (is_array($options['curlopts'])) {
            $curlOptions += $options['curlopts'];
        }

        if (isset($options['proxy'])) {
            $curlOptions[CURLOPT_PROXY] = $options['proxy'];
        }

        $response = $this->doCurlCall($curlOptions);

        if (!empty($response['response'])) {
            //success
            if (is_callable($callback)) {
                $callback(null, $response);
            }
        } else {
            // render error if applicable
            ($error =
                //OAuth error
                $response['error_description'] ?? null) ||
            ($error =
                //Restler exception
                $response['error']['message'] ?? null) ||
            ($error =
                //cURL error
                $response['errorMessage'] ?? null) ||
            ($error =
                //cURL error with out message
                $response['errorNumber'] ?? null) ||
            ($error =
                'Unknown Error');
            //success
            if (is_callable($callback)) {
                $exception = new \Error($error);
                $exception->uri = $response['error_uri'] ?? null;
                $callback($exception, null);
            }
        }
    }

    /**
     * Get a JSON response and transform it to a PHP array
     *
     * @return  array   the response
     */
    protected function decodeResponse($response)
    {
        // "false" means a failed curl request
        if (false === $response['response']) {
            $this->debug(print_r($response, true));
            return false;
        }
        return parent::decodeResponse($response);
    }

    protected function doCurlCall(array $curlOptions)
    {
        $curl = curl_init();

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $headers = curl_getinfo($curl);
        $errorNumber = curl_errno($curl);
        $errorMessage = curl_error($curl);

        curl_close($curl);

        return compact('response', 'headers', 'errorNumber', 'errorMessage');
    }

    protected function buildQuery($parameters)
    {
        return http_build_query($parameters, '', '&');
    }

    protected function debug($message)
    {
        if ($this->options['debug']) {
            print $message . "\n";
        }
    }
}
