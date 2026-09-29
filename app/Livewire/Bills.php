<?php

namespace App\Livewire;

use App\Demo\BillingDataset;
use Livewire\Attributes\Url;
use Livewire\Component;

class Bills extends Component
{
    #[Url]
    public string $scope = 'all';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    public function render()
    {
        $rows = app(BillingDataset::class)->statements();
        $search = mb_strtolower(trim($this->search));
        $filtered = $rows->filter(fn ($row) => ($this->scope === 'all' || $row['property'] === $this->scope) &&
            ($this->status === 'all' || $row['status'] === $this->status) &&
            ($search === '' || str_contains(mb_strtolower($row['account'].' '.$row['vendor'].' '.$row['property']), $search))
        );

        return view('livewire.bills', [
            'rows' => $filtered, 'total' => $rows->count(), 'properties' => $rows->pluck('property')->unique(),
        ])->layout('layouts.app');
    }
}
