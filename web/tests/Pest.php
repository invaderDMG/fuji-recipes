<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Los tests del import usan una BD en fichero (para la copia de seguridad) sin transacción envolvente.
pest()->extend(TestCase::class)->in('Import');
