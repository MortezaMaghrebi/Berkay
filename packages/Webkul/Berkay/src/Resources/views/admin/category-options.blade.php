@if ($node->id !== $excluded)
    <option value="{{ $node->id }}" @selected((string) $selected === (string) $node->id)>{{ str_repeat('— ', $depth) }}{{ $node->name }}</option>
    @foreach ($node->children as $child)
        @include('berkay::admin.category-options', ['node' => $child, 'depth' => $depth + 1, 'selected' => $selected, 'excluded' => $excluded])
    @endforeach
@endif
