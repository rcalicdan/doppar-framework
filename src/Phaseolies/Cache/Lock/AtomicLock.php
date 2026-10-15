<?php

namespace Phaseolies\Cache\Lock;

use Phaseolies\Cache\CacheStore;

class AtomicLock
{
    /**
     * The cache store implementation.
     */
    protected CacheStore $store;

    /**
     * The name of the lock.
     */
    protected string $name;

    /**
     * The number of seconds the lock should be maintained.
     */
    protected int $seconds;

    /**
     * The owner identifier of the lock.
     */
    protected string $owner;

    private bool $shouldRelease = true;

    private bool $isOwned = false;

    private bool $isRestored = false;

    /**
     * Create a new lock instance.
     */
    public function __construct(CacheStore $store, string $name, int $seconds, ?string $owner = null, bool $isRestored = false)
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Lock seconds must be greater than 0');
        }

        $this->store = $store;
        $this->name = $name;
        $this->seconds = $seconds;
        $this->owner = $owner ?: $this->generateOwner();
        $this->isRestored = $isRestored;

        // If this is a restored lock, check if we still own it
        if ($isRestored) {
            $this->isOwned = $this->validateRestoredOwnership();
        }
    }

    /**
     * Attempt to acquire the lock.
     */
    public function get(): bool
    {
        if ($this->isOwned) {
            return true;
        }

        try {
            $this->checkAndReleaseStaleLock();

            $lockData = [
                'owner' => $this->owner,
                'duration' => $this->seconds,
                'acquired_at' => time(),
                'expires_at' => time() + $this->seconds,
                'version' => 1
            ];

            $serializedData = json_encode($lockData);
            if ($serializedData === false) {
                throw new \RuntimeException('Failed to encode lock data');
            }

            $acquired = $this->store->add($this->name, $serializedData, $this->seconds);

            if ($acquired) {
                $this->isOwned = true;
            }

            return $acquired;
        } catch (\Exception $e) {
            error("Lock acquisition failed for '{$this->name}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Release the lock.
     */
    public function release(): bool
    {
        if (!$this->isOwned) {
            return false;
        }

        try {
            $lockData = $this->store->get($this->name);

            if (!$lockData || !is_string($lockData)) {
                $this->isOwned = false;
                return false;
            }

            $data = json_decode($lockData, true);
            if (!is_array($data) || ($data['owner'] ?? '') !== $this->owner) {
                $this->isOwned = false;
                return false;
            }

            $success = $this->store->delete($this->name);

            if ($success) {
                $this->isOwned = false;
            }

            return $success;
        } catch (\Exception $e) {
            error("Lock release failed for '{$this->name}': " . $e->getMessage());
            $this->isOwned = false;
            return false;
        }
    }

    /**
     * Check and release stale lock.
     */
    private function checkAndReleaseStaleLock(): void
    {
        try {
            $lockData = $this->store->get($this->name);

            if (!$lockData || !is_string($lockData)) {
                return;
            }

            $data = json_decode($lockData, true);

            if (!is_array($data) || !isset($data['expires_at'])) {
                // Corrupted lock data
                $this->store->delete($this->name);
                return;
            }

            $expiresAt = (int) $data['expires_at'];
            $currentTime = time();

            // Add small grace period
            if ($currentTime > $expiresAt + 2) {
                // Lock is expired - release it
                $this->store->delete($this->name);
            }
        } catch (\Exception $e) {
            // Don't log during normal operation to avoid noise
        }
    }

    /**
     * Safely release lock on destruction.
     */
    public function __destruct()
    {
        if ($this->isOwned && $this->shouldRelease) {
            $this->release();
        }
    }

    /**
     * Attempt to acquire the lock for the given number of seconds.
     */
    public function block(int $seconds): bool
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Block seconds must be greater than 0');
        }

        $startTime = time();
        $end = $startTime + $seconds;
        $attempts = 0;
        $maxAttempts = $seconds * 4;

        while (time() < $end && $attempts < $maxAttempts) {
            if ($this->get()) {
                return true;
            }

            // Simpler backoff without jitter for reliability
            // 100ms to 500ms
            $sleepTime = min(500000, 100000 * (1 + $attempts));
            usleep($sleepTime);
            $attempts++;
        }

        return false;
    }

    /**
     * Prevent the lock from being automatically released.
     */
    public function preventRelease(): self
    {
        $this->shouldRelease = false;

        return $this;
    }

    /**
     * Allow the lock to be automatically released.
     */
    public function allowRelease(): self
    {
        $this->shouldRelease = true;

        return $this;
    }

    /**
     * Returns the current owner of the lock.
     */
    public function owner(): string
    {
        try {
            $lockData = $this->store->get($this->name);

            if ($lockData && is_string($lockData)) {
                $data = json_decode($lockData, true);
                return is_array($data) ? ($data['owner'] ?? '') : '';
            }
        } catch (\Exception $e) {
            // Silent failure
        }

        return '';
    }

    /**
     * Determines whether this lock is owned by the current process.
     */
    public function isOwnedByCurrentProcess(): bool
    {
        return $this->isOwned && $this->owner === $this->owner();
    }

    /**
     * Get the remaining time until lock expiration.
     */
    public function getRemainingTime(): ?int
    {
        try {
            $lockData = $this->store->get($this->name);

            if ($lockData && is_string($lockData)) {
                $data = json_decode($lockData, true);
                if (is_array($data) && isset($data['expires_at'])) {
                    $remaining = (int) $data['expires_at'] - time();
                    return max(0, $remaining);
                }
            }
        } catch (\Exception $e) {
            // Silent failure
        }

        return null;
    }

    /**
     * Validate that we still own a restored lock.
     */
    private function validateRestoredOwnership(): bool
    {
        try {
            $lockData = $this->store->get($this->name);

            if (!$lockData || !is_string($lockData)) {
                return false;
            }

            $data = json_decode($lockData, true);
            if (!is_array($data) || ($data['owner'] ?? '') !== $this->owner) {
                return false;
            }

            // Check if lock is expired
            $expiresAt = (int) ($data['expires_at'] ?? 0);
            if (time() > $expiresAt) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate a random owner identifier.
     */
    protected function generateOwner(): string
    {
        return uniqid('', true) . '_' . getmypid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Get the owner of the lock.
     */
    public function getOwner(): string
    {
        return $this->owner;
    }

    /**
     * Get the name of the lock.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the seconds of the lock.
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * Check if the lock is currently owned by this instance.
     */
    public function isOwned(): bool
    {
        return $this->isOwned;
    }

    /**
     * Manually set the lock as owned (for restoreLock functionality).
     */
    public function setOwned(): self
    {
        $this->isOwned = true;

        return $this;
    }

    /**
     * Check if this lock was restored.
     */
    public function isRestored(): bool
    {
        return $this->isRestored;
    }
}