<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Factory;

use Kodhe\Framework\Trackback\Contracts\TransportInterface;
use Kodhe\Framework\Trackback\Support\TrackbackConfig;
use Kodhe\Framework\Trackback\Transport\HttpTransport;
use Kodhe\Framework\Trackback\Exceptions\TransportException;

/**
 * Factory for creating transport instances.
 */
class TransportFactory
{
    private static ?TransportInterface $defaultTransport = null;
    private static ?TrackbackConfig $config = null;

    /**
     * Create a transport instance.
     *
     * @param string|null $type Transport type ('http', 'curl', or custom class)
     * @throws TransportException If transport type is not supported
     */
    public static function create(?string $type = null): TransportInterface
    {
        $type = $type ?? 'http';

        switch ($type) {
            case 'http':
                return new HttpTransport(self::$config);
            
            case 'curl':
                return self::createCurlTransport();
            
            default:
                // Try to instantiate as class name
                if (class_exists($type)) {
                    $instance = new $type(self::$config);
                    
                    if (!$instance instanceof TransportInterface) {
                        throw new TransportException("Class {$type} must implement TransportInterface");
                    }
                    
                    return $instance;
                }
                
                throw new TransportException("Unsupported transport type: {$type}");
        }
    }

    /**
     * Get or create default transport (singleton pattern for reuse).
     */
    public static function getDefault(): TransportInterface
    {
        if (self::$defaultTransport === null) {
            self::$defaultTransport = self::create();
        }

        return self::$defaultTransport;
    }

    /**
     * Set default config for all transports.
     */
    public static function setConfig(TrackbackConfig $config): void
    {
        self::$config = $config;
        self::$defaultTransport = null; // Reset singleton
    }

    /**
     * Reset factory state.
     */
    public static function reset(): void
    {
        self::$defaultTransport = null;
        self::$config = null;
    }

    /**
     * Create cURL transport (if available).
     */
    private static function createCurlTransport(): TransportInterface
    {
        if (!function_exists('curl_init')) {
            throw new TransportException('cURL extension is not available');
        }

        return new CurlTransport(self::$config);
    }
}
