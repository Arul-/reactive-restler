<?php


namespace Luracast\Restler\GraphQL;

use Exception;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Luracast\Restler\Data\Route;
use Luracast\Restler\Restler;
use Luracast\Restler\Utils\PassThrough;
use ReflectionMethod;

class GraphQL
{
    const UI_GRAPHQL_PLAYGROUND = 'graphql-playground';
    const UI_GRAPHIQL = 'graphiql';

    public static $UI = self::UI_GRAPHQL_PLAYGROUND;

    public static $typeDefinitions = '';
    public static $resolvers = [
        'Query' => [],
        'Mutation' => [],
    ];

    private static $schema;
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
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'echo' => [
                    'type' => Type::string(),
                    'args' => [
                        'message' => Type::nonNull(Type::string()),
                    ],
                    'resolve' => function ($rootValue, $args) {
                        return $rootValue['prefix'] . $args['message'];
                    }
                ],
                'hello' => (Route::fromMethod(new ReflectionMethod(\Say::class, 'hello')))->toGraphQL([$this->restler, 'make']),
            ],
        ]);
        $mutationType = new ObjectType([
            'name' => 'Calc',
            'fields' => [
                'sum' => [
                    'type' => Type::int(),
                    'args' => [
                        'x' => ['type' => Type::int()],
                        'y' => ['type' => Type::int()],
                    ],
                    'resolve' => function ($calc, $args) {
                        return $args['x'] + $args['y'];
                    },
                ],
                'add' => (Route::fromMethod(new ReflectionMethod(\Math::class, 'add')))->toGraphQL([$this->restler, 'make']),
            ],
        ]);
        $schema = new Schema([
            'query' => $queryType,
            'mutation' => $mutationType
        ]);
        $rootValue = ['prefix' => 'You said: '];
        try {
            $result = \GraphQL\GraphQL::executeQuery($schema, $query, $rootValue, null, $variables);
            return $result->toArray();
        } catch (Exception $exception) {
            return [
                'errors' => [['message' => $exception->getMessage()]]
            ];
        }

        /*
        if (!static::$schema) {
            static::$schema = schema(static::$typeDefinitions, static::$resolvers);
        }
        return execute(static::$schema, $request_data);
        */

    }
}
