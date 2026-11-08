<?php

namespace Tests\Feature\BoxOffice;

use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Users\Models\Usuarios;
use Tests\TestCase;

class PrintFormatSelectionTest extends TestCase
{
    private function service(): TicketPrintService
    {
        return app(TicketPrintService::class);
    }

    private function event(int $format): EventoVista
    {
        return tap(new EventoVista)->setRawAttributes(['pk_evento' => 1, 'pk_boleto_formato_tipo' => $format]);
    }

    public function test_sale_and_courtesy_mappings_differ_where_legacy_did(): void
    {
        $user = new Usuarios;

        $this->assertSame('inferior-blanco', $this->service()->formatFor($this->event(2), $user));
        $this->assertSame('inferior-blanco-cortesia', $this->service()->formatFor($this->event(2), $user, courtesy: true));
        $this->assertSame('olmecas-membretado-v2', $this->service()->formatFor($this->event(10), $user));
        $this->assertSame('qr-olmecas', $this->service()->formatFor($this->event(10), $user, courtesy: true));
        $this->assertSame('ticket-ribetec', $this->service()->formatFor($this->event(19), $user, courtesy: true));
    }

    public function test_user_format_wins_and_unknown_falls_back_to_default(): void
    {
        $user = (new Usuarios)->forceFill(['pk_formato_boleto' => 16]);

        $this->assertSame('mini-ticket-barcode', $this->service()->formatFor($this->event(2), $user));
        $this->assertSame('superior-blanco', $this->service()->formatFor($this->event(99), new Usuarios));
    }
}
