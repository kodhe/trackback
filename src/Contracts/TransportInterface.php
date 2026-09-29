<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Contracts;

/**
 * Transport interface for sending trackback requests.
 */
interface TransportInterface
{
    /**
     * Send a trackback request to the specified URL.
     *
     * @param string $url Target URL
     * @param string $data POST data (URL-encoded)
     * @return array Response with 'success' (bool), 'body' (string), 'error' (string|null)
     * @throws \Kodhe\Framework\Trackback\Exceptions\TransportException On transport failure
     */
    public function send(string $url, string $data): array;

    /**
     * Set connection timeout in seconds.
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function setTimeout(int $seconds): self;

    /**
     * Set user agent string.
     *
     * @param string $userAgent User agent string
     * @return self
     */
    public function setUserAgent(string $userAgent): self;
}
