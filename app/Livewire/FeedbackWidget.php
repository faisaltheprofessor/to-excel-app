<?php

namespace App\Livewire;

use App\Models\Feedback;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class FeedbackWidget extends Component
{
    use WithFileUploads;

    /** bug | suggestion */
    public string $type = 'bug';

    /** Titel + Nachricht */
    public string $title = '';
    public string $message = '';

    /** Priorität (low|normal|high|urgent) */
    public string $priority = 'normal';

    /** Uploads */
    #[Validate([
        'uploads.*' => 'file|mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm,pdf,doc,docx,xls,xlsx|max:102400'
    ])]
    public array $uploads = [];

    public bool $submitting = false;

    protected function rules(): array
    {
        return [
            'type'      => 'required|in:bug,suggestion',
            'title'     => 'required|string|min:3|max:200',
            'message'   => 'required|string|min:5|max:5000',
            'priority'  => 'required|in:low,normal,high,urgent',
            'uploads.*' => 'file|mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm,pdf,doc,docx,xls,xlsx|max:102400',
        ];
    }

    protected function messages(): array
    {
        return [
            'type.required'     => 'Bitte wählen Sie einen Typ.',
            'title.required'    => 'Bitte geben Sie einen Titel ein.',
            'message.required'  => 'Bitte beschreiben Sie Ihr Anliegen.',
            'priority.required' => 'Bitte wählen Sie eine Priorität.',
            'uploads.*.mimes'   => 'Nur Bilder, Videos, PDF, Word oder Excel erlaubt.',
            'uploads.*.max'     => 'Datei ist zu groß.',
        ];
    }

    public function updatedUploads(): void
    {
        $this->validateOnly('uploads.*');

        if (count($this->uploads) > 5) {
            $this->addError('uploads', 'Maximal 5 Dateien erlaubt.');
            $this->uploads = array_slice($this->uploads, 0, 5);
        }

        $this->validateUploads();
    }

    private function validateUploads(): void
    {
        $maxImage  = 10 * 1024 * 1024;
        $maxPdf    = 20 * 1024 * 1024;
        $maxOffice = 20 * 1024 * 1024;
        $maxVideo  = 100 * 1024 * 1024;

        foreach ($this->uploads as $i => $file) {
            $mime = $file->getMimeType() ?? '';
            $size = $file->getSize() ?? 0;

            if (str_starts_with($mime, 'image/') && $size > $maxImage) {
                $this->addError("uploads.$i", 'Bild zu groß (max. 10 MB).');
                unset($this->uploads[$i]);
            } elseif (str_starts_with($mime, 'video/') && $size > $maxVideo) {
                $this->addError("uploads.$i", 'Video zu groß (max. 100 MB).');
                unset($this->uploads[$i]);
            } elseif ($mime === 'application/pdf' && $size > $maxPdf) {
                $this->addError("uploads.$i", 'PDF zu groß (max. 20 MB).');
                unset($this->uploads[$i]);
            } elseif (in_array($mime, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ], true) && $size > $maxOffice) {
                $this->addError("uploads.$i", 'Office-Dokument zu groß (max. 20 MB).');
                unset($this->uploads[$i]);
            }
        }

        $this->uploads = array_values($this->uploads);
    }

    public function removeUpload(int $index): void
    {
        if (isset($this->uploads[$index])) {
            unset($this->uploads[$index]);
            $this->uploads = array_values($this->uploads);
        }
    }

    public function submit(): void
    {
        $this->submitting = true;

        $this->validate();
        $this->validateUploads();

        $stored = [];
foreach ($this->uploads as $file) {
    $folder = 'feedback/' . now()->format('Y/m/d');
    $ext    = $file->getClientOriginalExtension();
    $name   = uniqid('', true) . '.' . $ext;

    // Store on the public disk
    $path = $file->storeAs($folder, $name, 'public');

    $stored[] = [
        'path' => $path,                                      // relative path in storage
        'url'  => Storage::disk('public')->url($path),        // ✅ public URL for <img>/<video>
        'mime' => $file->getMimeType(),
        'name' => $file->getClientOriginalName(),
        'size' => $file->getSize(),
    ];
}

        Feedback::create([
            'user_id'     => Auth::id(),
            'type'        => $this->type,
            'title'       => $this->title,
            'message'     => $this->message,
            'priority'    => $this->priority,
            'url'         => Request::fullUrl() ?: null,
            'user_agent'  => Request::userAgent() ?: null,
            'attachments' => $stored,
        ]);

        $this->reset(['title', 'message', 'uploads']);
        $this->submitting = false;

        $this->dispatch('feedback-sent');
    }

    public function render()
    {
        return view('livewire.feedback-widget');
    }

    public function searchMentions(string $q = ''): array
    {
    return \App\Models\User::query()
        ->when($q !== '', fn($qq) => $qq->where('name', 'like', $q.'%'))
        ->orderBy('name')
        ->limit(8)
        ->get(['id','name','email'])
        ->map(fn($u) => ['id'=>$u->id, 'name'=>$u->name, 'email'=>$u->email])
        ->all();
}
}
