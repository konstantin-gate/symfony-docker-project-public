<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Greeting\Service\GreetingEmailParser;
use PHPUnit\Framework\TestCase;

class GreetingEmailParserTest extends TestCase
{
    private GreetingEmailParser $parser;

    protected function setUp(): void
    {
        $this->parser = new GreetingEmailParser();
    }

    public function testParseReturnsEmptyArrayForEmptyString(): void
    {
        $this->assertSame([], $this->parser->parse(''));
    }

    public function testParseSplitsByComma(): void
    {
        $input = 'test1@example.com,test2@example.com';
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    public function testParseSplitsBySpace(): void
    {
        $input = 'test1@example.com test2@example.com';
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    public function testParseSplitsByNewline(): void
    {
        $input = "test1@example.com\ntest2@example.com";
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    public function testParseSplitsBySemicolon(): void
    {
        $input = 'test1@example.com;test2@example.com';
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    public function testParseFiltersInvalidEmails(): void
    {
        $input = 'valid@example.com, invalid-email, also@invalid';
        $expected = ['valid@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    public function testParseRemovesDuplicates(): void
    {
        $input = 'test@example.com, test@example.com';
        $expected = ['test@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }

    public function testParseHandlesMixedSeparatorsAndWhitespace(): void
    {
        $input = " test1@example.com,  test2@example.com\n; test3@example.com ";
        $expected = ['test1@example.com', 'test2@example.com', 'test3@example.com'];
        $this->assertSame($expected, $this->parser->parse($input));
    }
}
