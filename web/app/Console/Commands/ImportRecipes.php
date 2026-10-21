<?php

namespace App\Console\Commands;

use App\Enums\RecipeSource;
use App\Import\ImportReport;
use App\Import\RecipeImporter;
use App\Import\SourceReader;
use Illuminate\Console\Command;
use RuntimeException;

class ImportRecipes extends Command
{
    protected $signature = 'recipes:import
        {--dry-run : Muestra el diff sin escribir (es lo que se hace por defecto)}
        {--force : Escribe los cambios (antes hace una copia de seguridad)}
        {--only= : Limita la importación a un origen: fxw o mine}';

    protected $description = 'Importa las recetas de fujixweekly.db (solo lectura) a la BD de la app';

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('force')) {
            $this->error('--dry-run y --force son incompatibles.');

            return self::FAILURE;
        }

        $only = $this->option('only');
        if ($only !== null && ! in_array($only, [RecipeSource::Fxw->value, RecipeSource::Mine->value], true)) {
            $this->error("--only debe ser fxw o mine (recibido: {$only}).");

            return self::FAILURE;
        }

        try {
            $reader = SourceReader::fromConfig();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $write = (bool) $this->option('force');
        $importer = new RecipeImporter($reader);
        $report = $importer->run($only === null ? null : RecipeSource::from($only), $write);

        $this->printDetails($report);
        $this->printSummary($report);

        if ($report->written) {
            $this->info("Cambios guardados. Copia de seguridad: {$report->backup}");
        } else {
            $this->warn('Simulación: no se ha escrito nada. Usa --force para aplicar los cambios.');
        }

        return self::SUCCESS;
    }

    private function printDetails(ImportReport $report): void
    {
        if ($this->output->isVerbose()) {
            foreach ($report->created as $label) {
                $this->line("<fg=green>+ {$label}</>");
            }
        }

        foreach ($report->updated as $label => $changes) {
            $this->line("<fg=yellow>~ {$label}</>");
            foreach ($changes as $field => [$old, $new]) {
                $this->line("    {$field}: {$old} → {$new}");
            }
        }

        foreach ($report->locked as $label => $fields) {
            $this->line("<fg=cyan>🔒 {$label}</>");
            foreach ($fields as $field => [$current, $source]) {
                $this->line("    {$field}: se mantiene {$current} (origen: {$source})");
            }
        }

        foreach ($report->missing as $label) {
            $this->line("<fg=red>- {$label}</>");
        }

        foreach ($report->reappeared as $label) {
            $this->line("<fg=green>↺ {$label}</>");
        }
    }

    private function printSummary(ImportReport $report): void
    {
        $this->newLine();
        $this->table(['Resumen', 'Recetas'], [
            ['Creadas', count($report->created)],
            ['Actualizadas', count($report->updated)],
            ['Sin cambios', $report->unchanged],
            ['Campos protegidos por bloqueo', $report->lockedFieldCount()],
            ['Desaparecidas del origen', count($report->missing)],
            ['Reaparecidas', count($report->reappeared)],
            ['Pendientes de revisión (needs_review)', $report->needsReview],
        ]);

        if ($report->reviewReasons !== []) {
            arsort($report->reviewReasons);
            $this->table(['Motivo de revisión', 'Recetas'], collect($report->reviewReasons)->map(fn ($n, $k) => [$k, $n])->values());
        }

        if ($report->unmappedSimulations !== []) {
            arsort($report->unmappedSimulations);
            $this->table(['Simulación sin mapear', 'Recetas'], collect($report->unmappedSimulations)->map(fn ($n, $k) => [$k, $n])->values());
        }

        if ($report->newSensors !== []) {
            $this->warn('Sensores nuevos (se crearán al importar): '.implode(', ', $report->newSensors));
        }
    }
}
