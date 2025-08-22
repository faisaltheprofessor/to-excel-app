<?php

namespace App\Livewire;

use App\Models\OrganizationStructure;
use Livewire\Component;

class NewStructure extends Component
{
    public string $title = '';

    public function create()
    {
        // Build a candidate (don’t overwrite input yet)
        $candidate = $this->translitUmlauts(
            $this->normalizeTitle($this->title)
        );

        // 1) Windows-like validation
        if ($candidate === '') {
            $this->addError('title', 'Name darf nicht leer sein.');
            return;
        }
        if ($reason = $this->invalidNameReason($candidate)) {
            $this->addError('title', $reason);
            return;
        }

        // 2) Case-insensitive uniqueness (on candidate)
        $exists = OrganizationStructure::query()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($candidate)])
            ->exists();

        if ($exists) {
            $this->addError('title', 'Name ist bereits vergeben (Groß-/Kleinschreibung unbeachtet).');
            return;
        }

        // 3) Create with transliterated + normalized title
        $draft = OrganizationStructure::create([
            'title' => $candidate,
            'data'  => [],
        ]);

        // Optionally reflect the cleaned value in the UI (not required; we redirect anyway)
        // $this->title = $candidate;

        return $this->redirectRoute('importer.edit', $draft->id);
    }

    public function render()
    {
        return view('livewire.new-structure');
    }

    /** Trim + collapse inner whitespace to a single space */
    protected function normalizeTitle(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s ?? '');
        return trim($s);
    }

    /** German umlauts -> ASCII */
    protected function translitUmlauts(string $s): string
    {
        $map = [
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'ß' => 'ss',
        ];
        return strtr($s, $map);
    }

    /** Windows-like name validation */
    protected function invalidNameReason(string $name): ?string
    {
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
