<?php

declare(strict_types=1);

namespace Survos\FtmResolver;

interface ResolutionProvider
{
    public function name(): string;
    public function search(string $dataset, string $query, int $offset = 0, int $limit = 20): ResultPage;
    public function match(string $dataset, \Survos\FollowTheMoney\Entity $entity, int $limit = 10): ResultPage;
    public function entity(string $id): \Survos\FollowTheMoney\Entity;
    public function adjacent(string $id, int $offset = 0, int $limit = 20): AdjacentGraph;
    public function catalog(): array;
}
