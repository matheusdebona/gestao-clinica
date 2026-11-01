<?php

namespace App\Policies;

use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeadActivityPolicy
{
    /** Editing a lead comment: latest comment only, by its author or a clinic admin / super-admin. */
    public function update(User $user, LeadActivity $activity): Response
    {
        if (! $activity->isComment()) {
            return Response::deny('Apenas comentários podem ser editados.');
        }
        if ((int) $activity->user_id !== (int) $user->id && ! $user->hasAnyRole(['admin', 'super-admin'])) {
            return Response::deny('Somente quem escreveu o comentário ou um administrador pode editá-lo.');
        }
        if (! $activity->canBeEditedBy($user)) {
            return Response::deny('Somente o comentário mais recente pode ser editado.');
        }

        return Response::allow();
    }
}
