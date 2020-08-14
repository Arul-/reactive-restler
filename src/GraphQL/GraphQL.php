<?php


namespace Luracast\Restler\GraphQL;

use Exception;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use ratelimited\Authors;
use Luracast\Restler\Data\Route;
use Luracast\Restler\Restler;
use Luracast\Restler\Utils\ClassName;
use Luracast\Restler\Utils\PassThrough;
use Math;
use ReflectionMethod;
use Say;

class GraphQL
{
    const UI_GRAPHQL_PLAYGROUND = 'graphql-playground';
    const UI_GRAPHIQL = 'graphiql';

    public static $UI = self::UI_GRAPHQL_PLAYGROUND;

    public static $mutations = [];
    public static $queries = [];
    /**
     * @var Restler
     */
    private $restler;

    public function get()
    {
        return PassThrough::file(__DIR__ . '/client/' . static::$UI . '.html');
    }

    public function __construct(Restler $restler)
    {
        $this->restler = $restler;
    }

    public function test()
    {
        print_r((Route::fromMethod(new ReflectionMethod(\Say::class, 'hello')))->toGraphQL([$this->restler, 'make']));
    }

    /**
     * @param string $query {@from body}
     * @param array $variables {@from body}
     *
     * @return array|mixed[]
     * @throws \ReflectionException
     */
    public function post(string $query = '', array $variables = [])
    {
        static::$queries['echo'] = [
            'type' => Type::string(),
            'args' => [
                'message' => Type::nonNull(Type::string()),
            ],
            'resolve' => function ($rootValue, $args) {
                return $rootValue['prefix'] . $args['message'];
            }
        ];
        static::$mutations['sum'] = [
            'type' => Type::int(),
            'args' => [
                'x' => ['type' => Type::int()],
                'y' => ['type' => Type::int()],
            ],
            'resolve' => function ($calc, $args) {
                return $args['x'] + $args['y'];
            },
        ];
        $this->add(Say::class, 'hello');
        $this->add(Math::class, 'add');
        $this->add(Authors::class, 'post');

        $queryType = new ObjectType(['name' => 'Query', 'fields' => static::$queries]);
        $mutationType = new ObjectType(['name' => 'Mutation', 'fields' => static::$mutations]);
        $schema = new Schema(['query' => $queryType, 'mutation' => $mutationType]);
        $rootValue = ['prefix' => 'You said: '];
        try {
            $result = \GraphQL\GraphQL::executeQuery($schema, $query, $rootValue, null, $variables);
            return $result->toArray();
        } catch (Exception $exception) {
            return [
                'errors' => [['message' => $exception->getMessage()]]
            ];
        }
    }

    private function add(string $class, string $method): void
    {
        $route = Route::fromMethod(new ReflectionMethod($class, $method));
        $name = ClassName::short($class);
        switch ($route->httpMethod) {
            case 'POST':
                $name = 'make' . $name;
                break;
            case 'DELETE':
                $name = 'remove' . $name;
                break;
            case 'PUT':
            case 'PATCH':
                $name = 'update' . $name;
                break;
            default:
                $name = lcfirst($name);
        }
        $target = 'GET' === $route->httpMethod ? 'queries' : 'mutations';
        static::$$target[$name] = $route->toGraphQL([$this->restler, 'make']);
    }
}
