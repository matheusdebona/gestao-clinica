<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'client_origin_id' => $this->client_origin_id,
            'lead_status_id' => $this->lead_status_id,
            'notes' => $this->notes,
            'created_by_user_id' => $this->created_by_user_id,
            'client_id' => $this->client_id,
            'converted_at' => $this->converted_at,
            'cancelled_at' => $this->cancelled_at,
            'approached_at' => $this->approached_at,
            'replied_at' => $this->replied_at,
            'booked_at' => $this->booked_at,
            'approached_by' => $this->whenLoaded('approachedBy', fn () => $this->userRef($this->approachedBy)),
            'replied_by' => $this->whenLoaded('repliedBy', fn () => $this->userRef($this->repliedBy)),
            'booked_by' => $this->whenLoaded('bookedBy', fn () => $this->userRef($this->bookedBy)),
            'client_origin' => ClientOriginResource::make($this->whenLoaded('clientOrigin')),
            'status' => LeadStatusResource::make($this->whenLoaded('status')),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy === null ? null : [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'client' => $this->whenLoaded('client', fn () => $this->client === null ? null : [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'whatsapp' => $this->client->whatsapp,
            ]),
            'activities' => $this->whenLoaded('activities', fn () => $this->activityResources()),
            'comments_count' => $this->whenCounted('comments'),
            'last_activity_at' => $this->lastActivityAt()?->toISOString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userRef(mixed $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }

    private function activityResources(): array
    {
        $latestCommentId = LeadActivity::latestCommentIdIn($this->activities);

        return $this->activities
            ->map(fn (LeadActivity $activity) => (new LeadActivityResource($activity))->withLatestCommentId($latestCommentId))
            ->all();
    }
}
