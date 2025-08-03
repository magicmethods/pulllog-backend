<?php

namespace App\ValueObjects;

final class Definition
{
    public function __construct(
        public readonly string $symbol,
        public readonly string $label,
        public readonly string $value,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['symbol'] ?? '',
            $data['label'] ?? '',
            $data['value'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
