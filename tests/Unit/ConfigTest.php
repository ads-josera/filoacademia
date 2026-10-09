<?php

declare(strict_types=1);

namespace FiloAcademia\Tests\Unit;

use FiloAcademia\Support\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private function config(array $mercadopago): Config
    {
        return new Config(['mercadopago' => $mercadopago]);
    }

    public function testElModoElijeElJuegoDeLlaves(): void
    {
        $sets = [
            'test' => ['public_key' => 'TEST-PK', 'access_token' => 'TEST-AT'],
            'production' => ['public_key' => 'PROD-PK', 'access_token' => 'PROD-AT'],
        ];

        $test = $this->config(['mode' => 'test'] + $sets);
        self::assertSame('test', $test->mercadoPagoMode());
        self::assertSame('TEST-AT', $test->mercadoPagoCredential('access_token'));

        $prod = $this->config(['mode' => 'production'] + $sets);
        self::assertSame('production', $prod->mercadoPagoMode());
        self::assertSame('PROD-AT', $prod->mercadoPagoCredential('access_token'));
        self::assertSame('PROD-PK', $prod->mercadoPagoCredential('public_key'));
    }

    public function testUnModoMalEscritoNoCobraDeVerdad(): void
    {
        $config = $this->config(['mode' => 'produccion', 'test' => ['access_token' => 'TEST-AT'], 'production' => ['access_token' => 'PROD-AT']]);

        self::assertSame('test', $config->mercadoPagoMode());
        self::assertSame('TEST-AT', $config->mercadoPagoCredential('access_token'));
    }

    public function testProduccionSinLlavesNoUsaLasDePrueba(): void
    {
        $config = $this->config([
            'mode' => 'production',
            'public_key' => 'TEST-PK', 'access_token' => 'TEST-AT',
            'test' => ['access_token' => 'TEST-AT'],
            'production' => ['public_key' => '', 'access_token' => ''],
        ]);

        self::assertSame('', $config->mercadoPagoCredential('access_token'));
    }

    public function testCompatibleConLasLlavesSueltas(): void
    {
        // Configuración anterior (la del servidor antes de este cambio).
        $config = $this->config(['public_key' => 'PK', 'access_token' => 'AT']);

        self::assertSame('test', $config->mercadoPagoMode());
        self::assertSame('AT', $config->mercadoPagoCredential('access_token'));
        self::assertSame('PK', $config->mercadoPagoCredential('public_key'));
    }
}
