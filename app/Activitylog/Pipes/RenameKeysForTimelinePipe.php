<?php

namespace App\Activitylog\Pipes;

use Closure;
use Illuminate\Support\Arr;
use Spatie\Activitylog\Contracts\LoggablePipe;
use Spatie\Activitylog\EventLogBag;

class RenameKeysForTimelinePipe implements LoggablePipe
{
    /**
     * @param array<string,string> $map  ['dealer.name' => 'Comerciante', ...]
     */
    public function __construct(protected array $map)
    {
    }

    public function handle(EventLogBag $event, Closure $next): EventLogBag
    {
        // Trabajamos sobre una copia y luego reasignamos $event->changes
        $changes = $event->changes;

        foreach ($this->map as $from => $to) {
            foreach (['attributes', 'old'] as $bag) {
                if (! isset($changes[$bag]) || ! is_array($changes[$bag])) {
                    continue;
                }

                // Clave literal, sin interpretación de "dot"
                if (array_key_exists($from, $changes[$bag])) {
                    $value = $changes[$bag][$from];
                    unset($changes[$bag][$from]);
                    $changes[$bag][$to] = $value;
                }
            }
        }

        $event->changes = $changes;

        return $next($event);
    }
}
