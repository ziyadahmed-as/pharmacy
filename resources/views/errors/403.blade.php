@extends('layouts.app')

@section('content')
<div class="mx-auto my-12 max-w-xl rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
    <h1 class="mb-2 text-2xl font-bold text-slate-900">403 - {{ __('common.forbidden_title') }}</h1>
    <p class="mb-6 text-sm text-slate-600">{{ __('common.forbidden_body') }}</p>
    <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">{{ __('common.back_to_dashboard') }}</a>
</div>
@endsection
