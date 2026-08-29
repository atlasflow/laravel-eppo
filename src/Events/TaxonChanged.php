<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Events;

use Atlasflow\Eppo\Data\TaxonListItem;

/**
 * `eppo:sync` saw EPPO report this code as created or updated. Listen for this
 * to keep your own tables in step with the upstream database.
 */
final readonly class TaxonChanged
{
    public function __construct(
        public TaxonListItem $taxon,
        public int $invalidatedEntries,
    ) {}
}
