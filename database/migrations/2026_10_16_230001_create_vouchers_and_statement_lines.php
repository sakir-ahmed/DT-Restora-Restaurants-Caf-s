<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->date('entry_date');
            $table->string('pay_from_code', 20);
            $table->string('expense_account_code', 20)->nullable();
            $table->text('note')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'supplier_id']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('account_code', 20);
            $table->date('occurred_on');
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['account_code', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('vouchers');
    }
};
