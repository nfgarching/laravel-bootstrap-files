@props(['field'])

@php
    use Nfgarching\Files\Models\File;

    $filterFields = ['disk', 'state', 'type'];
    $where = collect($filterFields)
        ->filter(fn ($f) => $f !== $field && request()->get($f))
        ->map(fn ($f) => [$f, request()->get($f)])
        ->values()
        ->all();

    $options = File::select($field)
        ->selectRaw("count($field) as count")
        ->where($where)
        ->groupBy($field)
        ->orderBy($field)
        ->get()
        ->mapWithKeys(function ($row) use ($field) {
            $val = $row->$field;
            $label = $val instanceof \BackedEnum ? $val->label() : (string) $val;
            $key = $val instanceof \BackedEnum ? $val->value : $val;

            return [$key => "$label ({$row->count})"];
        });

    $oldval = request()->get($field);
@endphp

<x-table.filter.select :$field :$options :$oldval />
