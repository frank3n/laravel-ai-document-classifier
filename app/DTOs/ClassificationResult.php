<?php

namespace App\DTOs;

readonly class ClassificationResult
{
    public function __construct(
        public string  $category,
        public string  $confidence,
        public string  $rationale,
        public array   $rawResponse,
        public string  $documentSource,
        public ?string $filename = null,
        public ?string $documentExcerpt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'category'   => $this->category,
            'confidence' => $this->confidence,
            'rationale'  => $this->rationale,
        ];
    }
}
