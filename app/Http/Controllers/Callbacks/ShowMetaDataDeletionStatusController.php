<?php

namespace App\Http\Controllers\Callbacks;

use App\Http\Controllers\Controller;
use App\Repository\Contracts\MetaDataDeletionRepositoryContract;
use Illuminate\Http\Response;

final class ShowMetaDataDeletionStatusController extends Controller
{
    public function __invoke(string $code, MetaDataDeletionRepositoryContract $requests): Response
    {
        $row = $requests->findPublic($code);
        abort_if($row === null, 404);

        return response()->view('privacy.meta-deletion-status', [
            'code' => $row->confirmation_code,
            'state' => $row->state,
            'acceptedAt' => $row->accepted_at,
            'completedAt' => $row->completed_at,
            'reason' => $row->last_error_code,
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
