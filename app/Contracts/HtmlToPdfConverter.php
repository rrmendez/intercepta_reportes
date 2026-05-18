<?php

namespace App\Contracts;

interface HtmlToPdfConverter
{
    /**
     * @param  array{chrome_footer_html?: string|null}  $options
     */
    public function convert(string $html, array $options = []): string;
}
