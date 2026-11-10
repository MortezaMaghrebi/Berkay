<ol class="bk-order-steps" aria-label="{{ trans('berkay::workflow.manage') }}">
    @foreach (\Webkul\Berkay\Services\OrderWorkflow::STEPS as $step)
        <li @class(['is-done' => $progress['done'][$step], 'is-current' => ! $progress['ended'] && $progress['current'] === $step]) @if (! $progress['ended'] && $progress['current'] === $step) aria-current="step" @endif>
            <span aria-hidden="true">{{ $progress['done'][$step] ? '✓' : $loop->iteration }}</span>
            <strong>{{ trans('berkay::workflow.'.$step) }}</strong>
            @if ($progress['done'][$step])<span class="sr-only">{{ trans('berkay::workflow.done') }}</span>@endif
        </li>
    @endforeach
</ol>
