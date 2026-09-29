<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Transport;

use Kodhe\Framework\Trackback\Contracts\TransportInterface;
use Kodhe\Framework\Trackback\Exceptions\TransportException;
use Kodhe\Framework\Trackback\Support\TrackbackConfig;

/**
 * HTTP transport implementation using sockets (legacy compatible).
 */
class HttpTransport implements TransportInterface
{
    private int $timeout;
    private string $userAgent;
    /** @var resource|null */
    private $lastConnection = null;
    private string $lastHost = '';

    /**
     * @param TrackbackConfig|null $config Timeout and User-Agent are taken from this config (fresh defaults when null)
     */
    public function __construct(?TrackbackConfig $config = null)
    {
        $config = $config ?? new TrackbackConfig();
        $this->timeout = $config->getTimeout();
        $this->userAgent = $config->getUserAgent();
    }

    /**
     * Send a trackback request to the specified URL.
     *
     * @param string $url  Trackback endpoint URL
     * @param string $data URL-encoded POST body (tb_id is prepended when derivable from $url)
     * @return array{success:bool,body:string,error:string|null} success is TRUE only when the response contains <error>0</error>
     * @throws TransportException On invalid URL or connection/write failure
     */
    public function send(string $url, string $data): array
    {
        $target = parse_url($url);

        if ($target === false || !isset($target['host'])) {
            throw new TransportException('Invalid URL: ' . $url);
        }

        $host = $target['host'];
        $port = isset($target['port']) ? (int) $target['port'] : 80;
        
        // Build path
        $path = $target['path'] ?? '/';
        if (!empty($target['query'])) {
            $path .= '?' . $target['query'];
        }

        // Add tb_id to data if present in URL
        $tbId = $this->extractTrackbackId($url);
        if ($tbId !== false) {
            $data = 'tb_id=' . urlencode($tbId) . '&' . $data;
        }

        // Try to reuse connection if same host
        $fp = $this->getConnection($host, $port);

        if ($fp === false) {
            throw new TransportException('Failed to connect to: ' . $host);
        }

        // Build HTTP request
        $request = $this->buildRequest($path, $host, $data);

        // Send request
        $result = @fwrite($fp, $request);
        
        if ($result === false || $result === 0) {
            $this->closeConnection();
            throw new TransportException('Failed to send data to: ' . $url);
        }

        // Read response
        $response = $this->readResponse($fp);
        
        // Close connection if not reusable
        if ($this->lastConnection !== $fp) {
            @fclose($fp);
        }

        return [
            'success' => stripos($response, '<error>0</error>') !== false,
            'body' => $response,
            'error' => null,
        ];
    }

    /**
     * Set connection timeout in seconds.
     *
     * @param int $seconds Values below 1 are clamped to 1
     * @return self Fluent setter
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    /**
     * Set user agent string.
     *
     * @param string $userAgent Value sent in the User-Agent header
     * @return self Fluent setter
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Get or create socket connection.
     * @return resource|false
     */
    private function getConnection(string $host, int $port)
    {
        // Reuse connection if same host
        if ($this->lastConnection !== null && $this->lastHost === $host) {
            if (is_resource($this->lastConnection) && !feof($this->lastConnection)) {
                return $this->lastConnection;
            }
            $this->closeConnection();
        }

        // Create new connection with timeout
        $errno = 0;
        $errstr = '';
        
        $fp = @fsockopen($host, $port, $errno, $errstr, $this->timeout);

        if ($fp !== false) {
            $this->lastConnection = $fp;
            $this->lastHost = $host;
        }

        return $fp;
    }

    /**
     * Close current connection.
     */
    private function closeConnection(): void
    {
        if ($this->lastConnection !== null && is_resource($this->lastConnection)) {
            @fclose($this->lastConnection);
            $this->lastConnection = null;
            $this->lastHost = '';
        }
    }

    /**
     * Build HTTP POST request.
     */
    private function buildRequest(string $path, string $host, string $data): string
    {
        $request = "POST {$path} HTTP/1.0\r\n";
        $request .= "Host: {$host}\r\n";
        $request .= "Content-type: application/x-www-form-urlencoded\r\n";
        $request .= "Content-length: " . strlen($data) . "\r\n";
        $request .= "User-Agent: {$this->userAgent}\r\n";
        $request .= "Connection: close\r\n";
        $request .= "\r\n";
        $request .= $data;

        return $request;
    }

    /**
     * Read response from socket.
     * @param resource $fp
     */
    private function readResponse($fp): string
    {
        $response = '';
        
        stream_set_timeout($fp, $this->timeout);
        
        while (!feof($fp)) {
            $line = @fgets($fp, 4096);
            if ($line === false) {
                break;
            }
            $response .= $line;
        }

        return $response;
    }

    /**
     * Extract trackback ID from URL.
     */
    private function extractTrackbackId(string $url): string|false
    {
        $tbId = '';

        if (strpos($url, '?') !== false) {
            $parts = explode('/', $url);
            $end = end($parts);

            if (!is_numeric($end)) {
                $end = prev($parts) ?: '';
            }

            $parts = explode('=', $end);
            $tbId = end($parts);
        } else {
            $url = rtrim($url, '/');
            $parts = explode('/', $url);
            $tbId = end($parts);

            if (!is_numeric($tbId)) {
                $tbId = prev($parts) ?: '';
            }
        }

        return ctype_digit((string) $tbId) ? $tbId : false;
    }

    /**
     * Destructor - clean up connection.
     */
    public function __destruct()
    {
        $this->closeConnection();
    }
}
