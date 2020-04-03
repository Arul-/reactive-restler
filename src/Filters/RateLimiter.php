<?php namespace Luracast\Restler\Filters;


use Luracast\Restler\Contracts\FilterInterface;
use Luracast\Restler\Contracts\SelectivePathsInterface;
use Luracast\Restler\Contracts\SelectivePathsTrait;
use Luracast\Restler\Contracts\UsesAuthenticationInterface;
use Luracast\Restler\Exceptions\HttpException;
use Psr\SimpleCache\CacheInterface;
use Luracast\Restler\Contracts\UserIdentificationInterface;
use Luracast\Restler\ResponseHeaders;
use Luracast\Restler\StaticProperties;
use Luracast\Restler\Utils\ClassName;
use Psr\Http\Message\ServerRequestInterface;

class RateLimiter implements FilterInterface, SelectivePathsInterface, UsesAuthenticationInterface
{
    use SelectivePathsTrait;

    /**
     * @var int
     */
    public static $usagePerUnit = 1200;
    /**
     * @var int
     */
    public static $authenticatedUsagePerUnit = 5000;
    /**
     * @var string
     */
    public static $unit = 'hour';
    /**
     * @var string group the current api belongs to
     */
    public static $group = 'common';

    protected static $units = array(
        'second' => 1,
        'minute' => 60,
        'hour' => 3600, // 60*60 seconds
        'day' => 86400, // 60*60*24 seconds
        'week' => 604800, // 60*60*24*7 seconds
        'month' => 2592000, // 60*60*24*30 seconds
    );
    private $runtimeValues;

    /**
     * @var CacheInterface
     */
    protected $cache;
    /**
     * @var bool current auth status
     */
    protected $authenticated = false;

    public function __construct(StaticProperties $rateLimiter)
    {
        $this->runtimeValues = $rateLimiter;
        $class = ClassName::get(CacheInterface::class);
        /** @var CacheInterface cache */
        $this->cache = new $class;
    }

    /**
     * @param string $unit
     * @param int $usagePerUnit
     * @param int $authenticatedUsagePerUnit set it to false to give unlimited access
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public static function setLimit($unit, $usagePerUnit, $authenticatedUsagePerUnit = null)
    {
        static::$unit = $unit;
        static::$usagePerUnit = $usagePerUnit;
        static::$authenticatedUsagePerUnit =
            is_null($authenticatedUsagePerUnit) ? $usagePerUnit : $authenticatedUsagePerUnit;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseHeaders $responseHeaders
     * @return bool
     * @throws HttpException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function _isAllowed(ServerRequestInterface $request, ResponseHeaders $responseHeaders): bool
    {
        $authenticated = $this->authenticated;
        $responseHeaders['X-Auth-Status'] = ($authenticated ? 'true' : 'false');
        $unit = $this->runtimeValues->unit;
        $group = $this->runtimeValues->group;
        static::validate($unit);
        $timeUnit = static::$units[$unit];
        $maxPerUnit = $authenticated
            ? $this->runtimeValues->authenticatedUsagePerUnit
            : $this->runtimeValues->usagePerUnit;
        if ($maxPerUnit) {
            /** @var UserIdentificationInterface $user */
            $user = ClassName::get(UserIdentificationInterface::class);
            $id = "RateLimit_" . $maxPerUnit . '_per_' . $unit
                . '_for_' . $group
                . '_' . $user::getUniqueIdentifier();
            $lastRequest = $this->cache->get($id, ['time' => 0, 'used' => 0]);
            $time = $lastRequest['time'];
            $diff = time() - $time; # in seconds
            $used = $lastRequest['used'];

            $responseHeaders['X-RateLimit-Limit'] = "$maxPerUnit per " . $unit;
            if ($diff >= $timeUnit) {
                $used = 1;
                $time = time();
            } elseif ($used >= $maxPerUnit) {
                $responseHeaders['X-RateLimit-Remaining'] = '0';
                $wait = $timeUnit - $diff;
                sleep(1);
                throw new HttpException(429,
                    'Rate limit of ' . $maxPerUnit . ' request' .
                    ($maxPerUnit > 1 ? 's' : '') . ' per '
                    . $unit . ' exceeded. Please wait for '
                    . static::duration($wait) . '.'
                );
            } else {
                $used++;
            }
            $remainingPerUnit = $maxPerUnit - $used;
            $responseHeaders['X-RateLimit-Remaining'] = $remainingPerUnit;
            $this->cache->set(
                $id,
                array('time' => $time, 'used' => $used)
            );
        }
        return true;
    }

    public function _setAuthenticationStatus(bool $isAuthenticated = false, bool $isAuthFinished = false)
    {
        $this->authenticated = $isAuthenticated;
    }

    private static function validate($unit)
    {
        if (!isset(static::$units[$unit])) {
            throw new \InvalidArgumentException(
                'Rate Limit time unit should be '
                . implode('|', array_keys(static::$units)) . '.'
            );
        }
    }

    private function duration($secs)
    {
        $units = array(
            'week' => (int)($secs / 86400 / 7),
            'day' => $secs / 86400 % 7,
            'hour' => $secs / 3600 % 24,
            'minute' => $secs / 60 % 60,
            'second' => $secs % 60
        );

        $ret = array();

        //$unit = 'days';
        foreach ($units as $k => $v) {
            if ($v > 0) {
                $ret[] = $v > 1 ? "$v {$k}s" : "$v $k";
                //$unit = $k;
            }
        }
        $i = count($ret) - 1;
        if ($i) {
            $ret[$i] = 'and ' . $ret[$i];
        }
        return implode(' ', $ret);
    }

    public static function getExcludedPaths(): array
    {
        return empty(static::$excludedPaths) ? ['explorer'] : static::$excludedPaths;
    }
}
