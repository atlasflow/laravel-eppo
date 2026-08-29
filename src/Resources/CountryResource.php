<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Data\Country;
use Atlasflow\Eppo\Data\CountryCategorization;
use Atlasflow\Eppo\Data\CountryPresence;
use Atlasflow\Eppo\Support\Code;
use Illuminate\Support\Collection;

final class CountryResource extends Resource
{
    public readonly string $iso;

    public function __construct(CacheManager $cache, string $iso, bool $fresh = false)
    {
        parent::__construct($cache, $fresh);

        $this->iso = Code::country($iso);
    }

    public function overview(): Country
    {
        return Country::fromArray($this->fetch('overview'));
    }

    /**
     * Everything this country regulates, and on which list.
     *
     * @return Collection<int, CountryCategorization>
     */
    public function categorization(): Collection
    {
        return $this->collect($this->fetch('categorization'), CountryCategorization::fromArray(...));
    }

    /**
     * Every pest recorded in this country.
     *
     * @return Collection<int, CountryPresence>
     */
    public function presence(): Collection
    {
        return $this->collect($this->fetch('presence'), CountryPresence::fromArray(...));
    }

    public function forget(): int
    {
        return $this->cache->forgetSubject($this->iso);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function fetch(string $segment): array
    {
        return $this->get(
            sprintf('/country/%s/%s', $this->iso, $segment),
            'country.'.$segment,
            $this->iso,
        );
    }
}
