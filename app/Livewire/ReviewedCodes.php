<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\CodeReviewEntry;

class ReviewedCodes extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

    // pentru modalul de detalii
    public ?int $selectedId = null;
    public ?string $selectedFile = null;
    public ?string $selectedMime = null;
    public ?string $selectedCode = null;
    public ?string $selectedReview = null;
    public bool $showModal = false;

    protected $updatesQueryString = ['search'];

    protected $listeners = [
        // când NewCommit salvează, putem reîmprospăta lista
        'new-commit-saved' => '$refresh',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $items = CodeReviewEntry::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('file_name', 'like', '%' . $this->search . '%')
                       ->orWhere('code', 'like', '%' . $this->search . '%')
                       ->orWhere('review', 'like', '%' . $this->search . '%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        return view('livewire.reviewed-codes', [
            'items' => $items,
        ]);
    }

    public function show(int $id): void
    {
        $entry = CodeReviewEntry::findOrFail($id);

        $this->selectedId     = $entry->id;
        $this->selectedFile   = $entry->file_name ?? '(fișier fără nume)';
        $this->selectedMime   = $entry->mime_type ?? '-';
        $this->selectedCode   = $entry->code;
        $this->selectedReview = $entry->review;
        $this->showModal      = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        CodeReviewEntry::whereKey($id)->delete();

        if ($this->selectedId === $id) {
            $this->closeModal();
        }
    }

    // === nou: butonul care deschide componenta NewCommit ===
    public function openNewCommit(int $id): void
    {
        $this->dispatch('open-new-commit', id: $id);
    }
}
