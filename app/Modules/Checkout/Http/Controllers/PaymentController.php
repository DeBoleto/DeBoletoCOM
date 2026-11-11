<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Exceptions\SeatsUnavailableException;
use App\Modules\Checkout\Exceptions\CheckoutException;
use App\Modules\Checkout\Http\CheckoutPresenter;
use App\Modules\Checkout\Http\Requests\PayRequest;
use App\Modules\Checkout\Http\Requests\QuoteRequest;
use App\Modules\Checkout\Jobs\SendConversionEvent;
use App\Modules\Checkout\Services\PaymentProcessor;
use App\Modules\Checkout\Services\PendingPurchaseWriter;
use App\Modules\Checkout\Services\PurchaseFinalizer;
use App\Modules\Checkout\Services\QuoteCalculator;
use App\Modules\Checkout\Services\SelectionBuilder;
use App\Modules\Checkout\Services\WebCatalog;
use App\Modules\Checkout\Support\WebCart;
use App\Modules\Checkout\Support\WebCartStore;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Enums\PaymentType;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Paso de pago: resumen con opciones (meses, Ticket Defender, código), cobro
 * con tarjeta (3D Secure) o SPEI y regreso desde el banco.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly WebCatalog $catalog,
        private readonly WebCartStore $carts,
        private readonly QuoteCalculator $quotes,
    ) {}

    public function show(Request $request, PaymentProcessor $payments, PaymentGateway $gateway, SelectionBuilder $selections, int $evento): Response|RedirectResponse
    {
        $event = $this->catalog->event($evento);
        $cart = $this->carts->get($evento);
        $customer = $this->customer();

        if ($cart === null || $customer === null) {
            return $this->backToSeats($event, $cart, $customer === null ? null : 'Elige tus boletos para continuar.');
        }

        if ($cart->isExpired()) {
            return $this->backToSeats($event, $cart, 'Se terminó el tiempo para pagar y tus asientos se liberaron. Elígelos de nuevo.');
        }

        $eventRow = $this->catalog->eventRow($evento);
        $quote = $this->quotes->quote($cart, $eventRow);

        try {
            $selections->ensureCanPurchase($event, $customer);
            $blocked = null;
        } catch (CheckoutException $exception) {
            $blocked = $exception->getMessage();
        }

        return Inertia::render('Checkout/Payment', [
            'event' => CheckoutPresenter::event($event),
            'function' => CheckoutPresenter::function($this->catalog->openFunction($evento, $cart->selection->functionId)),
            'quote' => $quote->toArray(),
            'promotionText' => $cart->selection->promotionText,
            'options' => [
                'installments' => $this->quotes->installmentPlans($eventRow, $quote->total()),
                'defender' => $this->quotes->offersDefender($eventRow),
                'defenderRate' => (float) config('checkout.defender_rate'),
            ],
            'savedCards' => $payments->savedCards($customer),
            'openpay' => $gateway->publicConfig(),
            'heldUntil' => $cart->heldUntil,
            'transferHours' => (int) config('checkout.transfer_hours'),
            'whatsapp' => $customer->telefono,
            'blocked' => $blocked,
            'legal' => ['terms' => config('checkout.terms_url'), 'privacy' => config('checkout.privacy_url')],
            'seatsUrl' => route('checkout.process', ['slug' => $event->getAttribute('friendly_url'), 'function' => $cart->selection->functionId]),
        ]);
    }

    public function quote(QuoteRequest $request, int $evento): JsonResponse
    {
        $this->catalog->event($evento);
        $cart = $this->carts->get($evento);

        abort_if($cart === null || $this->customer() === null, 409, 'Tu selección expiró. Elige tus boletos de nuevo.');

        try {
            $quote = $this->quotes->quote($cart, $this->catalog->eventRow($evento), $request->options());
        } catch (CheckoutException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['cupon' => [$exception->getMessage()]]], 422);
        }

        return response()->json(['quote' => $quote->toArray()]);
    }

    public function pay(PayRequest $request, PaymentProcessor $payments, PurchaseFinalizer $finalizer, int $evento): HttpResponse
    {
        $event = $this->catalog->event($evento);
        $cart = $this->carts->get($evento);
        $customer = $this->customer();

        if ($cart === null || $customer === null || $cart->isExpired()) {
            return $this->backToSeats($event, $cart, 'Se terminó el tiempo para pagar. Elige tus boletos de nuevo.');
        }

        $lock = Cache::lock("checkout:pay:{$customer->pk_usuario}:{$evento}", 90);

        if (! $lock->get()) {
            return back()->with('error', 'Tu pago se está procesando. Espera un momento.');
        }

        try {
            if ($paid = $this->settlePreviousAttempt($request, $payments, $finalizer, $evento)) {
                return $paid;
            }

            $quote = $this->quotes->quote($cart, $this->catalog->eventRow($evento), $request->options());

            $result = $payments->pay(
                $cart, $quote, $event, $customer, $request->payment(), $this->carts->owner(), route('checkout.return'),
            );
        } catch (PaymentException|CheckoutException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (SeatsUnavailableException $exception) {
            $this->carts->forget($evento);

            return $this->backToSeats($event, null, $exception->getMessage());
        } finally {
            $lock->release();
        }

        $request->session()->put($this->attemptKey($evento), $result->purchase->codigo);

        if ($request->validated('metodo') === 'card') {
            SendConversionEvent::dispatch($evento, 'AddPaymentInfo', route('checkout.payment', $evento));
        }

        if ($result->needsRedirect()) {
            return Inertia::location((string) $result->redirectUrl);
        }

        $this->carts->forget($evento);

        return redirect()->route('checkout.complete', $result->purchase->codigo);
    }

    /**
     * El banco regresa aquí después del 3D Secure con ?id={cargo}. El estado se
     * consulta a la pasarela: el parámetro solo indica qué cargo revisar.
     */
    public function return(Request $request, PaymentProcessor $payments, PurchaseFinalizer $finalizer): RedirectResponse
    {
        $chargeId = (string) $request->query('id', '');
        abort_unless(preg_match('/^[A-Za-z0-9]{6,64}$/', $chargeId) === 1, 404);

        $purchase = EventosCompras::query()
            ->where('pk_openpay', $chargeId)
            ->where('pk_evento_compra_tipo', PaymentType::Web->value)
            ->first();

        abort_if($purchase === null || ($finalizer->snapshot($purchase)['source'] ?? null) !== PendingPurchaseWriter::SOURCE, 404);

        $customer = $this->customer();

        if ($customer !== null && $customer->pk_usuario !== $purchase->pk_usuario) {
            abort(404);
        }

        try {
            $charge = $payments->sync($purchase);
        } catch (PaymentException) {
            $charge = null;
        }

        if ($customer === null) {
            return redirect()->route('home')->with('success', 'Recibimos la respuesta de tu banco. Te enviaremos tus boletos por correo en cuanto se confirme el pago.');
        }

        if ($charge?->isPaid()) {
            $this->carts->forget($purchase->pk_evento);

            return redirect()->route('checkout.complete', $purchase->codigo);
        }

        if ($charge?->status->isFinalFailure() && $this->carts->get($purchase->pk_evento) !== null) {
            return redirect()->route('checkout.payment', $purchase->pk_evento)
                ->with('error', 'Tu banco no autorizó el pago. Revisa los datos o intenta con otra tarjeta.');
        }

        return redirect()->route('checkout.complete', $purchase->codigo);
    }

    /**
     * Si el cliente vuelve a pagar (por ejemplo, regresó del 3D Secure sin
     * terminar), el intento anterior se revisa: si ya se cobró se muestra; si
     * no, se libera para no bloquear sus propios asientos.
     */
    private function settlePreviousAttempt(Request $request, PaymentProcessor $payments, PurchaseFinalizer $finalizer, int $eventId): ?RedirectResponse
    {
        $code = $request->session()->pull($this->attemptKey($eventId));
        $previous = $code ? EventosCompras::query()->where('codigo', $code)->first() : null;

        if ($previous === null || $previous->status() !== PurchaseStatus::AwaitingPayment) {
            return null;
        }

        if ($previous->pk_openpay) {
            try {
                if ($payments->sync($previous)->isPaid()) {
                    $this->carts->forget($eventId);

                    return redirect()->route('checkout.complete', $previous->codigo);
                }
            } catch (PaymentException) {
                // Sin respuesta de la pasarela: se libera y la tarea programada concilia el cargo.
            }
        }

        $finalizer->release($previous);

        return null;
    }

    private function backToSeats(EventoVista $event, ?WebCart $cart, ?string $message): RedirectResponse
    {
        $redirect = redirect()->route('checkout.process', array_filter([
            'slug' => $event->getAttribute('friendly_url'),
            'function' => $cart?->selection->functionId,
        ]));

        return $message ? $redirect->with('error', $message) : $redirect;
    }

    private function attemptKey(int $eventId): string
    {
        return "checkout.web.attempt.{$eventId}";
    }

    private function customer(): ?Usuarios
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Usuarios ? $customer : null;
    }
}
