<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillItemParameterResult extends Model
{
    use HasFactory;

    protected $table = 'bill_item_parameter_results';

    protected $guarded = [];

    public function billItem()
    {
        return $this->belongsTo(BillItem::class, 'bill_item_id');
    }

    public function pathologyTestParameter()
    {
        return $this->belongsTo(PathologyTestParameter::class, 'pathology_test_parameter_id');
    }
}
