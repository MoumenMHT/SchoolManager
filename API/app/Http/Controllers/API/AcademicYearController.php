<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicYearController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $years = AcademicYear::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('start_date', 'desc')
            ->get();
        return response()->json($years);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('academic_years', 'name')->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_current' => 'boolean'
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;

        DB::beginTransaction();
        try {
            if (!empty($validated['is_current'])) {
                AcademicYear::where('tenant_id', $validated['tenant_id'])->update(['is_current' => false]);
            }

            $academicYear = AcademicYear::create($validated);
            DB::commit();
            return response()->json($academicYear, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create academic year: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AcademicYear $academicYear)
    {
        if ($academicYear->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }
        return response()->json($academicYear);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AcademicYear $academicYear)
    {
        if ($academicYear->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:60',
                Rule::unique('academic_years', 'name')->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })->ignore($academicYear->id)
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_current' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            if (isset($validated['is_current']) && $validated['is_current']) {
                AcademicYear::where('tenant_id', $academicYear->tenant_id)
                    ->where('id', '!=', $academicYear->id)
                    ->update(['is_current' => false]);
            }

            $academicYear->update($validated);
            DB::commit();
            return response()->json($academicYear);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update academic year.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicYear $academicYear)
    {
        if ($academicYear->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

        $academicYear->delete();
        return response()->json(['message' => 'Academic year deleted successfully']);
    }

    /**
     * Mark an academic year as current.
     */
    public function setCurrent(AcademicYear $academicYear)
    {
        if ($academicYear->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try {
            AcademicYear::where('tenant_id', $academicYear->tenant_id)->update(['is_current' => false]);
            $academicYear->update(['is_current' => true]);
            DB::commit();
            return response()->json(['message' => 'Academic year marked as current', 'academicYear' => $academicYear]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update academic year status.'], 500);
        }
    }
}
