<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the root administrator once, with a random strong password that is
 * printed a single time on the console. A seed run never touches an admin
 * that already exists, so re-seeding in production cannot reset the password.
 */
class RootAdminSeeder extends Seeder
{
    public const USERNAME = 'admin';

    public function run(): void
    {
        if (User::query()->where('username', self::USERNAME)->exists()) {
            $this->command?->info('Root administrator already exists. Use "php artisan admin:reset-password" if the password is lost.');

            return;
        }

        $password = Str::password(20, symbols: false);

        $user = User::query()->create([
            'name' => 'Administrator',
            'username' => self::USERNAME,
            'email' => self::USERNAME.'@'.self::mailDomain(),
            'password' => $password,
            'is_active' => true,
            'branch_id' => null,
        ]);

        $user->syncRoles(['super-admin']);

        $this->command?->newLine();
        $this->command?->warn('Root administrator created. Save this password now, it is shown only once.');
        $this->command?->table(['Username', 'Email', 'Password'], [[$user->username, $user->email, $password]]);
    }

    /**
     * Mail domain of the root account, taken from APP_URL.
     */
    public static function mailDomain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'dtrestora.local';
        }

        return str_contains($host, '.') ? $host : $host.'.local';
    }
}
