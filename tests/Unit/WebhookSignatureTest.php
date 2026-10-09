<?php

declare(strict_types=1);

namespace FiloAcademia\Tests\Unit;

use FiloAcademia\Payment\MercadoPago\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'clave-secreta-de-prueba';

    private static function sign(string $dataId, string $requestId, string $ts): string
    {
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";

        return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifest, self::SECRET);
    }

    public function testAceptaUnaFirmaCorrecta(): void
    {
        $header = self::sign('123456', 'req-1', '1760000000000');

        self::assertTrue((new WebhookSignature(self::SECRET))->isValid($header, 'req-1', '123456'));
    }

    public function testAceptaEspaciosEnLaCabecera(): void
    {
        $header = str_replace(',', ', ', self::sign('123456', 'req-1', '1760000000000'));

        self::assertTrue((new WebhookSignature(self::SECRET))->isValid($header, 'req-1', '123456'));
    }

    public function testRechazaOtroPago(): void
    {
        $header = self::sign('123456', 'req-1', '1760000000000');

        self::assertFalse((new WebhookSignature(self::SECRET))->isValid($header, 'req-1', '999999'));
    }

    public function testRechazaOtroRequestId(): void
    {
        $header = self::sign('123456', 'req-1', '1760000000000');

        self::assertFalse((new WebhookSignature(self::SECRET))->isValid($header, 'req-2', '123456'));
    }

    public function testRechazaOtraClave(): void
    {
        $header = self::sign('123456', 'req-1', '1760000000000');

        self::assertFalse((new WebhookSignature('otra-clave'))->isValid($header, 'req-1', '123456'));
    }

    public function testSinClaveConfiguradaRechazaTodo(): void
    {
        $header = self::sign('123456', 'req-1', '1760000000000');

        self::assertFalse((new WebhookSignature(''))->isValid($header, 'req-1', '123456'));
    }

    public function testAceptaCualquieraDeVariasClaves(): void
    {
        // Firmado con la clave de la aplicación espejo de prueba.
        $header = self::sign('123456', 'req-1', '1760000000000');
        $validator = new WebhookSignature(['clave-de-produccion', self::SECRET]);

        self::assertTrue($validator->isValid($header, 'req-1', '123456'));
        self::assertFalse((new WebhookSignature(['clave-de-produccion', 'otra']))->isValid($header, 'req-1', '123456'));
        self::assertFalse((new WebhookSignature(['', '  ']))->isValid($header, 'req-1', '123456'));
    }

    public function testRechazaCabecerasMalFormadas(): void
    {
        $validator = new WebhookSignature(self::SECRET);

        self::assertFalse($validator->isValid('', 'req-1', '123456'));
        self::assertFalse($validator->isValid('v1=abc', 'req-1', '123456'));
        self::assertFalse($validator->isValid('ts=abc,v1=abc', 'req-1', '123456'));
    }
}
