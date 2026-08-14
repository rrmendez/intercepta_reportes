<?php

use App\Services\VisitObservations\VisitObservationMetricsExtractor;

it('extracts nests removed and pigeon captures from observation text', function (
    ?string $observation,
    int $expectedNests,
    int $expectedFalconry,
    int $expectedTrap,
) {
    $metrics = (new VisitObservationMetricsExtractor)->extract($observation);

    expect($metrics->nestsRemoved)->toBe($expectedNests)
        ->and($metrics->capturesFalconry)->toBe($expectedFalconry)
        ->and($metrics->capturesTrap)->toBe($expectedTrap);
})->with([
    'captura con número en palabra' => ["Se recorre planta 4 en su totalidad \nSe captura una paloma", 0, 1, 0],
    'capturan dos palomas' => ["Se recorre planta 4 en su totalidad\nSe capturan dos palomas", 0, 2, 0],
    'quita nido sin número' => ["Se recorre completamente planta 3\nSe quita nido en Doblados", 1, 0, 0],
    'captura con dígito' => ['Se captura 1 paloma', 0, 1, 0],
    'retira nidos con dígito' => ['Se retira 2 nidos', 2, 0, 0],
    'capturaron con dígito' => ['se capturaron 4 palomas', 0, 4, 0],
    'captura en trampa' => ['Se captura 1 paloma en trampa', 0, 0, 1],
    'trampa en plural' => ['Se capturaron tres palomas en las trampas', 0, 0, 3],
    'frases múltiples suman' => ["Se capturan dos palomas.\nSe quita un nido en azotea. Se captura una paloma en trampa", 1, 2, 1],
    'mayúsculas y tildes' => ['SE CAPTURÓ UNA PALOMA', 0, 1, 0],
    'nidos retirados invertido' => ['2 nidos retirados en el galpón', 2, 0, 0],
    'palomas capturadas invertido' => ['dos palomas capturadas', 0, 2, 0],
    'atrapan palomas' => ['se atraparon cinco palomas', 0, 5, 0],
    'sacan nidos' => ['se sacan tres nidos del techo', 3, 0, 0],
    'remueve nido' => ['se remueve nido en cornisa', 1, 0, 0],
    'extrae nido' => ['Se extrae un nido en recupero planta 4', 1, 0, 0],
    'extraen nidos' => ['Se extraen 3 nidos', 3, 0, 0],
    'gorriones no cuentan como palomas' => ['Se capturan 2 gorriones', 0, 0, 0],
    'texto sin métricas' => ['Se recorre la planta sin novedades', 0, 0, 0],
    'número de planta no contamina' => ['Se recorre planta 4 en su totalidad', 0, 0, 0],
    'observación vacía' => ['', 0, 0, 0],
    'observación null' => [null, 0, 0, 0],
    'nido observado no cuenta' => ['se observa un nido en la cornisa', 0, 0, 0],
    'cantidad absurda se recorta al tope smallint' => ['se capturan 99999 palomas', 0, 32767, 0],
    'palomas avistadas no cuentan' => ['se observan 12 palomas en el techo', 0, 0, 0],
]);
