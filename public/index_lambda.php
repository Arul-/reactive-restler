<?php declare(strict_types=1);

use Luracast\Restler\Exceptions\HttpException;
use Luracast\Restler\Utils\Text;
use Swoole\Http\Response;
use Swoole\Http\Request;
use function RingCentral\Psr7\parse_query;

require __DIR__ . '/../vendor/autoload.php';

class LambdaTasks
{
    private static $tasks = [];
    private static $pending = [];

    public static function complete(string $id, array $eventData = [])
    {
        $response = static::$tasks[$id];
        if (empty($eventData)) {
            $response->status(500);
            $response->header = ['Content-Type' => 'application/json'];
            $response->end('{"error":"Invalid response from Lambda function"}');
            return;
        }
        $response->status($eventData['statusCode']);
        if (!isset($eventData['headers'])) {
            foreach ($eventData['multiValueHeaders'] as $header) {
                $eventData['headers'][] = end($header);
            }
        }
        $response->headers = $eventData['headers'];
        $response->end($eventData['isBase64Encoded'] ? base64_encode($eventData['body']) : $eventData['body']);
    }

    public static function fetch(): array
    {
        return array_shift(self::$pending) ?? [];
    }

    public static function add(Request $request, Response $response): string
    {
        $id = md5(uniqid('1512635720' . rand() . '-', true));
        $headers = [
            'content-type' => 'application/json',
            'lambda-runtime-aws-request-id' => $id,
            'lambda-runtime-deadline-ms' => "1585382905396",
            'lambda-runtime-invoked-function-arn' => 'arn:aws:lambda:ap-southeast-1:787614641643:function:restler',
            'lambda-runtime-trace-id' => "Root=1-$id;Parent=36f75ba8033a9eb7;Sampled=0",
            'date' => gmdate('D, d M Y H:i:s \G\M\T', time()), //"Sat, 28 Mar 2020 08:08:22 GMT",
            'transfer-encoding' => "chunked"
        ];
        $body = [
            'resource' => $request->server['request_uri'],
            'path' => $request->server['path_info'],
            'httpMethod' => $request->server['request_method'],
            'headers' => $request->header,
            'queryStringParameters' => parse_query($request->server['query_string'] ?? ''),
            'body' => (string)$request->rawContent(),
            'isBase64Encoded' => false,
            'pathParameters' => null,
            'stageVariables' => null,
            'requestContext' => [ //TODO: fill this properly
                'identify' => []
            ]
        ];
        $f = function ($item) {
            return [$item];
        };
        $body['multiValueHeaders'] = array_map($f, $body['headers']);
        $body['multiValueQueryStringParameters'] = array_map($f, $body['queryStringParameters']);
        static::$pending[] = compact('id', 'headers', 'body');
        static::$tasks[$id] = $response;
        return $id;
    }

}

$host = '127.0.0.1';
$http = new swoole_http_server($host, 8080);
$http->set([
    'worker_num' => 1, // The number of worker processes
    'daemonize' => false, // Whether start as a daemon process
    'backlog' => 128, // TCP backlog connection number
]);
$http->on('request', function (Request $request, Response $response) {
    $id = LambdaTasks::add($request, $response);
    echo 'adding - ' . $id . PHP_EOL;
});

$lambda = $http->listen($host, 9001, SWOOLE_SOCK_TCP);
$lambda->on('request', function (Request $request, Response $response) {
    $base = '/2018-06-01/runtime/invocation/';
    $uri = $request->server['request_uri'];
    if (Text::beginsWith($uri, $base)) {
        $uri = explode('/', str_replace($base, '', $uri));
        $uri = $uri[0];
        if ('next' == $uri) {
            $task = LambdaTasks::fetch();
            echo 'fetching   - ' . $task['id']
                . '  ' . ($task['body']['httpMethod'] ?? 'ANY')
                . ' ' . ($task['body']['path'] ?? '/*')
                . PHP_EOL;
            if (empty($task)) {
                $response->status(500);
                $response->header = ['Content-Type' => 'application/json'];
                $response->end('{"error":"no pending tasks"}');
                return;
            }
            $response->header = $task['headers'];
            $response->status(200);
            $response->end(json_encode($task['body']));
        } else {
            $data = json_decode($request->rawContent(), true) ?? [];
            $status = $data['body']['statusCode'] ?? '500';
            echo 'completing - ' . $uri
                . '  ' . $status. ' '. HttpException::$codes[$status]
                . PHP_EOL;
            LambdaTasks::complete($uri, $data);
            $response->status(200);
            $response->header = ['Content-Type' => 'application/json'];
            $response->end('{"success":true}');
        }
        return;
    }
    $response->status(404);
    $response->header = ['Content-Type' => 'application/json'];
    $response->end('{"error":"Invalid request"}');

});
$http->on('start', function ($server) {
    echo "API Gateway Server started at http://127.0.0.1:8080\n";
    echo "Lambda Function Server is started at http://127.0.0.1:9001\n";
});
$http->start();
