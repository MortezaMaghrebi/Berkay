<?php

namespace Webkul\Berkay\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;

class AttributeSettingsController extends Controller
{
    /** Save a named color or material without exposing technical attribute configuration. */
    public function save(Request $request, AttributeRepository $attributes, AttributeOptionRepository $options): RedirectResponse|JsonResponse
    {
        abort_unless(bouncer()->hasPermission('catalog.attributes.edit'), 403);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['color', 'material'])],
            'option_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $attribute = $attributes->findOneByField('code', $data['kind']);
        abort_unless($attribute, 404);
        $option = empty($data['option_id']) ? null : $options->findOrFail($data['option_id']);
        if ($option) {
            abort_unless((int) $option->attribute_id === (int) $attribute->id, 404);
        }
        if ($data['kind'] === 'color') {
            $request->validate(['color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        }
        $values = ['attribute_id' => $attribute->id, 'admin_name' => trim($data['name'])];
        foreach (core()->getAllLocales() as $locale) {
            $values[$locale->code] = ['label' => trim($data['name'])];
        }
        if ($data['kind'] === 'color') {
            $values['swatch_value'] = $data['color'];
        }
        if ($option) {
            $option = $options->update($values, $option->id);
        } else {
            $option = $options->create($values + ['sort_order' => 0]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => trans('berkay::app.saved'), 'html' => view('berkay::admin.attribute-option-row', ['attribute' => $attribute, 'option' => $option->fresh(), 'label' => $data['kind'] === 'color' ? 'colors' : 'materials'])->render()]);
        }

        return redirect()->route('admin.berkay.attributes.index')->with('success', trans('berkay::app.saved'));
    }
}
