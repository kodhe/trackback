<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Support;

use Kodhe\Framework\Trackback\Exceptions\InvalidUrlException;

/**
 * URL validation utility for security.
 */
class UrlValidator
{
    private TrackbackConfig $config;

    public function __construct(?TrackbackConfig $config = null)
    {
        $this->config = $config ?? new TrackbackConfig();
    }

    /**
     * Validate a single URL.
     *
     * @param string $url URL to validate
     * @return string Normalized URL
     * @throws InvalidUrlException If URL is invalid
     */
    public function validate(string $url): string
    {
        $url = trim($url);

        if (empty($url)) {
            throw new InvalidUrlException('URL cannot be empty');
        }

        // Add http:// if missing protocol
        if (!preg_match('#^[a-z]+://#i', $url)) {
            $url = 'http://' . $url;
        }

        // Parse URL
        $parsed = parse_url($url);
        
        if ($parsed === false || !isset($parsed['host'])) {
            throw new InvalidUrlException('Invalid URL format: ' . $url);
        }

        // Check protocol
        $protocol = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : 'http';
        if (!in_array($protocol, $this->config->getAllowedProtocols(), true)) {
            throw new InvalidUrlException('Protocol not allowed: ' . $protocol);
        }

        // Prevent SSRF - block private/internal IPs
        $host = $parsed['host'];
        if ($this->isPrivateIp($host)) {
            throw new InvalidUrlException('Access to private IP addresses is not allowed');
        }

        // Check URL length
        if (strlen($url) > $this->config->getMaxUrlLength()) {
            throw new InvalidUrlException('URL exceeds maximum length');
        }

        return $url;
    }

    /**
     * Check if host is a private/internal IP address.
     */
    private function isPrivateIp(string $host): bool
    {
        // Resolve hostname to IP
        $ip = gethostbyname($host);
        
        if ($ip === $host) {
            // Could not resolve, check if it's localhost
            return in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true);
        }

        // Check if IP is private/reserved
        return !$this->isPublicIp($ip);
    }

    /**
     * Check if IP is public (not private/reserved).
     */
    private function isPublicIp(string $ip): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        
        // IPv6 check
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // For IPv6, we need custom checks
            return $this->isPublicIpv6($ip);
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * Check if IPv6 address is public.
     */
    private function isPublicIpv6(string $ip): bool
    {
        // Block loopback, link-local, and unique local addresses
        $privatePrefixes = [
            '::1',           // Loopback
            'fe80::',        // Link-local
            'fc',            // Unique Local Address (ULA)
            'fd',            // Unique Local Address (ULA)
        ];

        $normalized = strtolower($ip);
        
        foreach ($privatePrefixes as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize URL by adding protocol if missing.
     */
    public function normalize(string $url): string
    {
        $url = trim($url);
        
        if (!preg_match('#^[a-z]+://#i', $url)) {
            return 'http://' . $url;
        }

        return $url;
    }
}
