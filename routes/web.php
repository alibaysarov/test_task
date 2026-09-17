<?php

use Illuminate\Support\Facades\Route;

Route::view('/docs', 'scribe.index')->name('scribe');

Route::get('/docs.postman', function () {
    return response(
        file_get_contents(storage_path('app/private/scribe/collection.json')),
        200,
        ['Content-Type' => 'application/json']
    );
})->name('scribe.postman');

Route::get('/docs.openapi', function () {
    return response()->file(storage_path('app/private/scribe/openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
})->name('scribe.openapi');
