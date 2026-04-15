<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpdOtCharge extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ipd_ot_charges';

    protected $guarded = [];

    protected $casts = [
        'performed_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:3',
        'total_amount' => 'decimal:2',
    ];

    public function ipdPatient()
    {
        return $this->belongsTo(IpdPatient::class, 'ipd_patient_id');
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
