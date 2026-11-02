<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create(['password' => 'old-password-1', 'email' => 'me@example.com']);
    $this->user->assignRole('cashier');
});

it('shows the signed-in staff member their own profile', function (): void {
    $this->actingAs($this->user, 'staff')->get(route('admin.profile.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/profile/Index')
            ->where('profile.email', 'me@example.com')
            ->where('profile.roles', ['cashier']));
});

it('updates the basic details and the photo, then removes the photo', function (): void {
    Storage::fake('public');

    $this->actingAs($this->user, 'staff')->post(route('admin.profile.update'), [
        'name' => 'New Name',
        'email' => 'me@example.com',
        'username' => 'newname',
        'mobile' => '01711111111',
        'locale' => 'bn',
        'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
    ])->assertRedirect();

    $user = $this->user->fresh();

    expect($user->name)->toBe('New Name')
        ->and($user->username)->toBe('newname')
        ->and($user->locale)->toBe('bn')
        ->and($user->avatar_path)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($user->avatar_path);

    $path = $user->avatar_path;

    $this->actingAs($user, 'staff')->post(route('admin.profile.update'), [
        'name' => 'New Name',
        'email' => 'me@example.com',
        'locale' => 'bn',
        'remove_avatar' => true,
    ])->assertRedirect();

    expect($user->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('does not take another staff member email or clear every login identifier', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->user, 'staff')->post(route('admin.profile.update'), [
        'name' => 'Me', 'email' => 'taken@example.com', 'locale' => 'en',
    ])->assertSessionHasErrors('email');

    $this->user->update(['username' => null, 'mobile' => null]);

    $this->actingAs($this->user, 'staff')->post(route('admin.profile.update'), [
        'name' => 'Me', 'email' => '', 'username' => '', 'mobile' => '', 'locale' => 'en',
    ])->assertSessionHasErrors('email');
});

it('does not let the profile change roles or the branch', function (): void {
    $this->actingAs($this->user, 'staff')->post(route('admin.profile.update'), [
        'name' => 'Me', 'email' => 'me@example.com', 'locale' => 'en', 'roles' => ['super-admin'], 'branch_id' => 99,
    ])->assertRedirect();

    expect($this->user->fresh()->getRoleNames()->all())->toBe(['cashier'])
        ->and($this->user->fresh()->branch_id)->toBe($this->user->branch_id);
});

it('changes the password only with the correct current password', function (): void {
    $this->actingAs($this->user, 'staff')->put(route('admin.profile.password'), [
        'current_password' => 'wrong-password',
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password-1', $this->user->fresh()->password))->toBeTrue();

    $this->actingAs($this->user, 'staff')->put(route('admin.profile.password'), [
        'current_password' => 'old-password-1',
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('brand-new-pass', $this->user->fresh()->password))->toBeTrue();
});

it('rejects a short or unconfirmed new password', function (): void {
    $this->actingAs($this->user, 'staff')->put(route('admin.profile.password'), [
        'current_password' => 'old-password-1',
        'password' => 'short',
        'password_confirmation' => 'other',
    ])->assertSessionHasErrors('password');
});
