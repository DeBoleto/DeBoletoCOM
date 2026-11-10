<?php

namespace Tests\Unit\Checkout;

use App\Modules\Checkout\Services\Coupon;
use App\Modules\Orders\Models\EventoCodigoPromocion;
use App\Modules\Users\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class CouponTest extends TestCase
{
    public function test_percentage_coupon_discounts_tickets_and_service_fee(): void
    {
        $coupon = new Coupon(1, 'PROMO10', EventoCodigoPromocion::TYPE_PERCENTAGE, 10);

        $this->assertSame(108.0, $coupon->discount(1000, 80, 2));
        $this->assertTrue($coupon->spreadsOverTickets());
        $this->assertSame('10% de descuento', $coupon->description());
    }

    public function test_free_tickets_coupon_gives_one_free_ticket_per_group(): void
    {
        $coupon = new Coupon(1, '2X1', EventoCodigoPromocion::TYPE_FREE_TICKETS, 1);

        $this->assertSame(1000.0, $coupon->discount(2000, 160, 4));
        $this->assertSame(500.0, $coupon->discount(1500, 120, 3));
        $this->assertFalse($coupon->spreadsOverTickets());
        $this->assertSame('2x1 en tus boletos', $coupon->description());
    }

    public function test_fixed_coupon_never_exceeds_the_purchase(): void
    {
        $coupon = new Coupon(1, 'REGALO', EventoCodigoPromocion::TYPE_FIXED, 5000);

        $this->assertSame(540.0, $coupon->discount(500, 40, 1));
    }

    public function test_phone_numbers_are_normalized_to_e164(): void
    {
        $this->assertSame('+525512345678', PhoneNumber::e164('55 1234 5678', '52'));
        $this->assertSame('+529931234567', PhoneNumber::e164('9931234567'));
        $this->assertSame('+525512345678', PhoneNumber::e164('525512345678', '52'));
        $this->assertSame('+13055550100', PhoneNumber::e164('3055550100', '1'));
    }
}
