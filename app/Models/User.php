<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'middle_initial', 'last_name', 'full_name', 'username', 'email', 'password', 'role', 'office_id', 'suffix', 'contact_number', 'phone_number'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id', 'office_id');
    }

    public function isPhysicalFacilitiesAdmin()
    {
        if (!$this->office || !$this->office->isPhysicalFacilities()) {
            return false;
        }

        $role = strtolower((string) $this->role);

        return in_array($role, ['admin', 'pf_admin'], true);
    }

    public function isOfficeApprover()
    {
        if (is_null($this->office_id)) {
            return false;
        }

        $role = strtolower((string) $this->role);

        if (in_array($role, ['admin', 'pc_admin'], true)) {
            return true;
        }

        return $role === 'pf_admin' && $this->office && $this->office->isPhysicalFacilities();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'user_id', 'user_id');
    }
}
