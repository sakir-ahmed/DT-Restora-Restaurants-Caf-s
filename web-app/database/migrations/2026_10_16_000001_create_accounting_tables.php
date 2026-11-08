<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique(); // 1000, 1100, 4000...
            $table->string('name');
            $table->string('type', 20); // asset | liability | equity | income | expense | cogs
            $table->string('subtype', 40)->nullable(); // cash | bank | wallet | sales | vat...
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 20); // 2026
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journals', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->nullable(); // invoice_no, PUR-12...
            $table->date('entry_date');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('draft'); // draft | posted | reversed
            $table->nullableMorphs('source'); // order, purchase, expense...
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['entry_date']);
            $table->index(['status']);
        });

        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('accounts');
    }
};
