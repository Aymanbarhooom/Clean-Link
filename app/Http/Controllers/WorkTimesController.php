<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\WorkTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponse;

class WorkTimesController extends Controller
{
    use ApiResponse;
    public function insertOrUpdate(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'work_times' => 'required|array|min:1',
            'work_times.*.day_of_week' => 'required|integer|between:0,6|distinct',
            'work_times.*.is_holiday' => 'required|boolean',
            'work_times.*.open_at' => 'required_if:work_times.*.is_holiday,false|nullable|date_format:H:i',
            'work_times.*.close_at' => 'required_if:work_times.*.is_holiday,false|nullable|date_format:H:i',
        ]);

        $user = auth()->user();
        $company = Company::find($request->company_id);

        if (!$user->isCompanyManager() && $company->manager_id !== $user->id) {
            return $this->errorResponse('No registered business organization profile linked to your account context', 422);
        }

        $savedWorkTimes = DB::transaction(function () use ($request) {
            $results = [];

            foreach ($request->work_times as $workTimeData) {
                $results[] = WorkTime::updateOrCreate(
                    [
                        'company_id' => $request->company_id,
                        'day_of_week' => $workTimeData['day_of_week'],
                    ],
                    [
                        'open_at' => $workTimeData['is_holiday'] ? null : ($workTimeData['open_at'] ?? null),
                        'close_at' => $workTimeData['is_holiday'] ? null : ($workTimeData['close_at'] ?? null),
                        'is_holiday' => $workTimeData['is_holiday'],
                    ]
                );
            }

            return $results;
        });

        return $this->successResponse($savedWorkTimes, 'Work times updated successfully');
    }

}
