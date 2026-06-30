<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\WorkTime;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class WorkTimesController extends Controller
{
    use ApiResponse;
    public function insertOrUpdate(Request $request)
    {
            $request->validate([
            'company_id' => 'required|exists:companies,id',
            'day_of_week' => 'required|integer|between:0,6',
            'is_holiday' => 'boolean',
            'open_at' => 'required_if:is_holiday,false',
            'close_at' => 'required_if:is_holiday,false',
        ]);

        $user = auth()->user();
        $company = Company::find($request->company_id);

        if(!$user->isCompanyManager() && $company->manager_id !== $user->id) {

            return $this->errorResponse('No registered business organization profile linked to your account context', 422);
        }

        $workTime = WorkTime::updateOrCreate(
            [
                'company_id' => $request->company_id,
                'day_of_week' => $request->day_of_week
            ],
            [
                'open_at' => $request->is_holiday ? null : $request->open_at,
                'close_at' => $request->is_holiday ? null : $request->close_at,
                'is_holiday' => $request->is_holiday ?? false,
            ]
        );

        return $this->successResponse($workTime,"Added Successfully");
        
    }

}
