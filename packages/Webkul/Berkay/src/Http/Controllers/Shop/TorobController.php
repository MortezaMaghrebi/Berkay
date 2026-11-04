<?php

namespace Webkul\Berkay\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Webkul\Berkay\Repositories\CatalogFeedRepository;
use Webkul\Berkay\Services\IntegrationSettings;
use Webkul\Berkay\Services\IranianMoney;
use Webkul\Berkay\Services\TorobToken;
use Webkul\Shop\Http\Controllers\Controller;

class TorobController extends Controller
{
    /** Inject catalog and external-authentication services. */
    public function __construct(
        protected CatalogFeedRepository $catalog,
        protected TorobToken $tokens,
        protected IranianMoney $money
    ) {}

    /** Serve the authenticated read-only Torob v3 product synchronization API. */
    public function index(): JsonResponse
    {
        abort_unless(app(IntegrationSettings::class)->get('torob.enabled'), 404);
        $authority = parse_url(config('app.url'), PHP_URL_HOST);
        $port = parse_url(config('app.url'), PHP_URL_PORT);
        $audience = $authority.($port ? ':'.$port : '');

        abort_unless(
            request()->header('X-Torob-Token-Version') === '1'
            && $this->tokens->verify((string) request()->header('X-Torob-Token'), $audience),
            401
        );

        $validator = Validator::make(request()->all(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', Rule::in(['date_added_desc', 'date_updated_desc', 'product_id_desc'])],
            'cursor' => ['sometimes', 'regex:/^[1-9][0-9]*$/', 'max:20'],
            'page_uniques' => ['sometimes', 'array', 'min:1', 'max:100'],
            'page_uniques.*' => ['string', 'regex:/^[1-9][0-9]*$/', 'max:20'],
            'page_urls' => ['sometimes', 'array', 'min:1', 'max:100'],
            'page_urls.*' => ['url:http,https', 'max:1500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid request parameters.'], 400);
        }

        $input = $validator->validated();
        $lookup = isset($input['page_uniques']) + isset($input['page_urls']);
        $cursor = ($input['sort'] ?? null) === 'product_id_desc';

        if (
            empty($input)
            || $lookup > 1
            || ($lookup && (isset($input['page']) || isset($input['cursor']) || isset($input['sort'])))
            || (! $lookup && ! isset($input['sort']))
            || (! $lookup && ! $cursor && ! isset($input['page']))
            || ($cursor && isset($input['page']))
            || (! $cursor && isset($input['cursor']))
            || count(array_diff(array_keys(request()->all()), array_keys($input))) > 0
        ) {
            return response()->json(['error' => 'Invalid request mode.'], 400);
        }

        $channel = core()->getCurrentChannel();
        abort_unless(in_array($channel->base_currency->code, ['IRR', 'IRT'], true), 503);

        $result = $this->catalog->feed($input, $channel->id, $channel->code, app()->getLocale());
        $products = $result['items']->map(function ($product) use ($channel) {
            $type = $product->getTypeInstance();
            $images = product_image()->getGalleryImages($product);
            $available = (bool) $type->isSaleable();

            return [
                'page_unique' => (string) $product->id,
                'page_url' => url($product->url_key),
                'title' => mb_substr((string) $product->name, 0, 500),
                'current_price' => $available ? $this->money->toToman($type->getMinimalPrice(), $channel->base_currency->code) : 0,
                'old_price' => $this->money->toToman($type->getRegularMinimalPrice(), $channel->base_currency->code),
                'availability' => $available,
                'category_name' => mb_substr((string) $product->categories->first()?->name, 0, 200),
                'image_links' => collect($images)->pluck('original_image_url')->filter()->values()->all(),
                'short_desc' => mb_substr(strip_tags((string) $product->short_description), 0, 500),
                'date_added' => $product->created_at?->toIso8601String(),
                'date_updated' => $product->updated_at?->toIso8601String(),
            ];
        })->all();

        unset($result['items']);

        return response()->json(['api_version' => 'torob_api_v3'] + $result + ['products' => $products])
            ->header('Cache-Control', 'no-store');
    }
}
