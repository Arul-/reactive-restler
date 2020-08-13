<?php


namespace Luracast\Restler\GraphQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Luracast\Restler\Utils\PassThrough;

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

    public function get()
    {
        return PassThrough::file(__DIR__ . '/client/' . static::$UI . '.html');
    }


    /**
     * @param string $query {@from body}
     * @param array $variables {@from body}
     *
     * @return array|mixed[]
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
            ],
        ]);
        $schema = new Schema([
            'query' => $queryType,
            'mutation' => $mutationType
        ]);
        $rootValue = ['prefix' => 'You said: '];
        $result = \GraphQL\GraphQL::executeQuery($schema, $query, $rootValue, null, $variables);
        return $result->toArray();

        /*
        if (!static::$schema) {
            static::$schema = schema(static::$typeDefinitions, static::$resolvers);
        }
        return execute(static::$schema, $request_data);
        */

    }
}
