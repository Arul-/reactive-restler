<?php declare(strict_types=1);

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
use Psr\Http\Message\ServerRequestInterface;
use ratelimited\Authors as RateLimitedAuthors;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\StreamingServer;
use v1\BMI as BMI1;

define('BASE', dirname(__DIR__));
include __DIR__ . "/../vendor/autoload.php";

App::$cacheDirectory = HumanReadableCache::$cacheDir = __DIR__ . '/../api/common/store';
App::$implementations[DataProviderInterface::class] = [ArrayDataProvider::class];
RateLimiter::setLimit('hour', 10);
RateLimiter::setIncludedPaths('examples/_009_rate_limiting');
App::$useUrlBasedVersioning = true;
App::$apiVendor = "SomeVendor";
App::$useVendorMIMEVersioning = true;
Router::setApiVersion(2);

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

$loop = React\EventLoop\Factory::create();

$server = new StreamingServer([
    new LimitConcurrentRequestsMiddleware(100), // 100 concurrent buffering handlers
    new RequestBodyBufferMiddleware(16 * 1024 * 1024), // 16 MiB
    new App(),
    /*
    function (ServerRequestInterface $request) {
        echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
        try {
            $h = new Reactler();
            return $h->handle($request);
        } catch (Throwable $throwable) {
            var_dump($throwable);
            die();
        }
    }
    */
]);


$server->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});


$socket = new React\Socket\Server(8080, $loop);
$server->listen($socket);

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();