<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * Devuelve [id => label] para selects (Filament, etc.).
     * Si el enum no define label(), usa el nombre del case.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [
                $c->value => method_exists($c, 'label') ? $c->label() : $c->name,
            ])
            ->all();
    }

    /**
     * Solo los valores (ids) del enum.
     *
     * @return array<int, int|string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Solo los labels del enum (si no hay label(), usa el nombre).
     *
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return array_map(
            fn (self $c) => method_exists($c, 'label') ? $c->label() : $c->name,
            self::cases()
        );
    }
}
