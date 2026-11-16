<?php

namespace App\Domain\Publishing\Media;

interface MediaStepTransportContract
{
    /** @return array{status:?int,body:array,may_have_sent:bool} */
    public function send(MediaStepRequest $request, string $accessToken): array;
}
