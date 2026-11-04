<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\BatchEvent;
use App\Models\User;
use App\Models\WasteBatch;
use Illuminate\Support\Facades\DB;

/**
 * The only way a batch changes state.
 *
 * Nothing else in the application writes to `status` directly. Every move is
 * validated against the enum's allowed transitions, stamped with its timestamp
 * and written to batch_events, so the audit trail cannot be bypassed by a
 * forgetful controller.
 */
class BatchStateMachine
{
    /** Column stamped when a batch enters each state. */
    private const STAMPS = [
        'REQUESTED'  => 'requested_at',
        'AVAILABLE'  => 'available_at',
        'ACCEPTED'   => 'accepted_at',
        'COLLECTED'  => 'collected_at',
        'IN_TRANSIT' => 'in_transit_at',
        'RECEIVED'   => 'received_at',
        'INSPECTING' => 'inspecting_at',
        'VERIFIED'   => 'verified_at',
        'REJECTED'   => 'rejected_at',
        'PROCESSING' => 'processing_at',
        'PROCESSED'  => 'processed_at',
    ];

    public function transition(
        WasteBatch $batch,
        BatchStatus $to,
        ?User $actor = null,
        array $attributes = [],
        array $meta = [],
    ): WasteBatch {
        $from = $batch->status;

        if (! $from->canTransitionTo($to)) {
            throw new \DomainException(
                "Cannot move batch {$batch->code} from {$from->value} to {$to->value}."
            );
        }

        return DB::transaction(function () use ($batch, $from, $to, $actor, $attributes, $meta) {
            $attributes['status'] = $to->value;

            if ($column = self::STAMPS[$to->value] ?? null) {
                $attributes[$column] ??= now();
            }

            $batch->forceFill($attributes)->save();

            BatchEvent::create([
                'waste_batch_id' => $batch->id,
                'from_status'    => $from->value,
                'to_status'      => $to->value,
                'actor_id'       => $actor?->id,
                'actor_role'     => $actor?->role?->value,
                'meta'           => $meta ?: null,
                'created_at'     => now(),
            ]);

            return $batch->refresh();
        });
    }

    /**
     * Claim a job for an agent without two agents winning it.
     *
     * The guard lives in the WHERE clause, so the database decides. An
     * optimistic UI cannot lie about who got there first.
     */
    public function claim(WasteBatch $batch, \App\Models\Agent $agent): bool
    {
        // Capacity is a hard gate, not a ranking hint: a vehicle that is
        // already committed cannot promise this load a place on board.
        if (! $agent->canAccept((float) ($batch->expected_kg ?? 0))) {
            return false;
        }
        $claimed = WasteBatch::whereKey($batch->id)
            ->where('status', BatchStatus::Available->value)
            ->whereNull('agent_id')
            ->update([
                'status'      => BatchStatus::Accepted->value,
                'agent_id'    => $agent->id,
                'accepted_at' => now(),
                'updated_at'  => now(),
            ]);

        if (! $claimed) {
            return false;
        }

        BatchEvent::create([
            'waste_batch_id' => $batch->id,
            'from_status'    => BatchStatus::Available->value,
            'to_status'      => BatchStatus::Accepted->value,
            'actor_id'       => $agent->user_id,
            'actor_role'     => 'agent',
            'meta'           => ['agent' => $agent->code],
            'created_at'     => now(),
        ]);

        return true;
    }
}
