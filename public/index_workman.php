<?php declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Stream;
use function GuzzleHttp\Psr7\stream_for;
use improved\Authors as ImprovedAuthors;
use Luracast\Restler\App;
use Luracast\Restler\Cache\HumanReadableCache;
use Luracast\Restler\Filters\RateLimiter;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\MediaTypes\Xml;
use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Reactler;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\ClassName;
use Luracast\Restler\Utils\Dump;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ratelimited\Authors as RateLimitedAuthors;
use v1\BMI as BMI1;
use Workerman\Worker;

define('BASE', dirname(__DIR__));
include __DIR__ . "/../vendor/autoload.php";

App::$cacheDirectory = HumanReadableCache::$cacheDir = __DIR__ . '/../api/common/store';

App::$implementations[DataProviderInterface::class] = [ArrayDataProvider::class];
App::$implementations[ResponseInterface::class] = [Response::class];
App::$implementations[RequestInterface::class] = [ServerRequest::class];
App::$implementations[ServerRequestInterface::class] = [ServerRequest::class];

RateLimiter::setLimit('hour', 10);
RateLimiter::setIncludedPaths('examples/_009_rate_limiting');
App::$useUrlBasedVersioning = true;
App::$apiVendor = "SomeVendor";
App::$useVendorMIMEVersioning = true;
Router::setApiVersion(2);

function read_headers($server = [])
{
    $headers = array();
    $copy_server = array(
        'CONTENT_TYPE' => 'Content-Type',
        'CONTENT_LENGTH' => 'Content-Length',
        'CONTENT_MD5' => 'Content-Md5',
    );
    foreach ($server as $key => $value) {
        if (substr($key, 0, 5) === 'HTTP_') {
            $key = substr($key, 5);
            if (!isset($copy_server[$key]) || !isset($server[$key])) {
                $key = str_replace(' ', '-',
                    ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$key] = $value;
            }
        } elseif (isset($copy_server[$key])) {
            $headers[$copy_server[$key]] = $value;
        }
    }
    if (!isset($headers['Authorization'])) {
        if (isset($server['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (isset($server['PHP_AUTH_USER'])) {
            $basic_pass = isset($server['PHP_AUTH_PW']) ? $server['PHP_AUTH_PW'] : '';
            $headers['Authorization'] = 'Basic ' . base64_encode($server['PHP_AUTH_USER'] . ':' . $basic_pass);
        } elseif (isset($server['PHP_AUTH_DIGEST'])) {
            $headers['Authorization'] = $server['PHP_AUTH_DIGEST'];
        }
    }
    return $headers;
}

class ResetForTests
{
    /**
     * @param string $folder {@from path}
     * @param int $version
     * @return array
     */
    function get($folder = 'explorer', $version = 1)
    {
        return Router::toArray();//["v$version"];//[$folder] ?? [];
    }

    function put()
    {
        //reset database
        $class = ClassName::get(DataProviderInterface::class);
        $class::reset();
        //reset cache
        $folder = BASE . '/api/common/store/';
        foreach (glob($folder . "*.php") as $filename) {
            unlink($filename);
        }
        return 'success';
    }
}

try {
    Router::mapApiClasses([
        //clean up db for tests
        '__cleanup_db' => ResetForTests::class,
        //examples
        'examples/_001_helloworld/say' => Say::class,
        'examples/_002_minimal/math' => Math::class,
        'examples/_003_multiformat/bmi' => BMI::class,
        'examples/_004_error_response/currency' => Currency::class,
        'examples/_005_protected_api' => Simple::class,
        'examples/_005_protected_api/secured' => Secured::class,
        'examples/_006_routing/api' => Api::class,
        'examples/_007_crud/authors' => Authors::class,
        'examples/_008_documentation/authors' => ImprovedAuthors::class,
        'examples/_009_rate_limiting/authors' => RateLimitedAuthors::class,
        'examples/_010_access_control' => Access::class,
        'examples/_011_versioning/bmi' => BMI1::class,
        //tests
        'tests/param/minmax' => MinMax::class,
        'tests/param/minmaxfix' => MinMaxFix::class,
        'tests/param/type' => Type::class,
        'tests/param/validation' => Validation::class,
        'tests/request_data' => Data::class,
        //Explorer
        'explorer' => Explorer::class,
    ]);
    Router::setOverridingResponseMediaTypes(Json::class, Xml::class);
    SimpleAuth::setIncludedPaths('examples/_005_protected_api');
    Router::addAuthenticator(SimpleAuth::class, 'examples/_005_protected_api/simpleauth');
    KeyAuth::setIncludedPaths('examples/_009_rate_limiting');
    Router::addAuthenticator(KeyAuth::class, 'examples/_009_rate_limiting/keyauth');
    AccessControl::setIncludedPaths('examples/_010_access_control');
    Router::addAuthenticator(AccessControl::class, 'examples/_010_access_control/accesscontrol');
    Router::setFilters(RateLimiter::class);
} catch (Throwable $t) {
    die($t->getMessage());
}
$routes = Router::toArray();
//var_export($routes);
//var_export(array_sort(array_keys($routes['v1'])));
//var_export(array_sort(array_keys($routes['v2'])));
//var_export(Router::$formatMap);

// #### http worker ####
$http_worker = new Worker("http://0.0.0.0:8080");

// 4 processes
$http_worker->count = 4;

// Emitted when data received
$http_worker->onMessage = function ($connection, $data) {
    /*
    $headers = read_headers($data['server']);
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    $uri = ServerRequest::getUriFromGlobals();
    $request = new ServerRequest($method, $uri, $headers,
        $GLOBALS['HTTP_RAW_REQUEST_DATA'], '1.1', $data['server']);
    */
    $request = ServerRequest::fromGlobals();
    $request = $request->withBody(stream_for($GLOBALS['HTTP_RAW_REQUEST_DATA']));
    echo Dump::request($request);
    $r = new Reactler();
    $response = $r->handle($request);
    $connection->send(Dump::response($response), true);
    $connection->close();
};

// run all workers
Worker::runAll();