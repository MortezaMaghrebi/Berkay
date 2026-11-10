<button type="button" id="bk-crop-open" hidden>{{ trans('berkay::photo.crop') }}</button>
<dialog id="bk-crop-dialog" class="bk-crop-dialog" aria-labelledby="bk-crop-title">
    <h2 id="bk-crop-title">{{ trans('berkay::photo.crop') }}</h2>
    <label for="bk-crop-file">{{ trans('berkay::photo.photo') }}</label>
    <select id="bk-crop-file"></select>
    <canvas width="1000" height="1000"></canvas>
    <label for="bk-crop-zoom">{{ trans('berkay::photo.zoom') }}</label>
    <input id="bk-crop-zoom" type="range" min="1" max="3" step=".05" value="1">
    <label for="bk-crop-x">{{ trans('berkay::photo.horizontal') }}</label>
    <input id="bk-crop-x" type="range" min="0" max="100" value="50" dir="ltr">
    <label for="bk-crop-y">{{ trans('berkay::photo.vertical') }}</label>
    <input id="bk-crop-y" type="range" min="0" max="100" value="50">
    <p role="alert" hidden>{{ trans('berkay::photo.error') }}</p>
    <div class="bk-toolbar">
        <button type="button" data-crop-apply disabled>{{ trans('berkay::photo.apply') }}</button>
        <button type="button" data-crop-close>{{ trans('berkay::app.back') }}</button>
    </div>
</dialog>
@pushOnce('scripts')
    <script type="module" src="{{ asset('berkay/product-photo-crop.js') }}?v={{ filemtime(public_path('berkay/product-photo-crop.js')) }}"></script>
@endPushOnce
