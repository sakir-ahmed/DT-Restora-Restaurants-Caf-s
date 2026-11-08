<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('admin:reset-password {username=admin : Username of the staff account}')]
#[Description('Set a new random strong password for a staff account and print it once.')]
class ResetAdminPasswordCommand extends Command
{
    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $user = User::query()->where('username', $username)->first();

        if ($user === null) {
            $this->error("No staff account has the username \"{$username}\".");

            return self::FAILURE;
        }

        $password = Str::password(20, symbols: false);

        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        $this->warn('Password changed. Save it now, it is shown only once.');
        $this->table(['Username', 'Password'], [[$user->username, $password]]);

        return self::SUCCESS;
    }
}
