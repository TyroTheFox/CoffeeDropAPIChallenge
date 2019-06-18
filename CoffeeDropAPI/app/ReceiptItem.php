<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReceiptItem extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'receiptitems';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coffeePodType',
        'tier1Total',
        'tier2Total',
        'tier3Total',
        'tier1Count',
        'tier2Count',
        'tier3Count',
        'total',
        'count'
    ];
}
