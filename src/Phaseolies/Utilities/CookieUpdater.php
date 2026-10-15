<?php

namespace Phaseolies\Utilities;

use Phaseolies\Support\CookieJar;
use Phaseolies\Http\Response\Cookie;

class CookieUpdater
{
    /**
     * The cookie instance being modified.
     */
    protected Cookie $cookie;

    /**
     * Initialize with a Cookie instance.
     */
    public function __construct(Cookie $cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * Update the cookie's value.
     */
    public function withValue(?string $value): self
    {
        $this->cookie = $this->cookie->withValue($value);

        return $this;
    }

    /**
     * Update the expiration time.
     */
    public function withExpires(int|string|\DateTimeInterface $expire): self
    {
        $this->cookie = $this->cookie->withExpires($expire);

        return $this;
    }

    /**
     * Update the cookie path.
     */
    public function withPath(string $path): self
    {
        $this->cookie = $this->cookie->withPath($path);

        return $this;
    }

    /**
     * Update the cookie domain.
     */
    public function withDomain(?string $domain): self
    {
        $this->cookie = $this->cookie->withDomain($domain);

        return $this;
    }

    /**
     * Set whether the cookie is HTTPS-only.
     */
    public function withSecure(bool $secure = true): self
    {
        $this->cookie = $this->cookie->withSecure($secure);

        return $this;
    }

    /**
     * Set whether the cookie is HTTP-only.
     */
    public function withHttpOnly(bool $httpOnly = true): self
    {
        $this->cookie = $this->cookie->withHttpOnly($httpOnly);

        return $this;
    }

    /**
     * Set whether the cookie value should be raw.
     */
    public function withRaw(bool $raw = true): self
    {
        $this->cookie = $this->cookie->withRaw($raw);

        return $this;
    }

    /**
     * Set the SameSite attribute.
     *
     * @param Cookie::SAMESITE_*|''|null $sameSite
     */
    public function withSameSite(?string $sameSite): self
    {
        $this->cookie = $this->cookie->withSameSite($sameSite);

        return $this;
    }

    /**
     * Set whether the cookie is partitioned (CHIPS).
     */
    public function withPartitioned(bool $partitioned = true): self
    {
        $this->cookie = $this->cookie->withPartitioned($partitioned);

        return $this;
    }

    /**
     * Persist the modified cookie to the response.
     */
    public function update(): bool
    {
        return app(CookieJar::class)->store($this->cookie);
    }
}
