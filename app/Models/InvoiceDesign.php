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
        'full_page_pad_path',
        'full_page_pad_content_offset',
        'footer_content_offset',
        'active_pad_type',
        'footer_photo_path',
        'module',
        'footer_content_position',
        'footer_font_size',
        'header_height',
        'footer_height',
        'status'
    ];

    protected $casts = [
        'full_page_pad_content_offset' => 'integer',
        'footer_content_offset' => 'integer',
    ];

    public static function reportFullPagePad(): ?self
    {
        $baseQuery = static::query()
            ->where('status', 'Active')
            ->whereNotNull('full_page_pad_path')
            ->where('full_page_pad_path', '!=', '');

        foreach (['billing', null, 'any'] as $module) {
            $query = clone $baseQuery;
            if ($module === 'billing') {
                $query->where('module', 'billing');
            } elseif ($module === null) {
                $query->whereNull('module');
            }

            $design = $query
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($design) {
                return $design;
            }
        }

        return null;
    }

    public static function resolveForModule(string $module, array $fallbackModules = []): ?self
    {
        $normalizedModule = strtolower(trim($module));
        $modules = array_merge([$normalizedModule], $fallbackModules);

        if ($normalizedModule !== 'billing') {
            $modules[] = 'billing';
        }

        foreach (array_unique(array_filter(array_map(
            static fn ($candidate): string => strtolower(trim((string) $candidate)),
            $modules
        ))) as $candidateModule) {
            $design = static::query()
                ->where('status', 'Active')
                ->whereRaw('LOWER(TRIM(module)) = ?', [$candidateModule])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($design) {
                return $design;
            }
        }

        return static::query()
            ->where('status', 'Active')
            ->whereNull('module')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

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

    public function getFullPagePadUrlAttribute()
    {
        return publicStorageUrl($this->full_page_pad_path);
    }
}
