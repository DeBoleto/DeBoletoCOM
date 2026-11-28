<?php

namespace Tests\Unit;

use App\Modules\Orders\Enums\DeliveryMethod;
use PHPUnit\Framework\TestCase;

class DeliveryMethodTest extends TestCase
{
    public function test_each_delivery_method_has_a_report_label(): void
    {
        $this->assertSame('Impreso en taquilla', DeliveryMethod::labelFor('impreso'));
        $this->assertSame('Enviado por WhatsApp', DeliveryMethod::labelFor('whatsapp'));
        $this->assertSame('PDF generado', DeliveryMethod::labelFor('digital'));
        $this->assertSame('Enviado por correo', DeliveryMethod::labelFor('correo'));
    }

    public function test_purchases_without_a_recorded_delivery_are_labeled_as_such(): void
    {
        $this->assertSame('Sin registro', DeliveryMethod::labelFor(null));
        $this->assertSame('Sin registro', DeliveryMethod::labelFor(''));
        $this->assertSame('Sin registro', DeliveryMethod::labelFor('fax'));
    }
}
