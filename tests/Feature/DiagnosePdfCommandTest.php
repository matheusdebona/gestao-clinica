<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiagnosePdfCommandTest extends TestCase
{
    public function test_command_prints_binary_candidates(): void
    {
        $this->artisan('pdf:diagnose')
            ->expectsOutputToContain('HOF Pay')
            ->expectsOutputToContain('chromium');
    }
}
