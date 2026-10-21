<?php

namespace App\Capture;

/**
 * Lo que se ha leído de una captura: la receta ya normalizada como atributos
 * del modelo Recipe, los campos dudosos y el texto del OCR.
 */
final class CaptureResult
{
    /**
     * @param  array<string, mixed>|null  $attributes  null si no se reconoce una receta
     * @param  array<string, array{reason: string, raw: ?string, suggestion: ?string}>  $reviewIssues
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly ?array $attributes,
        public readonly array $reviewIssues,
        public readonly array $lines,
    ) {}

    public function recognized(): bool
    {
        return $this->attributes !== null;
    }
}
