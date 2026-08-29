<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Data;

use Atlasflow\Eppo\Support\Cast;
use Illuminate\Support\Collection;

/**
 * A Regional Plant Protection Organization and its member countries.
 */
final readonly class Rppo
{
    /**
     * @param  Collection<int, RppoMember>  $members
     */
    public function __construct(
        public string $code,
        public string $acronym,
        public string $name,
        public Collection $members,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: Cast::string($data, 'rppo_code'),
            acronym: Cast::string($data, 'rppo_acronym'),
            name: Cast::string($data, 'rppo_name'),
            members: (new Collection(Cast::arr($data, 'members')))
                ->map(fn (mixed $member): RppoMember => RppoMember::fromArray(is_array($member) ? $member : []))
                ->values(),
        );
    }
}
