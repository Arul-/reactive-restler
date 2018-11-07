<?php declare(strict_types=1);

use Luracast\Restler\iUseAuthentication;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

include __DIR__ . "/../vendor/autoload.php";

UriInterface::class;
ServerRequestInterface::class;
ResponseInterface::class;

trait Authentication
{
    private $authenticated = false;

    public function __setAuthenticationStatus($isAuthenticated = false)
    {
        $this->authenticated = $isAuthenticated;
    }
}

class A implements iUseAuthentication
{
    use Authentication;

    public function __construct(bool $authenticated = false)
    {
        $this->authenticated = $authenticated;
    }
}

class B
{
    use Authentication;

    public function __construct(A $a)
    {
        var_dump($a->authenticated);
    }
}

$a = new A(true);

$b = new B($a);

//PHP Fatal error:  Uncaught Error: Cannot access protected property A::$authenticated in /Users/Arul/Projects/reactphp-restler/public/traits.php:26

