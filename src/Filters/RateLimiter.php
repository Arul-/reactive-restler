<?php namespace Luracast\Restler\Filters;


use Luracast\Restler\Contracts\FilterInterface;
use Luracast\Restler\Contracts\UsesAuthenticationInterface;
use Luracast\Restler\Defaults;
use Luracast\Restler\HttpException;
use Luracast\Restler\HumanReadableCache;
use Psr\Http\Message\ServerRequestInterface;

class RateLimiter implements FilterInterface, UsesAuthenticationInterface
{
    protected $cache;
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

    protected $headers = [];

    /**
     * @var array all paths beginning with any of the following will be excluded
     * from documentation
     */
    public static $excludedPaths = array('explorer');
    protected $authenticated = false;

    public function __construct()
    {
        HumanReadableCache::$cacheDir = __DIR__.'/../../api/common/store';
        $this->cache = new HumanReadableCache();
    }

    /**
     * @param string $unit
     * @param int $usagePerUnit
     * @param int $authenticatedUsagePerUnit set it to false to give unlimited access
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public static function setLimit($unit, $usagePerUnit, $authenticatedUsagePerUnit = null)
    {
        static::$unit = $unit;
        static::$usagePerUnit = $usagePerUnit;
        static::$authenticatedUsagePerUnit =
            is_null($authenticatedUsagePerUnit) ? $usagePerUnit : $authenticatedUsagePerUnit;
    }

    public function __isAllowed(ServerRequestInterface $request, array &$responseHeaders): bool
    {
        $allowed = $this->check($request->getUri()->getPath(), $this->authenticated);
        $responseHeaders += $this->headers;
        return $allowed;
    }

    public function __setAuthenticationStatus(bool $isAuthenticated = false, bool $isAuthFinished = false)
    {
        $this->headers['X-Auth-Status'] = ($isAuthenticated ? 'true' : 'false');
        $this->authenticated = $isAuthenticated;
    }

    private static function validate($unit)
    {
        if (!isset(static::$units[$unit]))
            throw new \InvalidArgumentException(
                'Rate Limit time unit should be '
                . implode('|', array_keys(static::$units)) . '.'
            );
    }

    private function check(string $path, bool $isAuthenticated = false)
    {
        foreach (static::$excludedPaths as $exclude) {
            if (empty($exclude) && empty($path)) {
                return true;
            } elseif (0 === strpos($path, $exclude)) {
                return true;
            }
        }
        static::validate(static::$unit);
        $timeUnit = static::$units[static::$unit];
        $maxPerUnit = $isAuthenticated
            ? static::$authenticatedUsagePerUnit
            : static::$usagePerUnit;
        if ($maxPerUnit) {
            $user = Defaults::$userIdentifierClass;
            if (!method_exists($user, 'getUniqueIdentifier')) {
                throw new \UnexpectedValueException('`Defaults::$userIdentifierClass` must implement `iIdentifyUser` interface');
            }
            $id = "RateLimit_" . $maxPerUnit . '_per_' . static::$unit
                . '_for_' . static::$group
                . '_' . $user::getUniqueIdentifier();
            $lastRequest = $this->cache->get($id, true)
                ?: array('time' => 0, 'used' => 0);
            $time = $lastRequest['time'];
            $diff = time() - $time; # in seconds
            $used = $lastRequest['used'];

            $this->headers['X-RateLimit-Limit'] = "$maxPerUnit per " . static::$unit;
            if ($diff >= $timeUnit) {
                $used = 1;
                $time = time();
            } elseif ($used >= $maxPerUnit) {
                $this->headers['X-RateLimit-Remaining'] = '0';
                $wait = $timeUnit - $diff;
                sleep(1);
                throw new HttpException(429,
                    'Rate limit of ' . $maxPerUnit . ' request' .
                    ($maxPerUnit > 1 ? 's' : '') . ' per '
                    . static::$unit . ' exceeded. Please wait for '
                    . static::duration($wait) . '.'
                );
            } else {
                $used++;
            }
            $remainingPerUnit = $maxPerUnit - $used;
            $this->headers['X-RateLimit-Remaining'] = $remainingPerUnit;
            $this->cache->set($id,
                array('time' => $time, 'used' => $used));
        }
        return true;
    }

    private function duration($secs)
    {
        $units = array(
            'week' => (int)($secs / 86400 / 7),
            'day' => $secs / 86400 % 7,
            'hour' => $secs / 3600 % 24,
            'minute' => $secs / 60 % 60,
            'second' => $secs % 60);

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
}