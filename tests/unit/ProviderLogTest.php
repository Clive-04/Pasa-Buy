<?php

use App\Libraries\ProviderLog;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Provider error bodies are reduced to error identifiers before logging
 * (security finding S10).
 *
 * @internal
 */
final class ProviderLogTest extends CIUnitTestCase
{
    public function testKeepsXenditErrorCodeButNotPersonalData(): void
    {
        $body = json_encode([
            'error_code' => 'API_VALIDATION_ERROR',
            'message'    => 'customer.email juan.delacruz@example.com is invalid; mobile 09171234567',
        ]);

        $summary = ProviderLog::summarize($body);

        $this->assertSame('error_code=API_VALIDATION_ERROR', $summary);
        $this->assertStringNotContainsString('example.com', $summary);
        $this->assertStringNotContainsString('0917', $summary);
    }

    public function testKeepsPayMongoNestedCodes(): void
    {
        $body = json_encode(['errors' => [['code' => 'parameter_invalid', 'detail' => 'billing.name Juan Dela Cruz']]]);

        $this->assertSame('code=parameter_invalid', ProviderLog::summarize($body));
    }

    public function testFreeTextCodesAndNonJsonBodiesAreDropped(): void
    {
        $this->assertSame('no error code (body not logged)', ProviderLog::summarize(json_encode(['errorCode' => 'Juan Dela Cruz has no access'])));
        $this->assertSame('non-JSON body (16 bytes, not logged)', ProviderLog::summarize('<html>oops</html'));
    }
}
