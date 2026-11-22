<?php

use App\Libraries\FeePolicy;
use App\Libraries\Money;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class MoneyAndFeePolicyTest extends CIUnitTestCase
{
    public static function validAmounts(): array
    {
        return [
            ['0', 0], ['5', 500], ['5.5', 550], ['5.05', 505], ['0.01', 1],
            ['12345678.99', 1_234_567_899], [' 42.10 ', 4210],
        ];
    }

    #[DataProvider('validAmounts')]
    public function testParsesAmountsIntoCentavos(string $input, int $expected): void
    {
        $this->assertSame($expected, Money::toCents($input));
    }

    public static function invalidAmounts(): array
    {
        return [['-1'], ['1.234'], ['1e3'], ['abc'], ['1,000'], ['+5'], ['.5'], ['123456789']];
    }

    #[DataProvider('invalidAmounts')]
    public function testRejectsInvalidAmounts(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toCents($input);
    }

    public function testFormatsCentavos(): void
    {
        $this->assertSame('₱1,234.05', Money::format(123_405));
        $this->assertSame('₱0.00', Money::format(0));
        $this->assertSame('1234.05', Money::toDecimal(123_405));
        $this->assertSame('₱140.50', Money::formatDecimal('140.50'));
    }

    public function testFloatingPointCaseStaysExact(): void
    {
        // 0.1 + 0.2 !== 0.3 with floats; exact with centavos.
        $this->assertSame(30, Money::toCents('0.10') + Money::toCents('0.20'));
    }

    public static function feeCases(): array
    {
        // service fee (centavos) => platform fee (centavos), 10% rounded half-up
        return [
            [5000, 500],   // ₱50.00 → ₱5.00
            [4500, 450],   // ₱45.00 → ₱4.50
            [45, 5],       // ₱0.45  → ₱0.045 → ₱0.05 (half-up)
            [44, 4],       // ₱0.44  → ₱0.044 → ₱0.04
            [5, 1],        // ₱0.05  → ₱0.005 → ₱0.01
            [4, 0],        // ₱0.04  → ₱0.004 → ₱0.00
            [0, 0],
            [1_000_000, 100_000],
        ];
    }

    #[DataProvider('feeCases')]
    public function testPlatformFeeIsTenPercentOfServiceFeeRoundedHalfUp(int $serviceFee, int $expected): void
    {
        $this->assertSame($expected, FeePolicy::platformFeeCents($serviceFee));
    }

    public function testRequesterPaysItemPlusServiceFeeAndRunnerBearsTheFee(): void
    {
        $item = 9550; // ₱95.50
        $fee  = 4500; // ₱45.00

        $this->assertSame(14050, FeePolicy::requesterTotalCents($item, $fee));
        $this->assertSame(13600, FeePolicy::runnerNetCents($item, $fee));
        $this->assertSame(4050, FeePolicy::runnerServiceEarningsCents($fee));
        // Conservation: requester total = runner net + platform fee.
        $this->assertSame(
            FeePolicy::requesterTotalCents($item, $fee),
            FeePolicy::runnerNetCents($item, $fee) + FeePolicy::platformFeeCents($fee)
        );
    }
}
