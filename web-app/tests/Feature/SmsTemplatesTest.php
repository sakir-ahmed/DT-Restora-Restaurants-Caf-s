<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\SmsTemplates;
use App\Services\Sms\SmsText;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->role('owner')->create();
});

it('counts SMS parts the way carriers do', function (): void {
    expect(SmsText::analyze(str_repeat('a', 160))['segments'])->toBe(1)
        ->and(SmsText::analyze(str_repeat('a', 161))['segments'])->toBe(2)
        ->and(SmsText::analyze(str_repeat('a', 306))['segments'])->toBe(2)
        ->and(SmsText::analyze(str_repeat('a', 307))['segments'])->toBe(3)
        ->and(SmsText::analyze('{')['units'])->toBe(2)
        ->and(SmsText::analyze('ধন্যবাদ')['encoding'])->toBe('unicode')
        ->and(SmsText::analyze(str_repeat('ক', 70))['segments'])->toBe(1)
        ->and(SmsText::analyze(str_repeat('ক', 71))['segments'])->toBe(2)
        ->and(SmsText::analyze('one two  three')['words'])->toBe(3)
        ->and(SmsText::analyze('')['segments'])->toBe(0);
});

it('uses the default wording until a custom one is saved, per language', function (): void {
    expect(SmsTemplates::render('birthday', ['name' => 'Rahim'], 'en'))->toBe('Happy birthday, Rahim. A treat is waiting on your next order.');

    SmsTemplates::save('birthday', ['en' => 'Hi {name}, lunch is on us today!']);

    expect(SmsTemplates::render('birthday', ['name' => 'Rahim'], 'en'))->toBe('Hi Rahim, lunch is on us today!')
        ->and(SmsTemplates::render('birthday', ['name' => 'Rahim'], 'bn'))->toBe(__('web.sms_birthday', ['name' => 'Rahim'], 'bn'));
});

it('shows built-in text in {variable} form and resets when the text equals the default', function (): void {
    expect(SmsTemplates::defaultBody('invoice', 'en'))->toContain('{invoice}')->toContain('{total}')->not->toContain(':invoice');

    SmsTemplates::save('invoice', ['en' => 'Custom {invoice} Tk {total}']);
    expect(SmsTemplates::customBody('invoice', 'en'))->not->toBeNull();

    SmsTemplates::save('invoice', ['en' => SmsTemplates::defaultBody('invoice', 'en')]);
    expect(SmsTemplates::customBody('invoice', 'en'))->toBeNull()
        ->and(Setting::current()->sms_templates)->toBeNull();
});

it('rejects unknown, missing and stray variables and over-long text', function (): void {
    $put = fn (array $body) => $this->actingAs($this->owner, 'staff')->put(route('admin.settings.sms.templates', 'invoice'), $body);

    $put(['en' => 'Total {total} {secret}'])->assertSessionHasErrors('en');
    $put(['en' => 'Invoice {invoice} paid'])->assertSessionHasErrors('en');
    $put(['en' => 'Invoice {invoice} total {total} }'])->assertSessionHasErrors('en');
    $put(['en' => str_repeat('a', 481).' {invoice} {total}'])->assertSessionHasErrors('en');
    $put(['en' => str_repeat('ক', 300).' {invoice} {total}'])->assertSessionHasErrors('en');

    expect(Setting::current()->sms_templates)->toBeNull();

    $put(['en' => 'Invoice {invoice}, total Tk {total}. Thanks!', 'bn' => 'ইনভয়েস {invoice}, মোট {total}'])->assertSessionHasNoErrors();

    expect(SmsTemplates::customBody('invoice', 'bn'))->toBe('ইনভয়েস {invoice}, মোট {total}');

    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.sms.templates', 'nope'), ['en' => 'x'])->assertNotFound();
});

it('sends the customer-language text through the real send points', function (): void {
    SmsTemplates::save('win_back', ['bn' => '{name}, আবার আসুন!']);

    expect(SmsTemplates::render('win_back', ['name' => 'করিম'], 'bn'))->toBe('করিম, আবার আসুন!')
        ->and(SmsTemplates::render('win_back', ['name' => 'Karim'], 'en'))->toBe(__('web.sms_winback', ['name' => 'Karim'], 'en'));
});
