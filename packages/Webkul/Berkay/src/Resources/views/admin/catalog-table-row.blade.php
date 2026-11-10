@php
    $editable = ! $item || $table->editablePrice($item);
    $price = (float) ($item?->price ?? 0);
    $sale = (float) ($item?->special_price ?? 0);
    $discount = $price > $sale && $sale > 0 ? round(100 * (1 - $sale / $price), 2) : 0;
    $discountAll = $parentRow && $item->type === 'configurable';
    if ($discountAll) {
        $variantDiscounts = $item->variants->map(function ($variant) {
            $regular = (float) $variant->price;
            $special = (float) $variant->special_price;

            return $regular > $special && $special > 0 ? round(100 * (1 - $special / $regular), 2) : 0;
        })->unique();
        $discount = $variantDiscounts->count() === 1 ? $variantDiscounts->first() : null;
    }
    $option = ! $parentRow && $item ? $color?->options->find($item->color) : null;
    $swatch = preg_match('/^#[0-9a-f]{6}$/i', $option?->swatch_value ?? '') ? $option->swatch_value : '#a8a29e';
@endphp
<tr data-row data-id="{{ $item?->id }}" @if ($parentRow) data-parent-row @else data-variant data-option="{{ $option?->id }}" hidden @endif>
    <td>
        <div class="bk-catalog-name">
            @if ($parentRow)
                @if ($item->variants->isNotEmpty() || $canAddColor)
                    <button type="button" data-expand aria-expanded="false" aria-label="{{ trans('berkay::table.variants') }}">⌄</button>
                @endif
                @if ($image = $item->images->first())
                    <img src="{{ asset('storage/'.$image->path) }}" alt="" width="36" height="36" loading="lazy">
                @endif
            @else
                <i data-swatch style="--swatch:{{ $swatch }}" title="{{ $option?->admin_name }}"></i>
            @endif
            <input name="name" aria-label="{{ trans('berkay::table.name') }}" value="{{ $item?->name }}" maxlength="200" required>
        </div>
        @unless ($parentRow)
            @php $details = $item?->additional['berkay_variant'] ?? []; @endphp
            <details class="bk-catalog-variant-details">
                <summary>{{ trans('berkay::app.variant-settings.title') }}</summary>
                <label>{{ trans('berkay::app.variant-dialog.colorDescription') }}
                    <input name="color_description" value="{{ $details['color_description'] ?? '' }}" maxlength="300">
                </label>
                <label>{{ trans('berkay::app.variant-dialog.material') }}
                    <select name="material">
                        <option value="">—</option>
                        @foreach ($materials as $material)
                            <option value="{{ $material->admin_name }}" @selected(($details['material'] ?? '') === $material->admin_name)>{{ $material->admin_name }}</option>
                        @endforeach
                    </select>
                </label>
                @foreach ([1, 2, 3] as $index)
                    <label>{{ trans('berkay::app.variant-dialog.feature') }} {{ $index }}
                        <input name="feature{{ $index }}" value="{{ $details['features'][$index - 1] ?? '' }}" maxlength="200">
                    </label>
                @endforeach
            </details>
        @endunless
    </td>
    <td><input name="price" aria-label="{{ trans('berkay::table.price') }}" type="number" inputmode="decimal" min="0" max="{{ $table::MAX_PRICE }}" step=".01" value="{{ $price }}" @disabled(!$editable)></td>
    <td><input name="quantity" aria-label="{{ trans('berkay::table.quantity') }}" type="number" inputmode="numeric" min="0" max="100000" value="{{ $item?->inventories->firstWhere('inventory_source_id', $source)?->qty ?? 0 }}" @disabled(!$source || $item?->getTypeInstance()->isComposite())></td>
    <td><input name="discount" aria-label="{{ trans('berkay::table.discount') }}" type="number" inputmode="decimal" min="0" max="99.99" step=".01" value="{{ $discount }}" @if($discountAll) title="{{ trans('berkay::table.discount-all') }}" placeholder="—" @endif @disabled(!$editable && !$discountAll)></td>
    <td><label class="bk-catalog-switch"><input name="status" type="checkbox" @checked($item?->status ?? true) aria-label="{{ trans('berkay::table.status') }}"><span aria-hidden="true"></span></label></td>
    <td><div class="bk-catalog-actions">
        <button type="button" data-save-row disabled>{{ trans('berkay::app.save') }}</button>
        @if ($parentRow)
            <a href="{{ route('admin.berkay.simple_product.edit', $item->id) }}">{{ trans('berkay::app.edit') }}</a>
        @endif
        @if (bouncer()->hasPermission('catalog.products.delete'))
            <button type="button" data-delete-row>{{ trans('berkay::table.delete') }}</button>
        @endif
    </div></td>
</tr>
