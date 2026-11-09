<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LogOut;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    public function __invoke(LogOut $logout): RedirectResponse
    {
        $logout->execute('manual');

        return redirect()->route('login');
    }
}
