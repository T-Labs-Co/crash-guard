<?php

declare(strict_types=1);

use TLabsCo\CrashGuard\Core\Reporting\Application\DTOs\ReportContextDTO;

it('creates DTO with default values', function () {
    $dto = new ReportContextDTO;

    expect($dto->userId)->toBeNull()
        ->and($dto->userEmail)->toBeNull()
        ->and($dto->ip)->toBeNull()
        ->and($dto->userAgent)->toBeNull()
        ->and($dto->route)->toBeNull()
        ->and($dto->extra)->toBe([]);
});

it('creates DTO with custom values', function () {
    $dto = new ReportContextDTO(
        userId: 'user-123',
        userEmail: 'user@example.com',
        ip: '192.168.1.1',
        userAgent: 'Mozilla/5.0',
        route: '/api/test',
        extra: ['custom' => 'value'],
    );

    expect($dto->userId)->toBe('user-123')
        ->and($dto->userEmail)->toBe('user@example.com')
        ->and($dto->ip)->toBe('192.168.1.1')
        ->and($dto->userAgent)->toBe('Mozilla/5.0')
        ->and($dto->route)->toBe('/api/test')
        ->and($dto->extra)->toBe(['custom' => 'value']);
});

it('creates DTO from array', function () {
    $data = [
        'user_id' => 'user-456',
        'user_email' => 'test@example.com',
        'ip' => '10.0.0.1',
        'user_agent' => 'curl/7.68',
        'route' => '/dashboard',
        'custom_field' => 'custom_value',
    ];

    $dto = ReportContextDTO::fromArray($data);

    expect($dto->userId)->toBe('user-456')
        ->and($dto->userEmail)->toBe('test@example.com')
        ->and($dto->ip)->toBe('10.0.0.1')
        ->and($dto->userAgent)->toBe('curl/7.68')
        ->and($dto->route)->toBe('/dashboard')
        ->and($dto->extra)->toBe(['custom_field' => 'custom_value']);
});

it('fromArray sets null for missing fields', function () {
    $data = ['custom_key' => 'custom_val'];

    $dto = ReportContextDTO::fromArray($data);

    expect($dto->userId)->toBeNull()
        ->and($dto->userEmail)->toBeNull()
        ->and($dto->ip)->toBeNull()
        ->and($dto->userAgent)->toBeNull()
        ->and($dto->route)->toBeNull()
        ->and($dto->extra)->toBe(['custom_key' => 'custom_val']);
});

it('converts DTO to array', function () {
    $dto = new ReportContextDTO(
        userId: 'user-789',
        userEmail: 'user@test.com',
        ip: '127.0.0.1',
        userAgent: 'TestAgent',
        route: '/test',
        extra: ['key' => 'value'],
    );

    $array = $dto->toArray();

    expect($array)->toBe([
        'user_id' => 'user-789',
        'user_email' => 'user@test.com',
        'ip' => '127.0.0.1',
        'user_agent' => 'TestAgent',
        'route' => '/test',
        'key' => 'value',
    ]);
});

it('toArray filters out null values', function () {
    $dto = new ReportContextDTO(
        userId: 'user-1',
        ip: '1.2.3.4',
    );

    $array = $dto->toArray();

    expect($array)->toBe([
        'user_id' => 'user-1',
        'ip' => '1.2.3.4',
    ])->not->toHaveKey('user_email')
        ->not->toHaveKey('user_agent')
        ->not->toHaveKey('route');
});

it('round-trips fromArray and toArray', function () {
    $original = [
        'user_id' => 'user-rt',
        'user_email' => 'rt@example.com',
        'ip' => '10.10.10.10',
        'user_agent' => 'RoundTrip',
        'route' => '/roundtrip',
        'extra_data' => 'extra',
    ];

    $dto = ReportContextDTO::fromArray($original);
    $result = $dto->toArray();

    expect($result)->toBe($original);
});
