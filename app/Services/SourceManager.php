<?php

namespace App\Services;

class SourceManager
{
    /**
     * Build a complete URL from the source configuration.
     *
     * @param string $bank The bank identifier (e.g., 'acba', 'ineco')
     * @param string $endpoint The endpoint key (e.g., 'currency_rates', 'deposits')
     * @return string The complete URL
     *
     * @throws \InvalidArgumentException If bank or endpoint is not found
     */
    public function url(string $bank, string $endpoint): string
    {
        $config = config("sources.banks.$bank");

        if (!$config) {
            throw new \InvalidArgumentException("Bank '$bank' not found in sources configuration.");
        }

        if (!isset($config['endpoints'][$endpoint])) {
            throw new \InvalidArgumentException("Endpoint '$endpoint' not found for bank '$bank'.");
        }

        return $config['website'] . $config['endpoints'][$endpoint];
    }

    /**
     * Get the website URL for a bank.
     *
     * @param string $bank The bank identifier
     * @return string The website URL
     *
     * @throws \InvalidArgumentException If bank is not found
     */
    public function getWebsite(string $bank): string
    {
        $config = config("sources.banks.$bank");

        if (!$config) {
            throw new \InvalidArgumentException("Bank '$bank' not found in sources configuration.");
        }

        return $config['website'];
    }

    /**
     * Get all available banks from the configuration.
     *
     * @return array
     */
    public function getBanks(): array
    {
        return array_keys(config('sources.banks', []));
    }

    /**
     * Get all endpoints for a specific bank.
     *
     * @param string $bank The bank identifier
     * @return array
     *
     * @throws \InvalidArgumentException If bank is not found
     */
    public function getEndpoints(string $bank): array
    {
        $config = config("sources.banks.$bank");

        if (!$config) {
            throw new \InvalidArgumentException("Bank '$bank' not found in sources configuration.");
        }

        return $config['endpoints'];
    }
}
