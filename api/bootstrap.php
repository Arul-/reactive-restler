<?php declare(strict_types=1);

use Auth\Client;
use Auth\Server;
use improved\Authors as ImprovedAuthors;
use Luracast\Restler\Cache\HumanReadableCache;
use Luracast\Restler\Defaults;
use Luracast\Restler\Filters\RateLimiter;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\MediaTypes\Upload;
use Luracast\Restler\MediaTypes\Xml;
use Luracast\Restler\Middleware\SessionMiddleware;
use Luracast\Restler\Middleware\StaticFiles;
use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;
use Luracast\Restler\Utils\ClassName;
use Luracast\Restler\Utils\Text;
use ratelimited\Authors as RateLimitedAuthors;
use SomeVendor\v1\BMI as VendorBMI1;
use v1\BMI as BMI1;

define('BASE', dirname(__DIR__));
include BASE . "/vendor/autoload.php";

Defaults::$cacheDirectory = HumanReadableCache::$cacheDir = BASE . '/api/common/store';
Defaults::$implementations[DataProviderInterface::class] = [SerializedFileDataProvider::class];
Defaults::$useUrlBasedVersioning = true;
Defaults::$apiVendor = "SomeVendor";
Defaults::$useVendorMIMEVersioning = true;
Defaults::$implementations[HttpClientInterface::class] = [CurlHttpClient::class];
Router::setApiVersion(2);
RateLimiter::setLimit('hour', 10);
RateLimiter::setIncludedPaths('examples/_009_rate_limiting');
Html::$template = 'blade'; //'handlebar'; //'twig'; //'php';
if (!Text::endsWith($_SERVER['SCRIPT_NAME'], 'index.php')) {
    //when serving through apache or nginx, static files will be served direcly by apache / nginx
    Restler::$middleware[] = new StaticFiles(BASE . '/' . 'public');
}
//Restler::$middleware[] = new SessionMiddleware('RESTLERSESSID', new ArrayCache(), [0, '', '', false, false]);
Restler::$middleware[] = new SessionMiddleware();

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
        'examples/_012_vendor_mime/bmi' => VendorBMI1::class,
        'examples/_013_html/tasks' => Tasks::class,
        'examples/_014_oauth2_client' => Client::class,
        'examples/_015_oauth2_server' => Server::class,
        'examples/_016_forms/users' => Users::class,
        //tests
        'tests/param/minmax' => MinMax::class,
        'tests/param/minmaxfix' => MinMaxFix::class,
        'tests/param/type' => Type::class,
        'tests/param/validation' => Validation::class,
        'tests/request_data' => Data::class,
        //Explorer
        'explorer' => Explorer::class,
    ]);
    Router::setOverridingResponseMediaTypes(Json::class, Xml::class, Html::class);
    Router::setOverridingRequestMediaTypes(Json::class, Upload::class);
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
//$routes = Router::toArray();
//var_export($routes);
//var_export(array_sort(array_keys($routes['v1'])));
//var_export(array_sort(array_keys($routes['v2'])));
//var_export(Router::$formatMap);