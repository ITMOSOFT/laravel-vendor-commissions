<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('itmosoft-vendor-commissions.table_prefix', 'itmosoft_');
        $rulesTable = $prefix.'vendor_commission_rules';
        $commissionsTable = $prefix.'vendor_commissions';

        if (! Schema::hasTable($rulesTable)) {
            Schema::create($rulesTable, function (Blueprint $table): void {
                $table->id();
                $table->string('name', 160);
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->string('rate_type', 20);
                $table->decimal('rate', 12, 4);
                $table->char('currency', 3)->default('USD');
                $table->decimal('minimum_amount', 18, 2)->nullable();
                $table->decimal('maximum_amount', 18, 2)->nullable();
                $table->integer('priority')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                $table->index(['currency', 'is_active', 'priority']);
            });
        }

        if (! Schema::hasTable($commissionsTable)) {
            Schema::create($commissionsTable, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->unsignedBigInteger('commission_rule_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->string('source_type')->nullable();
                $table->string('source_id')->nullable();
                $table->string('external_reference')->nullable()->unique();
                $table->decimal('gross_amount', 18, 2);
                $table->decimal('commission_amount', 18, 2);
                $table->decimal('vendor_amount', 18, 2);
                $table->char('currency', 3)->default('USD');
                $table->string('rate_type', 20);
                $table->decimal('rate', 12, 4);
                $table->string('status', 20)->default('pending')->index();
                $table->json('metadata')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
                $table->index(['vendor_id', 'currency', 'status']);
                $table->index(['source_type', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        $prefix = (string) config('itmosoft-vendor-commissions.table_prefix', 'itmosoft_');
        Schema::dropIfExists($prefix.'vendor_commissions');
        Schema::dropIfExists($prefix.'vendor_commission_rules');
    }
};
