<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class ShowLoginFormController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }
}
