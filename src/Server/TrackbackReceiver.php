<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Server;

use Kodhe\Framework\Trackback\Contracts\ParserInterface;
use Kodhe\Framework\Trackback\Support\TrackbackConfig;
use Kodhe\Framework\Trackback\Parser\TrackbackParser;
use Kodhe\Framework\Trackback\Exceptions\ParseException;
use Kodhe\Framework\Trackback\Exceptions\TrackbackReceiveException;

/**
 * Trackback receiver/server for handling incoming trackbacks.
 */
class TrackbackReceiver
{
    private ParserInterface $parser;
    private TrackbackConfig $config;
    private array $data = [];
    private array $errors = [];

    /**
     * @param ParserInterface|null $parser Parser used to sanitize incoming fields (default: TrackbackParser)
     * @param TrackbackConfig|null $config Validation limits (fresh defaults when null)
     */
    public function __construct(
        ?ParserInterface $parser = null,
        ?TrackbackConfig $config = null
    ) {
        $this->config = $config ?? new TrackbackConfig();
        $this->parser = $parser ?? new TrackbackParser($this->config);
    }

    /**
     * Receive and validate incoming trackback data from POST request.
     *
     * @param array|null $postData POST data (uses $_POST if null)
     * @return bool TRUE on success, FALSE on failure
     */
    public function receive(?array $postData = null): bool
    {
        $postData = $postData ?? $_POST;

        // Check for required fields
        $required = ['url', 'title', 'blog_name', 'excerpt'];
        foreach ($required as $field) {
            if (empty($postData[$field])) {
                $this->addError('The following required POST variable is missing: ' . $field);
                return false;
            }
        }

        try {
            // Parse and validate the request
            $parsed = $this->parser->parseRequest($postData);

            // Handle charset conversion
            $this->data['charset'] = $parsed['charset'];
            
            if ($this->data['charset'] === 'auto') {
                $this->data['charset'] = $this->detectCharset($postData);
            }

            // Convert encoding if needed
            foreach (['title', 'blog_name', 'excerpt'] as $field) {
                $value = $this->convertEncoding($postData[$field], $this->data['charset']);
                $value = strip_tags($value);
                
                if ($field === 'excerpt') {
                    $value = $this->parser->limitCharacters($value);
                }
                
                $this->data[$field] = $value;
            }

            // URL field - just strip tags, no XML conversion
            $this->data['url'] = strip_tags($postData['url']);

            return true;

        } catch (ParseException $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Detect charset from input data.
     */
    private function detectCharset(array $data): string
    {
        // Try to detect from content
        foreach ($data as $value) {
            if (is_string($value) && !empty($value)) {
                $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                if ($encoding !== false) {
                    return $encoding;
                }
            }
        }

        return 'UTF-8';
    }

    /**
     * Convert string encoding.
     */
    private function convertEncoding(string $value, string $fromCharset): string
    {
        $toCharset = $this->config->getCharset();

        if ($fromCharset === $toCharset) {
            return $value;
        }

        // Try mb_convert_encoding first
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, $toCharset, $fromCharset);
            if ($converted !== false) {
                return $converted;
            }
        }

        // Fallback to iconv
        if (function_exists('iconv')) {
            $converted = @iconv($fromCharset, $toCharset . '//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $value;
    }

    /**
     * Send error response.
     */
    public function sendError(string $message = 'Incomplete Information'): void
    {
        $this->outputResponse(1, $message);
    }

    /**
     * Send success response.
     */
    public function sendSuccess(): void
    {
        $this->outputResponse(0, '');
    }

    /**
     * Output XML response and exit.
     */
    private function outputResponse(int $errorCode, string $message): void
    {
        // Set proper headers
        if (!headers_sent()) {
            header('Content-Type: text/xml; charset=utf-8');
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?' . ">\n";
        $xml .= "<response>\n";
        $xml .= "<error>{$errorCode}</error>\n";
        
        if ($message !== '') {
            $xml .= "<message>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</message>\n";
        }
        
        $xml .= "</response>";

        echo $xml;
        exit;
    }

    /**
     * Get a specific data item.
     *
     * @param string $item Field name (url, title, excerpt, blog_name, charset)
     * @return string Sanitized value, or empty string when the item was not received
     */
    public function getData(string $item): string
    {
        return $this->data[$item] ?? '';
    }

    /**
     * Get all received data.
     *
     * @return array<string,string> All sanitized fields from the last successful receive()
     */
    public function getAllData(): array
    {
        return $this->data;
    }

    /**
     * Add an error message.
     */
    private function addError(string $message): void
    {
        $this->errors[] = $message;
        
        if (function_exists('log_message')) {
            log_message('error', $message);
        }
    }

    /**
     * Get all error messages.
     *
     * @return list<string> Validation errors accumulated by the last receive() call
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Clear errors.
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Clear received data.
     */
    public function clearData(): void
    {
        $this->data = [];
    }

    /**
     * Set the parser instance.
     */
    public function setParser(ParserInterface $parser): self
    {
        $this->parser = $parser;
        return $this;
    }
}
