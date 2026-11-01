<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LeadActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeadActivity */
class LeadActivityResource extends JsonResource
{
    /** Latest comment id of the lead, when already known (avoids one query per comment). */
    public ?int $latestCommentId = null;

    public function withLatestCommentId(?int $id): static
    {
        $this->latestCommentId = $id;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'note' => $this->note,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'from_status' => LeadStatusResource::make($this->whenLoaded('fromStatus')),
            'to_status' => LeadStatusResource::make($this->whenLoaded('toStatus')),
            'created_at' => $this->created_at,
            'edited_at' => $this->edited_at,
            'can_edit' => $this->when(
                $this->isComment(),
                fn () => $this->canBeEditedBy($request->user(), $this->latestCommentId),
            ),
        ];
    }
}
