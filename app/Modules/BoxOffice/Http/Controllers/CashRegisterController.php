<?php

namespace App\Modules\BoxOffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoxOffice\Http\Requests\CloseCashRegisterRequest;
use App\Modules\BoxOffice\Http\Requests\OpenCashRegisterRequest;
use App\Modules\BoxOffice\Models\CajaApertura;
use App\Modules\BoxOffice\Services\CashRegister\CashRegister;
use App\Modules\BoxOffice\Services\CashRegister\CloseAuthorizer;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Apertura y cierre de la caja del cajero de taquilla.
 */
class CashRegisterController extends Controller
{
    public function __construct(private readonly CashRegister $registers) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->registers->current($request->user()) !== null) {
            return redirect()->route('boxoffice.cash.show');
        }

        return Inertia::render('BoxOffice/CashRegister/Open', [
            'cashier' => $this->person($request->user()),
            'lastClosed' => CajaApertura::query()
                ->activas()
                ->where('pk_usuario', $request->user()->pk_usuario)
                ->where('estatus', CajaApertura::STATUS_CLOSED)
                ->latest('cierre_fecha')
                ->value('pk_caja_apertura'),
        ]);
    }

    public function store(OpenCashRegisterRequest $request): RedirectResponse
    {
        $this->registers->open(
            $request->user(),
            (float) $request->validated('monto_apertura'),
            $request->validated('caja'),
            $request->validated('comentario'),
        );

        return redirect()->route('boxoffice.tickets.index')->with('success', 'Caja abierta. Ya puedes vender boletos.');
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $register = $this->registers->current($request->user());

        if ($register === null) {
            return redirect()->route('boxoffice.cash.open')->with('error', 'No tienes una caja abierta.');
        }

        return $this->render($register);
    }

    public function close(CloseCashRegisterRequest $request, CloseAuthorizer $authorizer): RedirectResponse
    {
        $register = $this->registers->current($request->user());

        if ($register === null) {
            return redirect()->route('boxoffice.cash.open')->with('error', 'No tienes una caja abierta.');
        }

        $supervisor = $authorizer->authorize($request->user(), $request->validated('autoriza_correo'), $request->validated('autoriza_password'));
        $closed = $this->registers->close($register, $supervisor, (float) $request->validated('efectivo_contado'), $request->validated('comentario'));

        return redirect()->route('boxoffice.cash.closed', $closed->pk_caja_apertura)->with('success', 'Caja cerrada.');
    }

    public function closed(Request $request, int $apertura): Response
    {
        $register = CajaApertura::query()->activas()->where('estatus', CajaApertura::STATUS_CLOSED)->findOrFail($apertura);
        $user = $request->user();

        abort_unless(
            $register->pk_usuario === $user->pk_usuario || $register->cierre_pk_usuario_autoriza === $user->pk_usuario || $user->isAdministrator(),
            403,
        );

        return $this->render($register);
    }

    private function render(CajaApertura $register): Response
    {
        $register->loadMissing(['cajero', 'autoriza']);
        $summary = $this->registers->summary($register);

        return Inertia::render('BoxOffice/CashRegister/Show', [
            'register' => [
                'id' => $register->pk_caja_apertura,
                'name' => $register->caja,
                'open' => $register->isOpen(),
                'openedAt' => $register->apertura_fecha?->format('d/m/Y H:i'),
                'closedAt' => $register->cierre_fecha?->format('d/m/Y H:i'),
                'openingAmount' => $register->monto_apertura,
                'openingComment' => $register->comentario_apertura,
                'closingComment' => $register->comentario_cierre,
                'countedCash' => $register->efectivo_contado,
                'difference' => $register->diferencia,
                'cashier' => $this->person($register->cajero),
                'authorizer' => $register->autoriza ? $this->person($register->autoriza) : null,
            ],
            'purchases' => $summary['purchases']->values(),
            'totals' => $summary['totals'],
        ]);
    }

    private function person(?Usuarios $user): ?array
    {
        return $user === null ? null : ['name' => $user->nombreCompleto() ?: $user->correo, 'email' => $user->correo];
    }
}
