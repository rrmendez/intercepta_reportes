@php
    /** @var array<int, array<string, mixed>> $preview */
@endphp

@foreach ($preview as $entry)
    <x-import.file-preview-item :entry="$entry" />
@endforeach
