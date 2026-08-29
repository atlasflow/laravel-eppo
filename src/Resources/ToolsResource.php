<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Resources;

use Atlasflow\Eppo\Data\NameToCode;
use Atlasflow\Eppo\Data\SearchResult;
use Atlasflow\Eppo\Exceptions\InvalidArgumentException;
use Illuminate\Support\Collection;

/**
 * Name → code lookup and free-text search.
 */
final class ToolsResource extends Resource
{
    public const MODE_WHOLE_WORD = 1;

    public const MODE_STARTS_WITH = 2;

    public const MODE_CONTAINS = 3;

    /**
     * Resolve a taxon name to its EPPO code(s).
     *
     * @return Collection<int, NameToCode>
     */
    public function nameToCodes(string $name, bool $onlyPreferred = true): Collection
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A name is required for a name-to-code lookup.');
        }

        return $this->collect(
            $this->get('/tools/name2codes', 'tools.name2codes', null, [
                'name' => $name,
                'onlyPreferred' => $onlyPreferred,
            ]),
            NameToCode::fromArray(...),
        );
    }

    /**
     * The first EPPO code for a name, or null when nothing matches.
     */
    public function codeFor(string $name, bool $onlyPreferred = true): ?string
    {
        return $this->nameToCodes($name, $onlyPreferred)->first()?->eppocode;
    }

    /**
     * Free-text search over names and codes. EPPO caps this at 10 results.
     *
     * @param  self::MODE_*  $mode
     * @return Collection<int, SearchResult>
     */
    public function search(string $keyword, int $mode = self::MODE_STARTS_WITH, bool $onlyPreferred = false): Collection
    {
        $keyword = trim($keyword);

        if (mb_strlen($keyword) < 3) {
            throw new InvalidArgumentException('EPPO search needs a keyword of at least 3 characters.');
        }

        return $this->collect(
            $this->get('/tools/search', 'tools.search', null, [
                'keyword' => mb_substr($keyword, 0, 200),
                'searchMode' => $mode,
                'onlyPreferred' => $onlyPreferred,
            ]),
            SearchResult::fromArray(...),
        );
    }
}
