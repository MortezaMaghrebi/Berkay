@php
    $storyImages = $product->images->take(3);
    $storySpecs = $attributeData->filter(fn ($item) => ! in_array($item['type'], ['file', 'image']));
@endphp
<section class="bk-product-story" aria-labelledby="bk-story-title" v-pre>
    <header><p>{{ trans('berkay::app.product-story.eyebrow') }}</p><h2 id="bk-story-title">{{ $product->name }}</h2></header>
    <div class="bk-product-story-lead">
        @if ($storyImages->first())
            <figure><img src="{{ asset('storage/'.$storyImages->first()->path) }}" alt="{{ $product->name }}" loading="lazy" width="1000" height="1000"></figure>
        @endif
        <div class="bk-product-story-copy"><h3>{{ trans('berkay::app.product-story.title') }}</h3><div>{!! $product->description !!}</div></div>
    </div>
    @if ($storyImages->count() > 1)
        <div class="bk-product-story-photos">
            @foreach ($storyImages->skip(1) as $image)
                <figure><img src="{{ asset('storage/'.$image->path) }}" alt="{{ $product->name }} — {{ trans('berkay::app.product-story.details') }}" loading="lazy" width="900" height="900"><figcaption>{{ trans('berkay::app.product-story.details') }}</figcaption></figure>
            @endforeach
        </div>
    @endif
    <section class="bk-product-specifications" aria-labelledby="bk-specifications-title">
        <h2 id="bk-specifications-title">{{ trans('berkay::app.product-story.specifications') }}</h2>
        <table>
            <caption>{{ $product->name }}</caption>
            <tbody>
                <tr><th scope="row">{{ trans('berkay::app.product-story.product') }}</th><td>{{ $product->name }}</td></tr>
                <tr><th scope="row">{{ trans('berkay::app.product-story.sku') }}</th><td><bdi>{{ $product->sku }}</bdi></td></tr>
                @foreach ($storySpecs as $spec)
                    <tr><th scope="row">{{ $spec['label'] }}</th><td>{{ $spec['value'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
</section>
