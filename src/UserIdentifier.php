<?php namespace Luracast\Restler;

use Luracast\Restler\Contracts\UserIdentificationInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserIdentifier implements UserIdentificationInterface
{
    public const HEADERS_NGINX = [
        'x-real-ip',
        'forwarded',
        'x-forwarded-for',
        'x-forwarded',
        'x-cluster-client-ip',
        'client-ip',
    ];
    public const HEADERS_CLOUDFLARE = [
        'cf-connecting-ip',
        'true-client-ip',
        'forwarded',
        'x-forwarded-for',
        'x-forwarded',
        'x-cluster-client-ip',
        'client-ip',
    ];

    public const HEADERS_COMMON = [
        'client-ip',
        'x-forwarded-for',
        'x-forwarded',
        'x-cluster-client-ip',
        'cf-connecting-ip',
    ];

    public static $headersToInspect = self::HEADERS_COMMON;
    public static $attributesToInspect = ['client_ip', 'ip'];
    protected $id = null;
    protected $cacheId = null;
    protected $ip;
    protected $browser = '';
    protected $platform = '';
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
        $this->ip = $this->getIpAddress();
        if ($agent = $this->request->getHeaderLine('user_agent')) {
            if ($details = get_browser($agent)) {
                $this->browser = $details->parent;
                $this->platform = $details->platform;
            }
        }
    }

    public function getIpAddress(bool $ignoreProxies = false): string
    {
        foreach (static::$attributesToInspect as $attribute) {
            if ($ip = $this->request->getAttribute($attribute, false)) {
                return $ip;
            }
        }
        if (!$ignoreProxies) {
            foreach (static::$headersToInspect as $header) {
                if ($ips = $this->request->getHeaderLine($header)) {
                    if ($ip = $this->filterIP($ips)) {
                        return $ip;
                    }
                }
            }
        }
        $server = $this->request->getServerParams();
        if ($ips = $server['REMOTE_ADDR'] ?? []) {
            if ($ip = $this->filterIP($ips, false)) {
                return $ip;
            }
        }
        return 'x127.0.0.1';
    }

    private function filterIP(string $ips, bool $denyPrivateAndLocal = true): string
    {
        $options = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        if ($denyPrivateAndLocal) {
            $options |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        }
        foreach (explode(',', $ips) as $ip) {
            $ip = trim($ip); // just to be safe
            if (false !== ($result = filter_var($ip, FILTER_VALIDATE_IP, $options))) {
                return $ip;
            }
        }
        return '';
    }

    /**
     * Authentication classes should call this method
     *
     * @param string $id user id as identified by the authentication classes
     *
     * @return void
     */
    public function setUniqueIdentifier(string $id)
    {
        $this->id = $id;
    }

    /**
     * User identity to be used for caching purpose
     *
     * When the dynamic cache service places an object in the cache, it needs to
     * label it with a unique identifying string known as a cache ID. This
     * method gives that identifier
     *
     * @return string
     */
    public function getCacheIdentifier(): string
    {
        return $this->cacheId ?: $this->getUniqueIdentifier();
    }

    public function getUniqueIdentifier(bool $includePlatform = false): string
    {
        return $this->id ?: base64_encode('ip:' . ($includePlatform
                ? $this->ip . '-' . $this->platform
                : $this->ip
            ));
    }

    /**
     * User identity for caching purpose
     *
     * In a role based access control system this will be based on role
     *
     * @param $id
     *
     * @return void
     */
    public function setCacheIdentifier(string $id)
    {
        $this->cacheId = $id;
    }
}
