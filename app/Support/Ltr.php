<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Phone numbers, codes and similar values must read left-to-right inside an
 * Arabic page ("+1 555 0400", not "0400 555 1+"). <bdi dir="ltr"> isolates the
 * value without moving it out of its right-aligned place.
 */
final class Ltr
{
    public static function wrap(?string $value): ?HtmlString
    {
        return blank($value) ? null : new HtmlString('<bdi dir="ltr">'.e($value).'</bdi>');
    }
}
