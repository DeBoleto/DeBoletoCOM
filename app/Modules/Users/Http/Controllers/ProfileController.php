<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Checkout\Services\PaymentProcessor;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Users\Http\Requests\UpdateAccountRequest;
use App\Modules\Users\Models\Usuarios;
use App\Modules\Users\Services\DigitalTickets;
use App\Modules\Users\Services\Wallet\AppleWalletPass;
use App\Modules\Users\Services\Wallet\GoogleWalletPass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Perfil del cliente del sitio (perfil/* de deboleto): sus compras, el boleto
 * digital, sus datos y sus tarjetas guardadas.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly DigitalTickets $tickets,
        private readonly AppleWalletPass $apple,
        private readonly GoogleWalletPass $google,
    ) {}

    public function purchases(): Response
    {
        return Inertia::render('Profile/Purchases', [
            'purchases' => $this->tickets->purchases($this->customer()),
        ]);
    }

    public function ticket(string $codigo): Response|RedirectResponse
    {
        $purchase = $this->tickets->ownedBy($this->customer(), $codigo);
        abort_if($purchase === null, 404);

        return $this->renderPass($purchase, owner: true);
    }

    /**
     * Enlace que se manda por WhatsApp y correo: el código de la compra es
     * aleatorio y basta para ver los boletos, como en deboleto.
     */
    public function sharedTicket(string $codigo): Response|RedirectResponse
    {
        $purchase = $this->tickets->byCode($codigo);
        abort_if($purchase === null, 404);

        $customer = Auth::guard('customer')->user();

        if ($customer instanceof Usuarios && $purchase->pk_usuario === $customer->pk_usuario) {
            return redirect()->route('profile.ticket', $codigo);
        }

        return $this->renderPass($purchase, owner: false);
    }

    public function qr(string $codigo, int $boleto): JsonResponse
    {
        $purchase = $this->tickets->byCode($codigo);
        abort_unless($purchase !== null && $this->tickets->unavailable($purchase) === null && $this->tickets->owns($purchase, $boleto), 404);

        return response()->json([
            'qr' => $this->tickets->dynamicQr($boleto),
            'expiresIn' => (int) config('profile.dynamic_qr_seconds'),
        ])->header('Cache-Control', 'no-store');
    }

    public function scans(string $codigo): JsonResponse
    {
        $purchase = $this->tickets->byCode($codigo);
        abort_if($purchase === null, 404);

        return response()->json(['scanned' => $this->tickets->scanned($purchase)])->header('Cache-Control', 'no-store');
    }

    public function appleWallet(string $codigo): HttpResponse
    {
        abort_unless($this->apple->enabled(), 404);
        $purchase = $this->walletPurchase($codigo);

        try {
            $file = $this->apple->make($this->tickets->pass($purchase));
        } catch (RuntimeException $exception) {
            Log::error('No se pudo generar el pase de Apple Wallet.', ['compra' => $purchase->pk_evento_compra, 'error' => $exception->getMessage()]);
            abort(503, 'No pudimos generar el pase de Apple Wallet. Inténtalo más tarde.');
        }

        return response($file['content'], 200, [
            'Content-Type' => $file['type'],
            'Content-Disposition' => 'attachment; filename="'.$file['name'].'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function googleWallet(string $codigo): RedirectResponse
    {
        abort_unless($this->google->enabled(), 404);
        $purchase = $this->walletPurchase($codigo);

        try {
            $url = $this->google->saveUrl($this->tickets->pass($purchase), (int) $purchase->pk_evento_funcion);
        } catch (RuntimeException $exception) {
            Log::error('No se pudo generar el pase de Google Wallet.', ['compra' => $purchase->pk_evento_compra, 'error' => $exception->getMessage()]);
            abort(503, 'No pudimos generar el pase de Google Wallet. Inténtalo más tarde.');
        }

        return redirect()->away($url);
    }

    public function pdf(TicketPrintService $printing, string $codigo): HttpResponse
    {
        $purchase = $this->tickets->ownedBy($this->customer(), $codigo);
        abort_unless($purchase !== null && $purchase->status() === PurchaseStatus::Paid, 404);

        return $printing->digitalDocument($purchase)->download('deboleto-compra-'.$purchase->pk_evento_compra.'.pdf');
    }

    public function account(): Response
    {
        $customer = $this->customer();

        return Inertia::render('Profile/Account', [
            'account' => [
                'nombre' => (string) $customer->nombre,
                'apellido_paterno' => (string) $customer->apellido_paterno,
                'apellido_materno' => (string) $customer->apellido_materno,
                'correo' => (string) $customer->correo,
                'telefono' => (string) $customer->telefono,
                'nacimiento_fecha' => $customer->getAttribute('nacimiento_fecha')
                    ? Carbon::parse($customer->getAttribute('nacimiento_fecha'))->toDateString()
                    : '',
            ],
        ]);
    }

    public function updateAccount(UpdateAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['apellido_materno'] = (string) ($data['apellido_materno'] ?? '');

        $this->customer()->forceFill($data)->save();

        return back()->with('success', 'Tus datos se actualizaron.');
    }

    public function cards(PaymentProcessor $payments): Response
    {
        return Inertia::render('Profile/Cards', [
            'cards' => $payments->savedCards($this->customer()),
        ]);
    }

    public function deleteCard(PaymentGateway $gateway, string $card): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($customer->pk_customer, 404);

        try {
            $gateway->deleteCard($customer->pk_customer, $card);
        } catch (PaymentException) {
            return back()->with('error', 'No pudimos eliminar la tarjeta. Inténtalo de nuevo.');
        }

        return back()->with('success', 'La tarjeta se eliminó.');
    }

    private function renderPass(EventosCompras $purchase, bool $owner): Response|RedirectResponse
    {
        if (($reason = $this->tickets->unavailable($purchase)) !== null) {
            return $owner
                ? redirect()->route('profile.purchases')->with('error', $reason)
                : redirect()->route('home')->with('error', $reason);
        }

        return Inertia::render('Profile/Ticket', $this->tickets->pass($purchase) + [
            'owner' => $owner,
            'qrSeconds' => (int) config('profile.dynamic_qr_seconds'),
            'scanPollSeconds' => (int) config('profile.scan_poll_seconds'),
            'wallet' => [
                'apple' => $this->apple->enabled() ? route('ticket.wallet.apple', $purchase->codigo) : null,
                'google' => $this->google->enabled() ? route('ticket.wallet.google', $purchase->codigo) : null,
            ],
        ]);
    }

    private function walletPurchase(string $codigo): EventosCompras
    {
        $purchase = $this->tickets->byCode($codigo);
        abort_unless($purchase !== null && $this->tickets->unavailable($purchase) === null, 404);

        return $purchase;
    }

    private function customer(): Usuarios
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer instanceof Usuarios, 401);

        return $customer;
    }
}
