<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webkul\Berkay\Models\Article;

it('publishes a channel leather sample through the configuration form and can remove it', function () {
    $this->loginAsAdmin();
    Storage::fake(config('filesystems.default'));

    $route = route('admin.configuration.store', ['berkay', 'leather']);
    $channel = core()->getDefaultChannel()->code;
    $locale = core()->getDefaultLocaleCodeFromDefaultChannel();
    $config = ['channel' => $channel, 'locale' => $locale];
    $sample = 'برجستهٔ عسلی';

    $this->get(route('admin.configuration.index', ['berkay', 'leather']))
        ->assertOk()
        ->assertSee(trans('berkay::leather.title'));

    $this->post($route, $config + ['berkay' => ['leather' => ['patterned' => [
        'photo_1' => UploadedFile::fake()->image('swatch.jpg'),
        'caption_1' => $sample,
    ]]]])->assertSessionHasNoErrors()->assertRedirect();

    $path = DB::table('core_config')->where('code', 'berkay.leather.patterned.photo_1')
        ->where('channel_code', $channel)->value('value');

    expect($path)->toStartWith('configurations/');
    Storage::assertExists($path);
    $html = view('berkay::shop.leather-guide')->render();
    expect($html)->toContain(Storage::url($path), $sample, 'bk-leather-swatch--plain')
        ->not->toContain('bk-leather-swatch--patterned');

    $this->post($route, $config + ['berkay' => ['leather' => ['patterned' => [
        'photo_1' => ['delete' => 1],
    ]]]])->assertSessionHasNoErrors()->assertRedirect();

    Storage::assertMissing($path);
    expect(view('berkay::shop.leather-guide')->render())->toContain('bk-leather-swatch--patterned');
});

it('links a single card to the published article that contains the leather samples', function () {
    $migration = require base_path('packages/Webkul/Berkay/src/Database/Migrations/2026_11_10_000007_publish_leather_textures_guide.php');
    $migration->up();

    $slug = Article::LEATHER_GUIDE_SLUG;
    $url = route('shop.berkay.magazine.show', $slug);
    $card = view('berkay::shop.leather-guide-card')->render();

    expect($card)->toContain($url)->not->toContain('bk-leather-samples', 'bk-leather-guide-groups');
    $this->get($url)->assertOk()->assertSee(trans('berkay::leather.samples'));

    DB::table('berkay_articles')->where('slug', $slug)->update(['status' => 'draft']);
    expect(view('berkay::shop.leather-guide-card')->render())->not->toContain($url);
    $this->get($url)->assertNotFound();
});

it('rejects non-image uploads for the leather guide', function () {
    $this->loginAsAdmin();

    $this->post(route('admin.configuration.store', ['berkay', 'leather']), [
        'berkay' => ['leather' => ['plain' => ['photo_1' => UploadedFile::fake()->create('document.pdf')]]],
    ])->assertSessionHasErrors('berkay.leather.plain.photo_1');
});
