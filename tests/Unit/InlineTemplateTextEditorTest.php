<?php

declare(strict_types=1);

use App\Services\Reports\InlineTemplateTextEditor;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->editor = new InlineTemplateTextEditor;
});

it('replaces literal HTML text in the source', function (): void {
    $source = '<h1 class="title">Objetivo y metodología</h1>';

    $result = $this->editor->replace($source, 'Objetivo y metodología', 'Objetivo y método');

    expect($result['ok'])->toBeTrue()
        ->and($result['source'])->toBe('<h1 class="title">Objetivo y método</h1>');
});

it('tolerates collapsed whitespace between the preview and the source', function (): void {
    $source = "<p>Registro   del\n  control de fauna</p>";

    $result = $this->editor->replace($source, 'Registro del control de fauna', 'Control de fauna');

    expect($result['ok'])->toBeTrue()
        ->and($result['source'])->toBe('<p>Control de fauna</p>');
});

it('escapes single quotes when editing inside a @php default block', function (): void {
    $source = "@php \$texto = \$texto ?? 'El principal objetivo es disminuir.'; @endphp\n<p>{{ \$texto }}</p>";

    $result = $this->editor->replace($source, 'El principal objetivo es disminuir.', "El cliente's objetivo");

    expect($result['ok'])->toBeTrue()
        ->and($result['source'])->toContain("'El cliente\\'s objetivo'");
});

it('escapes HTML special characters when editing markup text', function (): void {
    $source = '<p>Aves y plagas</p>';

    $result = $this->editor->replace($source, 'Aves y plagas', 'Aves & <plagas>');

    expect($result['ok'])->toBeTrue()
        ->and($result['source'])->toBe('<p>Aves &amp; &lt;plagas&gt;</p>');
});

it('reports dynamic text that does not exist in the source', function (): void {
    $source = '<p>{{ $cliente }}</p>';

    $result = $this->editor->replace($source, 'Acme Corporation', 'Otro Cliente');

    expect($result['ok'])->toBeFalse()
        ->and($result['reason'])->toBe('dynamic')
        ->and($result['source'])->toBe($source);
});

it('reports ambiguous selections when no context could be captured', function (): void {
    $source = '<td>Total</td><td>Total</td>';

    $result = $this->editor->replace($source, 'Total', 'Suma');

    expect($result['ok'])->toBeFalse()
        ->and($result['reason'])->toBe('ambiguous');
});

it('reports dynamic when a value only matches unrelated literals despite context', function (): void {
    $source = <<<'BLADE'
    <style>.x { font-size: 8pt; margin: 8mm; }</style>
    @php $t = $t ?? 'Capturas con cetrería:'; @endphp
    <p>{{ $t }} @forelse ($caps as $c) {{ $c['quantity'] }} {{ $c['name'] }} @endforelse</p>
    BLADE;

    $result = $this->editor->replace($source, '8', '12', before: 'Capturas con cetrería: ', after: ' Paloma');

    expect($result['ok'])->toBeFalse()
        ->and($result['reason'])->toBe('dynamic')
        ->and($result['source'])->toBe($source);
});

it('disambiguates repeated text using surrounding context', function (): void {
    $source = '<span>Inicio Total fin</span><span>Arranque Total extra</span>';

    $result = $this->editor->replace($source, 'Total', 'Suma', before: 'Arranque', after: 'extra');

    expect($result['ok'])->toBeTrue()
        ->and($result['source'])->toBe('<span>Inicio Total fin</span><span>Arranque Suma extra</span>');
});

it('replaces a single character when surrounding context anchors it', function (): void {
    $source = '<p>Se retiraron 5 nidos.</p>';

    $result = $this->editor->replace($source, '5', '8', before: 'Se retiraron ', after: ' nidos.');

    expect($result['ok'])->toBeTrue()
        ->and($result['source'])->toBe('<p>Se retiraron 8 nidos.</p>');
});

it('does not replace a short selection without trustworthy context', function (): void {
    $source = '<style>.x { margin: 5mm; }</style><p>Total</p>';

    $result = $this->editor->replace($source, '5', '8');

    expect($result['ok'])->toBeFalse()
        ->and($result['reason'])->toBe('dynamic')
        ->and($result['source'])->toBe($source);
});

it('picks the right occurrence among repeats in the same block using long context', function (): void {
    $source = '<p>El gorrión migró. Luego el gorrión volvió al sector.</p>';

    $result = $this->editor->replace(
        $source,
        'gorrión',
        'halcón',
        before: 'El gorrión migró. Luego el ',
        after: ' volvió al sector.',
    );

    expect($result['ok'])->toBeTrue()
        ->and($result['source'])->toBe('<p>El gorrión migró. Luego el halcón volvió al sector.</p>');
});

it('rejects empty selections', function (): void {
    $result = $this->editor->replace('<p>texto</p>', '   ', 'b');

    expect($result['ok'])->toBeFalse()
        ->and($result['reason'])->toBe('too_short');
});
