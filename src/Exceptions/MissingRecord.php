<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Exceptions;

/**
 * Marks an exception that means "EPPO holds no such record".
 *
 * The HTTP status alone will not tell you this. A well-formed EPPO code that
 * does not exist answers 400 `{"code":400,"error":"Bad request"}`, not 404 —
 * verified against the live API, 2026-08-29. Only the Reporting Service
 * endpoints answer 404. Since this package validates code shape before it
 * sends anything, a 400 that comes back can only mean the record is absent,
 * so both statuses carry this marker.
 */
interface MissingRecord extends \Throwable {}
