<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Transport;

use InitPHP\Mailer\Transport\Smtp\StreamSocketClient;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fwrite;
use function is_resource;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;

use const STREAM_CRYPTO_METHOD_TLS_CLIENT;

final class StreamSocketClientTest extends TestCase
{
    /** @var resource|null */
    private $server;

    /** @var resource|null */
    private $peer;

    protected function tearDown(): void
    {
        foreach ([$this->peer, $this->server] as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testRefusesToStartTlsWhileClearTextBytesAreStillBuffered(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            $this->markTestSkipped('Cannot open a loopback listener: ' . $errstr);
        }
        $this->server = $server;
        $address = (string) stream_socket_get_name($server, false);
        [$host, $port] = explode(':', $address);

        $client = new StreamSocketClient();
        $client->open($host, (int) $port, 2);
        $peer = stream_socket_accept($server, 2);
        $this->assertNotFalse($peer);
        $this->peer = $peer;

        // A MITM (or server) sends the STARTTLS reply and an injected reply in
        // one segment; the second line stays in the client's read buffer.
        fwrite($peer, "220 Ready to start TLS\r\n250 injected before the handshake\r\n");

        $this->assertSame("220 Ready to start TLS\r\n", $client->readLine());
        $this->assertFalse($client->enableCrypto(STREAM_CRYPTO_METHOD_TLS_CLIENT));

        $client->close();
    }
}
