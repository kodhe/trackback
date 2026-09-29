<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Contracts;

/**
 * Main Trackback interface defining the public API.
 * Maintains backward compatibility with CodeIgniter 3 Trackback class.
 */
interface TrackbackInterface
{
    /**
     * Send a trackback to one or more URLs.
     *
     * @param array $tb_data Trackback data including url, title, excerpt, blog_name, ping_url
     * @return bool TRUE on success, FALSE on failure
     */
    public function send(array $tb_data): bool;

    /**
     * Receive incoming trackback data from POST request.
     *
     * @return bool TRUE on success, FALSE on failure
     */
    public function receive(): bool;

    /**
     * Extract trackback URLs from a string.
     *
     * @param string $urls Comma or space-separated URLs
     * @return array Array of validated URLs
     */
    public function extract_urls(string $urls): array;

    /**
     * Send error response for trackback.
     *
     * @param string $message Error message
     * @return void (exits)
     */
    public function send_error(string $message = 'Incomplete Information'): void;

    /**
     * Send success response for trackback.
     *
     * @return void (exits)
     */
    public function send_success(): void;

    /**
     * Get a specific data item.
     *
     * @param string $item Data item name
     * @return string The data value or empty string
     */
    public function data(string $item): string;

    /**
     * Display error messages.
     *
     * @param string $open Opening tag
     * @param string $close Closing tag
     * @return string Formatted error messages
     */
    public function display_errors(string $open = '<p>', string $close = '</p>'): string;

    /**
     * Get all error messages.
     *
     * @return array Array of error messages
     */
    public function get_errors(): array;

    /**
     * Clear all error messages.
     *
     * @return void
     */
    public function clear_errors(): void;
}
