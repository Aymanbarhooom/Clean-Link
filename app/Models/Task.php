<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['order_id', 'worker_id', 'status', 'image_before', 'image_after'];

    // --- Relationships ---

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    // --- Helper Functions ---

    /**
     * Update task status and synchronize changes back to the main client order.
     */
    public function advanceStatus(string $newStatus): bool
    {
        if (!in_array($newStatus, ['on_way', 'handling', 'done'])) {
            return false;
        }

        $this->update(['status' => $newStatus]);

        // Sync back to order status if worker completes the job
        if ($newStatus === 'done') {
            $this->order()->update(['status' => 'completed']);
            
            // Trigger automatic worker rating calculation update
            if($this->worker && $this->worker->workerProfile) {
                 $this->worker->workerProfile->updateRating();
            }
        }

        return true;
    }
}
