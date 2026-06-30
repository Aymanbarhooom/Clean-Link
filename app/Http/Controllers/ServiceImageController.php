<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'image_before' => 'required|image|max:2048',
            'image_after' => 'required|image|max:2048',
        ]);
        $user = auth()->user();
        $service = Service::find($request->service_id);
        $company = $service->company;
        if(!$user->isCompanyManager() && $company->manager_id !== $user->id) {

            return $this->errorResponse('No registered business organization profile linked to your account context', 422);
        }

        $serviceImage = ServiceImage::create([
            'service_id' => $request->service_id,
        ]);
        if ($request->hasFile('image_before')) {
            $path = $request->file('image_before')->store('service_secondary', 'public');
            $serviceImage->image_before = $path;
        }
        if ($request->hasFile('image_after')) {
            $path = $request->file('image_after')->store('service_secondary', 'public');
            $serviceImage->image_after = $path;
        }

        return $this->successResponse($serviceImage, 'Service Image added successfully', 211);
    }
}
