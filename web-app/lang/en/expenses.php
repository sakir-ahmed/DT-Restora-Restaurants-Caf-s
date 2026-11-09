<?php

declare(strict_types=1);

return [
    'title' => 'Expenses',
    'subtitle' => 'Operating expenses of this branch.',
    'add' => 'Add expense',
    'date' => 'Date',
    'category' => 'Category',
    'description' => 'Description',
    'amount' => 'Amount (BDT)',
    'recorded' => 'Expense recorded.',
    'deleted' => 'Expense deleted.',
    'empty' => 'No expenses recorded yet',
    'categories' => [
        'rent' => 'Rent',
        'utilities' => 'Utilities',
        'marketing' => 'Marketing',
        'repairs' => 'Repairs',
        'supplies' => 'Supplies',
        'salary_extra' => 'Extra payments',
        'other' => 'Other',
    ],
    'delete_journal_note' => 'Reversal for deleted expense #:id',
    'delete_title' => 'Delete this expense?',
    'delete_message' => 'The expense is taken out of reports and its accounting entry is reversed. It stays in the history marked as deleted.',
];
