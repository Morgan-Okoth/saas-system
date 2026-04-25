<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Invoice Model
 * 
 * Billing invoices for schools.
 * Generated from subscription charges and usage.
 */
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'subscription_id',
        'invoice_number',
        'amount',
        'currency',
        'status', // draft, pending, paid, overdue, void
        'due_date',
        'paid_at',
        'line_items',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'line_items' => 'array',
    ];

    /**
     * School relationship
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Subscription relationship
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Payment relationship
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || ($this->status === 'pending' && $this->due_date && $this->due_date->isPast());
    }

    /**
     * Check if invoice is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get remaining balance
     */
    public function getBalance(): float
    {
        $totalPaid = $this->payments()
            ->where('status', 'completed')
            ->sum('amount');

        return $this->amount - $totalPaid;
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark invoice as overdue
     */
    public function markAsOverdue(): void
    {
        if ($this->status === 'pending' && $this->due_date && $this->due_date->isPast()) {
            $this->update(['status' => 'overdue']);
        }
    }

    /**
     * Generate invoice number
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ym');
        $lastInvoice = self::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        $number = $lastInvoice ? (int) substr($lastInvoice->invoice_number, -6) + 1 : 1;

        return $prefix . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
