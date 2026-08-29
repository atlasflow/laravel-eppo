<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Atlasflow\Eppo\Eppo fresh()
 * @method static \Atlasflow\Eppo\Resources\TaxonResource taxon(string $eppocode)
 * @method static \Atlasflow\Eppo\Resources\TaxonsResource taxons()
 * @method static \Atlasflow\Eppo\Resources\CountryResource country(string $isoCode)
 * @method static \Atlasflow\Eppo\Resources\RppoResource rppo(string $rppoCode)
 * @method static \Atlasflow\Eppo\Resources\ReportingsResource reportings()
 * @method static \Atlasflow\Eppo\Resources\ReferencesResource references()
 * @method static \Atlasflow\Eppo\Resources\ToolsResource tools()
 * @method static \Atlasflow\Eppo\Data\Status status()
 * @method static array<array-key, mixed> raw(string $path, string $resource = 'raw', ?string $subject = null, array<string, scalar|null> $query = [])
 * @method static \Atlasflow\Eppo\Cache\CacheManager cache()
 *
 * @see \Atlasflow\Eppo\Eppo
 */
final class Eppo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Atlasflow\Eppo\Eppo::class;
    }
}
