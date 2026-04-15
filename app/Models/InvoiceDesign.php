<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class InvoiceDesign extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'invoicedesigns';

    protected $fillable = [
        'footer_content',
        'header_photo_path',
        'footer_photo_path',
        'module',
        'header_height',
        'footer_height',
        'status'
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

    public function getHeaderPhotoUrlAttribute()
    {
        return publicStorageUrl($this->header_photo_path);
    }

    public function getFooterPhotoUrlAttribute()
    {
        return publicStorageUrl($this->footer_photo_path);
    }
}
