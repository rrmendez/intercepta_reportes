@php
    /** @var bool $canProcessImport */
@endphp

<div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
    <div>
        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Listo para importar
        </h3>
        @if ($canProcessImport)
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Pulsa «Procesar importacion» en la barra inferior. Los archivos ya estan guardados; el sistema los procesara y podras ver el resultado en Importaciones de visitas en unos minutos.
            </p>
        @else
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-100/90">
                Todos los archivos tienen errores o no se pueden importar. Vuelve al paso anterior para corregirlos o sube otros archivos; el boton de importar permanecera deshabilitado hasta que al menos un archivo este listo.
            </p>
        @endif
    </div>
</div>
