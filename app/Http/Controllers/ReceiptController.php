<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * 80 mm receipts (D18): printable HTML for thermal printers and a PDF copy.
 */
class ReceiptController extends Controller
{
    public function show(Request $request, int $sale): View
    {
        return view('receipts.sale', $this->data($request, $sale) + ['autoPrint' => $request->boolean('print')]);
    }

    public function pdf(Request $request, int $sale): Response
    {
        $data = $this->data($request, $sale);

        return Pdf::loadView('receipts.sale', $data + ['autoPrint' => false, 'pdf' => true])
            ->setPaper([0, 0, 226.77, 800], 'portrait')
            ->download($data['sale']->number.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, int $saleId): array
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->business_id !== null, 403);

        $sale = Sale::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $user->business_id)
            ->with(['items.modifiers', 'payments', 'employee', 'client', 'business', 'refunds'])
            ->findOrFail($saleId);

        return ['sale' => $sale, 'business' => $sale->business];
    }
}
