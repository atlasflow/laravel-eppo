<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Cache\CacheManager;
use Atlasflow\Eppo\Data\CountryCategorization;
use Atlasflow\Eppo\Data\Rppo;
use Atlasflow\Eppo\Support\Code;
use Illuminate\Support\Collection;

final class RppoResource extends Resource
{
    public readonly string $code;

    public function __construct(CacheManager $cache, string $code, bool $fresh = false)
    {
        parent::__construct($cache, $fresh);

        $this->code = Code::rppo($code);
    }

    public function overview(): Rppo
    {
        return Rppo::fromArray($this->fetch('overview'));
    }

    /**
     * @return Collection<int, CountryCategorization>
     */
    public function categorization(): Collection
    {
        return $this->collect($this->fetch('categorization'), CountryCategorization::fromArray(...));
    }

    public function forget(): int
    {
        return $this->cache->forgetSubject($this->code);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function fetch(string $segment): array
    {
        return $this->get(
            sprintf('/rppo/%s/%s', $this->code, $segment),
            'rppo.'.$segment,
            $this->code,
        );
    }
}
