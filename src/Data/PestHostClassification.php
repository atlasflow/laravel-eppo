<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A host or pest classification, e.g. "Major host".
 */
final readonly class PestHostClassification
{
    public function __construct(
        public int $classId,
        public string $label,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            classId: Cast::int($data, 'class_id'),
            label: Cast::string($data, 'class_label'),
        );
    }
}
