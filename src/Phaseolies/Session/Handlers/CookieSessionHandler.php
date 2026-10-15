<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Contracts\AbstractSessionHandler;
use Phaseolies\Config\Config;
use RuntimeException;

class CookieSessionHandler extends AbstractSessionHandler
{
    /**
     * Initializes the session system and applies cookie-related configurations.
     *
     * @throws RuntimeException if the session fails to start.
     */
    public function initialize(): void
    {
        $this->configureCookieSession();
        $this->setCookieParameters();
        $this->validate();
    }

    /**
     * Starts a session lifecycle, handles session ID regeneration,
     * and ensures a session token is generated.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException("Failed to start session.");
        }

        if ($this->shouldRegenerate()) {
            $this->regenerate();
            session()->put('last_regenerated', time());
        }

        $this->generateToken();
    }

    /**
     * Configures PHP session INI settings to use cookie-based, user-defined session storage.
     */
    private function configureCookieSession(): void {}

    /**
     * Applies cookie parameters based on configuration.
     */
    private function setCookieParameters(): void
    {
        @ini_set('session.gc_maxlifetime', (string) (((int) ($this->config['lifetime'] ?? 120)) * 60));
    }

    /**
     * Validates session cookie integrity; destroys cookie if it is invalid or cannot be decrypted.
     */
    public function validate(): void
    {
        $cookieName = (string) ($this->config['cookie'] ?? '');

        if ($cookieName !== '' && isset($_COOKIE[$cookieName])) {
            try {
                if (empty($this->decrypt($_COOKIE[$cookieName]))) {
                    $this->destroyCookie();
                }
            } catch (RuntimeException $e) {
                $this->destroyCookie();
            }
        }
    }

    /**
     * Called when a session is opened.
     */
    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    /**
     * Called when a session is closed.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Reads session data from the cookie, decrypting it.
     */
    public function read(string $sessionId): string
    {
        $sessionName = (string) @session_name();

        if ($sessionName === '' || !isset($_COOKIE[$sessionName])) {
            return '';
        }

        try {
            $data = $this->decrypt($_COOKIE[$sessionName]);
            if ($data === false || $data === '') {
                throw new RuntimeException('Empty decrypted session data');
            }

            return $data;
        } catch (RuntimeException $e) {
            $this->destroyCookie();
            return '';
        }
    }

    /**
     * Writes session data to the cookie, encrypting it.
     */
    public function write(string $sessionId, string $sessionData): bool
    {
        if (empty($sessionData)) {
            return true;
        }

        try {
            $params = session_get_cookie_params();
            $encrypted = $this->encrypt($sessionData);
            $cookieName = session_name() ?: (string) config('session.cookie', 'doppar_session');

            $result = setcookie(
                $cookieName,
                $encrypted,
                [
                    'expires' => $params['lifetime'] ? time() + $params['lifetime'] : 0,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ]
            );

            if (!$result) {
                return false;
            }

            $_COOKIE[$cookieName] = $encrypted;

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Destroys the session data by invalidating the session cookie.
     */
    public function destroy(string $sessionId): bool
    {
        return $this->destroyCookie();
    }

    /**
     * Performs garbage collection for session data.
     */
    public function gc(int $maxlifetime): int|false
    {
        return 1;
    }

    /**
     * Destroys the session cookie by setting its expiration to a past time.
     */
    private function destroyCookie(): bool
    {
        if (headers_sent()) {
            return false;
        }

        $cookieName = (string) ($this->config['cookie'] ?? '');

        if ($cookieName !== '') {
            setcookie($cookieName, '', [
                'expires' => time() - 3600,
                'path' => (string) ($this->config['path'] ?? '/'),
                'domain' => (string) ($this->config['domain'] ?? ''),
                'secure' => (bool) ($this->config['secure'] ?? false),
                'httponly' => true,
            ]);

            unset($_COOKIE[$cookieName]);
        }

        return true;
    }

    /**
     * Encrypts the given data using AES-256-CBC encryption.
     *
     * @throws RuntimeException
     */
    private function encrypt(string $data): string
    {
        $key = Config::get('app.key');

        if (!is_string($key) || $key === '') {
            throw new RuntimeException('Encryption key not configured');
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypts the given base64-encoded encrypted data using AES-256-CBC decryption.
     *
     * @throws RuntimeException
     */
    private function decrypt(string $data): string|false
    {
        $key = Config::get('app.key');

        if (!is_string($key) || $key === '') {
            throw new RuntimeException('Encryption key not configured');
        }

        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            return false;
        }

        $ivSize = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decoded, 0, $ivSize);
        $encrypted = substr($decoded, $ivSize);

        if (strlen($iv) < $ivSize) {
            $iv = str_pad($iv, $ivSize, "\0");
        }

        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
}