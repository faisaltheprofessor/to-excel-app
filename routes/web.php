<?php

use App\Http\Controllers\TreeQuickExcelExportController;
use App\Livewire\FeedbackIndex;
use App\Livewire\FeedbackShow;
use App\Livewire\FeedbackTrash;
use App\Livewire\Importer;
use App\Livewire\TreeEditor;
use App\Livewire\TreeIndex;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

Route::get('/', fn () => redirect('/importer'))->name('home');

Route::middleware(['auth'])->group(function () {
    // Importer
    Route::get('/importer', TreeIndex::class)->name('importer.index');
    Route::get('/importer/create', Importer::class)->name('importer.create');
    Route::get('/importer/{tree}', TreeEditor::class)->name('importer.edit');

    Route::get('/feedback/kanban', \App\Livewire\FeedbackKanban::class)
        ->name('feedback.kanban');

    Route::get('/download-excel/{filename}', function ($filename) {
        $relativePath = 'temp/' . $filename;

        if (! Storage::exists($relativePath)) {
            abort(404, 'Datei nicht gefunden');
        }

        $absolutePath = Storage::path($relativePath);

        return response()
            ->download($absolutePath)
            ->deleteFileAfterSend(true);
    })->name('download-excel');


    // Feedback
    Route::get('/feedback',       FeedbackIndex::class)->name('feedback.index');
    Route::get('/feedback/trash', FeedbackTrash::class)->name('feedback.trash');
    Route::get('/feedback/{feedback}', FeedbackShow::class)->name('feedback.show');

    Route::get('/feedback/{feedback}/file/{index}', function (\App\Models\Feedback $feedback, int $index) {
        $paths = $feedback->attachments ?? [];
        abort_unless(isset($paths[$index]), 404);

        return response()->file(storage_path('app/private/' . $paths[$index]));
    })->name('feedback.file');

    Route::get('/importer/{tree}/export', TreeQuickExcelExportController::class)
        ->name('importer.export');

    // Settings (Volt)
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__ . '/auth.php';
