<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            // Owner-written SMS wording: {template key: {locale: body}}.
            $table->json('sms_templates')->nullable();
        });

        // The SMS module has its own permission. Deployments only run migrations,
        // so create it here and give it to the roles that already manage marketing.
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $permission = Permission::findOrCreate('sms.manage');

            foreach (['owner', 'branch-manager', 'super-admin'] as $name) {
                Role::query()->where('name', $name)->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
            }
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn('sms_templates');
        });

        Permission::query()->where('name', 'sms.manage')->delete();
    }
};
