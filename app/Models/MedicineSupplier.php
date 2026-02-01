<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class MedicineSupplier extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'medicinesuppliers';

    protected $fillable = [
        'name',
        'phone',
        'contact_person_name',
        'contact_person_phone',
        'drug_lisence_no',
        'address',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $model->created_at = date('Y-m-d H:i:s');
        });

        static::updating(function ($model) {
            $model->updated_at = date('Y-m-d H:i:s');
        });
    }

    public function medicinePurchases()
    {
        return $this->hasMany(MedicinePurchase::class, 'supplier_id');
    }

    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id');
    }

    public function medicines()
    {
        return $this->hasMany(MedicineInventory::class, 'supplier_id');
    }
}
