<?php

use Webkul\Berkay\Repositories\CatalogFeedRepository;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Berkay\Services\TorobToken;

// ==== Torob Product Selection ====

it('applies admin choices to every Torob lookup mode', function () {
    $this->loginAsAdmin();
    $channel = core()->getCurrentChannel();
    $channel->base_currency->code = 'IRT';
    app()->setLocale($channel->default_locale->code);
    $first = $this->createSimpleProduct();
    $second = $this->createSimpleProduct();
    $ids = [(string) $first->id, (string) $second->id];
    $feed = route('shop.berkay.torob.products');
    app(IntegrationSettings::class)->put('torob.enabled', true);
    $this->mock(TorobToken::class, fn ($mock) => $mock->shouldReceive('verify')->andReturn(true));
    $headers = ['X-Torob-Token-Version' => '1', 'X-Torob-Token' => 'fixture-token'];

    $this->postJson($feed, ['page_uniques' => $ids], $headers)->assertOk()->assertJsonCount(2, 'products');
    $this->put(route('admin.berkay.torob_products.mode'), ['default_export' => '0'])->assertSessionHasNoErrors();
    $this->postJson($feed, ['page_uniques' => $ids], $headers)->assertOk()->assertJsonCount(0, 'products');

    $this->put(route('admin.berkay.torob_products.update'), [
        'product_ids' => [$first->id], 'enabled' => '1',
    ])->assertSessionHasNoErrors();

    foreach ([
        ['page_uniques' => $ids],
        ['page_urls' => [url($first->url_key), url($second->url_key)]],
    ] as $input) {
        $this->postJson($feed, $input, $headers)->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.page_unique', (string) $first->id);
    }

    foreach ([
        ['page' => 1, 'sort' => 'date_added_desc'],
        ['page' => 1, 'sort' => 'date_updated_desc'],
        ['sort' => 'product_id_desc'],
        ['sort' => 'product_id_desc', 'cursor' => (string) $second->id],
    ] as $input) {
        $this->postJson($feed, $input, $headers)->assertOk()
            ->assertJsonFragment(['page_unique' => (string) $first->id])
            ->assertJsonMissing(['page_unique' => (string) $second->id]);
    }

    $this->postJson($feed, ['page_uniques' => [(string) $second->id]], $headers)
        ->assertOk()->assertJsonPath('total', 0)->assertJsonPath('products', []);
    $this->postJson($feed, ['sort' => 'product_id_desc', 'cursor' => (string) $first->id], $headers)
        ->assertOk()->assertJsonMissing(['page_unique' => (string) $first->id]);
});

it('bulk edits only matching eligible products and keeps explicit exclusions', function () {
    $this->loginAsAdmin();
    $channel = core()->getCurrentChannel();
    app()->setLocale($channel->default_locale->code);
    $locale = $channel->default_locale->code;
    $first = $this->createSimpleProduct(['name' => ['text_value' => 'Torob batch leather', 'locale' => $locale]]);
    $other = $this->createSimpleProduct(['name' => ['text_value' => 'Torob batch canvas', 'locale' => $locale]]);
    $hidden = $this->createSimpleProduct([
        'name' => ['text_value' => 'Torob batch hidden', 'locale' => $locale],
        'status' => ['boolean_value' => false, 'channel' => $channel->code],
    ]);
    $category = $channel->root_category_id;
    $first->categories()->attach($category);
    $hidden->categories()->attach($category);
    $filters = ['q' => 'Torob batch', 'category' => $category, 'state' => 'included'];

    $this->get(route('admin.berkay.torob_products.index', $filters))->assertOk()
        ->assertSee('Torob batch leather')->assertDontSee('Torob batch canvas')->assertDontSee('Torob batch hidden');
    $this->putJson(route('admin.berkay.torob_products.update', $filters), ['enabled' => '0'])
        ->assertUnprocessable()->assertJsonValidationErrors('product_ids');
    $this->put(route('admin.berkay.torob_products.update', $filters), [
        'scope' => 'filtered', 'enabled' => '0',
    ])->assertSessionHasNoErrors();

    $pivot = fn ($product) => $product->channels()->whereKey($channel->id)->withPivot('torob_export')->first()->pivot->torob_export;
    expect($pivot($first))->not->toBeNull()->toEqual(0)
        ->and($pivot($other))->toBeNull()->and($pivot($hidden))->toBeNull();
    $catalog = app(CatalogFeedRepository::class);
    $catalog->setExportDefault($channel->id, false);
    $catalog->setExportDefault($channel->id, true);
    $products = $catalog->feed(['page_uniques' => [$first->id, $other->id]], $channel->id, $channel->code, $channel->default_locale->code);

    expect($products['items']->modelKeys())->toBe([$other->id]);
});

it('grants read-only access without allowing staff to change selections', function () {
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.integrations']);

    $this->get(route('admin.berkay.torob_products.index'))->assertOk()->assertDontSee('id="torob-bulk"', false);
    $this->put(route('admin.berkay.torob_products.mode'), ['default_export' => '0'])->assertUnauthorized();
    $this->put(route('admin.berkay.torob_products.update'), ['scope' => 'filtered', 'enabled' => '0'])->assertUnauthorized();
});
