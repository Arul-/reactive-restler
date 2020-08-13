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
     * send query or mutation request
     * @param array $request_data
     * @return array|mixed[]
     *
     * @request-format Json
     */
    public function post(array $request_data)
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
        $schema = new Schema([
            'query' => $queryType
        ]);
        $query = $request_data['query'] ?? '';
        $variableValues = $request_data['variables'] ?? null;
        $rootValue = ['prefix' => 'You said: '];
        $result = \GraphQL\GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
        return $result->toArray();

        /*
        if (!static::$schema) {
            static::$schema = schema(static::$typeDefinitions, static::$resolvers);
        }
        return execute(static::$schema, $request_data);
        */

    }
}
