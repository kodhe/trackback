# Kodhe Trackback

A sender/receiver library for **Trackbacks** (cross-linked blog notifications) — a refactored version of the CodeIgniter 3 `Trackback` library under the `Kodhe\Framework\Trackback` namespace. Besides the CI3 API (`send()` / `receive()`), this package splits its internals into modular components: `Client/TrackbackClient`, `Server/TrackbackReceiver`, `Parser/TrackbackParser`, `Support/` (URL & response validators, config object), and an injectable transport (`Contracts/TransportInterface`).

> ⚠️ Trackbacks have practically been abandoned by the modern blog ecosystem; this package is available for legacy application compatibility.

## Installation

```bash
composer require kodhe/trackback
```

Requirements: PHP >= 8.1, the `curl` extension (default transport `Factory/CurlTransport`).

## Quick Start

```php
<?php

declare(strict_types=1);

use Kodhe\Framework\Trackback\Trackback;

$tb = new Trackback();

$success = $tb->send([
    'url'        => 'https://my-blog.com/post/new',   // the URL of our post
    'title'      => 'Post Title',
    'excerpt'    => 'A short summary of the post...',
    'blog_name'  => 'My Blog',
    'ping_url'   => 'https://other-blog/trackback/123',       // a single URL or an array
    'charset'    => 'UTF-8',                                 // optional
]);

if ($success === false) {
    echo $tb->error_string ?? '';   // CI3-style error property
}
```

## Directory Structure

```
src/
├── Trackback.php               # Main class (CI3-compatible API)
├── Client/TrackbackClient.php  # Modern OO sending
├── Server/TrackbackReceiver.php# Validation & extraction of incoming trackbacks
├── Parser/TrackbackParser.php
├── Support/                    # TrackbackConfig, UrlValidator, ResponseValidator
├── Transport/ + Factory/       # HttpTransport, CurlTransport
├── ValueObjects/TrackbackRequest.php
├── Contracts/                  # TransportInterface, ParserInterface, TrackbackInterface
└── Exceptions/
```

## Usage

### 1. Sending to multiple ping URLs

```php
$tb->send([
    'url'       => 'https://my-blog.com/post/1',
    'title'     => 'Post 1',
    'excerpt'   => 'An excerpt...',
    'blog_name' => 'My Blog',
    'ping_url'  => ['https://a.com/tb/1', 'https://b.com/tb/2'],
]);
// send() returns FALSE if any ping fails; each URL is validated
// by extract_urls()/validate_url() before being sent.
```

### 2. Receiving trackbacks (server side)

```php
if ($tb->receive()) {
    $data = $tb->data('title');      // a single field
    // store $tb->data(...) in the DB, then reply with success:
    $tb->send_success();             // <response><flerror>false</flerror></response>
} else {
    $tb->send_error('Incomplete Information'); // reply with an error (XML to output)
}
```

### 3. Configuration via TrackbackConfig

```php
use Kodhe\Framework\Trackback\Support\TrackbackConfig;

$config = $tb->getConfig();
$config->setTimeout(5);              // default 10 seconds
$config->setExcerptLength(250);      // default 500 characters
$config->setCharset('ISO-8859-1');   // default UTF-8
$tb->setConfig($config);
```

Built-in safety limits: max 64 KB payload, max 2048-character URLs, only the `http`/`https` protocols.

### 4. Modern style (without the CI3 API)

```php
use Kodhe\Framework\Trackback\Client\TrackbackClient;
use Kodhe\Framework\Trackback\ValueObjects\TrackbackRequest;

$request = TrackbackRequest::fromArray([
    'url' => 'https://my-blog.com/post/1',
    'title' => 'Title', 'excerpt' => '...', 'blog_name' => 'Blog',
    'ping_url' => 'https://target/tb',
]);

$client = new TrackbackClient();
$result = $client->send($request);   // bool / exception from Exceptions\*
```

## API Reference (`Trackback`)

| Method | Description |
|---|---|
| `send(array $tb_data)` | Sends a trackback; requires the keys `url,title,excerpt,blog_name,ping_url` |
| `receive()` | Validates incoming POST input; populates `$this->data` |
| `data($item)` | Retrieves a received data field |
| `process($url, $data)` | Executes a single HTTP ping |
| `extract_urls($urls)` | Cleans & validates the list of ping URLs |
| `validate_url(&$url)` / `get_id($url)` | Validates & parses the trackback ID |
| `send_success()` / `send_error($msg)` | XML reply to the pinger |
| `convert_xml($str)` / `limit_characters($str,$n)` | Sanitization |
| `getConfig()` / `setConfig(TrackbackConfig)` | Configuration object |

## CodeIgniter 3 Compatibility

A drop-in replacement for `CI_Trackback`: public methods, the `send()` data format, and XML replies are identical. Only the namespace/class names differ. CI helpers (`log_message`, `strip_tags` pipeline) are still used.

## Notes

- Send failures do not throw exceptions in the legacy `send()` API — check the return value; the modern `Client` layer throws `TrackbackSendException`.
- There is no retry mechanism; the timeout is set via `TrackbackConfig::setTimeout()`.

## Testing

```bash
vendor/bin/phpunit --filter Trackback
```
