<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;

/**
 * La regla "boolean" no acepta "true"/"false" en la query string: los convierte a 1/0.
 */
final class QueryBooleans
{
    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function normalize(Request $request, array $keys): array
    {
        return collect($keys)
            ->filter(fn (string $key) => in_array($request->query($key), ['true', 'false'], true))
            ->mapWithKeys(fn (string $key) => [$key => $request->query($key) === 'true' ? '1' : '0'])
            ->all();
    }
}
