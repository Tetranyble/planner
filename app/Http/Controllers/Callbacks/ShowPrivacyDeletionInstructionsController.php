<?php

namespace App\Http\Controllers\Callbacks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class ShowPrivacyDeletionInstructionsController extends Controller
{
    public function __invoke(): Response
    {
        return response()->view('privacy.deletion-instructions')
            ->header('Cache-Control', 'no-store');
    }
}
