<?php

namespace App\Domain\Publishing\LiveContracts;

use App\Domain\Publishing\Delivery\DispatchOutcome;
use App\Domain\Publishing\Delivery\ProviderFailureClassifier;

/** Last safety gate. Only the fenced, opt-in publication execution worker may call this. */
final readonly class GuardedTextDispatch
{
    public function __construct(private TextHttpTransportContract $transport) {}

    public function execute(
        PreparedProviderRequest $request,
        TextDispatchAuthorization $authorization,
        string $bearerToken,
    ): DispatchOutcome {
        ProviderTextEgressPolicy::assertAllowed($request);
        if ($authorization->blockers() !== []) {
            return new DispatchOutcome('PERMANENT_FAILURE', 'DISPATCH_GUARD_BLOCKED');
        }
        $reply = $this->transport->send($request, $bearerToken);
        if ($reply->status !== null && ($reply->status < 200 || $reply->status >= 300)) {
            if ($reply->status >= 300 && $reply->status < 400) {
                return new DispatchOutcome('UNCERTAIN', 'UNEXPECTED_REDIRECT', $reply->status);
            }

            return ProviderFailureClassifier::classify($reply->status, $reply->mayHaveSent);
        }

        return ProviderReceiptInterpreter::interpret($request->provider, $reply->status, $reply->body, $reply->headers, $reply->mayHaveSent);
    }
}
