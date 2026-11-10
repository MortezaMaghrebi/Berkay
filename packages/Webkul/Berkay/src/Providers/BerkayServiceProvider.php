<?php

namespace Webkul\Berkay\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Berkay\Console\Commands\PublishStarterContent;
use Webkul\Berkay\Console\Commands\SetupStore;
use Webkul\Berkay\Listeners\OrderSmsListener;

class BerkayServiceProvider extends ServiceProvider
{
    /** Register the Berkay extension configuration. */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/payment-methods.php', 'payment_methods');
        $this->mergeConfigFrom(__DIR__.'/../Config/admin-menu.php', 'menu.admin');
        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');
        $this->mergeConfigFrom(__DIR__.'/../Config/system.php', 'core');
    }

    /** Load extension routes, resources, migrations and theme hooks. */
    public function boot(): void
    {
        $this->app->booted(function () {
            $aliases = ['sales.orders' => ['admin.berkay.workshop.index'], 'catalog.products' => ['admin.berkay.simple_product.index'], 'catalog.products.create' => ['admin.berkay.simple_product.create', 'admin.berkay.simple_product.store', 'admin.catalog.products.create'], 'catalog.products.edit' => ['admin.berkay.simple_product.edit', 'admin.berkay.simple_product.update', 'admin.berkay.catalog_table.index', 'admin.berkay.catalog_table.update', 'admin.berkay.catalog_table.adjust'], 'catalog.products.delete' => ['admin.berkay.catalog_table.destroy'], 'catalog.categories' => ['admin.berkay.simple_category.index'], 'catalog.categories.create' => ['admin.berkay.simple_category.create', 'admin.berkay.simple_category.store'], 'catalog.categories.edit' => ['admin.berkay.simple_category.edit', 'admin.berkay.simple_category.update']];
            config(['acl' => array_map(function ($entry) use ($aliases) {
                if (isset($aliases[$entry['key']])) {
                    $entry['route'] = array_unique(array_merge((array) $entry['route'], $aliases[$entry['key']]));
                }

                return $entry;
            }, config('acl'))]);
            $allowed = ['berkay_zarinpal', 'cashondelivery', 'moneytransfer'];
            config(['payment_methods' => array_intersect_key(config('payment_methods', []), array_flip($allowed))]);
            config(['core' => array_values(array_filter(config('core', []), function ($item) use ($allowed) {
                $key = $item['key'] ?? '';

                return ! str_starts_with($key, 'sales.payment_methods.') || in_array(substr($key, strlen('sales.payment_methods.')), $allowed, true);
            }))]);
        });
        Event::listen('checkout.order.save.after', [OrderSmsListener::class, 'created']);
        Event::listen('sales.order.update-status.after', [OrderSmsListener::class, 'statusChanged']);
        Event::listen('sales.order.cancel.after', [OrderSmsListener::class, 'canceled']);
        Event::listen('sales.invoice.save.after', [OrderSmsListener::class, 'invoiced']);
        Event::listen('sales.shipment.save.after', [OrderSmsListener::class, 'shipped']);
        Event::listen('sales.refund.save.after', [OrderSmsListener::class, 'refunded']);
        $this->commands([SetupStore::class, PublishStarterContent::class]);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'berkay');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'berkay');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        Event::listen('bagisto.admin.sales.order.left_component.before', function ($manager) {
            $manager->addTemplate('berkay::admin.order-workflow');
        });

        Event::listen('bagisto.shop.layout.head.after', function ($manager) {
            $manager->addTemplate('berkay::shop.styles');
        });

        Event::listen(['bagisto.admin.layout.head.after', 'bagisto.admin.layout.head'], function ($manager) {
            $manager->addTemplate('berkay::admin.styles');
        });
    }
}
