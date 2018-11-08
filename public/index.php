<?php declare(strict_types=1);

use Luracast\Restler\Reactler;
use Luracast\Restler\Utils\Dump;

require __DIR__ . '/../api/bootstrap.php';

(new Reactler)->handle();
