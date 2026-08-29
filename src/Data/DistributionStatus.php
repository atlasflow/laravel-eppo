<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A pest status code used in distribution records.
 */
final readonly class DistributionStatus
{
    public function __construct(
        public string $pestStatus,
        public string $label,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pestStatus: Cast::string($data, 'peststatus'),
            label: Cast::string($data, 'peststatus_label'),
        );
    }
}
