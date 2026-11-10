<?php

namespace App\Livewire\Catalogue;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product->load(['category', 'units', 'prices.user']);
    }

    public function render()
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('catalogue.view') && ! $user->hasPermission('catalogue.manage'))) {
            abort(403);
        }

        return view('livewire.catalogue.show', [
            'canManage' => $user->hasPermission('catalogue.manage'),
            'canEditBasic' => $user->hasPermission('catalogue.edit_basic'),
        ])->layout('layouts.app', ['title' => $this->product->brand]);
    }
}
