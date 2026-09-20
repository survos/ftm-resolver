<?php

declare(strict_types=1);

namespace Survos\FtmResolver;

final readonly class AdjacentGraph
{
    /** @param array<string, array{entities: list<\Survos\FollowTheMoney\Entity|string>, total: int, relation: string}> $groups */
    public function __construct(public \Survos\FollowTheMoney\Entity $entity, public array $groups, public array $raw)
    {
    }
}
