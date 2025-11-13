<?php

namespace Tests\Unit;

use Erilshk\Vinti4Net\Vinti4Response;
use PHPUnit\Framework\TestCase;

class Vinti4ResponseTest extends TestCase
{
    public function testFromProcessorResultSuccess()
    {
        $processorResult = [
            'success' => true,
            'fingerprint_valid' => true,
            'message_type' => '8',
            'data' => [
                'transactionCode' => '1',
                'merchantRespPurchaseAmount' => 1500.00,
                'merchantRespCurrency' => 'CVE',
                'merchantRespTid' => 'TXN123'
            ]
        ];

        $response = Vinti4Response::fromProcessorResult($processorResult);

        $this->assertInstanceOf(Vinti4Response::class, $response);
        $this->assertEquals('SUCCESS', $response->status);
        $this->assertEquals('Transação válida.', $response->message);
        $this->assertTrue($response->success);
    }

    public function testFromProcessorResultCancelled()
    {
        $processorResult = [
            'success' => false,
            'fingerprint_valid' => false,
            'message_type' => '',
            'data' => [
                'UserCancelled' => 'true'
            ]
        ];

        $response = Vinti4Response::fromProcessorResult($processorResult);

        $this->assertEquals('CANCELLED', $response->status);
        $this->assertEquals('Utilizador cancelou a transação.', $response->message);
        $this->assertFalse($response->success);
    }

    public function testFromProcessorResultInvalidFingerprint()
    {
        $processorResult = [
            'success' => true,
            'fingerprint_valid' => false,
            'message_type' => '8',
            'data' => [
                'transactionCode' => '1'
            ]
        ];

        $response = Vinti4Response::fromProcessorResult($processorResult);

        $this->assertEquals('INVALID_FINGERPRINT', $response->status);
        $this->assertStringContainsString('Fingerprint inválido', $response->message);
        $this->assertFalse($response->success);
    }

    public function testFromProcessorResultWithDcc()
    {
        $processorResult = [
            'success' => true,
            'fingerprint_valid' => true,
            'message_type' => '8',
            'data' => [
                'transactionCode' => '1',
                'merchantRespDCCData' => json_encode([
                    'dcc' => 'Y',
                    'dccAmount' => 135.50,
                    'dccCurrency' => 'EUR',
                    'dccMarkup' => 1.5,
                    'dccRate' => 110.25
                ])
            ]
        ];

        $response = Vinti4Response::fromProcessorResult($processorResult);

        $this->assertTrue($response->dcc['enabled']);
        $this->assertEquals(135.50, $response->dcc['amount']);
        $this->assertEquals('EUR', $response->dcc['currency']);
        $this->assertEquals(1.5, $response->dcc['markup']);
        $this->assertEquals(110.25, $response->dcc['rate']);
    }

    public function testHelperMethods()
    {
        $response = new Vinti4Response('SUCCESS', 'Test', true);

        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->isCancelled());
        $this->assertFalse($response->hasInvalidFingerprint());

        $response = new Vinti4Response('CANCELLED', 'Test', false);
        $this->assertTrue($response->isCancelled());

        $response = new Vinti4Response('INVALID_FINGERPRINT', 'Test', false);
        $this->assertTrue($response->hasInvalidFingerprint());
    }

    public function testToArrayAndToJson()
    {
        $response = new Vinti4Response(
            'SUCCESS',
            'Test message',
            true,
            ['test' => 'data'],
            ['dcc' => 'info'],
            ['debug' => 'info'],
            'Detail'
        );

        $array = $response->toArray();
        
        $this->assertEquals('SUCCESS', $array['status']);
        $this->assertEquals('Test message', $array['message']);
        $this->assertTrue($array['success']);
        $this->assertEquals(['test' => 'data'], $array['data']);
        $this->assertEquals(['dcc' => 'info'], $array['dcc']);
        $this->assertEquals(['debug' => 'info'], $array['debug']);
        $this->assertEquals('Detail', $array['detail']);

        $json = $response->toJson();
        $this->assertJson($json);
        
        $decoded = json_decode($json, true);
        $this->assertEquals('SUCCESS', $decoded['status']);
    }

    public function testGenerateReceiptHtml()
    {
        $response = new Vinti4Response(
            'SUCCESS',
            'Transação válida.',
            true,
            [
                'transactionCode' => '1',
                'merchantRespMerchantRef' => 'REF123',
                'merchantRespTimeStamp' => '2024-01-01 12:00:00',
                'merchantRespTid' => 'TXN456',
                'merchantRespPurchaseAmount' => 1500.00,
                'merchantRespCurrency' => 'CVE',
                'merchantRespPan' => '1234567890123456'
            ]
        );

        $receipt = $response->generateReceiptHtml('Test Store');

        $this->assertStringContainsString('COMPROVATIVO DE PAGAMENTO', $receipt);
        $this->assertStringContainsString('Test Store', $receipt);
        $this->assertStringContainsString('REF123', $receipt);
        $this->assertStringContainsString('1 500,00 CVE', $receipt);
        $this->assertStringContainsString('<style>', $receipt);
        $this->assertStringContainsString('<h2>COMPROVATIVO DE PAGAMENTO</h2>', $receipt);
    }

    public function testStaticConstructors()
    {
        $success = Vinti4Response::success('Custom message');
        $this->assertEquals('SUCCESS', $success->status);
        $this->assertTrue($success->success);

        $error = Vinti4Response::error('Error message', 'Detail');
        $this->assertEquals('ERROR', $error->status);
        $this->assertFalse($error->success);

        $cancelled = Vinti4Response::cancelled();
        $this->assertEquals('CANCELLED', $cancelled->status);

        $invalidFp = Vinti4Response::invalidFingerprint(['debug' => 'info']);
        $this->assertEquals('INVALID_FINGERPRINT', $invalidFp->status);
    }
}