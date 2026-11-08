<?php

namespace Tests\Unit\BoxOffice;

use App\Modules\BoxOffice\Services\Checkout\CommissionCalculator;
use App\Modules\Events\Enums\CommissionType;
use Tests\TestCase;

class CommissionCalculatorTest extends TestCase
{
    private CommissionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CommissionCalculator;
    }

    public function test_free_tickets_have_no_fees(): void
    {
        $fees = $this->calculator->calculate(CommissionType::Fixed, 0, 500, 30, 3, true, false);

        $this->assertSame(['service' => 0.0, 'payment' => 0.0], $fees);
    }

    public function test_fixed_commission_in_cash_has_no_vat_on_bank_fee(): void
    {
        $fees = $this->calculator->calculate(CommissionType::Fixed, 500, 500, 30, 0, true, false);

        $this->assertEqualsWithDelta(30.0, $fees['service'], 0.001);
        $this->assertEqualsWithDelta(0.0, $fees['payment'], 0.001);
    }

    public function test_fixed_commission_with_terminal_adds_vat_and_scales_by_discount(): void
    {
        $fees = $this->calculator->calculate(CommissionType::Fixed, 400, 500, 30, 3, false, false);

        $service = 30 * 0.8;
        $bank = (400 + $service) * 0.03;

        $this->assertEqualsWithDelta($service, $fees['service'], 0.001);
        $this->assertEqualsWithDelta($bank * 1.16, $fees['payment'], 0.001);
    }

    public function test_percentage_commission_online_adds_gateway_fee(): void
    {
        $fees = $this->calculator->calculate(CommissionType::Percentage, 200, 200, 10, 3, true, true);

        $this->assertEqualsWithDelta(20.0, $fees['service'], 0.001);
        $this->assertEqualsWithDelta(6.0 + 2.5, $fees['payment'], 0.001);
    }

    public function test_ten_percent_service_fee_rule(): void
    {
        $fees = $this->calculator->calculate(CommissionType::Fixed, 300, 300, 20, 3, true, false, tenPercentServiceFee: true);

        $this->assertEqualsWithDelta(20.0, $fees['service'], 0.001);
        $this->assertEqualsWithDelta(32.0, $fees['payment'], 0.001);
    }

    public function test_no_card_fee_types(): void
    {
        $this->assertEqualsWithDelta(15.0, $this->calculator->calculate(CommissionType::PercentageNoCardFee, 150, 150, 10, 3, false, true)['service'], 0.001);
        $this->assertSame(0.0, $this->calculator->calculate(CommissionType::FixedNoCardFee, 150, 150, 12, 3, false, true)['payment']);
    }
}
