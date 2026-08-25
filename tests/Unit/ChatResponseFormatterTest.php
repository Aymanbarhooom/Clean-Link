<?php

namespace Tests\Unit;

use App\Services\Chat\ChatResponseFormatter;
use PHPUnit\Framework\TestCase;

class ChatResponseFormatterTest extends TestCase
{
    public function test_it_removes_decorative_markdown_and_excess_blank_lines(): void
    {
        $input = "### ***Available Services***\n\n\n- **Home Cleaning**\n* Office Cleaning\n\n\n\n**Price:** $20";
        $expected = "Available Services\n\nHome Cleaning\nOffice Cleaning\n\nPrice: $20";

        $this->assertSame($expected, (new ChatResponseFormatter())->format($input));
    }

    public function test_it_preserves_business_values_urls_hyphens_arabic_and_numbered_steps(): void
    {
        $input = <<<'TEXT'
Price: $20-$40
Date: 2026-08-25
Adjustment: -20
Company: Sparkle-Clean
URL: https://cleanlink.example/help#booking
العنوان: حي-النور

1. Choose a company
2. Choose a package
TEXT;

        $this->assertSame($input, (new ChatResponseFormatter())->format($input));
    }

    public function test_it_does_not_change_markdown_like_characters_inside_urls_or_sentences(): void
    {
        $input = "Open https://example.com/a-b?q=**value** if needed.\nThe total - including tax - is $25.";
        $formatted = (new ChatResponseFormatter())->format($input);

        $this->assertStringContainsString('https://example.com/a-b?q=**value**', $formatted);
        $this->assertStringContainsString('total - including tax -', $formatted);
    }
}
