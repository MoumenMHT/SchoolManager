<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'phone',
        'address',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    public function parent()
    {
        return $this->hasOne(ParentModel::class);
    }

    public function supervisor()
    {
        return $this->hasOne(Supervisor::class);
    }

    // Helper methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isTeacher()
    {
        return $this->role === 'teacher';
    }

    public function isParent()
    {
        return $this->role === 'parent';
    }

    public function isSupervisor()
    {
        return $this->role === 'supervisor';
    }

    public function isSecretariat()
    {
        return $this->role === 'secretariat';
    }

    public function isAccountant()
    {
        return $this->role === 'accountant';
    }

    public function isPrimaryDirector()
    {
        return $this->role === 'primary_director';
    }

    public function isCemDirector()
    {
        return $this->role === 'cem_director';
    }

    public function isLyceeDirector()
    {
        return $this->role === 'lycee_director';
    }

    public function isDirector()
    {
        return in_array($this->role, ['primary_director', 'cem_director', 'lycee_director']);
    }

    public function directorCycle()
    {
        if ($this->isPrimaryDirector()) return 'primaire';
        if ($this->isCemDirector()) return 'cem';
        if ($this->isLyceeDirector()) return 'lycee';
        return null;
    }
}
