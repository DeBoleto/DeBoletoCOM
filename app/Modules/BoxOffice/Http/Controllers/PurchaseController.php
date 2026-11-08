<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Http\Requests\SendWhatsAppRequest;
use App\Modules\BoxOffice\Policies\BoxOfficeAbilities;
use App\Modules\BoxOffice\Services\Delivery\WhatsAppTicketSender;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega de una compra ya registrada: impresión física, PDF y WhatsApp.
 */
class PurchaseController extends Controller
{
    public function __construct(private readonly TicketPrintService $printing) {}

    public function print(Request $request, int $compra): View
    {
        return $this->printing->printView($this->purchase($compra), $request->user());
    }

    public function digital(Request $request, int $compra): Response
    {
        return $this->printing->digitalPdf($this->purchase($compra), $request->user());
    }

    public function whatsapp(SendWhatsAppRequest $request, WhatsAppTicketSender $sender, int $compra): JsonResponse
    {
        try {
            $sender->send($this->purchase($compra), $request->validated('telefono'), $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Los boletos se enviaron por WhatsApp.']);
    }

    private function purchase(int $purchaseId): EventosCompras
    {
        $purchase = EventosCompras::query()->findOrFail($purchaseId);

        Gate::authorize(BoxOfficeAbilities::PURCHASE, $purchase);

        return $purchase;
    }
}
