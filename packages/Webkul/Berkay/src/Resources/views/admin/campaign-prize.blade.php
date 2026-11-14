<article class="bk-campaign-prize" data-prize-row>
    <div class="bk-prize-heading">
        <strong data-prize-number>گزینهٔ گردونه</strong>
        <output data-prize-chance></output>
        @if ($canEdit)<button class="bk-outline-button" type="button" data-prize-remove>حذف گزینه</button>@endif
    </div>
    <label>عنوان جایزه
        <input name="prizes[{{ $i }}][label]" data-prize-field="label" maxlength="45" value="{{ $prize['label'] ?? '' }}" required>
    </label>
    <label>نوع جایزه
        <select name="prizes[{{ $i }}][type]" data-prize-field="type">
            @foreach ($types as $type => $label)
                <option value="{{ $type }}" @selected(($prize['type'] ?? 'none') === $type)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label data-prize-value><span>مقدار جایزه</span>
        <input name="prizes[{{ $i }}][value]" data-prize-field="value" type="number" min="0" max="9000000" value="{{ $prize['value'] ?? '' }}">
    </label>
    <label>وزن شانس
        <input name="prizes[{{ $i }}][weight]" data-prize-field="weight" type="number" min="1" max="10000" value="{{ $prize['weight'] ?? 1 }}" required>
    </label>
</article>
