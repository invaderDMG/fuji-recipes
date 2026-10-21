<?php

namespace App\Capture;

use App\Import\SourceRecipeMapper;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Lee una receta de una captura o de un texto con el OCR y el lector de
 * fxw_import.py (resources/python/recipe_from_capture.py) y la normaliza con
 * el mismo mapeador que la importación.
 */
final class CaptureReader
{
    public function __construct(private readonly SourceRecipeMapper $mapper = new SourceRecipeMapper) {}

    public function fromImage(string $path): CaptureResult
    {
        return $this->run($path);
    }

    public function fromText(string $text): CaptureResult
    {
        $path = tempnam(sys_get_temp_dir(), 'fxw-capture').'.txt';
        file_put_contents($path, $text);

        try {
            return $this->run($path);
        } finally {
            @unlink($path);
        }
    }

    private function run(string $path): CaptureResult
    {
        $config = config('fuji.capture');

        if (! is_executable($config['python'])) {
            throw new RuntimeException("No encuentro el Python del proyecto ({$config['python']}). Ejecuta: make setup-python");
        }

        $result = Process::timeout($config['timeout'])->run([
            $config['python'],
            resource_path('python/recipe_from_capture.py'),
            $config['scripts_dir'],
            $path,
        ]);

        if ($result->failed()) {
            $lines = array_filter(explode("\n", trim($result->errorOutput())));
            throw new RuntimeException('No se pudo leer la captura: '.(end($lines) ?: 'error desconocido'));
        }

        $data = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

        if ($data['recipe'] === null) {
            return new CaptureResult(null, [], $data['lines']);
        }

        // Misma forma que una fila de v_recipes para reutilizar el mapeador
        $row = (object) [
            ...array_fill_keys(['monochromatic_color', 'smooth_skin', 'tone_curve', 'd_range_priority',
                'post_title', 'published', 'image_url', 'raw_json'], null),
            ...$data['recipe'],
            'post_url' => 'capture:',
            'block_index' => 0,
            'name' => $data['name'] ?? '',
            'author' => $data['author'] === null ? null : ltrim($data['author'], '@ '),
        ];
        $mapped = $this->mapper->map($row, []);

        return new CaptureResult(
            [...$mapped->attributes, 'name' => $data['name']],
            $mapped->reviewIssues,
            $data['lines'],
        );
    }
}
