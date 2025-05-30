<?php

namespace Condoedge\Finance\Http\Controllers\GL;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Condoedge\Finance\Services\GL\FiscalPeriodService;
use Condoedge\Finance\Models\GL\FiscalYearSetup;
use Condoedge\Finance\Models\GL\FiscalPeriod;
use Carbon\Carbon;

class FiscalPeriodController extends Controller
{
    protected FiscalPeriodService $fiscalPeriodService;

    public function __construct(FiscalPeriodService $fiscalPeriodService)
    {
        $this->fiscalPeriodService = $fiscalPeriodService;
    }

    /**
     * Get fiscal year setup
     */
    public function getFiscalYearSetup()
    {
        $setup = FiscalYearSetup::getActive();
        
        return response()->json([
            'data' => $setup,
            'message' => $setup ? 'Fiscal year setup retrieved successfully' : 'No active fiscal year setup found'
        ]);
    }

    /**
     * Create or update fiscal year setup
     */
    public function setFiscalYearSetup(Request $request)
    {
        $request->validate([
            'company_fiscal_start_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        try {
            // Deactivate existing setups
            FiscalYearSetup::where('is_active', true)->update(['is_active' => false]);

            // Create new setup
            $setup = FiscalYearSetup::create([
                'company_fiscal_start_date' => $request->company_fiscal_start_date,
                'notes' => $request->notes,
                'is_active' => true
            ]);

            return response()->json([
                'data' => $setup,
                'message' => 'Fiscal year setup created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create fiscal year setup',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Create fiscal periods
     */
    public function createFiscalPeriods(Request $request)
    {
        $request->validate([
            'fiscal_year' => 'required|integer',
            'fiscal_start_date' => 'required|date',
            'number_of_periods' => 'integer|min:1|max:24'
        ]);

        try {
            $periods = $this->fiscalPeriodService->createFiscalPeriods(
                $request->fiscal_year,
                Carbon::parse($request->fiscal_start_date),
                $request->number_of_periods ?? 12
            );

            return response()->json([
                'data' => $periods,
                'message' => 'Fiscal periods created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create fiscal periods',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get fiscal periods
     */
    public function getFiscalPeriods(Request $request)
    {
        $query = FiscalPeriod::query();

        if ($request->has('fiscal_year')) {
            $query->where('fiscal_year', $request->fiscal_year);
        }

        $periods = $query->orderBy('fiscal_year')->orderBy('period_number')->get();

        return response()->json([
            'data' => $periods,
            'message' => 'Fiscal periods retrieved successfully'
        ]);
    }

    /**
     * Get period status
     */
    public function getPeriodStatus($periodId)
    {
        try {
            $status = $this->fiscalPeriodService->getPeriodStatus($periodId);

            return response()->json([
                'data' => $status,
                'message' => 'Period status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get period status',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Close period
     */
    public function closePeriod(Request $request, $periodId)
    {
        $request->validate([
            'modules' => 'array',
            'modules.*' => 'in:GL,BNK,RM,PM'
        ]);

        try {
            $modules = $request->modules ?? ['GL', 'BNK', 'RM', 'PM'];
            
            // Validate period closure
            foreach ($modules as $module) {
                $errors = $this->fiscalPeriodService->validatePeriodClosure($periodId, $module);
                if (!empty($errors)) {
                    return response()->json([
                        'error' => 'Cannot close period',
                        'validation_errors' => $errors
                    ], 422);
                }
            }

            $result = $this->fiscalPeriodService->closePeriod($periodId, $modules);

            return response()->json([
                'data' => $result,
                'message' => 'Period closed successfully for ' . implode(', ', $modules)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to close period',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Open period
     */
    public function openPeriod(Request $request, $periodId)
    {
        $request->validate([
            'modules' => 'array',
            'modules.*' => 'in:GL,BNK,RM,PM'
        ]);

        try {
            $modules = $request->modules ?? ['GL', 'BNK', 'RM', 'PM'];
            $result = $this->fiscalPeriodService->openPeriod($periodId, $modules);

            return response()->json([
                'data' => $result,
                'message' => 'Period opened successfully for ' . implode(', ', $modules)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to open period',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get open periods
     */
    public function getOpenPeriods(Request $request)
    {
        $module = $request->get('module', 'GL');
        
        try {
            $periods = $this->fiscalPeriodService->getOpenPeriods($module);

            return response()->json([
                'data' => $periods,
                'message' => "Open periods for {$module} retrieved successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get open periods',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update fiscal period
     */
    public function updateFiscalPeriod(Request $request, $periodId)
    {
        $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after:start_date'
        ]);

        try {
            $period = FiscalPeriod::findOrFail($periodId);
            
            if ($request->has('start_date')) {
                $period->start_date = $request->start_date;
            }
            
            if ($request->has('end_date')) {
                $period->end_date = $request->end_date;
            }
            
            $period->save();

            return response()->json([
                'data' => $period,
                'message' => 'Fiscal period updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update fiscal period',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
