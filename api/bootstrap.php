<?php declare(strict_types=1);

use improved\Authors as ImprovedAuthors;
use Luracast\Restler\Cache\HumanReadableCache;
use Luracast\Restler\Defaults;
use Luracast\Restler\Filters\RateLimiter;
use Luracast\Restler\MediaTypes\Html;
use Luracast\Restler\MediaTypes\Json;
use Luracast\Restler\MediaTypes\Xml;
use Luracast\Restler\OpenApi3\Explorer;
use Luracast\Restler\Router;
use Luracast\Restler\UI\Forms;
use Luracast\Restler\UI\FormStyles;
use Luracast\Restler\Utils\ClassName;
use ratelimited\Authors as RateLimitedAuthors;
use v1\BMI as BMI1;

define('BASE', dirname(__DIR__));
include BASE . "/vendor/autoload.php";

Defaults::$cacheDirectory = HumanReadableCache::$cacheDir = BASE . '/api/common/store';
Defaults::$implementations[DataProviderInterface::class] = [SerializedFileDataProvider::class];
Defaults::$useUrlBasedVersioning = true;
Defaults::$apiVendor = "SomeVendor";
Defaults::$useVendorMIMEVersioning = true;
Router::setApiVersion(2);
RateLimiter::setLimit('hour', 10);
RateLimiter::setIncludedPaths('examples/_009_rate_limiting');
Html::$template = 'blade'; //'handlebar'; //'twig'; //'php';
$themes4 = [
    'cerulean',
    'cosmo',
    'cyborg',
    'darkly',
    'flatly',
    'journal',
    'litera',
    'lumen',
    'lux',
    'materia',
    'minty',
    'pulse',
    'sandstone',
    'simplex',
    'sketchy',
    'slate',
    'solar',
    'spacelab',
    'superhero',
    'united',
    'yeti',
];
//bootstarp 3
$themes = [
    'cerulean',
    'cosmo',
    'cyborg',
    'darkly',
    'flatly',
    'journal',
    'lumen',
    'paper',
    'readable',
    'sandstone',
    'simplex',
    'slate',
    'solar',
    'spacelab',
    'superhero',
    'united',
    'yeti',
];
$theme = 'foundation5'; //$themes[array_rand($themes, 1)];
$style = $theme == 'foundation5' ? 'foundation5' : 'bootstrap3';
Html::$data += compact('theme', 'themes', 'style');

Forms::$style = FormStyles::$$style;


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
        'examples/_013_html/tasks' => Tasks::class,
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