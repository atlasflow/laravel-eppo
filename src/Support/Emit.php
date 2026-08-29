<?php

declare(strict_types=1);

namespace Atlasflow\Eppo\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;

/**
 * Dispatches package events through whatever dispatcher is bound *now*.
 *
 * Holding a dispatcher instance from construction time would make the package's
 * events invisible to `Event::fake()` in application tests, because faking
 * swaps the container binding and not the captured object.
 */
final class Emit
{
    public static function event(object $event, ?Dispatcher $using = null): void
    {
        if ($using !== null) {
            $using->dispatch($event);

            return;
        }

        $app = Facade::getFacadeApplication();

        if ($app === null || ! $app->bound('events')) {
            return;
        }

        $app->make('events')->dispatch($event);
    }
}
