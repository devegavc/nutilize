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
        return $this->office && $this->office->isPhysicalFacilities() && strtolower($this->role) === 'admin';
    }

    public function isOfficeApprover()
    {
        return !is_null($this->office_id) && strtolower((string) $this->role) === 'admin';
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'user_id', 'user_id');
    }
}
