<x-admin::layouts>
    <x-slot:title>{{ trans('berkay::app.magazine') }}</x-slot>
    <section class="bk-admin bk-seller-editor">
        <div class="bk-toolbar"><h1>{{ trans('berkay::app.magazine') }}</h1><a href="{{ route('admin.berkay.articles.index') }}">{{ trans('berkay::app.back') }}</a></div>
        @include('berkay::admin.seller-feedback')
        @if (bouncer()->hasPermission('berkay.articles.create'))
            <details class="bk-ai-helper" id="bk-ai-helper"
                data-import-url="{{ route('admin.berkay.articles.parse') }}"
                data-template="{{ trans('berkay::app.ai.prompt-template') }}"
                data-topic-required="{{ trans('berkay::app.ai.topic-required') }}"
                data-ready="{{ trans('berkay::app.ai.ready') }}"
                data-copied="{{ trans('berkay::app.ai.copied') }}"
                data-manual-copy="{{ trans('berkay::app.ai.manual-copy') }}"
                data-imported="{{ trans('berkay::app.ai.imported') }}"
                data-invalid="{{ trans('berkay::app.ai.invalid') }}"
                data-busy="{{ trans('berkay::app.ai.busy') }}">
                <summary>{{ trans('berkay::app.ai.title') }}</summary>
                <p>{{ trans('berkay::app.ai.intro') }}</p>
                <label for="ai-topic">{{ trans('berkay::app.ai.topic') }}</label>
                <input id="ai-topic" maxlength="200" placeholder="{{ trans('berkay::app.ai.topic-example') }}">
                <button type="button" id="ai-prompt-button" class="bk-button">{{ trans('berkay::app.ai.make-prompt') }}</button>
                <label for="ai-prompt">{{ trans('berkay::app.ai.prompt') }}</label>
                <textarea id="ai-prompt" rows="7" readonly></textarea>
                <button type="button" id="ai-copy-button">{{ trans('berkay::app.ai.copy') }}</button>
                <label for="ai-result">{{ trans('berkay::app.ai.result') }}</label>
                <textarea id="ai-result" rows="8" maxlength="120000" dir="ltr"></textarea>
                <button type="button" id="ai-import-button" class="bk-button">{{ trans('berkay::app.ai.import') }}</button>
                <p id="ai-message" role="status" aria-live="polite"></p>
            </details>
        @endif
        <form class="bk-form bk-article-form" method="post" action="{{ $article ? route('admin.berkay.articles.update', $article->id) : route('admin.berkay.articles.store') }}">
            @csrf
            @if ($article) @method('PUT') @endif
            <label for="title">{{ trans('berkay::app.title') }}</label>
            <input id="title" name="title" value="{{ old('title', $article?->title) }}" required maxlength="255">
            <label for="body">{{ trans('berkay::app.body') }}</label>
            <textarea id="body" name="body" rows="16" required>{{ old('body', $article?->body) }}</textarea>
            <label for="category">{{ trans('berkay::app.category') }}</label>
            <input id="category" name="category" value="{{ old('category', $article?->category) }}" maxlength="120">
            <label for="status">{{ trans('berkay::app.status') }}</label>
            <select id="status" name="status">@foreach (['draft', 'published'] as $status)<option value="{{ $status }}" @selected(old('status', $article?->status ?: 'draft') === $status)>{{ trans('berkay::app.'.$status) }}</option>@endforeach</select>
            <details class="bk-advanced">
                <summary>{{ trans('berkay::app.ai.optional') }}</summary>
                <p>{{ trans('berkay::app.ai.auto-help') }}</p>
                @foreach (['slug', 'excerpt', 'meta_title', 'meta_description'] as $field)
                    <label for="{{ $field }}">{{ trans('berkay::app.'.str_replace('_', '-', $field)) }}</label>
                    @if (in_array($field, ['excerpt', 'meta_description']))
                        <textarea id="{{ $field }}" name="{{ $field }}" rows="3">{{ old($field, $article?->{$field}) }}</textarea>
                    @else
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $article?->{$field}) }}" maxlength="{{ $field === 'slug' ? 180 : 255 }}">
                    @endif
                @endforeach
                <label for="published_at">{{ trans('berkay::app.published-at') }}</label>
                <input id="published_at" type="datetime-local" name="published_at" value="{{ old('published_at', $article?->published_at?->format('Y-m-d\TH:i')) }}">
                <p>{{ trans('berkay::app.article-time') }}</p>
            </details>
            <button class="bk-button" type="submit">{{ trans('berkay::app.save') }}</button>
        </form>
        @if ($article && bouncer()->hasPermission('berkay.articles.delete'))
            <form method="post" action="{{ route('admin.berkay.articles.destroy', $article->id) }}" class="bk-delete-form">
                @csrf @method('DELETE')
                <label><input type="checkbox" required> {{ trans('berkay::app.delete-confirm') }}</label>
                <button type="submit">{{ trans('berkay::app.delete') }}</button>
            </form>
        @endif
    </section>
    @pushOnce('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.6.2/tinymce.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="{{ asset('berkay/article-assistant.js') }}" defer></script>
        <script>
            window.addEventListener('load', () => requestAnimationFrame(() => {
                if (!window.tinymce) return;
                tinymce.init({
                    selector: 'textarea#body', height: 500,
                    directionality: @json(core()->getCurrentLocale()->direction),
                    menubar: false, plugins: 'link lists table code fullscreen wordcount image',
                    toolbar: 'undo redo | blocks | bold italic | bullist numlist | link image table | code fullscreen',
                    setup(editor) { editor.on('change input undo redo', () => editor.save()); },
                });
                document.querySelector('.bk-article-form').addEventListener('submit', () => tinymce.triggerSave());
            }));
        </script>
    @endPushOnce
</x-admin::layouts>
