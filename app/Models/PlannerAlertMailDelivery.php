<?php

namespace App\Models;

use Database\Factories\PlannerAlertMailDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlannerAlertMailDelivery extends Model
{
    use HasFactory;

    protected $table = 'planner_alert_mail_outbox';

    protected static function newFactory(): PlannerAlertMailDeliveryFactory
    {
        return PlannerAlertMailDeliveryFactory::new();
    }

    protected $fillable = [

        'alert_event_id',

        'brand_id',

        'lease_expires_at',

        'lease_owner',

        'organisation_id',

        'recipient_user_id',

        'sent_at',

        'state',

    ];

    protected function casts(): array
    {
        return ['lease_expires_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
