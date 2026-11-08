<?php

declare(strict_types=1);

it('normalizes Bangladeshi mobile numbers to the 8801XXXXXXXXX format', function (string $input, string $expected) {
    expect(normalize_bd_mobile($input))->toBe($expected);
})->with([
    ['01711000001', '8801711000001'],
    ['8801711000001', '8801711000001'],
    ['+8801711000001', '8801711000001'],
    ['+880 1711-000001', '8801711000001'],
]);

it('validates BD mobile numbers', function (string $input, bool $expected) {
    expect(is_bd_mobile($input))->toBe($expected);
})->with([
    ['01711000001', true],
    ['01611000001', true],
    ['01211000001', false],
    ['0171100000', false],
]);
