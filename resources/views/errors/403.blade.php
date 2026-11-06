@extends('layouts.app')

@section('title', 'Access Denied - PMS')

<div class="max-w-xl mx-auto my-12 text-center bg-white p-8 rounded-xl shadow-sm border border-slate-200">
    <div class="w-16 h-16 rounded-full bg-red-100 text-red-600 mx-auto flex items-center justify-center mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
    </div>
    <h1 class="text-2xl font-bold text-slate-900 mb-2">403 - Access Denied</h1>
    <p class="text-sm text-slate-600 mb-6">
        {{ $exception->getMessage() ?: 'You are not authorized to access this resource under your assigned role.' }}
    </p>
    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-teal-600 hover:bg-teal-700">
        Return to Dashboard
    </a>
</div>
