<?php

namespace App\Scraping\Http;

use App\Scraping\Exceptions\ResponseTooLarge;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * Response body sink that refuses to grow past a byte limit. curl writes the
 * body here after decoding it, so a gzip bomb is counted at its real size.
 */
final class LimitedSink implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private int $received = 0;

    private ?int $overflow = null;

    public function __construct(private readonly int $maxBytes)
    {
        $this->stream = Utils::streamFor(Utils::tryFopen('php://temp', 'w+'));
    }

    /**
     * @throws ResponseTooLarge Guzzle catches it and aborts the transfer.
     */
    public function write(string $string): int
    {
        $this->received += strlen($string);

        if ($this->received > $this->maxBytes) {
            $this->overflow = $this->received;

            throw ResponseTooLarge::after($this->received);
        }

        return $this->stream->write($string);
    }

    /**
     * The bytes received when the limit was passed, or null if it never was.
     */
    public function overflow(): ?int
    {
        return $this->overflow;
    }
}
