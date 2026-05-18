{{--
    Fragmento HTML para el pie nativo de Chromium (`footerTemplate` / Browsershot::footerHtml).
    Logos en data URI (`ReportPdfPublicImageDataUri`).
    Evitar reglas que puedan filtrarse al documento principal; el `style` de reset de `html`/`body` solo vive en el fragmento del footerTemplate de Chromium.
    El bloque `flex:1` va *debajo* del contenido para rellenar hasta el borde inferior del iframe del pie.
    `html`/`body` sin margen: Chromium suele aplicar margen por defecto al subdocumento del footerTemplate (franja fina).
--}}
@php
    $resolvePublicAsset = static function (string $relativePublicPath): ?string {
        $path = public_path($relativePublicPath);

        return is_string($path) && $path !== '' && is_file($path) ? asset($relativePublicPath) : null;
    };

    $birdlifeLogoUrl = $footerPartnerBirdlifeUrl ?? $resolvePublicAsset('images/birdlife.png');
    $aucLogoUrl = $footerPartnerAucUrl ?? $resolvePublicAsset('images/auc.svg');
    $birdlifeSrc = \App\Services\ReportPdfPublicImageDataUri::fromRelativePublicPath('images/birdlife.png') ?? $birdlifeLogoUrl;
    $aucSrc = \App\Services\ReportPdfPublicImageDataUri::fromRelativePublicPath('images/auc.svg') ?? $aucLogoUrl;
    $reportNumberLabel = ($report !== null && $report->getKey() !== null)
        ? (string) $report->getKey()
        : '—';
    $clientAddressLine = trim((string) ($client->address ?? ''));
    $chromeFooterSlotMm = max(18, min(55, (int) config('services.report_pdf.chrome_footer_slot_mm', 28)));
    $chromeFooterYOffsetMm = max(-10, min(10, (int) config('services.report_pdf.chrome_footer_y_offset_mm', 6)));
@endphp
<style type="text/css">html,body{margin:0;padding:0;width:100%;height:100%;}</style>
<div style="width:100%;height:{{ $chromeFooterSlotMm }}mm;min-height:{{ $chromeFooterSlotMm }}mm;box-sizing:border-box;margin:0;padding:0;transform:translateY({{ $chromeFooterYOffsetMm }}mm);display:flex;flex-direction:column;background-color:#cfd4de;border-top:1px solid #b8c0cc;-webkit-print-color-adjust:exact;print-color-adjust:exact;font-family:DejaVu Sans,Helvetica,Arial,sans-serif;font-size:9px;line-height:1.35;color:#374151;">
    <div style="flex:0 0 auto;width:100%;box-sizing:border-box;display:flex;flex-wrap:nowrap;align-items:flex-end;justify-content:space-between;gap:10px;padding:5px 4px 4px 4px;">
        <div style="flex:1 1 0;min-width:0;padding:0 6px;">
            <div style="margin:0 0 2px 0;">
                <span style="font-weight:700;color:#1f2937;">Cliente</span>
                <span style="font-weight:700;font-size:11px;color:#1f2937;"> {{ $client->name }}</span>@if ($clientAddressLine !== '')
                    <span style="font-size:8px;font-weight:400;color:#4b5563;">, {{ $clientAddressLine }}</span>
                @endif
            </div>
            <div style="margin:0 0 2px 0;">
                <span style="font-weight:700;color:#1f2937;">Período</span>
                <span> {{ $period_label }}</span>
            </div>
            <div style="margin:0;">
                <span style="font-weight:700;color:#1f2937;">Informe Nº</span>
                <span> {{ $reportNumberLabel }}</span>
            </div>
        </div>
        <div style="display:flex;flex-wrap:nowrap;align-items:flex-end;justify-content:flex-end;gap:8px;flex:0 0 auto;padding:0 4px 0 0;">
            @if ($birdlifeSrc !== null)
                <img src="{{ $birdlifeSrc }}" alt="" style="display:block;height:56px;width:auto;max-width:190px;margin:0;object-fit:contain;">
            @endif
            @if ($aucSrc !== null)
                <img src="{{ $aucSrc }}" alt="" style="display:block;height:56px;width:auto;max-width:190px;margin:0;object-fit:contain;">
            @endif
        </div>
    </div>
    <div style="flex:1 1 auto;width:100%;min-height:0;background-color:#cfd4de;-webkit-print-color-adjust:exact;print-color-adjust:exact;"></div>
</div>
