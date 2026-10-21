<?php

namespace App\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Copia de seguridad de la BD de Laravel con VACUUM INTO (copia consistente
 * aunque haya transacciones o WAL), conservando las N más recientes.
 */
final class DatabaseBackup
{
    public function create(): string
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('La copia de seguridad solo admite SQLite.');
        }

        $dir = config('fuji.backups.path');
        File::ensureDirectoryExists($dir);

        $base = $dir.'/database-'.now()->format('Ymd-His');
        $path = "{$base}.sqlite";
        for ($i = 2; file_exists($path); $i++) {
            $path = "{$base}-{$i}.sqlite";
        }

        $connection->statement('VACUUM INTO ?', [$path]);

        if (! is_file($path)) {
            throw new RuntimeException("No se pudo crear la copia de seguridad en {$path}");
        }

        $this->prune();

        return $path;
    }

    private function prune(): void
    {
        $files = glob(config('fuji.backups.path').'/database-*.sqlite');
        rsort($files);

        foreach (array_slice($files, config('fuji.backups.keep')) as $old) {
            File::delete($old);
        }
    }
}
