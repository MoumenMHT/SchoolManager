<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAcademicYear
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->has('academic_year_id') && $request->has('academic_year')) {
            $yearString = $request->input('academic_year');
            $academicYear = \App\Models\AcademicYear::where('name', $yearString)->first();
            
            if ($academicYear) {
                $request->merge(['academic_year_id' => $academicYear->id]);
            }
        }

        return $next($request);
    }
}
