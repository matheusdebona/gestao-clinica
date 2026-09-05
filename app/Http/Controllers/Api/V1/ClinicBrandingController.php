<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdateClinicBrandingRequest;
use App\Http\Requests\Api\V1\Clinic\UploadClinicLogoRequest;
use App\Http\Resources\Api\V1\ClinicBrandingResource;
use App\Models\Clinic;
use App\Support\CurrentClinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClinicBrandingController extends Controller
{
    public function show(): ClinicBrandingResource
    {
        return new ClinicBrandingResource($this->currentClinic());
    }

    public function update(UpdateClinicBrandingRequest $request): ClinicBrandingResource
    {
        $clinic = $this->currentClinic();
        $clinic->updateBranding($request->validated());

        return new ClinicBrandingResource($clinic->fresh());
    }

    public function uploadLogo(UploadClinicLogoRequest $request): ClinicBrandingResource
    {
        $clinic = $this->currentClinic();
        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $path = "clinics/{$clinic->id}/logo.{$extension}";

        $previous = $clinic->branding()['logo_path'] ?? null;

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()), [
            'ContentType' => $file->getMimeType() ?: 'image/png',
        ]);

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('s3')->delete($previous);
        }

        $clinic->updateBranding(['logo_path' => $path]);

        return new ClinicBrandingResource($clinic->fresh());
    }

    public function deleteLogo(): ClinicBrandingResource|JsonResponse
    {
        $clinic = $this->currentClinic();
        $logoPath = $clinic->branding()['logo_path'] ?? null;

        if (is_string($logoPath) && $logoPath !== '') {
            Storage::disk('s3')->delete($logoPath);
        }

        $clinic->updateBranding(['logo_path' => null]);

        return new ClinicBrandingResource($clinic->fresh());
    }

    private function currentClinic(): Clinic
    {
        $clinicId = CurrentClinic::id();

        if ($clinicId === null) {
            throw new NotFoundHttpException('No clinic bound to this user.');
        }

        return Clinic::query()->findOrFail($clinicId);
    }
}
