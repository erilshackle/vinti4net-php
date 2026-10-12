<?php

declare(strict_types=1);

namespace Tests\Unit;

use Erilshk\Sisp\Core\Token;
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    private TokenProbe $processor;
    private array $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = json_decode(file_get_contents(__DIR__ . '/../Fixtures/token-v1.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->processor = new TokenProbe($this->fixtures['posID'], $this->fixtures['posAuthCode']);
    }

    public function testAllFiveProjectRequestVectors(): void
    {
        foreach ($this->fixtures['requests'] as $fields) {
            self::assertSame($fields['fingerprint'], $this->processor->requestFingerprint($fields));
            $fields['merchantSession'] = '  ' . $fields['merchantSession'] . '  ';
            self::assertSame($fields['fingerprint'], $this->processor->requestFingerprint($fields));
            $fields['amount'] .= '.000';
            self::assertSame($fields['fingerprint'], $this->processor->requestFingerprint($fields));
            if ($fields['entityCode'] !== '') {
                $fields['entityCode'] = '000' . $fields['entityCode'];
                $fields['referenceNumber'] = '00' . $fields['referenceNumber'];
                self::assertSame($fields['fingerprint'], $this->processor->requestFingerprint($fields));
            }
        }
    }

    public function testAllProjectResponseVectors(): void
    {
        foreach ($this->fixtures['responses'] as $fields) {
            self::assertSame($fields['resultFingerPrint'], $this->processor->responseFingerprint($fields));
            self::assertTrue($this->processor->processResponse($fields)['success']);
        }
    }

    public function testPreparedOperationsUseTheirEndpointsAndVersionOne(): void
    {
        foreach ($this->fixtures['requests'] as $fields) {
            $fields['merchantRef'] = 'R20210714100158';
            $fields['merchantSession'] = 'S26101210300012';
            $result = $this->processor->preparePayment($fields);
            $path = match ($fields['transactionCode']) {
                '5' => 'TokenEnrollment', '7' => 'TokenCancel', default => 'TokenPayment',
            };
            self::assertStringContainsString('/' . $path . '?', $result['postUrl']);
            self::assertSame('1', $result['fields']['fingerprintversion']);
            self::assertSame($fields['token'], $result['fields']['token']);
            self::assertSame($this->processor->requestFingerprint($result['fields']), $result['fields']['fingerprint']);
        }
    }

    public function testExactDecimalScaleAndOpaqueToken(): void
    {
        $fields = $this->fixtures['requests'][1];
        $fields['amount'] = '0001.230';
        $fields['token'] = '0000123';
        $expected = base64_encode(hash('sha512', base64_encode(hash('sha512', 'TOKEN_TEST_AUTH', true))
            . '2026-10-12 10:30:00' . '1230' . 'R261012103000AB' . 'S26101210300012' . '90000001' . '132' . '6' . '0000123', true));
        self::assertSame($expected, $this->processor->requestFingerprint($fields));
        $fields['amount'] = '1.23';
        self::assertSame($expected, $this->processor->requestFingerprint($fields));
        $fields['token'] = '123';
        self::assertNotSame($expected, $this->processor->requestFingerprint($fields));
    }

    public function testCallbacksFailClosedForMissingFieldsTamperingAndVersions(): void
    {
        foreach ($this->fixtures['responses'] as $fields) {
            foreach (array_keys($fields) as $key) {
                if (in_array($key, ['approvalCode', 'merchantRespClientReceipt', 'merchantRespReloadCode', 'merchantRespReferenceNumber', 'merchantRespEntityCode'], true) && $fields[$key] === '') continue;
                $missing = $fields;
                unset($missing[$key]);
                self::assertFalse($this->processor->processResponse($missing)['success'], $key);
            }
            foreach (['merchantResp', 'merchantRespMerchantRef', 'merchantRespTimeStamp', 'resultFingerPrint', 'messageType', 'resultFingerPrintVersion'] as $key) {
                $changed = $fields;
                $changed[$key] = 'unexpected';
                self::assertFalse($this->processor->processResponse($changed)['success'], $key);
            }
        }
    }

    public function testOperationContextAndCancellationDecisionAreIsolated(): void
    {
        $cancel = $this->fixtures['responses'][2];
        self::assertTrue($this->processor->processResponse($cancel, '7')['success']);
        self::assertFalse($this->processor->processResponse($cancel, '6')['success']);
        $alternative = new TokenProbe('90000001', 'TOKEN_TEST_AUTH', cancelMessageType: 'B');
        self::assertFalse($alternative->processResponse($cancel, '7')['success']);
        $cancel['messageType'] = 'B';
        $cancel['resultFingerPrint'] = $alternative->responseFingerprint($cancel);
        self::assertTrue($alternative->processResponse($cancel, '7')['success']);
        self::assertFalse($this->processor->processResponse($cancel, '7')['success']);
        self::assertFalse($this->processor->processResponse($this->fixtures['responses'][0], '6')['success']);
    }

    public function testEndpointOverridesKeepQueryAndCase(): void
    {
        $processor = new Token('90000001', 'auth', 'https://example.com/tokenEnrollment?tenant=test');
        $result = $processor->preparePayment($this->validRequest('5'));
        self::assertStringStartsWith('https://example.com/tokenEnrollment?tenant=test&FingerPrint=', $result['postUrl']);
        self::assertArrayNotHasKey('posAuthCode', $result['fields']);
    }

    public function testInvalidRequests(): void
    {
        $cases = [
            ['transactionCode' => '1'], ['token' => 123], ['token' => ''],
            ['amount' => '1.5'], ['amount' => '-1'], ['amount' => '10000000000000'],
            ['currency' => 'EUR'], ['merchantRef' => '   '], ['merchantSession' => '   '],
            ['urlMerchantResponse' => 'http://example.com'], ['timeStamp' => '2026-02-30 12:00:00'],
            ['transactionCode' => '7', 'amount' => '1'],
            ['transactionCode' => '2', 'entityCode' => ''],
            ['transactionCode' => '2', 'entityCode' => '123456', 'referenceNumber' => '123'],
            ['transactionCode' => '3', 'referenceNumber' => ''],
        ];
        foreach ($cases as $change) {
            try {
                $this->processor->preparePayment(array_replace($this->validRequest('6'), $change));
                self::fail('Invalid request was accepted: ' . json_encode($change));
            } catch (Vinti4Exception $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testErrorsAndUserCancellationNeverSucceed(): void
    {
        $error = ['messageType' => '6', 'merchantResp' => '0'];
        self::assertFalse($this->processor->processResponse($error)['success']);
        self::assertFalse($this->processor->processResponse($error)['fingerprint_valid']);
        $cancel = ['merchantRef' => 'R20210714100158', 'merchantSession' => 'S26101210300012', 'userCancelled' => 'true'];
        self::assertFalse($this->processor->processResponse($cancel)['success']);
        self::assertSame('true', $this->processor->processResponse($cancel)['data']['UserCancelled']);
        $error = ['messageType' => '6', 'merchantRespMessageID' => 'error-id',
            'merchantRespErrorCode' => '4', 'merchantRespErrorDetail' => 'detail',
            'merchantRespErrorDescription' => 'description', 'merchantRespMerchantRef' => 'ref',
            'merchantRespMerchantSession' => 'session', 'merchantRespAdditionalErrorMessage' => 'invalid token',
            'merchantRespTimeStamp' => '2026-10-12 10:30:00'];
        $error['resultFingerPrint'] = base64_encode(hash('sha512', base64_encode(hash('sha512', 'TOKEN_TEST_AUTH', true))
            . '6error-id4detaildescriptionrefsessioninvalid token2026-10-12 10:30:00', true));
        self::assertTrue($this->processor->processResponse($error)['fingerprint_valid']);
        self::assertFalse($this->processor->processResponse($error)['success']);
    }

    public function testInvalidEndpointConfiguration(): void
    {
        foreach (['http://example.com', 'https://example.com/#fragment', 'invalid-url'] as $endpoint) {
            try {
                $processor = new Token('90000001', 'auth', $endpoint);
                $processor->preparePayment($this->validRequest('5'));
                self::fail('Invalid endpoints were accepted.');
            } catch (Vinti4Exception $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
        $this->expectException(Vinti4Exception::class);
        new Token('90000001', 'auth', cancelMessageType: 'A');
    }

    public function testLocalHttpCallbacksAndRejectedLookalikes(): void
    {
        foreach (['http://localhost:8000/callback', 'http://127.0.0.1:8000/callback', 'http://[::1]:8000/callback'] as $url) {
            $fields = $this->validRequest('5');
            $fields['urlMerchantResponse'] = $url;
            self::assertSame($url, $this->processor->preparePayment($fields)['fields']['urlMerchantResponse']);
        }
        foreach (['http://localhost.example/callback', 'http://192.168.1.1/callback', 'http://localhost@evil.example/callback', 'http://user@localhost/callback', 'http://localhost/callback#fragment'] as $url) {
            try {
                $this->processor->preparePayment(array_replace($this->validRequest('5'), ['urlMerchantResponse' => $url]));
                self::fail('Invalid HTTP callback was accepted.');
            } catch (Vinti4Exception $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testOptionalEmptyCallbackFieldsMayBeOmitted(): void
    {
        $cancel = $this->fixtures['responses'][2];
        foreach (['approvalCode', 'merchantRespClientReceipt', 'merchantRespReloadCode', 'merchantRespReferenceNumber', 'merchantRespEntityCode'] as $field) {
            unset($cancel[$field]);
        }
        self::assertTrue($this->processor->processResponse($cancel, '7')['success']);
        self::assertFalse($this->processor->processResponse($cancel, '2')['success']);
        self::assertFalse($this->processor->processResponse($cancel, '3')['success']);
    }

    private function validRequest(string $code): array
    {
        return ['transactionCode' => $code, 'amount' => '89', 'token' => $code === '5' ? '' : '000123',
            'merchantRef' => 'R20210714100158', 'merchantSession' => 'S26101210300012',
            'urlMerchantResponse' => 'https://example.com/callback'];
    }
}

class TokenProbe extends Token
{
    public function requestFingerprint(array $data): string { return $this->fingerprintRequest($data); }
    public function responseFingerprint(array $data): string { return $this->fingerprintResponse($data); }
}
