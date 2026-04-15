<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class MedicineInventory extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'medicineinventories';

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'date',
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

    public function category()
    {
        return $this->belongsTo(MedicineCategory::class, 'medicine_category_id', 'id');
    }

    public function medicineCategory()
    {
        return $this->category();
    }
    
    public function supplier()
    {
        return $this->belongsTo(MedicineSupplier::class, 'supplier_id', 'id');
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class, 'medicine_inventory_id');
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class, 'medicine_inventory_id');
    }
}
