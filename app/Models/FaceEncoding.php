<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaceEncoding extends Model
{
    use HasFactory;

    protected $table = 'face_encodings';
    protected $guarded = [];

    protected $casts = [
        'descriptor' => 'array',
    ];
}
