<?php

/**
 * Unit Tests — Input Sanitization Helper (XSS Prevention)
 *
 * Tests the sanitizeString(), sanitizeNumeric(), and sanitizeArray() functions
 * defined in src/helpers/sanitize.php.
 *
 * These tests verify that dangerous HTML/JavaScript input is properly escaped
 * to prevent Cross-Site Scripting (XSS) attacks.
 *
 * Requirements: 7.1, 7.2
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers sanitizeString
 * @covers sanitizeNumeric
 * @covers sanitizeArray
 */
class SanitizeTest extends TestCase
{
    // =========================================================================
    // Section 1 — sanitizeString(): XSS escaping
    // =========================================================================

    /**
     * <script> tags must be escaped to prevent script injection.
     *
     * Requirements: 7.1, 7.2
     */
    public function testScriptTagIsEscaped(): void
    {
        $input  = '<script>alert("xss")</script>';
        $output = sanitizeString($input);

        $this->assertStringNotContainsString('<script>', $output,
            '<script> tag must be escaped');
        $this->assertStringContainsString('&lt;script&gt;', $output,
            'Opening <script> must become &lt;script&gt;');
    }

    /**
     * <img onerror> event handler must be escaped.
     *
     * Requirements: 7.1, 7.2
     */
    public function testImgOnerrorIsEscaped(): void
    {
        $input  = '<img src=x onerror=alert(1)>';
        $output = sanitizeString($input);

        $this->assertStringNotContainsString('<img', $output,
            '<img tag must be escaped');
        $this->assertStringContainsString('&lt;img', $output,
            'Opening < of <img must become &lt;');
    }

    /**
     * Double-quote characters must be escaped to &#34; or &quot;.
     *
     * Requirements: 7.1, 7.2
     */
    public function testDoubleQuoteIsEscaped(): void
    {
        $input  = '"hello"';
        $output = sanitizeString($input);

        $this->assertStringNotContainsString('"', $output,
            'Double quotes must be escaped');
        // htmlspecialchars with ENT_QUOTES encodes " as &quot;
        $this->assertStringContainsString('&quot;', $output,
            'Double quote must become &quot;');
    }

    /**
     * Single-quote characters must be escaped to &#039; or &apos;.
     *
     * Requirements: 7.1, 7.2
     */
    public function testSingleQuoteIsEscaped(): void
    {
        $input  = "it's a test";
        $output = sanitizeString($input);

        $this->assertStringNotContainsString("'", $output,
            "Single quotes must be escaped");
        // htmlspecialchars with ENT_QUOTES|ENT_HTML5 encodes ' as &apos;
        $this->assertStringContainsString('&apos;', $output,
            "Single quote must become &apos; (ENT_HTML5 encoding)");
    }

    /**
     * Ampersand must be escaped to &amp;.
     *
     * Requirements: 7.1, 7.2
     */
    public function testAmpersandIsEscaped(): void
    {
        $input  = 'Tom & Jerry';
        $output = sanitizeString($input);

        // The raw & should be gone (replaced by &amp;)
        // We check that the output contains &amp; and not a bare &
        $this->assertStringContainsString('&amp;', $output,
            'Ampersand must become &amp;');
        // Ensure no bare & remains (only & followed by amp; is acceptable)
        $this->assertDoesNotMatchRegularExpression('/&(?!amp;|lt;|gt;|quot;|#039;|apos;)/', $output,
            'No bare & should remain after sanitization');
    }

    /**
     * Greater-than sign must be escaped to &gt;.
     *
     * Requirements: 7.1, 7.2
     */
    public function testGreaterThanIsEscaped(): void
    {
        $input  = '5 > 3';
        $output = sanitizeString($input);

        $this->assertStringNotContainsString('>', $output,
            '> must be escaped');
        $this->assertStringContainsString('&gt;', $output,
            '> must become &gt;');
    }

    /**
     * Less-than sign must be escaped to &lt;.
     *
     * Requirements: 7.1, 7.2
     */
    public function testLessThanIsEscaped(): void
    {
        $input  = '3 < 5';
        $output = sanitizeString($input);

        $this->assertStringNotContainsString('<', $output,
            '< must be escaped');
        $this->assertStringContainsString('&lt;', $output,
            '< must become &lt;');
    }

    /**
     * Normal alphanumeric text must pass through unchanged.
     *
     * Requirements: 7.2
     */
    public function testNormalTextPassesThroughUnchanged(): void
    {
        $input  = 'Hello World 123';
        $output = sanitizeString($input);

        $this->assertSame($input, $output,
            'Plain alphanumeric text must not be modified');
    }

    /**
     * An empty string must pass through as an empty string.
     *
     * Requirements: 7.2
     */
    public function testEmptyStringPassesThroughUnchanged(): void
    {
        $output = sanitizeString('');

        $this->assertSame('', $output,
            'Empty string must remain empty after sanitization');
    }

    /**
     * A complex XSS payload combining multiple vectors must be fully escaped.
     *
     * Requirements: 7.1, 7.2
     */
    public function testComplexXssPayloadIsEscaped(): void
    {
        $input  = '<script>document.cookie="stolen="+document.cookie</script>';
        $output = sanitizeString($input);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    // =========================================================================
    // Section 2 — sanitizeNumeric(): numeric validation
    // =========================================================================

    /**
     * An integer value must be accepted and returned as float.
     *
     * Requirements: 7.2
     */
    public function testSanitizeNumericAcceptsInteger(): void
    {
        $result = sanitizeNumeric(42);

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(42.0, $result, 0.0001);
    }

    /**
     * A float value must be accepted and returned as float.
     *
     * Requirements: 7.2
     */
    public function testSanitizeNumericAcceptsFloat(): void
    {
        $result = sanitizeNumeric(3.14);

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(3.14, $result, 0.0001);
    }

    /**
     * A numeric string must be accepted and parsed to float.
     *
     * Requirements: 7.2
     */
    public function testSanitizeNumericAcceptsNumericString(): void
    {
        $result = sanitizeNumeric('0.75');

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(0.75, $result, 0.0001);
    }

    /**
     * A non-numeric string must be rejected (returns false).
     *
     * Requirements: 7.2
     */
    public function testSanitizeNumericRejectsNonNumericString(): void
    {
        $result = sanitizeNumeric('abc');

        $this->assertFalse($result,
            'Non-numeric string must return false');
    }

    /**
     * An empty string must be rejected (returns false).
     *
     * Requirements: 7.2
     */
    public function testSanitizeNumericRejectsEmptyString(): void
    {
        $result = sanitizeNumeric('');

        $this->assertFalse($result,
            'Empty string must return false');
    }

    /**
     * A boolean value must be rejected (returns false).
     *
     * Requirements: 7.2
     */
    public function testSanitizeNumericRejectsBoolean(): void
    {
        $this->assertFalse(sanitizeNumeric(true),
            'Boolean true must return false');
        $this->assertFalse(sanitizeNumeric(false),
            'Boolean false must return false');
    }

    /**
     * A string with only whitespace must be rejected (returns false).
     *
     * Requirements: 7.2
     */
    public function testSanitizeNumericRejectsWhitespaceString(): void
    {
        $result = sanitizeNumeric('   ');

        $this->assertFalse($result,
            'Whitespace-only string must return false');
    }

    /**
     * A numeric string with surrounding whitespace must be accepted.
     *
     * Requirements: 7.2
     */
    public function testSanitizeNumericAcceptsNumericStringWithWhitespace(): void
    {
        $result = sanitizeNumeric('  42  ');

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(42.0, $result, 0.0001);
    }

    // =========================================================================
    // Section 3 — sanitizeArray(): recursive sanitization
    // =========================================================================

    /**
     * sanitizeArray() must escape string values in a flat array.
     *
     * Requirements: 7.2
     */
    public function testSanitizeArrayEscapesStrings(): void
    {
        $input  = ['name' => '<b>Bold</b>', 'value' => 'normal'];
        $output = sanitizeArray($input);

        $this->assertStringContainsString('&lt;b&gt;', $output['name'],
            'HTML tags in array values must be escaped');
        $this->assertSame('normal', $output['value'],
            'Plain text values must pass through unchanged');
    }

    /**
     * sanitizeArray() must recursively sanitize nested arrays.
     *
     * Requirements: 7.2
     */
    public function testSanitizeArrayRecursivelySanitizesNestedArrays(): void
    {
        $input = [
            'level1' => [
                'level2' => '<script>alert(1)</script>',
            ],
        ];
        $output = sanitizeArray($input);

        $this->assertStringNotContainsString('<script>', $output['level1']['level2'],
            'Nested <script> tag must be escaped');
        $this->assertStringContainsString('&lt;script&gt;', $output['level1']['level2'],
            'Nested <script> must become &lt;script&gt;');
    }

    /**
     * sanitizeArray() must leave non-string values (int, float, bool) unchanged.
     *
     * Requirements: 7.2
     */
    public function testSanitizeArrayPreservesNonStringValues(): void
    {
        $input  = ['count' => 5, 'ratio' => 0.75, 'active' => true];
        $output = sanitizeArray($input);

        $this->assertSame(5, $output['count']);
        $this->assertEqualsWithDelta(0.75, $output['ratio'], 0.0001);
        $this->assertTrue($output['active']);
    }

    /**
     * sanitizeArray() on an empty array must return an empty array.
     *
     * Requirements: 7.2
     */
    public function testSanitizeArrayHandlesEmptyArray(): void
    {
        $output = sanitizeArray([]);

        $this->assertSame([], $output,
            'Empty array must return empty array');
    }
}
