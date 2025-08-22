<?php

use App\Livewire\FeedbackIndex;
use App\Livewire\FeedbackShow;
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

    // Excel download
    Route::get('/download-excel/{filename}', function ($filename) {
        $path = 'temp/' . $filename;
        if (!Storage::exists($path)) {
            abort(404);
        }
        return response()
            ->download(storage_path('app/private/' . $path))
            ->deleteFileAfterSend(true);
    })->name('download-excel');

    // Feedback
    Route::get('/feedback', FeedbackIndex::class)->name('feedback.index');
    Route::get('/feedback/{feedback}', FeedbackShow::class)->name('feedback.show');

    // Private file download for feedback attachments (index = array index)
    Route::get('/feedback/{feedback}/file/{index}', function (\App\Models\Feedback $feedback, int $index) {
        $paths = $feedback->attachments ?? [];
        abort_unless(isset($paths[$index]), 404);
        // Add authorization here if needed
        return response()->file(storage_path('app/private/' . $paths[$index]));
    })->name('feedback.file');

    // Settings (Volt)
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__ . '/auth.php';
