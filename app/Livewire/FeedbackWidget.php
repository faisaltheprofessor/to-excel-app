<?php

namespace App\Livewire;

use App\Models\Feedback;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class FeedbackWidget extends Component
{
    use WithFileUploads;

    /** bug | suggestion | question */
    public string $type = 'bug';

    /** Nachrichtentext */
    public string $message = '';

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    #[Validate(['uploads.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm'])]
    public array $uploads = [];

    public bool $submitting = false;

    protected function rules(): array
    {
        return [
            'type'       => 'required|in:bug,suggestion,question',
            'message'    => 'required|string|min:5|max:5000',
            'uploads.*'  => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm',
        ];
    }

    protected function messages(): array
    {
        return [
            'type.required'      => 'Bitte wählen Sie einen Typ.',
            'type.in'            => 'Ungültiger Typ.',
            'message.required'   => 'Bitte beschreiben Sie Ihr Anliegen.',
            'message.min'        => 'Die Nachricht ist zu kurz.',
            'message.max'        => 'Die Nachricht ist zu lang.',
            'uploads.*.file'     => 'Ungültige Datei.',
            'uploads.*.max'      => 'Datei zu groß (max. 10 MB pro Datei).',
            'uploads.*.mimes'    => 'Nur Bilder/Videos sind erlaubt.',
        ];
    }

    public function updatedUploads(): void
    {
        $this->validateOnly('uploads.*');

        // Max 5 Dateien insgesamt
        if (count($this->uploads) > 5) {
            $this->addError('uploads', 'Maximal 5 Dateien erlaubt.');
            $this->uploads = array_slice($this->uploads, 0, 5);
        }
    }

    public function submit(): void
    {
        $this->submitting = true;
        $validated = $this->validate();

        // Speichern der Dateien (private Disk)
        $stored = [];
        foreach ($this->uploads as $file) {
            $stored[] = $file->store('feedback/'.now()->format('Y/m/d'), 'public');
        }

        // Persist Feedback
        Feedback::create([
            'user_id'     => Auth::id(),
            'type'        => $this->type,
            'message'     => $this->message,
            'url'         => Request::fullUrl() ?: null,
            'user_agent'  => Request::userAgent() ?: null,
            'attachments' => $stored,
        ]);

        // Eingaben zurücksetzen (Typ beibehalten)
        $this->reset(['message', 'uploads']);
        $this->submitting = false;

        // Browser-Event -> öffnet Erfolgsmodal & schließt Popover (siehe Blade)
        $this->dispatch('feedback-sent');
    }

    public function render()
    {
        return view('livewire.feedback-widget');
    }
}
