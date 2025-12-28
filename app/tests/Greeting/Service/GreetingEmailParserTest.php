<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Greeting\Service\GreetingEmailParser;
use PHPUnit\Framework\TestCase;

/**
 * Testovací třída pro GreetingEmailParser.
 * Obsahuje testy pro parsování a validaci e-mailových adres.
 */
class GreetingEmailParserTest extends TestCase
{
    private GreetingEmailParser $parser;

    /**
     * Inicializuje testovací instanci GreetingEmailParser.
     * Tato metoda se volá před každým testem.
     */
    protected function setUp(): void
    {
        $this->parser = new GreetingEmailParser();
    }

    /**
     * Testuje, že metoda parse vrátí prázdné pole pro prázdný řetězec.
     * Ověřuje správné chování při zpracování prázdného vstupu.
     */
    public function testParseReturnsEmptyArrayForEmptyString(): void
    {
        $this->assertSame([], $this->parser->parse(''));
    }

    /**
     * Testuje, že metoda parse správně rozdělí e-maily oddělené čárkou.
     * Ověřuje funkčnost oddělovače čárka.
     */
    public function testParseSplitsByComma(): void
    {
        $input = 'test1@example.com,test2@example.com';
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    /**
     * Testuje, že metoda parse správně rozdělí e-maily oddělené mezerou.
     * Ověřuje funkčnost oddělovače mezera.
     */
    public function testParseSplitsBySpace(): void
    {
        $input = 'test1@example.com test2@example.com';
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    /**
     * Testuje, že metoda parse správně rozdělí e-maily oddělené novým řádkem.
     * Ověřuje funkčnost oddělovače nový řádek.
     */
    public function testParseSplitsByNewline(): void
    {
        $input = "test1@example.com\ntest2@example.com";
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    /**
     * Testuje, že metoda parse správně rozdělí e-maily oddělené středníkem.
     * Ověřuje funkčnost oddělovače středník.
     */
    public function testParseSplitsBySemicolon(): void
    {
        $input = 'test1@example.com;test2@example.com';
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    /**
     * Testuje, že metoda parse správně filtrované neplatné e-mailové adresy.
     * Ověřuje funkčnost filtru pro neplatné e-maily.
     */
    public function testParseFiltersInvalidEmails(): void
    {
        $input = 'valid@example.com, invalid-email, also@invalid';
        $expected = ['valid@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    /**
     * Testuje, že metoda parse odstraní duplikáty e-mailových adres.
     * Ověřuje funkčnost odstranění duplikátů.
     */
    public function testParseRemovesDuplicates(): void
    {
        $input = 'test@example.com, test@example.com';
        $expected = ['test@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    /**
     * Testuje, že metoda parse správně zpracuje smíšené oddělovače a mezeru.
     * Ověřuje funkčnost při kombinaci různých oddělovačů a mezer.
     */
    public function testParseHandlesMixedSeparatorsAndWhitespace(): void
    {
        $input = " test1@example.com,  test2@example.com\n; test3@example.com ";
        $expected = ['test1@example.com', 'test2@example.com', 'test3@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }
}
