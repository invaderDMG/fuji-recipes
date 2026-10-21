<?php

use App\Http\Controllers\Api\RecipeController;
use Illuminate\Support\Facades\Route;

Route::get('recipes', [RecipeController::class, 'index'])->name('api.recipes.index');
Route::post('recipes', [RecipeController::class, 'store'])->name('api.recipes.store');
Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('api.recipes.show');
Route::get('recipes/{recipe}/similar', [RecipeController::class, 'similar'])->name('api.recipes.similar');
