<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'account_code',
        'full_name',
        'father_name',
        'phone',
        'email',
        'type',
        'account_sub_category_id',
        'branch_id',
        'date_of_birth',
        'kyc_date',
        'occupation',
        'monthly_income',
        'id_card_type',
        'id_card_number',
        'id_card_file',
        'business_name',
        'tin',
        'license_type',
        'license_number',
        'license_issue_date',
        'license_expiry_date',
        'account_number',
        'shop_house_no',
        'is_pep',
        'is_sanction_list_applied',
        'income_source_id',
        'nationality_id',
        'transaction_volume',
        'purpose',
        'geographical_area',
        'distribution_channels',
        'risk_level',
        'is_active',
        'address',
        'profile_picture',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(AccountSubCategory::class, 'account_sub_category_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function isActive()
    {
        return $this->where('is_active', 1);
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'chart_of_account_id');
    }
}
