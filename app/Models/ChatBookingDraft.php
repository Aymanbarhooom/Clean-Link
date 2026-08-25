<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatBookingDraft extends Model
{
    protected $fillable = [
        'chat_conversation_id', 'user_id', 'company_id', 'service_id',
        'package_id', 'location_id', 'start_time', 'payment_method', 'note',
        'note_handled', 'open_package_attributes', 'quoted_price',
        'quoted_duration', 'summary_hash', 'validated_at', 'created_order_id',
        'summary_message_count',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'note_handled' => 'boolean',
        'open_package_attributes' => 'array',
        'quoted_price' => 'float',
        'quoted_duration' => 'integer',
        'validated_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function package(): BelongsTo { return $this->belongsTo(Package::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function createdOrder(): BelongsTo { return $this->belongsTo(Order::class, 'created_order_id'); }
}
