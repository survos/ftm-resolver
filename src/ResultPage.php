<?php

declare(strict_types=1);

namespace Survos\FtmResolver;

final readonly class ResultPage
{
    /** @param list<Candidate> $candidates */
    public function __construct(public array $candidates, public int $total, public string $totalRelation, public array $raw)
    {
    }
}
