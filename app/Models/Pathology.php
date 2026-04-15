<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class Pathology extends Authenticatable
{
    use Notifiable, HasFactory, SoftDeletes;

    protected $table = 'pathologies';

    protected $guarded = [];


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

    // public function tests()
    // {
    //     return $this->hasMany(PathologyTest::class);
    // }

    /**
     * Relationship with Patient
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
    
    public function doctor()
    {
        return $this->belongsTo(Admin::class, 'doctor_id');
    }

    /**
     * Generate unique bill number
     */
    public static function generateBillNo()
    {
        $prefix = web_setting_prefix('pathology_bill_prefix', 'Bill');
        $lastBill = self::orderBy('id', 'desc')->first();
        $lastNumber = $lastBill ? (int) str_replace($prefix, '', (string) $lastBill->bill_no) : 0;
        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate totals based on tests
     */
    public function calculateTotals()
    {
        $testTotal = $this->tests->sum('amount');
        $testTax = $this->tests->sum(function ($test) {
            return ($test->amount * $test->tax) / 100;
        });

        $this->total = $testTotal;
        $this->tax_amount = $testTax;
        $this->net_amount = $testTotal + $testTax - $this->discount - $this->extra_discount;

        return $this;
    }

    /**
     * Scope for active pathologies
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Get formatted bill number
     */
    public function getFormattedBillNoAttribute()
    {
        return strtoupper($this->bill_no);
    }


}
