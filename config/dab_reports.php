<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DAB reporting foundation
    |--------------------------------------------------------------------------
    | WeSarafi reports all regulatory figures in AFN. Currency columns retain
    | the required split between AFN, USD (AFN equivalent), and other foreign
    | currencies (AFN equivalent).
    */
    'base_currency' => 'AFN',
    'usd_currency' => 'USD',

    /*
    | Monetary accounts are remeasured at the reporting-date closing rate.
    | Non-monetary accounts remain at their historical AFN carrying amount.
    */
    'monetary_account_codes' => [
        '1000', // Cash on Hand
        '1001', // Cash in Bank
        '1002', // Accounts Receivable
        '1006', // Due from Branches
        '1007', // Input Tax Receivable
        '1008', // Claims on Da Afghanistan Bank
        '2000', // Accounts Payable
        '2001', // Customer Amanat
        '2002', // Due to Branches
        '2003', // Accrued Expenses
        '2004', // Tax Payable
        '2005', // Deferred Revenue
        '2100', // Loans Payable
        '2101', // Bonds Payable
        '2102', // Deferred Tax Liability
    ],

    'current_profit_loss_code' => '3002',
];
