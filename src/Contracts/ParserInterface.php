<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Contracts;

/**
 * Parser interface for parsing trackback requests and responses.
 */
interface ParserInterface
{
    /**
     * Parse incoming trackback request data.
     *
     * @param array $data POST data
     * @return array Parsed and validated trackback data
     * @throws \Kodhe\Framework\Trackback\Exceptions\ParseException On parse failure
     */
    public function parseRequest(array $data): array;

    /**
     * Parse trackback response XML.
     *
     * @param string $response Response body
     * @return array Response with 'success' (bool), 'error_code' (int), 'message' (string)
     */
    public function parseResponse(string $response): array;

    /**
     * Extract URLs from a string (comma or space separated).
     *
     * @param string $urls String containing URLs
     * @return array Array of extracted URLs
     */
    public function extractUrls(string $urls): array;

    /**
     * Build trackback POST data from array.
     *
     * @param array $data Trackback data
     * @return string URL-encoded POST data
     */
    public function buildPostData(array $data): string;
}
