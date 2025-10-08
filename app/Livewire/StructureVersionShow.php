<?php
// app/Livewire/StructureVersionShow.php
namespace App\Livewire;

use App\Models\OrganizationStructure;
use App\Models\OrganizationStructureVersion;
use Livewire\Component;

class StructureVersionShow extends Component
{
    public OrganizationStructure $tree;
    public OrganizationStructureVersion $version;

    public array $snapshot = [];
    public string $title = '';

    public function mount(OrganizationStructure $tree, OrganizationStructureVersion $version)
    {
        abort_if($version->organization_structure_id !== $tree->id, 404);

        $this->tree = $tree;
        $this->version = $version;

        $this->snapshot = $version->data ?? [];
        $this->title    = $version->title ?? $tree->title ?? '';
    }

    public function render()
    {
        return view('livewire.version-show', [
            'editable' => false,           // force read-only in blade
            'tree'     => $this->snapshot, // reuse partials
            'title'    => $this->title,
            'version'  => $this->version,
            'model'    => $this->tree,     // for header badges if you want
        ]);
    }
}
