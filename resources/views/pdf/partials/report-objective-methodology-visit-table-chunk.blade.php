@if ($visitColumnsChunk->isNotEmpty())
    <div class="report-objective-methodology-page__table-wrap">
        <table class="report-objective-methodology-page__table">
            <thead>
                <tr>
                    @foreach ($visitColumnsChunk as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($visitRows as $row)
                    <tr>
                        @foreach ($visitColumnsChunk as $column)
                            <td>{{ is_array($row) && array_key_exists($column['key'], $row) ? $row[$column['key']] : data_get($row, $column['key'], '') }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
