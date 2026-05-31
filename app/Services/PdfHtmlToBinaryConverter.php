<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HtmlToPdfConverter;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class PdfHtmlToBinaryConverter implements HtmlToPdfConverter
{
    /**
     * @param  array{chrome_footer_html?: string|null}  $options
     */
    public function convert(string $html, array $options = []): string
    {
        return $this->chromium($html, $options);
    }

    /**
     * @param  array{chrome_footer_html?: string|null}  $options
     */
    private function chromium(string $html, array $options = []): string
    {
        $nodeBinary = $this->resolveNodeBinary();
        $nodeModulesPath = base_path('node_modules');

        if (! is_dir($nodeModulesPath.'/puppeteer')) {
            throw new RuntimeException(
                'Falta el paquete npm `puppeteer`. En la raiz del proyecto ejecuta: npm install'
            );
        }

        $sideMarginMm = max(0, min(40, (int) config('services.report_pdf.margins_mm', 12)));
        $bottomMarginMm = max(0, min(40, (int) config('services.report_pdf.bottom_margin_mm', 0)));
        $chromeFooterSlotMm = max(18, min(55, (int) config('services.report_pdf.chrome_footer_slot_mm', 28)));

        $chromeFooterHtml = $options['chrome_footer_html'] ?? null;
        $hasChromeFooter = is_string($chromeFooterHtml) && trim($chromeFooterHtml) !== '';

        $browsershot = Browsershot::html($html)
            ->setNodeBinary($nodeBinary)
            ->setNodeModulePath($nodeModulesPath)
            ->emulateMedia('print')
            ->format('A4')
            ->showBackground()
            ->waitUntilNetworkIdle();

        if (str_contains($html, 'data-report-charts')) {
            $browsershot->waitForFunction(
                'window.__reportChartsReady === true && (!Array.isArray(window.__reportChartInstances) || window.__reportChartInstances.every((chart) => chart.chartArea && chart.chartArea.height > 0))',
                null,
                15_000,
            );
        }

        if ($hasChromeFooter) {
            /** Solo el slot del pie: `bottom_margin_mm` no se suma (evita margen inferior "extra" encima del pie). */
            $bottomMm = $chromeFooterSlotMm;

            $browsershot
                ->margins($sideMarginMm, $sideMarginMm, $bottomMm, $sideMarginMm, 'mm')
                ->showBrowserHeaderAndFooter()
                ->headerHtml('<div style="height:0;margin:0;padding:0;font-size:0;width:100%;overflow:hidden;">&nbsp;</div>')
                ->footerHtml($chromeFooterHtml);
        } else {
            $browsershot->margins($sideMarginMm, $sideMarginMm, $bottomMarginMm, $sideMarginMm, 'mm');
        }

        $this->applyChromeExecutable($browsershot);

        try {
            return $browsershot->pdf();
        } catch (ProcessFailedException $e) {
            $detail = $e->getMessage();
            $chromeHint = str_contains($detail, 'Could not find Chrome')
                ? ' Si Puppeteer no descargo Chrome, ejecuta en el proyecto: npm run puppeteer:install-chrome. '
                .'O instala Google Chrome, o define BROWSERSHOT_CHROME_PATH (ruta al ejecutable, p. ej. '
                .'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome en macOS). '
                : '';

            throw new RuntimeException(
                'No se pudo generar el PDF con Chromium.'.$chromeHint
                .'Comprueba Node (BROWSERSHOT_NODE_BINARY si hace falta). Detalle: '.$detail,
                0,
                $e,
            );
        }
    }

    private function resolveNodeBinary(): string
    {
        foreach ($this->explicitNodeBinaryCandidates() as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_executable($candidate)) {
                return $candidate;
            }
        }

        foreach ($this->versionManagerNodeBinaries() as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $extraDirs = array_values(array_unique(array_filter([
            ...$this->staticNodeSearchDirs(),
            ...$this->nvmNodeBinaryParentDirs(),
            ...$this->herdNvmBinaryParentDirs(),
        ])));

        $finder = new ExecutableFinder;
        $found = $finder->find('node', null, $extraDirs);

        if (is_string($found) && $found !== '' && is_executable($found)) {
            return $found;
        }

        $fromShell = $this->resolveNodeBinaryViaLoginShell();
        if ($fromShell !== null) {
            return $fromShell;
        }

        throw new RuntimeException(
            'No se encontro el ejecutable `node`. Instala Node.js (p. ej. brew install node), '
            .'ejecuta `npm install` en el proyecto para instalar puppeteer, y define en .env '
            .'BROWSERSHOT_NODE_BINARY con la ruta absoluta al ejecutable (en la terminal: '
            .'`which node` o `command -v node`). Con Herd/PHP-FPM el PATH suele ser minimo.'
        );
    }

    /**
     * @return list<string>
     */
    private function explicitNodeBinaryCandidates(): array
    {
        $fromEnv = getenv('BROWSERSHOT_NODE_BINARY');
        $fromServer = $_SERVER['BROWSERSHOT_NODE_BINARY'] ?? null;
        $fromConfig = config('services.browsershot.node_binary');

        return [
            is_string($fromEnv) ? $fromEnv : '',
            is_string($fromServer) ? $fromServer : '',
            is_string($fromConfig) ? $fromConfig : '',
        ];
    }

    /**
     * Rutas absolutas conocidas (Homebrew versionado, nvm, Volta, asdf, fnm).
     *
     * @return list<string>
     */
    private function versionManagerNodeBinaries(): array
    {
        $paths = [
            ...$this->sortedHomebrewNodeBinaries(),
            ...$this->sortedHerdBundledNvmNodeBinaries(),
            ...$this->sortedNvmNodeBinaries(),
        ];

        $home = $this->userHomeDirectory();
        if ($home !== null) {
            $paths[] = $home.'/.volta/bin/node';
            $paths[] = $home.'/.asdf/shims/node';
            $paths = array_merge($paths, glob($home.'/.local/share/fnm/node-versions/*/installation/bin/node') ?: []);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private function sortedHomebrewNodeBinaries(): array
    {
        $paths = [];
        foreach (['/opt/homebrew/opt', '/usr/local/opt'] as $optRoot) {
            $paths = array_merge($paths, glob($optRoot.'/node@*/bin/node', GLOB_NOSORT) ?: []);
        }

        usort($paths, function (string $a, string $b): int {
            return version_compare($this->brewNodeVersionTag($b), $this->brewNodeVersionTag($a));
        });

        return $paths;
    }

    private function brewNodeVersionTag(string $path): string
    {
        if (preg_match('#/node@([^/]+)/bin/node$#', $path, $m) === 1) {
            return $m[1];
        }

        return '0';
    }

    /**
     * Node que instala Laravel Herd (nvm embebido).
     *
     * @return list<string>
     */
    private function sortedHerdBundledNvmNodeBinaries(): array
    {
        $home = $this->userHomeDirectory();
        if ($home === null) {
            return [];
        }

        $pattern = $home.'/Library/Application Support/Herd/config/nvm/versions/node/*/bin/node';
        $paths = glob($pattern, GLOB_NOSORT) ?: [];
        usort($paths, function (string $a, string $b): int {
            return version_compare($this->herdNvmNodeVersionTag($b), $this->herdNvmNodeVersionTag($a));
        });

        return $paths;
    }

    private function herdNvmNodeVersionTag(string $path): string
    {
        if (preg_match('#/node/(v[\d.]+)/bin/node$#', $path, $m) === 1) {
            return ltrim($m[1], 'v');
        }

        return '0';
    }

    /**
     * @return list<string>
     */
    private function herdNvmBinaryParentDirs(): array
    {
        $home = $this->userHomeDirectory();
        if ($home === null) {
            return [];
        }

        return glob($home.'/Library/Application Support/Herd/config/nvm/versions/node/*/bin', GLOB_NOSORT) ?: [];
    }

    /**
     * @return list<string>
     */
    private function sortedNvmNodeBinaries(): array
    {
        $home = $this->userHomeDirectory();
        if ($home === null) {
            return [];
        }

        $paths = glob($home.'/.nvm/versions/node/*/bin/node', GLOB_NOSORT) ?: [];
        usort($paths, function (string $a, string $b): int {
            return version_compare($this->nvmFolderVersion($b), $this->nvmFolderVersion($a));
        });

        return $paths;
    }

    private function nvmFolderVersion(string $path): string
    {
        $dir = dirname($path, 2);
        $base = basename($dir);

        return ltrim($base, 'v');
    }

    /**
     * @return list<string>
     */
    private function nvmNodeBinaryParentDirs(): array
    {
        $home = $this->userHomeDirectory();
        if ($home === null) {
            return [];
        }

        $dirs = glob($home.'/.nvm/versions/node/*/bin', GLOB_NOSORT) ?: [];

        return $dirs;
    }

    /**
     * @return list<string>
     */
    private function staticNodeSearchDirs(): array
    {
        $dirs = [
            '/opt/homebrew/bin',
            '/usr/local/bin',
            '/usr/local/opt/node/bin',
        ];

        $prefix = $this->homebrewNodePrefix();
        if ($prefix !== '') {
            $dirs[] = $prefix.'/bin';
        }

        return array_values(array_filter($dirs));
    }

    private function homebrewNodePrefix(): string
    {
        foreach (['/opt/homebrew/opt/node', '/usr/local/opt/node'] as $prefix) {
            if (is_executable($prefix.'/bin/node')) {
                return $prefix;
            }
        }

        return '';
    }

    private function applyChromeExecutable(Browsershot $browsershot): void
    {
        $path = $this->resolveChromeExecutablePath();

        if ($path !== null) {
            $browsershot->setChromePath($path);
        }
    }

    private function resolveChromeExecutablePath(): ?string
    {
        $fromConfig = config('services.browsershot.chrome_path');
        $candidates = [
            is_string($fromConfig) && $fromConfig !== '' ? $fromConfig : null,
            $this->nonEmptyStringOrNull(getenv('BROWSERSHOT_CHROME_PATH') ?: null),
            $this->nonEmptyStringOrNull(getenv('PUPPETEER_EXECUTABLE_PATH') ?: null),
            ...$this->defaultChromeExecutableCandidates(),
        ];

        foreach ($candidates as $path) {
            if ($path !== null && $path !== '' && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  non-empty-string|null  $value
     */
    private function nonEmptyStringOrNull(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @return list<non-empty-string>
     */
    private function defaultChromeExecutableCandidates(): array
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return [
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
                '/Applications/Google Chrome Canary.app/Contents/MacOS/Google Chrome Canary',
                '/Applications/Chromium.app/Contents/MacOS/Chromium',
                '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
            ];
        }

        if (PHP_OS_FAMILY === 'Linux') {
            return [
                '/usr/bin/google-chrome-stable',
                '/usr/bin/google-chrome',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/snap/bin/chromium',
            ];
        }

        return [];
    }

    private function resolveNodeBinaryViaLoginShell(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        $home = $this->userHomeDirectory();
        $cwd = $home ?? base_path();

        $commands = [
            "/bin/zsh -lic 'command -v node'",
            "/bin/bash -lc 'command -v node'",
        ];

        foreach ($commands as $shellCommand) {
            $process = Process::fromShellCommandline($shellCommand, $cwd);
            $process->setTimeout(3.0);
            $process->run();

            if (! $process->isSuccessful()) {
                continue;
            }

            $path = trim($process->getOutput());
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function userHomeDirectory(): ?string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME');

        return is_string($home) && $home !== '' ? $home : null;
    }
}
