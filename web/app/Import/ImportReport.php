<?php

namespace App\Import;

/**
 * Resultado de una importación (simulada o real).
 */
final class ImportReport
{
    /** @var list<string> */
    public array $created = [];

    /** @var array<string, array<string, array{0: string, 1: string}>> receta => campo => [antes, después] */
    public array $updated = [];

    public int $unchanged = 0;

    /** @var array<string, array<string, array{0: string, 1: string}>> receta => campo => [actual, origen] */
    public array $locked = [];

    /** @var list<string> */
    public array $missing = [];

    /** @var list<string> */
    public array $reappeared = [];

    /** @var list<string> recetas borradas en la app: se ignoran y no se recrean */
    public array $skippedDeleted = [];

    public int $needsReview = 0;

    public int $photosAdded = 0;

    /** @var array<string, int> "campo: motivo" => recetas */
    public array $reviewReasons = [];

    /** @var array<string, int> texto original => recetas */
    public array $unmappedSimulations = [];

    /** @var list<string> */
    public array $newSensors = [];

    public ?string $backup = null;

    public bool $written = false;

    public function lockedFieldCount(): int
    {
        return array_sum(array_map(count(...), $this->locked));
    }
}
