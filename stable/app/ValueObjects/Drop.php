<?php

namespace App\ValueObjects;

final class Drop
{
    public function __construct(
        public readonly string $rarity,
        public readonly string $name,
        public readonly string $marker,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['rarity'] ?? '',
            $data['name'] ?? '',
            $data['marker'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'rarity' => $this->rarity,
            'name' => $this->name,
            'marker' => $this->marker,
        ];
    }
}
