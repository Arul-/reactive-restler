<?php declare(strict_types=1);

use Illuminate\Config\Repository as Config;
use Luracast\Restler\App;
use Luracast\Restler\Container;
use Luracast\Restler\Filters\RateLimiter;
use Luracast\Restler\HumanReadableCache;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\MediaTypes\Xml;
use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Reactler;
use Luracast\Restler\Router;
use Luracast\Restler\Scope;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Http\Server;
use React\Promise\Promise;
use improved\Authors as ImprovedAuthors;
use ratelimited\Authors as RateLimitedAuthors;

include __DIR__ . "/../vendor/autoload.php";

//Defaults::$validatorClass = Validator::class;
//App::$useUrlBasedVersioning = true;
App::$cacheDirectory = HumanReadableCache::$cacheDir = __DIR__ . '/../api/common/store';

define('BASE', dirname(__DIR__));
App::$implementations[DataProviderInterface::class] = [ArrayDataProvider::class];


//$class = DATA_STORE_IMPLEMENTATION;
/** @var DataProviderInterface $i */
//$instance = new $class('db2');


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
        $class = App::getClass(DataProviderInterface::class);
        $class::reset();
        //reset cache
        $folder = BASE . '/api/common/store/';
        foreach (glob($folder . "*.php") as $filename) {
            unlink($filename);
        }
    }
}

RateLimiter::setLimit('hour', 10);
RateLimiter::$includedPaths = ['examples/_009_rate_limiting'];

App::$useUrlBasedVersioning = true;

Router::setApiVersion(2);

try {
    Router::mapApiClasses([
        //clean up db for tests
        ResetForTests::class => '__cleanup_db',
        //examples
        Say::class => 'examples/_001_helloworld/say',
        Math::class => 'examples/_002_minimal/math',
        BMI::class => 'examples/_003_multiformat/bmi',
        Currency::class => 'examples/_004_error_response/currency',
        Simple::class => 'examples/_005_protected_api',
        Secured::class => 'examples/_005_protected_api/secured',
        Api::class => 'examples/_006_routing/api',
        Authors::class => 'examples/_007_crud/authors',
        ImprovedAuthors::class => 'examples/_008_documentation/authors',
        RateLimitedAuthors::class => 'examples/_009_rate_limiting/authors',
        Access::class => 'examples/_010_access_control',
        'BMI' => 'examples/_011_versioning/bmi',
        //tests
        MinMax::class => 'tests/param/minmax',
        MinMaxFix::class => 'tests/param/minmaxfix',
        Type::class => 'tests/param/type',
        Validation::class => 'tests/param/validation',
        Data::class => 'tests/request_data',
        //Explorer
        Explorer::class => 'explorer',
    ]);
    Router::setMediaTypes(Json::class, Xml::class);
    Router::addAuthenticator(SimpleAuth::class, 'examples/_005_protected_api/simpleauth');
    Router::addAuthenticator(KeyAuth::class, 'examples/_009_rate_limiting/keyauth');
    Router::addAuthenticator(AccessControl::class, 'examples/_010_access_control/accesscontrol');
    Router::setFilters(RateLimiter::class);
} catch (Throwable $t) {
    die($t->getMessage());
}
$routes = Router::toArray();

//var_dump(Router::$versionMap);

$loop = React\EventLoop\Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    return new Promise(function ($resolve, $reject) use ($request) {
        echo '      ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . PHP_EOL;
        $content = "";
        $request->getBody()->on('data', function ($data) use (&$content) {
            $content .= $data;
        });

        $request->getBody()->on('end', function () use ($request, $resolve, &$content) {
            $c = new Container();
            $h = new Reactler($c);
            $request = $request->withAttribute('reactler', $h);
            Scope::set('Restler', $h);
            try {
                $response = $h->handle($request, new Response(), $content);
                $resolve($response);
            } catch (Throwable $throwable) {
                var_dump($throwable);
                die();
            }
        });

        // an error occurs e.g. on invalid chucked encoded data or an unexpected 'end' event
        $request->getBody()->on('error', function (Exception $exception) use ($resolve, &$contentLength) {
            $response = new Response(
                400,
                ['Content-Type' => 'text/plain'],
                "An error occurred while reading at length: " . $contentLength
            );
            $resolve($response);
        });
    });
});

$socket = new React\Socket\Server(8080, $loop);
$server->listen($socket);

echo "Server running at http://127.0.0.1:8080\n";

$loop->run();