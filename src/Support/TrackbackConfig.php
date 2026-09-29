<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Support;

/**
 * Configuration for Trackback operations.
 */
class TrackbackConfig
{
    /**
     * Character encoding.
     */
    public const DEFAULT_CHARSET = 'UTF-8';

    /**
     * Maximum excerpt length.
     */
    public const DEFAULT_EXCERPT_LENGTH = 500;

    /**
     * Connection timeout in seconds.
     */
    public const DEFAULT_TIMEOUT = 10;

    /**
     * Maximum payload size in bytes.
     */
    public const MAX_PAYLOAD_SIZE = 65536; // 64KB

    /**
     * Maximum URL length.
     */
    public const MAX_URL_LENGTH = 2048;

    /**
     * Allowed protocols for URLs.
     */
    public const ALLOWED_PROTOCOLS = ['http', 'https'];

    /**
     * User agent string.
     */
    public const USER_AGENT = 'CodeIgniter Trackback';

    private string $charset;
    private int $excerptLength;
    private int $timeout;
    private int $maxPayloadSize;
    private int $maxUrlLength;
    private array $allowedProtocols;
    private string $userAgent;
    private bool $convertAscii;

    /**
     * Initialize configuration with safe defaults.
     */
    public function __construct()
    {
        $this->charset = self::DEFAULT_CHARSET;
        $this->excerptLength = self::DEFAULT_EXCERPT_LENGTH;
        $this->timeout = self::DEFAULT_TIMEOUT;
        $this->maxPayloadSize = self::MAX_PAYLOAD_SIZE;
        $this->maxUrlLength = self::MAX_URL_LENGTH;
        $this->allowedProtocols = self::ALLOWED_PROTOCOLS;
        $this->userAgent = self::USER_AGENT;
        $this->convertAscii = true;
    }

    /**
     * @return string Character encoding used for outgoing trackbacks
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @param string $charset Character encoding, e.g. 'UTF-8'
     * @return self Fluent setter
     */
    public function setCharset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * @return int Maximum excerpt length in characters
     */
    public function getExcerptLength(): int
    {
        return $this->excerptLength;
    }

    /**
     * @param int $length Maximum excerpt length in characters
     * @return self Fluent setter
     */
    public function setExcerptLength(int $length): self
    {
        $this->excerptLength = max(1, $length);
        return $this;
    }

    /**
     * @return int Connection timeout in seconds
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $seconds Connection timeout in seconds
     * @return self Fluent setter
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    /**
     * @return int Maximum request payload size in bytes
     */
    public function getMaxPayloadSize(): int
    {
        return $this->maxPayloadSize;
    }

    /**
     * @param int $bytes Maximum request payload size in bytes
     * @return self Fluent setter
     */
    public function setMaxPayloadSize(int $bytes): self
    {
        $this->maxPayloadSize = max(1024, $bytes);
        return $this;
    }

    /**
     * @return int Maximum accepted URL length in characters
     */
    public function getMaxUrlLength(): int
    {
        return $this->maxUrlLength;
    }

    /**
     * @param int $length Maximum accepted URL length in characters
     * @return self Fluent setter
     */
    public function setMaxUrlLength(int $length): self
    {
        $this->maxUrlLength = max(100, $length);
        return $this;
    }

    /**
     * @return list<string> URL protocols considered valid (e.g. ['http','https'])
     */
    public function getAllowedProtocols(): array
    {
        return $this->allowedProtocols;
    }

    /**
     * @param list<string> $protocols URL protocols to accept
     * @return self Fluent setter
     */
    public function setAllowedProtocols(array $protocols): self
    {
        $this->allowedProtocols = $protocols;
        return $this;
    }

    /**
     * @return string User-Agent header sent with trackback requests
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @param string $agent User-Agent header value
     * @return self Fluent setter
     */
    public function setUserAgent(string $agent): self
    {
        $this->userAgent = $agent;
        return $this;
    }

    /**
     * @return bool Whether high-ASCII characters are converted to entities
     */
    public function isConvertAscii(): bool
    {
        return $this->convertAscii;
    }

    /**
     * @param bool $convert Convert high-ASCII characters to numeric entities
     * @return self Fluent setter
     */
    public function setConvertAscii(bool $convert): self
    {
        $this->convertAscii = $convert;
        return $this;
    }
}
