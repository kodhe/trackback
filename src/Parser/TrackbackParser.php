<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Parser;

use Kodhe\Framework\Trackback\Contracts\ParserInterface;
use Kodhe\Framework\Trackback\Exceptions\ParseException;
use Kodhe\Framework\Trackback\Support\TrackbackConfig;

/**
 * Trackback parser implementation.
 */
class TrackbackParser implements ParserInterface
{
    private TrackbackConfig $config;

    /**
     * @param TrackbackConfig|null $config Parser configuration (defaults to a fresh TrackbackConfig)
     */
    public function __construct(?TrackbackConfig $config = null)
    {
        $this->config = $config ?? new TrackbackConfig();
    }

    /**
     * Parse incoming trackback request data.
     *
     * @param array<string,mixed> $data Raw request fields; requires url, title, blog_name, excerpt
     * @return array{url:string,title:string,excerpt:string,blog_name:string,charset:string} Sanitized fields
     * @throws ParseException When a required field is missing or the payload exceeds the configured maximum size
     */
    public function parseRequest(array $data): array
    {
        $required = ['url', 'title', 'blog_name', 'excerpt'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ParseException('Missing required field: ' . $field);
            }
        }

        // Validate payload size
        $payloadSize = strlen(serialize($data));
        if ($payloadSize > $this->config->getMaxPayloadSize()) {
            throw new ParseException('Payload exceeds maximum allowed size');
        }

        return [
            'url' => strip_tags($data['url']),
            'title' => strip_tags($data['title']),
            'excerpt' => strip_tags($data['excerpt']),
            'blog_name' => strip_tags($data['blog_name']),
            'charset' => isset($data['charset']) 
                ? strtoupper(trim($data['charset'])) 
                : 'auto',
        ];
    }

    /**
     * Parse trackback response XML.
     *
     * @param string $response Raw XML response body from the receiving blog
     * @return array{success:bool,error_code:int,message:string} Parsed result; success is TRUE only when <error> is 0
     */
    public function parseResponse(string $response): array
    {
        $result = [
            'success' => false,
            'error_code' => 1,
            'message' => 'Invalid or empty response',
        ];

        if (empty($response)) {
            return $result;
        }

        // Check for error tag
        if (preg_match('/<error>(\d+)<\/error>/i', $response, $errorMatch)) {
            $result['error_code'] = (int) $errorMatch[1];
            $result['success'] = ($result['error_code'] === 0);
        }

        // Extract message if present
        if (preg_match('/<message>(.*?)<\/message>/is', $response, $messageMatch)) {
            $result['message'] = trim($messageMatch[1]);
        } elseif ($result['success']) {
            $result['message'] = 'Trackback received successfully';
        }

        return $result;
    }

    /**
     * Extract URLs from a string (comma or space separated).
     *
     * @param string $urls One or more URLs separated by commas and/or whitespace
     * @return list<string> Unique, non-empty URLs in order of appearance
     */
    public function extractUrls(string $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        // Remove whitespace and normalize separators
        $urls = preg_replace('/\s*(\S+)\s*/', '$1,', $urls);
        
        // Remove duplicate commas
        $urls = str_replace(',,', ',', $urls);
        
        // Split by comma and remove empty entries
        $urlArray = array_filter(explode(',', rtrim($urls, ',')), fn($u) => !empty(trim($u)));
        
        // Remove duplicates
        return array_values(array_unique($urlArray));
    }

    /**
     * Build trackback POST data from array.
     *
     * @param array<string,mixed> $data Recognized keys: url, title, blog_name, excerpt, charset, tb_id
     * @return string URL-encoded query string (RFC 3986); tb_id is placed first when present
     */
    public function buildPostData(array $data): string
    {
        $params = [];
        
        $fields = ['url', 'title', 'blog_name', 'excerpt', 'charset'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $params[$field] = rawurlencode((string) $data[$field]);
            }
        }

        // Add tb_id if present
        if (isset($data['tb_id'])) {
            array_unshift($params, 'tb_id=' . rawurlencode((string) $data['tb_id']));
            return implode('&', $params);
        }

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Convert XML special characters to entities.
     *
     * @param string $str Input text; existing numeric/named entities are preserved
     * @return string Text with &, <, >, ", ' and - replaced by their entity forms
     */
    public function convertXml(string $str): string
    {
        $temp = '__TEMP_AMPERSANDS__';

        $str = preg_replace(['/#&#(\d+);/', '/&(\w+);/'], $temp . '\\1;', $str);

        $str = str_replace(
            ['&', '<', '>', '"', "'", '-'],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&#39;', '&#45;'],
            $str
        );

        return preg_replace(
            ['/'.$temp.'(\d+);/', '/'.$temp.'(\w+);/'],
            ['&#\\1;', '&\\1;'],
            $str
        );
    }

    /**
     * Limit characters preserving words.
     *
     * @param string $str     Input text (whitespace runs are collapsed before counting)
     * @param int    $n       Maximum length in bytes before the end character is appended
     * @param string $endChar Suffix appended when truncation occurs (default: horizontal ellipsis entity)
     * @return string The original text if short enough, otherwise truncated at the word boundary nearest $n
     */
    public function limitCharacters(string $str, int $n = 500, string $endChar = '&#8230;'): string
    {
        if (strlen($str) < $n) {
            return $str;
        }

        $str = preg_replace('/\s+/', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $str));

        if (strlen($str) <= $n) {
            return $str;
        }

        $out = '';
        foreach (explode(' ', trim($str)) as $val) {
            $out .= $val . ' ';
            if (strlen($out) >= $n) {
                return rtrim($out) . $endChar;
            }
        }

        return $str;
    }

    /**
     * Convert high ASCII to entities.
     *
     * @param string $str UTF-8 input text
     * @return string Text with every multi-byte (high ASCII) character replaced by its &#N; numeric entity
     */
    public function convertAscii(string $str): string
    {
        $count = 1;
        $out = '';
        $temp = [];

        for ($i = 0, $s = strlen($str); $i < $s; $i++) {
            $ordinal = ord($str[$i]);

            if ($ordinal < 128) {
                $out .= $str[$i];
            } else {
                if (count($temp) === 0) {
                    $count = ($ordinal < 224) ? 2 : 3;
                }

                $temp[] = $ordinal;

                if (count($temp) === $count) {
                    $number = ($count === 3)
                        ? (($temp[0] % 16) * 4096) + (($temp[1] % 64) * 64) + ($temp[2] % 64)
                        : (($temp[0] % 32) * 64) + ($temp[1] % 64);

                    $out .= '&#' . $number . ';';
                    $count = 1;
                    $temp = [];
                }
            }
        }

        return $out;
    }

    /**
     * Extract trackback ID from URL.
     *
     * @param string $url Trackback target URL (with or without query string)
     * @return string|false The trailing numeric/parsed ID, or FALSE when no ID can be extracted
     */
    public function getTrackbackId(string $url): string|false
    {
        $tbId = '';

        if (strpos($url, '?') !== false) {
            $tbArray = explode('/', $url);
            $tbEnd = $tbArray[count($tbArray) - 1];

            if (!is_numeric($tbEnd)) {
                $tbEnd = $tbArray[count($tbArray) - 2] ?? '';
            }

            $tbArray = explode('=', $tbEnd);
            $tbId = $tbArray[count($tbArray) - 1];
        } else {
            $url = rtrim($url, '/');
            $tbArray = explode('/', $url);
            $tbId = $tbArray[count($tbArray) - 1];

            if (!is_numeric($tbId)) {
                $tbId = $tbArray[count($tbArray) - 2] ?? '';
            }
        }

        return ctype_digit((string) $tbId) ? $tbId : false;
    }
}
