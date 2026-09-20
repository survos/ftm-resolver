<?php

declare(strict_types=1);

namespace Survos\FtmResolver;

final readonly class Candidate
{
    public function __construct(public \Survos\FollowTheMoney\Entity $entity, public ?float $score, public ?bool $suggestedMatch, public array $raw)
    {
    }
}
