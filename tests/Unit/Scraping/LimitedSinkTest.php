<?php

use App\Scraping\Exceptions\ResponseTooLarge;
use App\Scraping\Http\LimitedSink;

it('keeps what is written up to the limit', function () {
    $sink = new LimitedSink(10);

    $sink->write('12345');
    $sink->write('67890');
    $sink->rewind();

    expect($sink->getContents())->toBe('1234567890')
        ->and($sink->overflow())->toBeNull();
});

it('refuses the write that passes the limit and remembers the total', function () {
    $sink = new LimitedSink(10);
    $sink->write('12345678');

    expect(fn () => $sink->write('9012'))->toThrow(ResponseTooLarge::class, '12 bytes');

    $sink->rewind();

    expect($sink->overflow())->toBe(12)
        ->and($sink->getContents())->toBe('12345678'); // the refused chunk is not kept
});
