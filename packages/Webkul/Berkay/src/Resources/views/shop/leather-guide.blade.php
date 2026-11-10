<section class="bk-leather-guide" aria-labelledby="bk-leather-guide-title">
    <header class="bk-leather-guide-intro">
        <h2 id="bk-leather-guide-title">{{ trans('berkay::leather.title') }}</h2>
        <p>{{ trans('berkay::leather.intro') }}</p>
    </header>

    <div class="bk-leather-guide-groups">
        @foreach (['plain', 'patterned'] as $group)
            @php
                $samples = collect([1, 2, 3])->map(fn ($slot) => [
                    'photo' => core()->getConfigData("berkay.leather.{$group}.photo_{$slot}"),
                    'caption' => core()->getConfigData("berkay.leather.{$group}.caption_{$slot}"),
                ])->filter(fn ($sample) => filled($sample['photo']));
            @endphp

            <section class="bk-leather-guide-group" aria-labelledby="bk-leather-{{ $group }}">
                <header>
                    <h3 id="bk-leather-{{ $group }}">{{ trans('berkay::leather.'.$group) }}</h3>
                    <p>{{ trans('berkay::leather.'.$group.'-description') }}</p>
                </header>

                @if ($samples->isNotEmpty())
                    <ul class="bk-leather-samples" role="list">
                        @foreach ($samples as $sample)
                            <li>
                                <figure>
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($sample['photo']) }}" alt="{{ $sample['caption'] ? '' : trans('berkay::leather.'.$group) }}" width="400" height="300" loading="lazy">
                                    @if (filled($sample['caption']))
                                        <figcaption>{{ $sample['caption'] }}</figcaption>
                                    @endif
                                </figure>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <figure class="bk-leather-illustration">
                        <svg class="bk-leather-swatch bk-leather-swatch--{{ $group }}" viewBox="0 0 560 168" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                            @if ($group === 'patterned')
                                <defs>
                                    <pattern id="bk-leather-pattern" width="72" height="46" patternUnits="userSpaceOnUse">
                                        <path d="M-36 23Q-12 6 0 0Q12 6 36 23Q12 40 0 46Q-12 40-36 23ZM36 23Q60 6 72 0Q84 6 108 23Q84 40 72 46Q60 40 36 23Z" fill="none" stroke="currentColor" stroke-width="1.2"/>
                                        <path d="M0 15Q11 16 17 23Q11 30 0 31Q-11 30-17 23Q-11 16 0 15Z" fill="none" stroke="currentColor" stroke-opacity=".55"/>
                                    </pattern>
                                </defs>
                                <rect width="560" height="168" fill="url(#bk-leather-pattern)"/>
                            @else
                                <defs>
                                    <pattern id="bk-leather-grain" width="88" height="56" patternUnits="userSpaceOnUse">
                                        <path d="M3 14l7-2m27 18 3-1m17-14 6 1m17 26 5-2M16 46l4-1m35 6 4-2m13-41 3 1" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"/>
                                    </pattern>
                                </defs>
                                <rect width="560" height="168" fill="url(#bk-leather-grain)"/>
                            @endif
                        </svg>
                        <figcaption>{{ trans('berkay::leather.illustration') }}</figcaption>
                    </figure>
                @endif
            </section>
        @endforeach
    </div>
</section>
