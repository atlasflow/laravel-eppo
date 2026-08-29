<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;

/**
 * A host-pest link. The same shape serves /hosts and /pests: on a pest's
 * hosts list the code is the plant, on a plant's pests list it is the pest.
 */
final readonly class HostPest
{
    public function __construct(
        public string $eppocode,
        public string $prefname,
        public ?int $classId,
        public ?string $classLabel,
        public ?string $bibref,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eppocode: Cast::string($data, 'eppocode'),
            prefname: Cast::string($data, 'prefname'),
            classId: Cast::nullableInt($data, 'class_id'),
            classLabel: Cast::nullableString($data, 'class_label'),
            bibref: Cast::nullableString($data, 'bibref'),
        );
    }
}
