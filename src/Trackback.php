<?php

declare(strict_types=1);

namespace Kodhe\Framework\Trackback;

use Kodhe\Framework\Trackback\Support\TrackbackConfig;

/**
 * Trackback Class - Main Facade
 *
 * Trackback Sending/Receiving Class
 * Maintains 100% backward compatibility with CodeIgniter 3 Trackback class
 * while using modern modular architecture internally.
 *
 * @package         Kodhe\Framework\Trackback
 * @subpackage      Libraries
 * @category        Trackbacks
 * @author          EllisLab Dev Team
 * @link            https://codeigniter.com/user_guide/libraries/trackback.html
 */
class Trackback
{
    /**
     * Character set
     *
     * @var string
     */
    public $charset = 'UTF-8';

    /**
     * Trackback data
     *
     * @var array
     */
    public $data = [
        'url' => '',
        'title' => '',
        'excerpt' => '',
        'blog_name' => '',
        'charset' => ''
    ];

    /**
     * Convert ASCII flag
     *
     * Whether to convert high-ASCII and MS Word
     * characters to HTML entities.
     *
     * @var bool
     */
    public $convert_ascii = true;

    /**
     * Response
     *
     * @var string
     */
    public $response = '';

    /**
     * Error messages list
     *
     * @var string[]
     */
    public $error_msg = [];

    /**
     * Configuration
     *
     * @var TrackbackConfig|null
     */
    private ?TrackbackConfig $config = null;

    // --------------------------------------------------------------------

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->config = new TrackbackConfig();
        $this->config->setCharset($this->charset);
        $this->config->setConvertAscii((bool) $this->convert_ascii);
        
        log_message('info', 'Trackback Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Get internal config instance
     */
    public function getConfig(): TrackbackConfig
    {
        if ($this->config === null) {
            $this->config = new TrackbackConfig();
        }
        return $this->config;
    }

    /**
     * Set configuration
     */
    public function setConfig(TrackbackConfig $config): self
    {
        $this->config = $config;
        $this->charset = $config->getCharset();
        $this->convert_ascii = $config->isConvertAscii();
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Send Trackback
     *
     * @param array $tb_data Trackback data
     * @return bool TRUE on success, FALSE on failure
     */
    public function send($tb_data)
    {
        if (!is_array($tb_data)) {
            $this->set_error('The send() method must be passed an array');
            return false;
        }

        // Pre-process the Trackback Data
        foreach (['url', 'title', 'excerpt', 'blog_name', 'ping_url'] as $item) {
            if (!isset($tb_data[$item])) {
                $this->set_error('Required item missing: ' . $item);
                return false;
            }

            switch ($item) {
                case 'ping_url':
                    $$item = $this->extract_urls($tb_data[$item]);
                    break;
                case 'excerpt':
                    $$item = $this->limit_characters($this->convert_xml(strip_tags(stripslashes($tb_data[$item]))));
                    break;
                case 'url':
                    $$item = str_replace('&#45;', '-', $this->convert_xml(strip_tags(stripslashes($tb_data[$item]))));
                    break;
                default:
                    $$item = $this->convert_xml(strip_tags(stripslashes($tb_data[$item])));
                    break;
            }

            // Convert High ASCII Characters
            if ($this->convert_ascii === true && in_array($item, ['excerpt', 'title', 'blog_name'], true)) {
                $$item = $this->convert_ascii($$item);
            }
        }

        // Build the Trackback data string
        $charset = isset($tb_data['charset']) ? $tb_data['charset'] : $this->charset;

        $data = 'url=' . rawurlencode($url) . '&title=' . rawurlencode($title) . '&blog_name=' . rawurlencode($blog_name)
            . '&excerpt=' . rawurlencode($excerpt) . '&charset=' . rawurlencode($charset);

        // Send Trackback(s)
        $return = true;
        if (count($ping_url) > 0) {
            foreach ($ping_url as $url) {
                if ($this->process($url, $data) === false) {
                    $return = false;
                }
            }
        }

        return $return;
    }

    // --------------------------------------------------------------------

    /**
     * Receive Trackback Data
     *
     * This function simply validates the incoming TB data.
     * It returns FALSE on failure and TRUE on success.
     * If the data is valid it is set to the $this->data array
     * so that it can be inserted into a database.
     *
     * @return bool
     */
    public function receive()
    {
        foreach (['url', 'title', 'blog_name', 'excerpt'] as $val) {
            if (empty($_POST[$val])) {
                $this->set_error('The following required POST variable is missing: ' . $val);
                return false;
            }

            $this->data['charset'] = isset($_POST['charset']) ? strtoupper(trim($_POST['charset'])) : 'auto';

            if ($val !== 'url' && MB_ENABLED === true) {
                if (MB_ENABLED === true) {
                    $_POST[$val] = mb_convert_encoding($_POST[$val], $this->charset, $this->data['charset']);
                } elseif (ICONV_ENABLED === true) {
                    $_POST[$val] = @iconv($this->data['charset'], $this->charset . '//IGNORE', $_POST[$val]);
                }
            }

            $_POST[$val] = ($val !== 'url') ? $this->convert_xml(strip_tags($_POST[$val])) : strip_tags($_POST[$val]);

            if ($val === 'excerpt') {
                $_POST['excerpt'] = $this->limit_characters($_POST['excerpt']);
            }

            $this->data[$val] = $_POST[$val];
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Send Trackback Error Message
     *
     * Allows custom errors to be set. By default it
     * sends the "incomplete information" error, as that's
     * the most common one.
     *
     * @param string $message Error message
     * @return void (exits)
     */
    public function send_error($message = 'Incomplete Information')
    {
        exit('<?xml version="1.0" encoding="utf-8"?' . ">\n<response>\n<error>1</error>\n<message>" . $message . "</message>\n</response>");
    }

    // --------------------------------------------------------------------

    /**
     * Send Trackback Success Message
     *
     * This should be called when a trackback has been
     * successfully received and inserted.
     *
     * @return void (exits)
     */
    public function send_success()
    {
        exit('<?xml version="1.0" encoding="utf-8"?' . ">\n<response>\n<error>0</error>\n</response>");
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a particular item
     *
     * @param string $item Data item name
     * @return string The data value or empty string
     */
    public function data($item)
    {
        return isset($this->data[$item]) ? $this->data[$item] : '';
    }

    // --------------------------------------------------------------------

    /**
     * Process Trackback
     *
     * Opens a socket connection and passes the data to
     * the server. Returns TRUE on success, FALSE on failure
     *
     * @param string $url Target URL
     * @param string $data POST data
     * @return bool
     */
    public function process($url, $data)
    {
        $target = parse_url($url);

        // Open the socket
        if (!$fp = @fsockopen($target['host'], 80)) {
            $this->set_error('Invalid Connection: ' . $url);
            return false;
        }

        // Build the path
        $path = isset($target['path']) ? $target['path'] : $url;
        empty($target['query']) OR $path .= '?' . $target['query'];

        // Add the Trackback ID to the data string
        if ($id = $this->get_id($url)) {
            $data = 'tb_id=' . $id . '&' . $data;
        }

        // Transfer the data
        fputs($fp, 'POST ' . $path . " HTTP/1.0\r\n");
        fputs($fp, 'Host: ' . $target['host'] . "\r\n");
        fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
        fputs($fp, 'Content-length: ' . strlen($data) . "\r\n");
        fputs($fp, "Connection: close\r\n\r\n");
        fputs($fp, $data);

        // Was it successful?
        $this->response = '';
        while (!feof($fp)) {
            $this->response .= fgets($fp, 128);
        }
        @fclose($fp);

        if (stripos($this->response, '<error>0</error>') === false) {
            $message = preg_match('/<message>(.*?)<\/message>/is', $this->response, $match)
                ? trim($match[1])
                : 'An unknown error was encountered';
            $this->set_error($message);
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Extract Trackback URLs
     *
     * This function lets multiple trackbacks be sent.
     * It takes a string of URLs (separated by comma or
     * space) and puts each URL into an array
     *
     * @param string $urls Comma or space-separated URLs
     * @return array Array of validated URLs
     */
    public function extract_urls($urls)
    {
        // Remove the pesky white space and replace with a comma, then replace doubles.
        $urls = str_replace(',,', ',', preg_replace('/\s*(\S+)\s*/', '\1,', $urls));

        // Break into an array via commas and remove duplicates
        $urls = array_unique(preg_split('/[,]/', rtrim($urls, ',')));

        array_walk($urls, [$this, 'validate_url']);
        return $urls;
    }

    // --------------------------------------------------------------------

    /**
     * Validate URL
     *
     * Simply adds "http://" if missing
     *
     * @param string &$url URL to validate
     * @return void
     */
    public function validate_url(&$url)
    {
        $url = trim($url);

        if (stripos($url, 'http') !== 0) {
            $url = 'http://' . $url;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Find the Trackback URL's ID
     *
     * @param string $url Trackback URL
     * @return string|false Trackback ID or false
     */
    public function get_id($url)
    {
        $tb_id = '';

        if (strpos($url, '?') !== false) {
            $tb_array = explode('/', $url);
            $tb_end   = $tb_array[count($tb_array) - 1];

            if (!is_numeric($tb_end)) {
                $tb_end  = $tb_array[count($tb_array) - 2];
            }

            $tb_array = explode('=', $tb_end);
            $tb_id    = $tb_array[count($tb_array) - 1];
        } else {
            $url = rtrim($url, '/');

            $tb_array = explode('/', $url);
            $tb_id    = $tb_array[count($tb_array) - 1];

            if (!is_numeric($tb_id)) {
                $tb_id = $tb_array[count($tb_array) - 2];
            }
        }

        return ctype_digit((string) $tb_id) ? $tb_id : false;
    }

    // --------------------------------------------------------------------

    /**
     * Convert Reserved XML characters to Entities
     *
     * @param string $str Input string
     * @return string String with XML entities
     */
    public function convert_xml($str)
    {
        $temp = '__TEMP_AMPERSANDS__';

        $str = preg_replace(['/#&#(\d+);/', '/&(\w+);/'], $temp . '\\1;', $str);

        $str = str_replace(['&', '<', '>', '"', "'", '-'],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&#39;', '&#45;'],
            $str);

        return preg_replace(['/' . $temp . '(\d+);/', '/' . $temp . '(\w+);/'], ['&#\\1;', '&\\1;'], $str);
    }

    // --------------------------------------------------------------------

    /**
     * Character limiter
     *
     * Limits the string based on the character count. Will preserve complete words.
     *
     * @param string $str Input string
     * @param int $n Maximum length
     * @param string $end_char Ellipsis string
     * @return string Limited string
     */
    public function limit_characters($str, $n = 500, $end_char = '&#8230;')
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
                return rtrim($out) . $end_char;
            }
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * High ASCII to Entities
     *
     * Converts High ascii text and MS Word special chars
     * to character entities
     *
     * @param string $str Input string
     * @return string String with entities
     */
    public function convert_ascii($str)
    {
        $count = 1;
        $out   = '';
        $temp  = [];

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

    // --------------------------------------------------------------------

    /**
     * Set error message
     *
     * @param string $msg Error message
     * @return void
     */
    public function set_error($msg)
    {
        log_message('error', $msg);
        $this->error_msg[] = $msg;
    }

    // --------------------------------------------------------------------

    /**
     * Show error messages
     *
     * @param string $open Opening tag
     * @param string $close Closing tag
     * @return string Formatted error messages
     */
    public function display_errors($open = '<p>', $close = '</p>')
    {
        return (count($this->error_msg) > 0) ? $open . implode($close . $open, $this->error_msg) . $close : '';
    }

    // --------------------------------------------------------------------

    /**
     * Get all error messages (new method for better testing)
     *
     * @return array Array of error messages
     */
    public function get_errors(): array
    {
        return $this->error_msg;
    }

    /**
     * Clear all error messages (new method for better testing)
     *
     * @return void
     */
    public function clear_errors(): void
    {
        $this->error_msg = [];
    }
}
