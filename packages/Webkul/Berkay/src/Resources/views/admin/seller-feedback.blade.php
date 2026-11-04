@if (session('success'))<p class="bk-notice" role="status">{{ session('success') }}</p>@endif
@if ($errors->any())
    <div class="bk-errors" role="alert"><p>{{ trans('berkay::app.seller.check-fields') }}</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
