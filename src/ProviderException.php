<?php

declare(strict_types=1);

namespace Survos\FtmResolver;

final class ProviderException extends \RuntimeException
{
    public function __construct(public readonly ?int $status, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
