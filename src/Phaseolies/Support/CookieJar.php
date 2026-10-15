<?php

namespace Phaseolies\Support;

use Phaseolies\Http\Response\Cookie;

class CookieJar
{
    /**
     * Default cookie settings.
     *
     * @var array{expires: int, path: string, domain: string|null, secure: bool|null, httponly: bool, raw: bool, samesite: 'lax'|'none'|'strict'|''|null, partitioned: bool}
     */
    protected array $defaults = [
        'expires' => 0,
        'path' => '/',
        'domain' => null,
        'secure' => null,
        'httponly' => true,
        'raw' => false,
        'samesite' => Cookie::SAMESITE_LAX,
        'partitioned' => false,
    ];

    /**
     * Create a new cookie instance.
     *
     * @param array<string, mixed> $options
     */
    public function make(string $name, ?string $value = null, array $options = []): Cookie
    {
        /** @var array{expires: int, path: string, domain: string|null, secure: bool|null, httponly: bool, raw: bool, samesite: 'lax'|'none'|'strict'|''|null, partitioned: bool} $resolved */
        $resolved = array_merge($this->defaults, $options);

        return Cookie::create(
            $name,
            $value,
            $resolved['expires'],
            $resolved['path'],
            $resolved['domain'],
            $resolved['secure'],
            $resolved['httponly'],
            $resolved['raw'],
            $resolved['samesite'],
            $resolved['partitioned']
        );
    }

    /**
     * Retrieve a cookie value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_COOKIE[$key] ?? $default;
    }

    /**
     * Check if a cookie exists.
     */
    public function has(string $key): bool
    {
        return isset($_COOKIE[$key]);
    }

    /**
     * Store a cookie.
     *
     * @param array<string, mixed> $options
     */
    public function store(Cookie|string $name, ?string $value = null, array $options = []): bool
    {
        if (!$name instanceof Cookie) {
            $name = $this->make($name, $value, $options);
        }

        return $this->setCookie($name);
    }

    /**
     * Remove a cookie.
     *
     * @param array<string, mixed> $options
     */
    public function remove(string $name, array $options = []): void
    {
        $options['expires'] = time() - 3600;
        $options['value'] = '';
        $this->store($name, null, $options);
    }

    /**
     * Store a cookie that lasts "forever" (5 years).
     *
     * @param array<string, mixed> $options
     */
    public function forever(string $name, string $value, array $options = []): void
    {
        $options['expires'] = time() + 60 * 60 * 24 * 365 * 5;
        $this->store($name, $value, $options);
    }

    /**
     * Set a cookie using the Cookie object.
     */
    protected function setCookie(Cookie $cookie): bool
    {
        if ($cookie->isRaw()) {
            $name = $cookie->getName();
            $value = $cookie->getValue() ?? '';
        } else {
            $name = str_replace(Cookie::RESERVED_CHARS_FROM, Cookie::RESERVED_CHARS_TO, $cookie->getName());
            $value = rawurlencode($cookie->getValue() ?? '');
        }

        $options = [
            'expires' => $cookie->getExpiresTime(),
            'path' => $cookie->getPath(),
            'domain' => $cookie->getDomain() ?? '',
            'secure' => $cookie->isSecure(),
            'httponly' => $cookie->isHttpOnly(),
        ];

        if (null !== $cookie->getSameSite()) {
            $options['samesite'] = $cookie->getSameSite();
        }

        if ($cookie->isPartitioned()) {
            $options['partitioned'] = true;
        }

        return setcookie($name, $value, $options);
    }

    /**
     * Get all cookies that were sent with the current request.
     *
     * @return array<string, mixed>
     */
    public function all(bool $decodeValues = true): array
    {
        if (!$decodeValues) {
            return $_COOKIE;
        }

        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            $cookies[$name] = is_string($value) ? rawurldecode($value) : $value;
        }

        return $cookies;
    }
}