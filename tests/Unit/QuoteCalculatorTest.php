<?php

declare(strict_types=1);

namespace FiloAcademia\Tests\Unit;

use FiloAcademia\Catalog\Catalog;
use FiloAcademia\Catalog\QuoteCalculator;
use FiloAcademia\Catalog\QuoteException;
use PHPUnit\Framework\TestCase;

final class QuoteCalculatorTest extends TestCase
{
    private QuoteCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new QuoteCalculator(Catalog::fromFile(dirname(__DIR__, 2) . '/config/catalog.php'));
    }

    public function testEjemploPublicadoEnElSitio(): void
    {
        // «afilado + remoción de 2 mm + punta rota = 250 + 450 + 300 = $1,000 por cuchillo»
        $quote = $this->calculator->calculate([['removal' => 'remocion-2mm', 'extras' => ['punta'], 'qty' => 1]], 'taller', false);

        self::assertSame(1000, $quote->lines[0]->unitPrice);
        self::assertSame(1000, $quote->total());
    }

    public function testLaEntregaSeCobraUnaSolaVezPorPedido(): void
    {
        $quote = $this->calculator->calculate([
            ['removal' => 'ninguna', 'extras' => [], 'qty' => 2],
            ['removal' => 'remocion-1mm', 'extras' => ['oxido'], 'qty' => 1],
        ], 'cdmx', false);

        // 2×250 + (250+290+180) + 290 de entrega (una vez, no por línea)
        self::assertSame(500 + 720, $quote->servicesSubtotal());
        self::assertSame(290, $quote->deliveryAmount);
        self::assertSame(1510, $quote->total());
        self::assertSame(3, $quote->knifeCount());
    }

    public function testElSeguroSoloAplicaConPaqueteria(): void
    {
        $line = [['removal' => 'ninguna', 'extras' => [], 'qty' => 1]];

        self::assertSame(0, $this->calculator->calculate($line, 'cdmx', true)->insuranceAmount);
        self::assertSame(190, $this->calculator->calculate($line, 'nacional', true)->insuranceAmount);
        self::assertSame(250 + 680 + 190, $this->calculator->calculate($line, 'nacional', true)->total());
    }

    public function testIgnoraPreciosQueVienenDelNavegador(): void
    {
        $quote = $this->calculator->calculate(
            [['removal' => 'ninguna', 'extras' => [], 'qty' => 1, 'unitPrice' => 1, 'price' => 1, 'total' => 1]],
            'taller',
            false,
        );

        self::assertSame(250, $quote->total());
    }

    public function testExtrasRepetidosSeCobranUnaVez(): void
    {
        $quote = $this->calculator->calculate([['removal' => 'ninguna', 'extras' => ['punta', 'punta'], 'qty' => 1]], 'taller', false);

        self::assertSame(550, $quote->total());
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function entradasInvalidas(): iterable
    {
        yield 'carrito vacío' => [[], 'taller'];
        yield 'no es lista' => ['texto', 'taller'];
        yield 'remoción inexistente' => [[['removal' => 'remocion-9mm', 'extras' => [], 'qty' => 1]], 'taller'];
        yield 'extra inexistente' => [[['removal' => 'ninguna', 'extras' => ['gratis'], 'qty' => 1]], 'taller'];
        yield 'cantidad cero' => [[['removal' => 'ninguna', 'extras' => [], 'qty' => 0]], 'taller'];
        yield 'cantidad negativa' => [[['removal' => 'ninguna', 'extras' => [], 'qty' => -3]], 'taller'];
        yield 'cantidad excesiva' => [[['removal' => 'ninguna', 'extras' => [], 'qty' => 21]], 'taller'];
        yield 'cantidad decimal' => [[['removal' => 'ninguna', 'extras' => [], 'qty' => 1.5]], 'taller'];
        yield 'entrega no disponible' => [[['removal' => 'ninguna', 'extras' => [], 'qty' => 1]], 'internacional'];
        yield 'entrega inexistente' => [[['removal' => 'ninguna', 'extras' => [], 'qty' => 1]], 'luna'];
        yield 'demasiadas líneas' => [array_fill(0, 11, ['removal' => 'ninguna', 'extras' => [], 'qty' => 1]), 'taller'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('entradasInvalidas')]
    public function testRechazaEntradasInvalidas(mixed $lines, string $delivery): void
    {
        $this->expectException(QuoteException::class);
        $this->calculator->calculate($lines, $delivery, false);
    }
}
