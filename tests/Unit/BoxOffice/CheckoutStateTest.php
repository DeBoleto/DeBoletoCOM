<?php

namespace Tests\Unit\BoxOffice;

use App\Modules\BoxOffice\Services\Checkout\CheckoutState;
use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\Orders\Enums\PaymentType;
use PHPUnit\Framework\TestCase;

class CheckoutStateTest extends TestCase
{
    private function state(): CheckoutState
    {
        return new CheckoutState(10, 20, PaymentType::Cash, [
            new TicketLine(1, 'ORO', 'A1', 500, 30, 0),
            new TicketLine(1, 'ORO', 'A2', 500, 30, 0),
            TicketLine::courtesy(1, 'ORO', 'A3'),
        ], localityId: 1, sectionId: 'ORO', promotion: 1, promotionText: '2X1');
    }

    public function test_totals_ignore_courtesies(): void
    {
        $state = $this->state();

        $this->assertSame(1000.0, $state->subtotal());
        $this->assertSame(60.0, $state->serviceFees());
        $this->assertSame(1060.0, $state->total());
        $this->assertSame(2, $state->paidCount());
        $this->assertSame(1, $state->courtesyCount());
    }

    public function test_round_trips_through_session_array(): void
    {
        $state = $this->state()->withPurchase(99);
        $copy = CheckoutState::fromArray($state->toArray());

        $this->assertEquals($state, $copy);
        $this->assertTrue($copy->isPurchased());
    }

    public function test_general_admission_and_table_seats_are_detected(): void
    {
        $this->assertTrue((new TicketLine(1, 'GRAL', 'NA_NA3', 100))->isGeneralAdmission());
        $this->assertTrue((new TicketLine(1, 'MESA', 'M4-5', 100))->isTableSeat());
        $this->assertFalse((new TicketLine(1, 'ORO', 'B7', 100))->isTableSeat());
    }
}
