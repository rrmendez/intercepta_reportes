<div class="space-y-4">
    <style>
        .template-preview .page-break {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 2rem 0;
            color: rgb(107 114 128);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .template-preview .page-break::before,
        .template-preview .page-break::after {
            content: "";
            flex: 1;
            border-top: 1px dashed rgb(209 213 219);
        }

        .template-preview .page-break::after {
            content: "Nueva pagina";
            display: flex;
            justify-content: center;
            border-top: 0;
        }
    </style>

    <div class="template-preview">
        {!! $previewHtml !!}
    </div>
</div>
