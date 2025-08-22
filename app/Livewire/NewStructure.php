<?php

namespace App\Livewire;

use App\Models\OrganizationStructure;
use Livewire\Component;

class NewStructure extends Component
{
    public string $title = '';

    public function create()
    {
        // 0) Normalize: trim + collapse multiple spaces
        $normalized = $this->normalizeTitle($this->title);
        $this->title = $normalized; // reflect back to UI

        // 1) Windows-like validation
        if ($reason = $this->invalidNameReason($normalized)) {
            $this->addError('title', $reason);
            return;
        }

        // 2) Case-insensitive uniqueness (on normalized title)
        $exists = OrganizationStructure::query()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($normalized)])
            ->exists();

        if ($exists) {
            $this->addError('title', 'Name ist bereits vergeben');
            return;
        }

        // 3) Create
        $draft = OrganizationStructure::create([
            'title' => $normalized,
            'data'  => [],
        ]);

        return $this->redirectRoute('importer.edit', $draft->id);
    }

    public function render()
    {
        return view('livewire.new-structure');
    }

    /** Trim + collapse inner whitespace to a single space */
    protected function normalizeTitle(string $s): string
    {
        // Convert all whitespace runs (tabs, newlines, multi-spaces) to single space
        $s = preg_replace('/\s+/u', ' ', $s ?? '');
        // Trim leading/trailing spaces
        $s = trim($s);
        return $s;
    }

    /** Windows-like name validation */
    protected function invalidNameReason(string $name): ?string
    {
        if ($name === '') return 'Name darf nicht leer sein.';
        if (mb_strlen($name) > 255) return 'Name darf höchstens 255 Zeichen lang sein.';
        if ($name === '.' || $name === '..') return 'Name darf nicht "." oder ".." sein.';
        if (preg_match('/[<>:"\/\\\\|?*]/u', $name) || preg_match('/[\x00-\x1F]/u', $name)) {
            return 'Ungültige Zeichen: < > : " / \\ | ? * oder Steuerzeichen sind nicht erlaubt.';
        }
        if (preg_match('/[ \.]$/u', $name)) {
            return 'Name darf nicht mit einem Punkt oder Leerzeichen enden.';
        }
        $upper = mb_strtoupper(rtrim($name, " ."));
        $reserved = ['CON','PRN','AUX','NUL',
            'COM1','COM2','COM3','COM4','COM5','COM6','COM7','COM8','COM9',
            'LPT1','LPT2','LPT3','LPT4','LPT5','LPT6','LPT7','LPT8','LPT9'];
        if (in_array($upper, $reserved, true)) {
            return 'Name ist unter Windows reserviert (z. B. CON, PRN, AUX, NUL, COM1–COM9, LPT1–LPT9).';
        }
        if (substr_count($name, '-') > 3) {
            return 'Name darf höchstens drei Bindestriche (-) enthalten.';
        }
        return null;
    }
}
