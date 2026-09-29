<?php

namespace App\Livewire;

use App\Demo\BillingDataset;
use App\Demo\ReviewStatement;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BillDetail extends Component
{
    #[Locked]
    public int $expectedBill;

    #[Locked]
    public int $version = 0;

    public string $note = '';

    public string $message = '';

    public function mount(int $expectedBill): void
    {
        $this->expectedBill = $expectedBill;
        $this->version = $this->bill()['version'] ?? 0;
    }

    public function decide(string $decision): void
    {
        $this->resetErrorBag();
        $this->message = '';
        app(ReviewStatement::class)->record($this->expectedBill, $this->version, $decision, $this->note);
        $this->version++;
        $this->note = '';
        $this->message = $decision === 'verify' ? 'Demo review saved. The dashboard now includes this statement as verified.' : 'Review reopened. This statement is back in the dashboard review queue.';
    }

    private function bill(): array
    {
        $row = app(BillingDataset::class)->statements()->firstWhere('id', $this->expectedBill);
        abort_unless($row, 404);

        return $row;
    }

    public function render()
    {
        $bill = $this->bill();

        return view('livewire.bill-detail', [
            'bill' => $bill,
            'history' => DB::table('demo_review_decisions')->where('statement_id', $bill['statement_id'])->orderByDesc('version')->get(),
        ])->layout('layouts.app');
    }
}
