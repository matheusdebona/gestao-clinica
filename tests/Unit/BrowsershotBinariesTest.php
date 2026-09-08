<?php

namespace Tests\Unit;

use App\Support\BrowsershotBinaries;
use Tests\TestCase;

class BrowsershotBinariesTest extends TestCase
{
    public function test_chrome_candidates_include_configured_and_common_paths(): void
    {
        config(['browsershot.chrome_path' => '/opt/chrome/chrome']);

        $candidates = BrowsershotBinaries::chromeCandidates();

        $this->assertContains('/opt/chrome/chrome', $candidates);
        $this->assertContains('/usr/bin/chromium', $candidates);
        $this->assertContains('/usr/bin/google-chrome-stable', $candidates);
    }

    public function test_empty_config_is_ignored(): void
    {
        config(['browsershot.chrome_path' => '']);

        $candidates = BrowsershotBinaries::chromeCandidates();

        $this->assertNotContains('', $candidates);
        $this->assertContains('/usr/bin/chromium', $candidates);
    }

    public function test_first_existing_executable_returns_null_for_missing_files(): void
    {
        $this->assertNull(BrowsershotBinaries::firstExistingExecutable([
            '/tmp/hofpay-missing-chrome-'.uniqid(),
        ]));
    }
}
