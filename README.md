# Kodhe Trackback

Library pengirim/penerima **Trackback** (notifikasi blog-saling-hubung) hasil refaktor `Trackback` CodeIgniter 3, dengan namespace `Kodhe\Framework\Trackback`. Selain API CI3 (`send()` / `receive()`), package ini memecah internals menjadi komponen modular: `Client/TrackbackClient`, `Server/TrackbackReceiver`, `Parser/TrackbackParser`, `Support/` (validator URL & response, config object), dan transport dapat-disuntik (`Contracts/TransportInterface`).

> ⚠️ Trackback praktis sudah ditinggalkan ekosistem blog modern; package ini tersedia untuk kompatibilitas aplikasi lama.

## Instalasi

```bash
composer require kodhe/trackback
```

Persyaratan: PHP >= 8.1, extension `curl` (transport default `Factory/CurlTransport`).

## Quick Start

```php
<?php

declare(strict_types=1);

use Kodhe\Framework\Trackback\Trackback;

$tb = new Trackback();

$success = $tb->send([
    'url'        => 'https://blog-saya.com/tulisan/baru',   // URL tulisan kita
    'title'      => 'Judul Tulisan',
    'excerpt'    => 'Ringkasan singkat tulisan...',
    'blog_name'  => 'Blog Saya',
    'ping_url'   => 'https://blog-lain/trackback/123',       // satu atau array
    'charset'    => 'UTF-8',                                 // opsional
]);

if ($success === false) {
    echo $tb->error_string ?? '';   // properti error gaya CI3
}
```

## Struktur Direktori

```
src/
├── Trackback.php               # Kelas utama (API kompatibel CI3)
├── Client/TrackbackClient.php  # Pengiriman modern (OO)
├── Server/TrackbackReceiver.php# Validasi & ekstraksi trackback masuk
├── Parser/TrackbackParser.php
├── Support/                    # TrackbackConfig, UrlValidator, ResponseValidator
├── Transport/ + Factory/       # HttpTransport, CurlTransport
├── ValueObjects/TrackbackRequest.php
├── Contracts/                  # TransportInterface, ParserInterface, TrackbackInterface
└── Exceptions/
```

## Penggunaan

### 1. Mengirim ke banyak ping URL

```php
$tb->send([
    'url'       => 'https://blog-saya.com/post/1',
    'title'     => 'Post 1',
    'excerpt'   => 'Cuplikan...',
    'blog_name' => 'Blog Saya',
    'ping_url'  => ['https://a.com/tb/1', 'https://b.com/tb/2'],
]);
// send() mengembalikan FALSE bila salah satu ping gagal; tiap URL divalidasi
// oleh extract_urls()/validate_url() sebelum dikirim.
```

### 2. Menerima trackback (sisi server)

```php
if ($tb->receive()) {
    $data = $tb->data('title');      // field tunggal
    // simpan $tb->data(...) ke DB, lalu balas sukses:
    $tb->send_success();             // <response><flerror>false</flerror></response>
} else {
    $tb->send_error('Incomplete Information'); // balas error (XML ke output)
}
```

### 3. Konfigurasi via TrackbackConfig

```php
use Kodhe\Framework\Trackback\Support\TrackbackConfig;

$config = $tb->getConfig();
$config->setTimeout(5);              // default 10 detik
$config->setExcerptLength(250);      // default 500 karakter
$config->setCharset('ISO-8859-1');   // default UTF-8
$tb->setConfig($config);
```

Batas aman bawaan: payload maks 64 KB, URL maks 2048 karakter, protokol hanya `http`/`https`.

### 4. Gaya modern (tanpa API CI3)

```php
use Kodhe\Framework\Trackback\Client\TrackbackClient;
use Kodhe\Framework\Trackback\ValueObjects\TrackbackRequest;

$request = TrackbackRequest::fromArray([
    'url' => 'https://blog-saya.com/post/1',
    'title' => 'Judul', 'excerpt' => '...', 'blog_name' => 'Blog',
    'ping_url' => 'https://target/tb',
]);

$client = new TrackbackClient();
$result = $client->send($request);   // bool / exception dari Exceptions\*
```

## Referensi API (`Trackback`)

| Method | Keterangan |
|---|---|
| `send(array $tb_data)` | Kirim trackback; wajib key `url,title,excerpt,blog_name,ping_url` |
| `receive()` | Validasi input POST masuk; isi `$this->data` |
| `data($item)` | Ambil field data yang diterima |
| `process($url, $data)` | Eksekusi satu HTTP ping |
| `extract_urls($urls)` | Bersihkan & validasi daftar ping URL |
| `validate_url(&$url)` / `get_id($url)` | Validasi & parsing ID trackback |
| `send_success()` / `send_error($msg)` | Balasan XML ke peming |
| `convert_xml($str)` / `limit_characters($str,$n)` | Sanitasi |
| `getConfig()` / `setConfig(TrackbackConfig)` | Konfigurasi objek |

## Kompatibilitas CodeIgniter 3

Drop-in terhadap `CI_Trackback`: method publik, format data `send()`, dan balasan XML identik. Perbedaan hanya namespace/nama kelas. Helper CI (`log_message`, `strip_tags` pipeline) tetap dipakai.

## Catatan

- Gagal kirim tidak melempar exception pada API `send()` lama — periksa return value; layer `Client` modern melempar `TrackbackSendException`.
- Tidak ada mekanisme retry; timeout diatur via `TrackbackConfig::setTimeout()`.

## Pengujian

```bash
vendor/bin/phpunit --filter Trackback
```
