<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Berkay\Repositories\ArticleRepository;

it('stores a validated article cover and preserves it on a text-only edit', function () {
    Storage::fake('public');
    $this->loginAsAdmin();
    $payload = ['title' => 'Covered guide', 'slug' => 'covered-guide-test', 'body' => '<h2>Choosing a bag</h2><p>A useful guide.</p>', 'status' => 'published'];
    $this->post(route('admin.berkay.articles.store'), $payload + [
        'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
        'cover_alt' => 'A leather bag on a table',
    ])->assertRedirect(route('admin.berkay.articles.index'));

    $articles = app(ArticleRepository::class);
    $article = $articles->findOneByField('slug', $payload['slug']);
    $path = $article->cover_path;
    expect($path)->toStartWith('berkay/articles/');
    Storage::disk('public')->assertExists($path);

    $this->put(route('admin.berkay.articles.update', $article->id), $payload)->assertRedirect(route('admin.berkay.articles.index'));
    expect($articles->find($article->id)->cover_path)->toBe($path);
    $this->get(route('shop.berkay.magazine.show', $article->slug))->assertOk()
        ->assertSee('section-1')->assertSee('A leather bag on a table')->assertSee($path);
});

it('rejects executable and oversized article covers', function () {
    Storage::fake('public');
    $this->loginAsAdmin();
    $payload = ['title' => 'Invalid cover', 'slug' => 'invalid-cover-test', 'body' => '<p>A guide.</p>', 'status' => 'draft'];

    foreach ([UploadedFile::fake()->create('cover.svg', 10, 'image/svg+xml'), UploadedFile::fake()->image('cover.jpg')->size(5000)] as $file) {
        $this->post(route('admin.berkay.articles.store'), $payload + ['cover_image' => $file])->assertSessionHasErrors('cover_image');
    }

    expect(app(ArticleRepository::class)->findOneByField('slug', $payload['slug']))->toBeNull();
});
