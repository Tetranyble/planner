<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PublicationJob;

interface PublicationDeliveryContextRepositoryContract
{
    /**
     * @return array{provider:string,external_account_id:string,caption:string,has_media:bool,account_uuid:string,mode:string,account_kind:string,account_active:bool}
     */
    public function manifest(Organisation $organisation, Brand $brand, PublicationJob $job): array;

    /** Decrypt the tenant-verified account token only when the send is ready. Never log or persist the plaintext. */
    public function accessToken(Organisation $organisation, Brand $brand, PublicationJob $job): string;
}
