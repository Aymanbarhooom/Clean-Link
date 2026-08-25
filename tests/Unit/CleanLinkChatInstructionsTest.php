<?php

namespace Tests\Unit;

use App\Services\Chat\CleanLinkChatInstructions;
use PHPUnit\Framework\TestCase;

class CleanLinkChatInstructionsTest extends TestCase
{
    public function test_chat_is_restricted_to_clean_link_topics(): void
    {
        $instructions = CleanLinkChatInstructions::text();

        $this->assertStringContainsString(
            'scope is strictly limited to the Clean Link application',
            $instructions
        );
        $this->assertStringContainsString(
            'Do not answer general-knowledge',
            $instructions
        );
        $this->assertStringContainsString(
            'Do not call Clean Link tools for an unrelated question',
            $instructions
        );
        $this->assertStringNotContainsString(
            'you may answer normally',
            $instructions
        );
        $this->assertStringContainsString('Do not use Markdown headings', $instructions);
        $this->assertStringContainsString('do not use tables or nested bullet lists', $instructions);
        $this->assertStringContainsString('Arabic responses must follow the same clean plain-text rules', $instructions);
    }
}
