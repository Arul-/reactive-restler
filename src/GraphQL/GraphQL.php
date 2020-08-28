<?php


namespace Luracast\Restler\GraphQL;

use Exception;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Illuminate\Support\Str;
use Luracast\Restler\Data\Route;
use Luracast\Restler\Defaults;
use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Restler;
use Luracast\Restler\Router;
use Luracast\Restler\StaticProperties;
use Luracast\Restler\Utils\ClassName;
use Luracast\Restler\Utils\CommentParser;
use Luracast\Restler\Utils\PassThrough;
use Math;
use ratelimited\Authors;
use ReflectionClass;
use ReflectionMethod;
use Say;
use Throwable;

/**
 * query language support
 */
class GraphQL
{
    const UI_GRAPHQL_PLAYGROUND = 'graphql-playground';
    const UI_GRAPHIQL = 'graphiql';

    public static $UI = self::UI_GRAPHQL_PLAYGROUND;

    public static $context = [];
    public static $definitions = [];
    public static $mutations = [];
    public static $queries = [];
    /**
     * @var Restler
     */
    private $restler;
    /**
     * @var StaticProperties
     */
    private $graphQL;

    public function __construct(Restler $restler, StaticProperties $graphQL)
    {
        $this->restler = $restler;
        $graphQL->context['maker'] = [$restler, 'make'];
        $this->graphQL = $graphQL;
    }

    /**
     * loads graphql client
     * @return \Psr\Http\Message\ResponseInterface
     * @throws HttpException
     */
    public function get()
    {
        return PassThrough::file(__DIR__ . '/client/' . static::$UI . '.html');
    }


    /**
     * runs graphql queries
     * @param string $query {@from body}
     * @param array $variables {@from body}
     *
     * @return array|mixed[]
     */
    public function post(string $query = '', array $variables = [])
    {
        $queryType = new ObjectType(['name' => 'Query', 'fields' => static::$queries]);
        $mutationType = new ObjectType(['name' => 'Mutation', 'fields' => static::$mutations]);
        $schema = new Schema(['query' => $queryType, 'mutation' => $mutationType]);
        $root = ['prefix' => 'You said: '];
        try {
            $result = \GraphQL\GraphQL::executeQuery($schema, $query, $root, $this->graphQL->context, $variables);
            return $result->toArray();
        } catch (Exception $exception) {
            return [
                'errors' => [['message' => $exception->getMessage()]]
            ];
        }
    }

    /**
     * @param array $map $className => Resource name or just $className
     * @throws Exception
     */
    public static function mapApiClasses(array $map): void
    {
        try {
            foreach ($map as $className => $name) {
                if (is_numeric($className)) {
                    $className = $name;
                    $name = ClassName::short($className);
                }
                $className = Defaults::$aliases[$className] ?? $className;
                if (!class_exists($className)) {
                    throw new Exception(
                        'Class not found',
                        500
                    );
                }
                $class = new ReflectionClass($className);
                $methods = $class->getMethods(
                    ReflectionMethod::IS_PUBLIC +
                    ReflectionMethod::IS_PROTECTED
                );
                $scope = null;
                foreach ($methods as $method) {
                    if ($method->isStatic()) {
                        continue;
                    }
                    $methodName = strtolower($method->getName());
                    //method name should not begin with _
                    if ($methodName[0] == '_') {
                        continue;
                    }
                    $metadata = [];
                    if ($doc = $method->getDocComment()) {
                        try {
                            $metadata = CommentParser::parse($doc);
                        } catch (Exception $e) {
                            throw new HttpException(
                                500,
                                "Error while parsing comments of `{$className}::{$method->getName()}` method. " . $e->getMessage()
                            );
                        }
                        //@access should not be private
                        if ('private' == ($metadata['access'] ?? false)) {
                            continue;
                        }
                    }
                    if (is_null($scope)) {
                        $scope = Router::scope($class);
                    }
                    static::addMethod($name, $method, $metadata, $scope);
                }
            }
        } catch (Throwable $e) {
            throw new Exception(
                "mapAPIClasses failed. " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public static function addMethod(string $name, ReflectionMethod $method, ?array $metadata = null, array $scope = [])
    {
        $route = Route::fromMethod($method, $metadata, $scope);
        if ($mutation = $route->mutation ?? false) {
            return static::addRoute($mutation, $route, true);
        }
        if ($query = $route->query ?? false) {
            return static::addRoute($query, $route, false);
        }
        $single = Str::singular($name);
        switch ($route->httpMethod) {
            case 'POST':
                $name = 'make' . $single;
                break;
            case 'DELETE':
                $name = 'remove' . $single;
                break;
            case 'PUT':
            case 'PATCH':
                $name = 'update' . $single;
                break;
            default:
                $name = isset($route->parameters['id'])
                    ? 'get' . $single
                    : lcfirst($name);
        }
        return static::addRoute($name, $route, 'GET' !== $route->httpMethod);
    }

    public static function addRoute(string $name, Route $route, bool $isMutation = false)
    {
        $target = $isMutation ? 'mutations' : 'queries';
        static::$$target[$name] = $route->toGraphQL();
    }
}
