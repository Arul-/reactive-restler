<?php

use Luracast\Restler\Data\Returns;

include __DIR__ . '/../vendor/autoload.php';

$data = json_decode('
{
    "current_page": 1,
    "data": [
        {
            "id": 1,
            "name": "Arul",
            "email": "arul@luracast.com",
            "message": "This is super cool",
            "created_at": "2020-08-06T03:00:13.000000Z",
            "updated_at": "2020-08-06T03:00:13.000000Z"
        }
    ],
    "first_page_url": "/reviews?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "/reviews?page=1",
    "next_page_url": null,
    "path": "/reviews",
    "per_page": 20,
    "prev_page_url": null,
    "to": 1,
    "total": 1
}', true);

$type = Returns::fromSampleData($data, 'Pagination');
echo (json_encode($type, JSON_PRETTY_PRINT)) . PHP_EOL;
print_r($type);
//var_export($type);

