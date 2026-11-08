<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    protected $fillable = ['branch_id', 'terminal_code', 'for_date', 'last_number'];

    protected $casts = [
        'for_date' => 'date',
        'last_number' => 'integer',
    ];
}
