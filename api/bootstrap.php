<?php declare(strict_types=1);

use Auth\Client;
use Auth\Server;
use improved\Authors as ImprovedAuthors;
use Luracast\Restler\Cache\HumanReadable;
use Luracast\Restler\Data\Route;
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
use Psr\Http\Message\ServerRequestInterface;
use ratelimited\Authors as RateLimitedAuthors;
use SomeVendor\v1\BMI as VendorBMI1;
use v1\BMI as BMI1;

define('BASE', dirname(__DIR__));
include BASE . "/vendor/autoload.php";

Defaults::$cacheDirectory = BASE . '/api/common/store';
Defaults::$implementations[DataProviderInterface::class] = [SerializedFileDataProvider::class];
Defaults::$useUrlBasedVersioning = true;
Defaults::$apiVendor = "SomeVendor";
Defaults::$useVendorMIMEVersioning = true;
Defaults::$implementations[HttpClientInterface::class] = [SimpleHttpClient::class];
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
        $folder = Defaults::$cacheDirectory . DIRECTORY_SEPARATOR;

        $pattern = Text::contains(BASE, 'private')
            ? "$folder*.php"
            : "$folder{,*/,*/*/,*/*/*/}*.php";
        $files = glob($pattern, GLOB_BRACE);
        foreach ($files as $filename) {
            unlink($filename);
        }
        return $files;
    }

    function package()
    {
        if (Text::contains(BASE, 'private'))
            return [];
        //make sure the following classes are added
        class_exists(Symfony\Polyfill\Mbstring\Mbstring::class);
        class_exists(React\Promise\RejectedPromise::class);
        class_exists(ClassName::get('HttpClientInterface'));
        /*

        //class_exists(Symfony\Polyfill\Php73\Php73::class);
        class_exists(\Luracast\Restler\ArrayObject::class);
        class_exists(\Illuminate\Support\Collection::class);
        class_exists(Twig\Node\Expression\Test\DefinedTest::class);
        class_exists(Twig\Node\IfNode::class);
        class_exists(Twig\Lexer::class);
        class_exists(Twig\TwigFilter::class);
        class_exists(Twig\TwigTest::class);
        class_exists(Twig\TokenParser\ApplyTokenParser::class);
        class_exists(Twig\TokenParser\ForTokenParser::class);
        class_exists(Twig\TokenParser\IfTokenParser::class);
        class_exists(Twig\TokenParser\ExtendsTokenParser::class);
        class_exists(Twig\TokenParser\IncludeTokenParser::class);
        class_exists(Twig\TokenParser\BlockTokenParser::class);
        class_exists(Twig\TokenParser\UseTokenParser::class);
        class_exists(Twig\TokenParser\FilterTokenParser::class);
        class_exists(Twig\TokenParser\MacroTokenParser::class);
        class_exists(Twig\TokenParser\ImportTokenParser::class);
        class_exists(Twig\TokenParser\FromTokenParser::class);
        class_exists(Twig\TokenParser\SetTokenParser::class);
        class_exists(Twig\TokenParser\SpacelessTokenParser::class);
        class_exists(Twig\TokenParser\FlushTokenParser::class);
        class_exists(Twig\TokenParser\DoTokenParser::class);
        class_exists(Twig\TokenParser\EmbedTokenParser::class);
        class_exists(Twig\TokenParser\WithTokenParser::class);
        class_exists(Twig\TokenParser\DeprecatedTokenParser::class);
        class_exists(Twig\NodeVisitor\MacroAutoImportNodeVisitor::class);

        class_exists(OAuth2\ResponseType\AccessToken::class);
        class_exists(OAuth2\ResponseType\AuthorizationCode::class);
        class_exists(OAuth2\Controller\AuthorizeController::class);
        */
        $assets = [
            'src/OpenApi3/client/index.html',
            'src/OpenApi3/client/oauth2-redirect.html',
        ];
        $files = get_included_files();
        $targets = [];
        foreach ($files as $file) {
            if (Text::beginsWith($file, '/private/') || Text::beginsWith($file, Defaults::$cacheDirectory))
                continue;
            $base = str_replace(BASE . DIRECTORY_SEPARATOR, '', $file);
            $target = Defaults::$cacheDirectory . '/package/' . $base;
            $dir = dirname($target);
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            copy($file, $target);
            $targets[] = $base;
        }
        foreach ($assets as $base) {
            $file = BASE . DIRECTORY_SEPARATOR . $base;
            $target = Defaults::$cacheDirectory . '/package/' . $base;
            $dir = dirname($target);
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            copy($file, $target);
            $targets[] = $base;
        }
        $pack = function ($dir) {
            $parent = dirname($dir);
            $parent = '.' == $parent ? '' : $parent . '/';
            $command = sprintf(
                'cp -R "%s/%s" "%s/package/%s"',
                BASE, $dir, Defaults::$cacheDirectory, $parent
            );
            return exec($command);
        };
        $pack('views');
        $pack('src/views');
        $pack('public');
        exec('chmod +x "' . Defaults::$cacheDirectory . '/package/bootstrap"');
        return $targets;
    }
}

class baseUrl
{
    /**
     * @var Restler
     */
    private $r;
    /**
     * @var ServerRequestInterface
     */
    private $s;

    public function __construct(Restler $r, ServerRequestInterface $s)
    {
        $this->r = $r;
        $this->s = $s;
    }

    function get()
    {
        return [
            'BASE_URL' => (string)$this->r->baseUrl,
            'BASE_PATH' => (string)$this->r->baseUrl->getPath(),
            'SCRIPT_NAME' => $this->s->getServerParams()['SCRIPT_NAME'],
            'REQUEST_URI' => $this->s->getServerParams()['REQUEST_URI'],
            'PATH' => $this->r->path,
            'FULL_URL' => (string)$this->s->getUri(),
            'FULL_PATH' => (string)$this->s->getUri()->getPath(),
        ];
    }
}

try {
    Router::setOverridingResponseMediaTypes(Json::class, Xml::class, Html::class);
    Router::setOverridingRequestMediaTypes(Json::class, Upload::class);
    SimpleAuth::setIncludedPaths('examples/_005_protected_api');
    Router::addAuthenticator(SimpleAuth::class);
    KeyAuth::setIncludedPaths('examples/_009_rate_limiting');
    Router::addAuthenticator(KeyAuth::class);
    AccessControl::setIncludedPaths('examples/_010_access_control');
    Router::addAuthenticator(AccessControl::class);
    Router::addAuthenticator(Server::class);

    RateLimiter::setIncludedPaths('examples/_009_rate_limiting');
    Router::setFilters(RateLimiter::class);
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
        'tests/upload/files' => Files::class,
        //Explorer
        'explorer' => Explorer::class,
        'baseurl' => BaseUrl::class,
    ]);
    $cache = new HumanReadable();
    $cache->set('route', Router::toArray());
    $cache->set('models', Router::$models);
} catch (Throwable $t) {
    die($t->getMessage());
}


//$routes = Router::toArray();
//var_export($routes);
//var_export(array_sort(array_keys($routes['v1'])));
//var_export(array_sort(array_keys($routes['v2'])));
//var_export(Router::$formatMap);
