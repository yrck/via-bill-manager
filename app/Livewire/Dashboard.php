<?php

namespace App\Livewire;

use App\Demo\BillingDataset;
use Illuminate\Support\Collection;
use Livewire\Component;

class Dashboard extends Component
{
    public string $scope = 'all';

    public string $filter = 'all';

    public string $mode = 'operations';

    public ?int $selected = null;

    public function updatedScope(): void
    {
        $this->selected = null;
    }

    public function setFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['all', 'missing', 'review', 'due']), 422);
        $this->filter = $filter;
        $this->selected = null;
    }

    public function inspect(int $id): void
    {
        abort_unless($this->scoped()->contains('id', $id), 404);
        $this->selected = $id;
    }

    private function scoped(): Collection
    {
        return app(BillingDataset::class)->statements()->filter(fn ($row) => $this->scope === 'all' || $row['property'] === $this->scope);
    }

    public function render()
    {
        $rows = $this->scoped();
        $work = $rows->filter(fn ($row) => in_array($row['status'], ['missing', 'review']) || $row['due_soon'])->filter(fn ($row) => match ($this->filter) {
            'missing', 'review' => $row['status'] === $this->filter,
            'due' => $row['due_soon'], default => true,
        })->sortBy(fn ($row) => $row['due_soon'] ? 0 : ($row['status'] === 'missing' ? 1 : 2));

        return view('livewire.dashboard', [
            'rows' => $rows, 'work' => $work, 'detail' => $rows->firstWhere('id', $this->selected),
            'properties' => app(BillingDataset::class)->statements()->pluck('property')->unique(),
            'missing' => $rows->where('status', 'missing')->count(), 'review' => $rows->where('status', 'review')->count(),
            'verified' => $rows->where('status', 'verified')->count(), 'received' => $rows->whereIn('status', ['verified', 'review'])->count(),
            'due' => $rows->where('due_soon', true)->count(), 'charges' => $rows->sum('cents') / 100,
        ])->layout('layouts.app');
    }
}
