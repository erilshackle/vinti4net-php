<?php

namespace Tests\Unit;

use Erilshk\Sisp\Core\Sisp;
use Erilshk\Sisp\Vinti4Response;
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
                'messageType' => '8',
                'merchantRespMerchantRef' => 'REF123',
                'merchantRespTimeStamp' => '2024-01-01 12:00:00',
                'merchantRespTid' => 'TXN456',
                'merchantRespPurchaseAmount' => 1500.00,
                'merchantRespCurrency' => 'CVE',
                'merchantRespPan' => '1234567890123456',
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

    public function testRefundMessage()
    {
        // Transaction code = 4 (Sisp::TRANSACTION_TYPE_REFUND)
        $data = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_REFUND
        ];

        $response = Vinti4Response::fromProcessorResult([
            'success' => true,
            'fingerprint_valid' => true,
            'data' => $data
        ]);

        $this->assertEquals('SUCCESS', $response->status);
        $this->assertEquals('Reembolso processado com sucesso.', $response->message);
    }

    public function testDccInvalidJson()
    {
        $data = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_PURCHASE,
            'merchantRespDCCData' => '{invalid_json}'
        ];

        $response = Vinti4Response::fromProcessorResult([
            'success' => true,
            'fingerprint_valid' => true,
            'data' => $data
        ]);

        $this->assertFalse($response->dcc['enabled']);
        $this->assertEquals('DCC inválido ou mal formatado', $response->dcc['error']);
    }

    public function testGetClearingPeriodAndAdditionalErrorMessage()
    {
        $data = [
            'merchantRespCP' => '2024',
            'merchantRespAdditionalErrorMessage' => 'Erro adicional'
        ];

        $response = new Vinti4Response(
            'ERROR',
            'Transação falhou',
            false,
            $data
        );

        // Cobrir getClearingPeriod
        $this->assertEquals('2024', $response->getClearingPeriod());

        // Cobrir GetAdditionalErrorMessage
        $this->assertEquals('Erro adicional', $response->GetAdditionalErrorMessage());
    }

    public function testGetClearingPeriodAndAdditionalErrorMessageWithMissingData()
    {
        // Sem dados
        $response = new Vinti4Response(
            'ERROR',
            'Transação falhou',
            false,
            []
        );

        // Métodos devem retornar null ou string vazia
        $this->assertNull($response->getClearingPeriod());
        $this->assertEquals('', $response->GetAdditionalErrorMessage());
    }


    public function testGetMerchantRefAndTransactionIdWithMissingData()
    {
        $response = new Vinti4Response(
            'SUCCESS',
            'Test',
            true,
            [] // sem dados
        );

        $this->assertNull($response->getMerchantRef());
        $this->assertNull($response->getTransactionId());
    }

    public function testGetMerchantRefAndTransactionIdWithData()
    {
        $data = [
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespTid' => 'TXN456'
        ];

        $response = new Vinti4Response(
            'SUCCESS',
            'Test',
            true,
            $data
        );

        $this->assertEquals('REF123', $response->getMerchantRef());
        $this->assertEquals('TXN456', $response->getTransactionId());
    }

    // public function testRecpeitInstantiation()
    // {
    //     $data = [
    //         'merchantRespMerchantRef' => 'REF123',
    //         'merchantRespTid' => 'TXN456'
    //     ];
    //     $response = Vinti4Response::success(
    //         'SUCCESS',
    //         $data
    //     );

    //     $recepit = $response->receipt('My COmpany');

    //     // $this->assertInstanceOf(Erilshk\Sisp\Receipt::class, $recepit);
    // }

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
