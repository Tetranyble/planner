<?php

namespace App\Http\Controllers\Callbacks;

use App\Http\Controllers\Controller;
use App\Services\Social\HandleMetaDataDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class ReceiveMetaDataDeletionController extends Controller
{
    public function __invoke(Request $request, HandleMetaDataDeletion $handler): JsonResponse
    {
        // Meta POSTs application/x-www-form-urlencoded signed_request, not a user session.
        $signed = $request->input('signed_request');
        if (! is_string($signed) || $signed === '') {
            abort(422, 'A signed request is required.');
        }
        $root = rtrim((string) config('app.url'), '/');
        if (! app()->environment(['local', 'testing']) && ! str_starts_with($root, 'https://')) {
            throw new ServiceUnavailableHttpException(null, 'The public status URL requires HTTPS.');
        }
        $accepted = $handler->receive($signed);

        return response()->json([
            'url' => $root.'/meta/data-deletion/status/'.$accepted->confirmation_code,
            'confirmation_code' => $accepted->confirmation_code,
        ])->header('Cache-Control', 'no-store');
    }
}
