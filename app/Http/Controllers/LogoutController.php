<?php

namespace App\Http\Controllers;

use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * Handle user manual logout.
     */
    public function __invoke(Request $request, AuthenticationService $authService): RedirectResponse
    {
        $authService->logout();

        return redirect()->route('login');
    }
}
