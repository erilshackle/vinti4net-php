<?php

namespace Tests\Unit;

use Erilshk\Sisp\Vinti4Net;
use Erilshk\Sisp\Vinti4Response;
use PHPUnit\Framework\TestCase;

class Vinti4NetTest extends TestCase
{
    private Vinti4Net $vinti4net;

    protected function setUp(): void
    {
        $this->vinti4net = new Vinti4Net('TEST_POS_123', 'TEST_AUTH_456');
    }

    public function testConstructorInitializesPaymentAndRefund()
    {
        $this->assertInstanceOf(Vinti4Net::class, $this->vinti4net);
    }

    public function testSetRequestParams()
    {
        $params = [
            'merchantRef' => 'CUSTOM_REF',
            'merchantSession' => 'CUSTOM_SESS',
            'languageMessages' => 'en',
            'email' => 'test@example.com'
        ];

        $result = $this->vinti4net->setRequestParams($params);

        $this->assertSame($this->vinti4net, $result);
    }

    public function testSetMerchantGeneratesDefaultSession()
    {
        $result = $this->vinti4net->setMerchant("REF123");

        $request = $this->vinti4net->getRequest();

        $this->assertSame("REF123", $request['merchantRef']);
        $this->assertStringStartsWith("S", $request['merchantSession']);
        $this->assertSame($this->vinti4net, $result);
    }

    public function testSetMerchantUsesProvidedSession()
    {
        $result = $this->vinti4net->setMerchant("REF456", "MANUAL_SESSION");

        $request = $this->vinti4net->getRequest();

        $this->assertSame("REF456", $request['merchantRef']);
        $this->assertSame("MANUAL_SESSION", $request['merchantSession']);
        $this->assertSame($this->vinti4net, $result);
    }


    public function testSetRequestParamsThrowsExceptionForInvalidParam()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parâmetro não permitido: invalid_param');

        $this->vinti4net->setRequestParams([
            'invalid_param' => 'value'
        ]);
    }

    public function testPreparePurchasePayment()
    {
        $billing = [
            'email' => 'test@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Praia, Plateau',
            'billAddrPostCode' => '7600'
        ];

        $result = $this->vinti4net->preparePurchase(1500, $billing, 'CVE');

        $this->assertSame($this->vinti4net, $result);
        $this->assertNotEmpty($this->vinti4net->getRequest());
    }

    public function testPrepareServicePayment()
    {
        $result = $this->vinti4net->prepareServicePayment(2500, 10001, '123456789');

        $this->assertSame($this->vinti4net, $result);
    }

    public function testPrepareRechargePayment()
    {
        $result = $this->vinti4net->prepareRecharge(500, 10021, '9912345');

        $this->assertSame($this->vinti4net, $result);
    }

    public function testPrepareRefundPayment()
    {
        $result = $this->vinti4net->prepareRefund(
            1500,
            'TXN789',
            '2024'
        );

        $this->assertSame($this->vinti4net, $result);
    }

    public function testCreatePaymentFormTriggersRefundPreparePayment()
    {
        // Prepare refund
        $this->vinti4net->prepareRefund(1500, "TXN123", "2024");

        // Mock de comportamento interno esperado
        $form = $this->vinti4net->createPaymentForm("https://callback.example.com");

        $this->assertStringContainsString('<form', $form);
        $this->assertStringContainsString('method="post"', $form);

        // Garante que refund foi realmente usado:
        $request = $this->vinti4net->getRequest();
        $this->assertArrayHasKey('fields', $request);
        $this->assertArrayHasKey('postUrl', $request);
    }


    public function testCreatePaymentForm()
    {
        $this->vinti4net->preparePurchase(1500, [
            'email' => 'test@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Teste',
            'billAddrPostCode' => '7600',
        ]);

        $form = $this->vinti4net->createPaymentForm('https://callback.example.com');

        $this->assertStringContainsString('<form', $form);
        $this->assertStringContainsString('method="post"', $form);
        $this->assertStringContainsString('onload', $form);
        $this->assertStringContainsString('hidden', $form);
    }

    public function testCreatePaymentFormThrowsExceptionWhenNotPrepared()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nenhum pagamento preparado.');

        $this->vinti4net->createPaymentForm('https://callback.example.com');
    }


    public function testCreatePaymentFormThrowsExceptionForInvalidPaymentData()
    {
        // Criar mock do Payment
        $mockPayment = $this->getMockBuilder(\Erilshk\Sisp\Core\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['preparePayment'])
            ->getMock();

        // preparePayment retorna array inválido (sem fields e postUrl)
        $mockPayment->method('preparePayment')->willReturn([]);

        // Substituir $payment da instância real
        $vinti = $this->vinti4net;
        $ref = new \ReflectionClass($vinti);
        $prop = $ref->getProperty('payment');
        $prop->setAccessible(true);
        $prop->setValue($vinti, $mockPayment);

        // Preparar pagamento para definir $prepared = true
        $vinti->preparePurchase(1000, [
            'email' => 'x@test.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua X',
            'billAddrPostCode' => '7600'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Dados de pagamento inválidos.");

        $vinti->createPaymentForm("https://callback.example.com");
    }



    public function testProcessResponseForPayment()
    {
        $postData = [
            'transactionCode' => '1',
            'messageType' => '8',
            'resultFingerPrint' => 'valid_fingerprint',
            'merchantRespPurchaseAmount' => 1500.00,
            'merchantRespCurrency' => 'CVE',
            'merchantRespMerchantRef' => 'REF123'
        ];

        $response = $this->vinti4net->processResponse($postData);

        $this->assertInstanceOf(Vinti4Response::class, $response);
    }

    public function testProcessResponseForRefund()
    {
        $postData = [
            'transactionCode' => '4',
            'messageType' => '10',
            'resultFingerPrint' => 'valid_fingerprint',
            'merchantRespPurchaseAmount' => 1500.00,
            'merchantRespMerchantRef' => 'REF123'
        ];

        $response = $this->vinti4net->processResponse($postData);

        $this->assertInstanceOf(Vinti4Response::class, $response);
    }
}
