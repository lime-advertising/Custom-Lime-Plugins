<?php

namespace LimeRM\Agent;

/**
 * Handles creation and retrieval of the agent shared secret.
 */
class SecretManager
{
    private const OPTION_KEY = 'lrm_agent_shared_secret';

    /**
     * Ensure the shared secret exists and is stored securely.
     */
    public function ensureSecretExists(): void
    {
        if (! $this->getStoredSecret()) {
            $this->rotateSecret();
        }
    }

    /**
     * Retrieve the shared secret, generating it if absent.
     */
    public function getSecret(): string
    {
        $secret = $this->getStoredSecret();

        if (! $secret) {
            $secret = $this->rotateSecret();
        }

        return $secret;
    }

    /**
     * Generate a new secret and persist it.
     */
    public function rotateSecret(): string
    {
        $secret = $this->generateSecret();
        \update_site_option(self::OPTION_KEY, $secret);

        return $secret;
    }

    private function getStoredSecret(): ?string
    {
        $stored = \get_site_option(self::OPTION_KEY);

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return null;
    }

    private function generateSecret(): string
    {
        // 256-bit secret encoded with base64 for storage/display.
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
