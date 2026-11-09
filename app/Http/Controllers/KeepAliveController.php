<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/** Called by the idle-warning dialog; the session middleware refreshes the activity stamp. */
class KeepAliveController extends Controller
{
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
