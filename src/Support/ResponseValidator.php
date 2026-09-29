<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback\Support;

use Kodhe\Framework\Trackback\Exceptions\ParseException;

/**
 * Response validation utility.
 */
class ResponseValidator
{
    /**
     * Validate trackback response XML.
     *
     * @param string $response Response body
     * @return array Validated response with 'success' (bool), 'error_code' (int), 'message' (string)
     */
    public function validate(string $response): array
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

        // Sanitize message to prevent XSS in error display
        $result['message'] = $this->sanitizeMessage($result['message']);

        return $result;
    }

    /**
     * Check if response indicates success.
     */
    public function isSuccess(string $response): bool
    {
        $result = $this->validate($response);
        return $result['success'];
    }

    /**
     * Sanitize error message for safe display.
     */
    private function sanitizeMessage(string $message): string
    {
        // Strip any remaining tags and limit length
        $message = strip_tags($message);
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        
        // Limit message length to prevent abuse
        return mb_substr($message, 0, 500);
    }
}
