<form method="post" action="{{ route('admin.berkay.attributes.save') }}" class="bk-attribute-edit-row" data-failed="{{ trans('berkay::app.storefront-editor.failed') }}" data-saving="{{ trans('berkay::app.storefront-editor.loading') }}">
    @csrf
    <input type="hidden" name="kind" value="{{ $attribute->code }}">
    @if ($option)<input type="hidden" name="option_id" value="{{ $option->id }}">@endif
    <label>{{ trans('berkay::app.variant-settings.name') }}<input name="name" value="{{ $option?->admin_name }}" required maxlength="100" placeholder="{{ trans('berkay::app.variant-settings.'.($label === 'colors' ? 'new-color' : 'new-material')) }}" @disabled(!bouncer()->hasPermission('catalog.attributes.edit'))></label>
    @if ($label === 'colors')
        <label>{{ trans('berkay::app.variant-settings.pick') }}<input type="color" name="color" value="{{ preg_match('/^#[0-9a-fA-F]{6}$/', $option?->swatch_value ?? '') ? $option->swatch_value : '#714b32' }}" @disabled(!bouncer()->hasPermission('catalog.attributes.edit'))></label>
    @endif
    @if (bouncer()->hasPermission('catalog.attributes.edit'))<button type="submit">{{ trans('berkay::app.variant-settings.'.($option ? 'save-row' : 'add')) }}</button>@endif
<p class="bk-option-status" role="status" aria-live="polite"></p>
</form>
