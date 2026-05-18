<?php

namespace Tests;

use App\Contracts\HtmlToPdfConverter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\StubHtmlToPdfConverter;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(HtmlToPdfConverter::class, StubHtmlToPdfConverter::class);
    }
}
