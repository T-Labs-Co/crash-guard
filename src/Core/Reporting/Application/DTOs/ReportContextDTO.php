<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\DTOs;

/**
 * Typed DTO for crash report context data.
 */
readonly class ReportContextDTO
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public ?string $userId = null,
        public ?string $userEmail = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $route = null,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'] ?? null,
            userEmail: $data['user_email'] ?? null,
            ip: $data['ip'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            route: $data['route'] ?? null,
            extra: array_diff_key($data, array_flip(['user_id', 'user_email', 'ip', 'user_agent', 'route'])),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->userId,
            'user_email' => $this->userEmail,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'route' => $this->route,
            ...$this->extra,
        ]);
    }
}
