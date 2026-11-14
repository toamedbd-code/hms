<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MainCategory extends Model
{
    use SoftDeletes;

    protected $table = 'main_categories';

    protected $fillable = ['name', 'status'];
}
