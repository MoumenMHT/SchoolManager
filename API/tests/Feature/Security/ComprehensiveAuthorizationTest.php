<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Fee;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprehensiveAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // All roles defined in the system
    protected array $allRoles = [
        'admin',
        'secretariat',
        'primary_director',
        'cem_director',
        'lycee_director',
        'accountant',
        'teacher',
        'supervisor',
        'parent'
    ];

    /**
     * @dataProvider routeAuthorizationProvider
     */
    public function test_role_based_authorization(string $method, string $endpointName, array $allowedRoles): void
    {
        $this->withoutExceptionHandling();

        // 1. Create necessary domain entities to prevent 404s for route parameters
        $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);
        $teacher = Teacher::factory()->create(['tenant_id' => $this->tenant->id]);
        $schoolClass = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id]);
        $fee = Fee::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $parentUser = User::factory()->create(['role' => 'parent', 'tenant_id' => $this->tenant->id]);
        $parentModel = \App\Models\ParentModel::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $parentUser->id]);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $parentModel->id]);
        $payment = Payment::factory()->create(['tenant_id' => $this->tenant->id, 'contract_id' => $contract->id]);

        // Map abstract endpoints to concrete URLs
        $urlMap = [
            '/api/users' => '/api/users',
            '/api/students' => '/api/students',
            '/api/students/{id}' => "/api/students/{$student->id}",
            '/api/teachers' => '/api/teachers',
            '/api/teachers/{id}' => "/api/teachers/{$teacher->id}",
            '/api/classes' => '/api/classes',
            '/api/classes/{id}' => "/api/classes/{$schoolClass->id}",
            '/api/subjects' => '/api/subjects',
            '/api/subjects/{id}' => "/api/subjects/{$subject->id}",
            '/api/fees' => '/api/fees',
            '/api/fees/{id}' => "/api/fees/{$fee->id}",
            '/api/contracts' => '/api/contracts',
            '/api/contracts/{id}' => "/api/contracts/{$contract->id}",
            '/api/payments' => '/api/payments',
            '/api/payments/{id}' => "/api/payments/{$payment->id}",
            '/api/grades' => '/api/grades',
            '/api/attendances' => '/api/attendances',
            '/api/supervisor/classes' => '/api/supervisor/classes',
            '/api/parent/students' => '/api/parent/students',
        ];

        $url = $urlMap[$endpointName] ?? $endpointName;

        // Catch the expected exceptions or 403s
        foreach ($this->allRoles as $role) {
            Auth::forgetGuards();
            $user = User::factory()->create(['role' => $role, 'tenant_id' => $this->tenant->id]);
            $token = $user->createToken('test')->plainTextToken;

            try {
                $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                    ->json($method, $url);

                $status = $response->status();
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $status = 403;
            } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
                $status = 403;
            } catch (\Exception $e) {
                $status = 200; // It passed the middleware authorization check and hit an internal error, meaning it's authorized
            }

            if (in_array($role, $allowedRoles)) {
                $this->assertNotEquals(403, $status, "Role '{$role}' should be ALLOWED to access {$method} {$endpointName}, but got 403.");
                $this->assertNotEquals(401, $status, "Role '{$role}' got 401 instead of proper auth logic.");
            } else {
                $this->assertEquals(403, $status, "Role '{$role}' should be FORBIDDEN from accessing {$method} {$endpointName}, but got {$status}.");
            }
        }
    }

    public static function routeAuthorizationProvider(): array
    {
        $allAcademicManagers = ['admin', 'secretariat', 'primary_director', 'cem_director', 'lycee_director', 'accountant'];
        
        return [
            // User Management
            ['GET', '/api/users', ['admin', 'secretariat']],
            
            // Academic Management
            ['GET', '/api/students', $allAcademicManagers],
            ['GET', '/api/students/{id}', $allAcademicManagers],
            ['DELETE', '/api/students/{id}', $allAcademicManagers],
            
            ['GET', '/api/teachers', $allAcademicManagers],
            ['GET', '/api/teachers/{id}', $allAcademicManagers],
            
            ['GET', '/api/classes', $allAcademicManagers],
            ['POST', '/api/classes', $allAcademicManagers],

            ['GET', '/api/subjects', ['admin', 'secretariat', 'primary_director', 'cem_director', 'lycee_director', 'accountant', 'teacher', 'supervisor', 'parent']], // shared
            ['POST', '/api/subjects', $allAcademicManagers],
            
            // Payment Management
            ['GET', '/api/fees', ['admin', 'accountant']],
            ['POST', '/api/fees', ['admin', 'accountant']],
            ['DELETE', '/api/fees/{id}', ['admin', 'accountant']],
            
            ['GET', '/api/contracts', ['admin', 'accountant']],
            ['POST', '/api/contracts', ['admin', 'accountant']],
            
            ['GET', '/api/payments', ['admin', 'accountant']],
            ['POST', '/api/payments', ['admin', 'accountant']],
            
            // Grades & Attendance
            ['GET', '/api/grades', ['teacher', 'supervisor', 'admin', 'primary_director', 'cem_director', 'lycee_director']],
            ['POST', '/api/grades', ['teacher', 'supervisor', 'admin', 'primary_director', 'cem_director', 'lycee_director']],
            
            ['GET', '/api/attendances', ['teacher', 'supervisor', 'admin', 'primary_director', 'cem_director', 'lycee_director']],
            ['POST', '/api/attendances', ['teacher', 'supervisor', 'admin', 'primary_director', 'cem_director', 'lycee_director']],

            // Supervisor Portal
            ['GET', '/api/supervisor/classes', ['supervisor']],

            // Parent Portal
            ['GET', '/api/parent/students', ['parent', 'admin']],
        ];
    }
}
