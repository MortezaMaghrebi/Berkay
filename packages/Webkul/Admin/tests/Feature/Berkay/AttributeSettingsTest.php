<?php

use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;

it('saves simple color choices and refuses invalid colors or cross-attribute edits', function () {
    $this->loginAsAdmin();
    $attribute = app(AttributeRepository::class)->findOneByField('code', 'color');
    $option = app(AttributeOptionRepository::class)->create(['attribute_id' => $attribute->id, 'admin_name' => 'Test color', 'sort_order' => 0]);
    $url = route('admin.berkay.attributes.save');
    $this->post($url, ['kind' => 'color', 'option_id' => $option->id, 'name' => 'Olive', 'color' => '#556b2f'])->assertSessionHasNoErrors()->assertRedirect();
    expect($option->fresh()->admin_name)->toBe('Olive')->and($option->fresh()->swatch_value)->toBe('#556b2f');
    $this->postJson($url, ['kind' => 'color', 'option_id' => $option->id, 'name' => 'Bad', 'color' => 'red'])->assertUnprocessable();
    $this->postJson($url, ['kind' => 'material', 'option_id' => $option->id, 'name' => 'Bad'])->assertNotFound();
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.attributes', 'catalog.attributes']);
    $this->postJson($url, ['kind' => 'color', 'name' => 'Bad', 'color' => '#ffffff'])->assertForbidden();
});
