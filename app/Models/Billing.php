<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Billing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [

        'invoice_number',
        'bill_number',
        'case_number',

        // Patient Details
        'patient_id',
        'patient_name',
        'patient_mobile',
        'gender',

        // Doctor Details
        'doctor_id',
        'doctor_name',
        'doctor_type',

        // referrer Details
        'referrer_id',

        // Payment Details
        'card_type',
        'pay_mode',
        'card_number',

        // Financial Summary
        'total',
        'discount',
        'discount_type',
        'payable_amount',
        'paid_amt',
        'change_amt',
        'receiving_amt',
        'due_amount',
        'extra_flat_discount',

        // Delivery and Notes
        'delivery_date',
        'remarks',

        // Commission Details
        'commission_total',
        'physyst_amt',
        'commission_slider',

        // System Fields
        'created_by',
        'payment_status',
        'status',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'paid_amt' => 'decimal:2',
        'change_amt' => 'decimal:2',
        'receiving_amt' => 'decimal:2',
        'commission_total' => 'decimal:2',
        'physyst_amt' => 'decimal:2',
        'commission_slider' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($billing) {
            // Auto-generate numbers only for new records
            if (empty($billing->invoice_number)) {
                $billing->invoice_number = self::generateInvoiceNumber();
            }
            if (empty($billing->bill_number)) {
                $billing->bill_number = self::generateBillNumber();
            }
            if (empty($billing->case_number)) {
                $billing->case_number = self::generateCaseNumber();
            }
        });
    }

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber(): string
    {
        $year = Carbon::now()->year;
        $prefix = "INV-{$year}-";

        // Get the last invoice number for current year
        $lastInvoice = self::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            // Extract the number part and increment
            $lastNumber = (int) substr($lastInvoice->invoice_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique bill number
     */
    public static function generateBillNumber(): string
    {
        $year = Carbon::now()->year;
        $prefix = "BILL-{$year}-";

        $lastBill = self::where('bill_number', 'like', $prefix . '%')
            ->orderBy('bill_number', 'desc')
            ->first();

        if ($lastBill) {
            $lastNumber = (int) substr($lastBill->bill_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique case number
     */
    public static function generateCaseNumber(): string
    {
        $year = Carbon::now()->year;
        $prefix = "CASE-{$year}-";

        $lastCase = self::where('case_number', 'like', $prefix . '%')
            ->orderBy('case_number', 'desc')
            ->first();

        if ($lastCase) {
            $lastNumber = (int) substr($lastCase->case_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Relationships
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Admin::class);
    }

    public function billItems()
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'case_id', 'case_number');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'billing_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    public function getDoctorAttribute()
    {
        if ($this->doctor_type === 'admin') {
            return $this->belongsTo(Admin::class, 'doctor_id')->first();
        } elseif ($this->doctor_type === 'billing') {
            return $this->belongsTo(BillingDoctor::class, 'doctor_id')->first();
        }

        return null;
    }

    public function scopeWhereDoctorType($query, $type)
    {
        return $query->where('doctor_type', $type);
    }

    public function scopeWherePaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Accessors & Mutators
     */
    public function getFormattedTotalAttribute(): string
    {
        return '৳' . number_format($this->total, 2);
    }

    public function getFormattedPayableAmountAttribute(): string
    {
        return '৳' . number_format($this->payable_amount, 2);
    }

    public function getDiscountAmountAttribute(): float
    {
        if ($this->discount_type === 'percentage') {
            return ($this->total * $this->discount) / 100;
        }
        return $this->discount;
    }

    public function getFormattedDiscountAmountAttribute(): string
    {
        return '৳' . number_format($this->discount_amount, 2);
    }

    /**
     * Business Logic Methods
     */
    public function calculateTotals(): void
    {
        $itemsTotal = $this->billItems()->sum('net_amount');
        $this->total = $itemsTotal;

        // Calculate payable amount after discount
        $discountAmount = 0;
        if ($this->discount > 0) {
            if ($this->discount_type === 'percentage') {
                $discountAmount = ($this->total * $this->discount) / 100;
            } else {
                $discountAmount = $this->discount;
            }
        }

        $this->payable_amount = max(0, $this->total - $discountAmount);

        // Calculate commission
        if ($this->commission_slider > 0) {
            $this->commission_total = $this->total;
            $this->physyst_amt = ($this->commission_total * $this->commission_slider) / 100;
        }

        $this->save();
    }

    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->payments()->sum('amount');

        if ($totalPaid >= $this->payable_amount) {
            $this->payment_status = 'Paid';
        } elseif ($totalPaid > 0) {
            $this->payment_status = 'Partial';
        } else {
            $this->payment_status = 'Pending';
        }

        $this->save();
    }
    public function dueCollections()
{
    return $this->hasMany(
        \App\Models\DueCollection::class,
        'billing_id',
        'id'
    );
}
}
