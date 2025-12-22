<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Greeting\Service\GreetingXmlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GreetingXmlParserTest extends TestCase
{
    private GreetingXmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new GreetingXmlParser();
    }

    public function testParseValidXml(): void
    {
        $xml = <<<XML
<contacts>
    <email>user1@example.com</email>
    <group>
        <email>user2@example.com</email>
        <subgroup>
            <email>  user3@example.com  </email>
        </subgroup>
    </group>
</contacts>
XML;
        $result = $this->parser->parse($xml);

        $this->assertCount(3, $result);
        $this->assertContains('user1@example.com', $result);
        $this->assertContains('user2@example.com', $result);
        $this->assertContains('user3@example.com', $result);
    }

    public function testParseXmlWithNoEmails(): void
    {
        $xml = '<root><other>data</other><contact>no-email-tag</contact></root>';
        $result = $this->parser->parse($xml);

        $this->assertEmpty($result);
    }

    #[DataProvider('invalidXmlProvider')]
    public function testParseInvalidXml(string $invalidXml): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid XML');

        $this->parser->parse($invalidXml);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidXmlProvider(): array
    {
        return [
            'unclosed tag' => ['<root><email>test@test.com</root>'],
            'malformed syntax' => ['<emails><email>test@test.com</email content>'],
            'random text' => ['not an xml at all'],
        ];
    }

    public function testParseEmptyString(): void
    {
        $result = $this->parser->parse('');
        $this->assertEmpty($result);
    }

    public function testFiltersInvalidEmails(): void
    {
        $xml = <<<XML
<contacts>
    <email>valid@example.com</email>
    <email>invalid-email</email>
    <email>another.valid@test.org</email>
    <email></email>
</contacts>
XML;
        $result = $this->parser->parse($xml);

        $this->assertCount(2, $result);
        $this->assertEquals(['valid@example.com', 'another.valid@test.org'], array_values($result));
    }

    public function testXxeProtection(): void
    {
        // Create a "secret" file that we will try to read
        $secretFile = sys_get_temp_dir() . '/secret_test_file.txt';
        file_put_contents($secretFile, 'CONFIDENTIAL_DATA');

        // XML payload that attempts to include the secret file
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE root [
  <!ENTITY xxe SYSTEM "file://{$secretFile}">
]>
<root>
  <email>&xxe;</email>
  <email>safe@example.com</email>
</root>
XML;

        try {
            $result = $this->parser->parse($xml);

            // Result should NOT contain the secret content
            foreach ($result as $email) {
                $this->assertStringNotContainsString('CONFIDENTIAL_DATA', $email);
            }

            $this->assertContains('safe@example.com', $result);
            $this->assertNotContains('CONFIDENTIAL_DATA', $result);
        } finally {
            @unlink($secretFile);
        }
    }

    public function testParseLargeXml(): void
    {
        $count = 10000;
        $xml = '<contacts>';

        for ($i = 0; $i < $count; ++$i) {
            $xml .= "<email>user{$i}@example.com</email>";
        }

        $xml .= '</contacts>';
        $startTime = microtime(true);
        $result = $this->parser->parse($xml);
        $duration = microtime(true) - $startTime;
        $this->assertCount($count, $result);

        // Performance assertions
        $this->assertLessThan(2.0, $duration, \sprintf('Parsing %d emails took too long: %.2fs', $count, $duration));
    }

    public function testParseXmlWithNamespaces(): void
    {
        $xml = <<<XML
<contacts xmlns="http://example.com/ns" xmlns:ns2="http://example.com/ns2">
    <email>default_ns@example.com</email>
    <ns2:email>prefixed_ns@example.com</ns2:email>
</contacts>
XML;
        $result = $this->parser->parse($xml);

        $this->assertCount(2, $result);
        $this->assertContains('default_ns@example.com', $result);
        $this->assertContains('prefixed_ns@example.com', $result);
    }

    public function testParseXmlWithCdata(): void
    {
        $xml = <<<XML
<contacts>
    <email><![CDATA[ cdata_user@example.com ]]></email>
    <email><![CDATA[another@test.com]]></email>
</contacts>
XML;
        $result = $this->parser->parse($xml);

        $this->assertCount(2, $result);
        $this->assertContains('cdata_user@example.com', $result);
        $this->assertContains('another@test.com', $result);
    }

    public function testParseXmlWithCommentsAndPi(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!-- Global comment -->
<contacts>
    <?php echo "ignore me"; ?>
    <!-- Contact comment -->
    <email>comment_test@example.com</email>
</contacts>
XML;
        $result = $this->parser->parse($xml);

        $this->assertCount(1, $result);
        $this->assertContains('comment_test@example.com', $result);
    }

    public function testParseXmlWithUnicodeEmails(): void
    {
        $xml = <<<XML
<contacts>
    <email>pelé@example.com</email>
    <email>user@пе́льмени.рф</email>
    <email>user@xn--80a1a.xn--p1ai</email>
</contacts>
XML;
        $result = $this->parser->parse($xml);

        $this->assertContains('user@xn--80a1a.xn--p1ai', $result);
        $this->assertContains('pelé@example.com', $result);
        $this->assertContains('user@пе́льмени.рф', $result);
    }

    public function testParseXmlWithDuplicateEmails(): void
    {
        $xml = <<<XML
<contacts>
    <email>duplicate@example.com</email>
    <email>DUPLICATE@example.com</email>
    <email>duplicate@example.com</email>
    <email>unique@example.com</email>
</contacts>
XML;
        $result = $this->parser->parse($xml);

        $this->assertCount(2, $result);
        $this->assertContains('duplicate@example.com', $result);
        $this->assertContains('unique@example.com', $result);
    }

    public function testParseXmlWithBom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $xml = '<contacts><email>bom_test@example.com</email></contacts>';

        $result = $this->parser->parse($bom . $xml);

        $this->assertCount(1, $result);
        $this->assertContains('bom_test@example.com', $result);
    }
}
