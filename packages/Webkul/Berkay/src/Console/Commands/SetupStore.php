<?php

namespace Webkul\Berkay\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Core\Repositories\CurrencyRepository;
use Webkul\Core\Repositories\LocaleRepository;
use Webkul\Product\Repositories\ProductRepository;

class SetupStore extends Command
{
    /** Offer explicit sample data separately from production store setup. */
    protected $signature = 'berkay:setup {--samples : Add clearly labeled sample products}';

    /** Describe the non-destructive Persian store setup command. */
    protected $description = 'Configure the default Berkay channel for Persian and tomans without resetting its data.';

    /** Set store defaults through the standard Bagisto repositories. */
    public function handle(
        ChannelRepository $channels,
        LocaleRepository $locales,
        CurrencyRepository $currencies,
        CoreConfigRepository $settings,
        CategoryRepository $categories,
        ProductRepository $products
    ): int {
        $channel = $channels->findOneByField('code', 'default');
        $locale = $locales->findOneByField('code', 'fa');

        if (
            ! $channel
            || ! $locale
        ) {
            $this->error('Install Bagisto before configuring Berkay.');

            return self::FAILURE;
        }

        $currency = $currencies->findOneByField('code', 'IRT') ?: $currencies->create([
            'code' => 'IRT', 'name' => 'تومان', 'symbol' => 'تومان', 'decimal' => 0,
            'currency_position' => 'right', 'group_separator' => ',', 'decimal_separator' => '.',
        ]);
        $currencies->update(['name' => 'تومان', 'symbol' => 'تومان', 'decimal' => 0, 'currency_position' => 'right'], $currency->id);
        $this->configureEnvironment();
        $locales->update(['status' => 1], $locale->id);
        $channels->update([
            'theme' => 'default',
            'default_locale_id' => $locale->id,
            'base_currency_id' => $currency->id,
            'locales' => [$locale->id],
            'currencies' => [$currency->id],
            'inventory_sources' => $channel->inventory_sources->pluck('id')->all(),
            'fa' => [
                'name' => trans('berkay::app.brand', [], 'fa'),
                'description' => trans('berkay::app.tagline-default', [], 'fa'),
                'home_seo' => ['meta_title' => 'برکای | فروشگاه محصولات چرمی', 'meta_description' => 'مجموعهٔ کیف و محصولات چرمی برکای؛ انتخاب و سفارش آنلاین.'],
            ],
        ], $channel->id);

        if (! core()->getConfigData('berkay.store.identity.name')) {
            $settings->create([
                'locale' => 'fa', 'channel' => $channel->code,
                'berkay' => ['store' => ['identity' => [
                    'name' => 'برکای', 'tagline' => 'محصولات چرمی و ساخت اختصاصی', 'custom_orders' => 1,
                ]]],
            ]);
        }

        if (core()->getConfigData('sales.checkout.mini_cart.offer_info') === 'Get Up To 30% OFF on your 1st order') {
            $settings->create(['sales' => ['checkout' => ['mini_cart' => ['offer_info' => '']]]]);
        }

        foreach (['cashondelivery' => ['Cash On Delivery', 'پرداخت هنگام تحویل'], 'moneytransfer' => ['Money Transfer', 'واریز بانکی با تأیید فروشگاه']] as $code => [$original, $label]) {
            if (core()->getConfigData('sales.payment_methods.'.$code.'.title') === $original) {
                $settings->create(['channel' => $channel->code, 'locale' => 'fa', 'sales' => ['payment_methods' => [$code => ['title' => $label, 'description' => $label]]]]);
            }
        }

        if (! core()->getConfigData('general.design.admin_logo.logo_image')) {
            Storage::put('configurations/berkay-logo.svg', File::get(public_path('berkay/logo.svg')));
            $settings->create(['general' => ['design' => ['admin_logo' => ['logo_image' => 'configurations/berkay-logo.svg']]]]);
        }

        if ($this->option('samples')) {
            $this->samples($channel, $categories, $products);
            $this->call('indexer:index', ['--mode' => ['full']]);
        }

        $this->call('optimize:clear');
        $this->info('Berkay Persian store configuration is ready.');

        return self::SUCCESS;
    }

    /** Persist the store currency without exposing or changing service credentials. */
    protected function configureEnvironment(): void
    {
        $path = base_path('.env');

        if (File::exists($path)) {
            File::put($path, preg_replace('/^APP_CURRENCY=.*$/m', 'APP_CURRENCY=IRT', File::get($path)));
        }

        config(['app.currency' => 'IRT']);
    }

    /** Add explicitly labeled sample products only when requested. */
    protected function samples($channel, CategoryRepository $categories, ProductRepository $products): void
    {
        $collection = [];

        foreach (['women-bags' => 'کیف زنانه', 'men-bags' => 'کیف مردانه', 'wallets' => 'کیف پول', 'backpacks' => 'کوله‌پشتی', 'accessories' => 'اکسسوری'] as $slug => $name) {
            $collection[$slug] = $categories->findBySlug($slug) ?: $categories->create([
                'parent_id' => $channel->root_category_id,
                'position' => count($collection) + 1,
                'status' => 1,
                'display_mode' => 'products_and_description',
                'locale' => 'fa',
                'name' => $name,
                'slug' => $slug,
                'description' => '<p>'.$name.'</p>',
                'meta_title' => $name,
                'meta_description' => $name.' برکای',
                'attributes' => [],
                'fa' => ['name' => $name, 'slug' => $slug, 'description' => '<p>'.$name.'</p>', 'meta_title' => $name],
            ]);
        }

        $samples = [
            ['BK-SAMPLE-01', 'کیف دوشی کلاسیک', 'women-bags', 2450000, 'hero-v2.webp'],
            ['BK-SAMPLE-02', 'کیف روزمره', 'men-bags', 3200000, 'tote-sample.webp'],
            ['BK-SAMPLE-03', 'کیف دستی', 'women-bags', 2850000, 'wallet-sample.webp'],
            ['BK-SAMPLE-04', 'کیف همراه', 'accessories', 1850000, 'hero-v2.webp'],
            ['BK-SAMPLE-05', 'کیف مینیمال', 'women-bags', 2100000, 'tote-sample.webp'],
            ['BK-SAMPLE-06', 'کیف شهری', 'men-bags', 3650000, 'wallet-sample.webp'],
        ];

        foreach ($samples as $index => [$sku, $name, $category, $price, $image]) {
            if ($products->findOneByField('sku', $sku)) {
                continue;
            }

            $product = $products->create(['type' => 'simple', 'attribute_family_id' => 1, 'sku' => $sku]);
            $imageFile = new UploadedFile(public_path('berkay/'.$image), $image, null, null, true);
            $product = $products->update([
                'channel' => $channel->code, 'locale' => 'fa', 'sku' => $sku,
                'name' => $name.' — نمونه', 'url_key' => 'sample-'.strtolower($sku),
                'description' => '<p>این محصول و قیمت آن صرفاً برای پیش‌نمایش فروشگاه است و باید پیش از شروع فروش با کالای واقعی جایگزین شود.</p>',
                'short_description' => '<p>محصول نمونه برای پیش‌نمایش طراحی برکای</p>',
                'price' => $price, 'weight' => '0.5', 'status' => 1, 'visible_individually' => 1,
                'new' => 1, 'featured' => 1, 'guest_checkout' => 1, 'manage_stock' => 1,
                'tax_category_id' => null, 'categories' => [$collection[$category]->id],
                'channels' => [$channel->id], 'inventories' => [$channel->inventory_sources->first()->id => 10],
                'images' => ['files' => [$imageFile]],
                'meta_title' => $name.' برکای', 'meta_description' => 'محصول نمونهٔ فروشگاه برکای',
            ], $product->id);
            Event::dispatch('catalog.product.update.after', $product);
        }
    }
}
