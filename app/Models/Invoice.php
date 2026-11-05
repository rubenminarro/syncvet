<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Enums\InvoiceCondition;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SifenStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'branch_id',
        'client_id',
        'consultation_id',
        'status',
        'invoice_stamp_id',
        'stamp_number',
        'number',
        'customer_name',
        'customer_tax_id',
        'customer_address',
        'customer_email',
        'condition',
        'due_date',
        'total_exempt',
        'total_5',
        'total_10',
        'iva_5',
        'iva_10',
        'discount_total',
        'total',
        'payment_status',
        'balance',
        'cdc',
        'security_code',
        'sifen_status',
        'sifen_response_code',
        'sifen_response_message',
        'sifen_batch_id',
        'xml_path',
        'kude_path',
        'qr_url',
        'sifen_sent_at',
        'sifen_approved_at',
        'created_by',
        'issue_date',
        'issued_at',
        'issued_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'condition' => InvoiceCondition::class,
            'due_date' => 'date',
            'total_exempt' => 'decimal:2',
            'total_5' => 'decimal:2',
            'total_10' => 'decimal:2',
            'iva_5' => 'decimal:2',
            'iva_10' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_status' => InvoicePaymentStatus::class,
            'balance' => 'decimal:2',
            'sifen_status' => SifenStatus::class,
            'sifen_sent_at' => 'datetime',
            'sifen_approved_at' => 'datetime',
            'issue_date' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function invoiceStamp(): BelongsTo
    {
        return $this->belongsTo(InvoiceStamp::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function sifenEvents(): HasMany
    {
        return $this->hasMany(SifenEvent::class);
    }
}
