<?php

use Webkul\Berkay\Repositories\ArticleRepository;
use Webkul\Berkay\Repositories\CustomRequestRepository;

it('denies editorial writes to a read-only custom staff role', function () {
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.articles']);

    $this->get(route('admin.berkay.articles.index'))->assertOk();
    $this->post(route('admin.berkay.articles.store'), [])->assertUnauthorized();
});

it('sanitizes editorial content before publishing', function () {
    $this->loginAsAdminWithPermissions(['berkay', 'berkay.articles', 'berkay.articles.create']);

    $this->post(route('admin.berkay.articles.store'), [
        'title' => 'Leather care',
        'slug' => 'test-leather-care',
        'body' => '<p>Useful article.</p><script>alert(1)</script>',
        'status' => 'published',
    ])->assertRedirect(route('admin.berkay.articles.index'));

    $article = app(ArticleRepository::class)->findOneByField('slug', 'test-leather-care');
    expect($article->body)->not->toContain('<script')
        ->and($article->published_at)->not->toBeNull();
});

it('does not expose drafts or future articles on their public URL', function () {
    $articles = app(ArticleRepository::class);

    foreach ([
        ['slug' => 'test-draft', 'status' => 'draft', 'published_at' => now()->subDay()],
        ['slug' => 'test-future', 'status' => 'published', 'published_at' => now()->addDay()],
    ] as $data) {
        $article = $articles->create($data + ['title' => 'Private article', 'body' => '<p>Not public.</p>']);
        $this->get(route('shop.berkay.magazine.show', $article->slug))->assertNotFound();
    }
});

it('accepts normalized Persian phone inquiries and keeps staff notes private', function () {
    $this->setConfig('berkay.store.identity.custom_orders', 1);

    $this->post(route('shop.berkay.custom_order.store'), [
        'name' => 'Test customer',
        'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
        'product_type' => 'Bag',
        'description' => 'A custom leather bag with an adjustable strap.',
    ])->assertRedirect(route('shop.berkay.custom_order.create'));

    $inquiry = app(CustomRequestRepository::class)->findOneByField('name', 'Test customer');
    expect($inquiry->phone)->toBe('09123456789')->and($inquiry->status)->toBe('new');
    $this->get(route('admin.berkay.custom_orders.index'))->assertRedirect();
});

it('rejects spam trap submissions and invalid inquiry data', function () {
    $this->setConfig('berkay.store.identity.custom_orders', 1);

    $this->post(route('shop.berkay.custom_order.store'), [
        'name' => 'Spam',
        'phone' => '123',
        'product_type' => 'Bag',
        'description' => 'This is a sufficiently long message.',
        'website' => 'https://spam.example',
    ])->assertSessionHasErrors(['phone', 'website']);
});

it('loads editorial and payment datagrids as JSON rather than starting an export', function () {
    $this->loginAsAdmin();
    $this->getJson(route('admin.berkay.articles.index'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertJsonStructure(['columns', 'records', 'meta']);
    $this->getJson(route('admin.berkay.payments.index'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertJsonStructure(['columns', 'records', 'meta']);
});

it('streams a Persian invoice as a valid PDF download', function () {
    $this->loginAsAdmin();
    $invoice = $this->invoiceOrder($this->createOrder());
    $response = $this->get(route('admin.sales.invoices.print', $invoice->id));
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->streamedContent())->toStartWith('%PDF-');
});
