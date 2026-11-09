<div class="inline-flex overflow-hidden rounded-lg border border-slate-300 bg-white text-xs" role="group" aria-label="{{ __('common.language') }}">
    @foreach (['en' => 'EN', 'am' => 'አማ'] as $code => $label)
        <a href="{{ route('locale.switch', $code) }}"
           @class(['px-3 py-1.5 font-medium', 'bg-teal-600 text-white' => app()->getLocale() === $code, 'text-slate-600 hover:bg-slate-50' => app()->getLocale() !== $code])
           @if(app()->getLocale() === $code) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
</div>
