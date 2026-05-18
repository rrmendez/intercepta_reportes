<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'browsershot' => [
        /** Ruta absoluta al binario `node` si el proceso PHP no lo tiene en PATH. */
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        /** Solo si no usas puppeteer en node_modules del proyecto. */
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    ],

    /**
     * PDF mensual (Chromium): margenes de `PdfHtmlToBinaryConverter`.
     * - `margins_mm`: arriba e izquierda/derecha (y sangrado horizontal del pie fijo).
     * - `bottom_margin_mm`: solo cuando no hay pie nativo de Chromium; por defecto 0. Con pie Chrome,
     *   el margen inferior del PDF es exactamente `chrome_footer_slot_mm` (sin margen interior adicional).
     * - `chrome_footer_y_offset_mm`: compensa el inset inferior interno que Chromium deja bajo el
     *   `footerTemplate`; no agrega margen, solo desplaza el pie dentro del slot.
     */
    'report_pdf' => [
        'margins_mm' => max(0, min(40, (int) env('REPORT_PDF_MARGINS_MM', 12))),
        'bottom_margin_mm' => max(0, min(40, (int) env('REPORT_PDF_BOTTOM_MARGIN_MM', 0))),
        /** Alto reservado en el margen inferior del PDF para el pie nativo de Chromium (`footerTemplate`). */
        'chrome_footer_slot_mm' => max(18, min(55, (int) env('REPORT_PDF_CHROME_FOOTER_SLOT_MM', 28))),
        'chrome_footer_y_offset_mm' => max(-10, min(10, (int) env('REPORT_PDF_CHROME_FOOTER_Y_OFFSET_MM', 6))),
    ],

];
