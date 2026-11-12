<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\Concerns\InteractsWithApi;
use App\Http\Controllers\Api\Controller;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Services\PosSettings;
use App\Services\PublicCache;
use App\Services\Sms\SmsFeatures;
use App\Services\Sms\SmsProviders;
use App\Services\Sms\SmsTemplates;
use App\Services\SmsPromotionService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Everything on the web Settings page and the SMS module for the mobile app: general settings,
 * payment methods, SMS provider, features and wording, and the per-branch POS, invoice and delivery
 * settings. Secrets are write-only: a saved token is reported as saved, never returned.
 */
class SettingsApiController extends Controller
{
    use InteractsWithApi;

    private const GENERAL_KEYS = [
        'discount_limit_percent', 'wastage_spike_multiplier', 'pos_pin_minutes', 'pos_lock_enabled',
        'delete_confirm_delay_enabled', 'delete_confirm_delay_seconds', 'show_deleted_items', 'delivery_otp_enabled',
        'tax_registration_no', 'social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok',
        'social_whatsapp', 'established_year', 'website_enabled',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        return $this->success([
            'general' => Setting::current()->only(self::GENERAL_KEYS),
            'payment_methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (PaymentMethod $method): array => [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'name_bn' => $method->name_bn,
                'type' => $method->type,
                'is_system' => (bool) $method->is_system,
                'is_active' => (bool) $method->is_active,
                'payments_count' => Payment::query()->where('method', $method->code)->count(),
            ])->all(),
            'sms' => $this->smsPayload(),
            'layouts' => PosSettings::LAYOUTS,
            'papers' => PosSettings::PAPERS,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        $validated = $request->validate([
            'discount_limit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'wastage_spike_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'pos_pin_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'pos_lock_enabled' => ['sometimes', 'boolean'],
            'delete_confirm_delay_enabled' => ['sometimes', 'boolean'],
            'delete_confirm_delay_seconds' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'show_deleted_items' => ['sometimes', 'boolean'],
            'delivery_otp_enabled' => ['required', 'boolean'],
            'tax_registration_no' => ['nullable', 'string', 'max:40'],
            'social_facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social_instagram' => ['nullable', 'url:http,https', 'max:255'],
            'social_youtube' => ['nullable', 'url:http,https', 'max:255'],
            'social_tiktok' => ['nullable', 'url:http,https', 'max:255'],
            'social_whatsapp' => ['nullable', 'url:http,https', 'max:255'],
            'established_year' => ['nullable', 'integer', 'min:1800', 'max:'.now()->year],
            'website_enabled' => ['sometimes', 'boolean'],
        ]);

        Setting::current()->update($validated);
        PublicCache::bump();

        activity()->causedBy($user)->performedOn(Setting::current())->log('settings.updated');

        return $this->success(Setting::current()->only(self::GENERAL_KEYS), __('settings.saved'));
    }

    public function storeMethod(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,19}$/', 'unique:payment_methods,code'],
            'name' => ['required', 'string', 'max:60'],
            'name_bn' => ['nullable', 'string', 'max:60'],
            'type' => ['required', Rule::in($this->creatableTypes())],
            'is_active' => ['required', 'boolean'],
        ]);

        $method = PaymentMethod::query()->create($validated + [
            'sort_order' => (int) PaymentMethod::query()->max('sort_order') + 1,
        ]);

        activity()->causedBy($user)->performedOn($method)->log('payment_method.created');

        return $this->success(['id' => $method->id], __('settings.method_saved'), 201);
    }

    public function updateMethod(Request $request, PaymentMethod $method): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'name_bn' => ['nullable', 'string', 'max:60'],
            'type' => [$method->is_system ? 'prohibited' : 'required', Rule::in($this->creatableTypes())],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($method->code === 'cash') {
            $validated['is_active'] = true;
        }

        $method->update($validated);

        activity()->causedBy($user)->performedOn($method)->log('payment_method.updated');

        return $this->success(['id' => $method->id], __('settings.method_saved'));
    }

    public function destroyMethod(Request $request, PaymentMethod $method): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        if ($method->is_system) {
            throw ValidationException::withMessages(['method' => __('settings.method_system')]);
        }

        if (Payment::query()->where('method', $method->code)->exists()) {
            throw ValidationException::withMessages(['method' => __('settings.method_in_use')]);
        }

        $method->delete();
        activity()->causedBy($user)->log('payment_method.deleted');

        return $this->success(null, __('settings.method_deleted'));
    }

    public function updateSms(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        $provider = (string) $request->validate([
            'provider' => ['required', Rule::in(array_keys(SmsProviders::all()))],
        ])['provider'];

        $rules = [];

        foreach (SmsProviders::all()[$provider]['fields'] as $field) {
            $rules['fields.'.$field['key']] = ['nullable', 'string', 'max:255'];
        }

        $input = $request->validate($rules)['fields'] ?? [];

        $setting = Setting::current();
        $all = is_array($setting->sms_config) ? $setting->sms_config : [];
        $values = is_array($all[$provider] ?? null) ? $all[$provider] : [];

        foreach (SmsProviders::all()[$provider]['fields'] as $field) {
            $value = trim((string) ($input[$field['key']] ?? ''));

            // A secret left blank keeps the one already saved.
            if ($value !== '' || ! $field['secret']) {
                $values[$field['key']] = $value;
            }

            if ($field['required'] && ($values[$field['key']] ?? '') === '') {
                throw ValidationException::withMessages(['fields.'.$field['key'] => __('settings.sms_field_required', ['field' => __('settings.sms_field_'.$field['key'])])]);
            }
        }

        $all[$provider] = $values;
        $setting->update(['sms_provider' => $provider, 'sms_config' => $all]);

        activity()->causedBy($user)->performedOn($setting)->log('settings.sms_provider_updated');

        return $this->success($this->smsPayload(), __('settings.saved'));
    }

    public function updateSmsFeatures(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        $input = $request->validate([
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
        ])['features'];

        $states = [];

        foreach (SmsFeatures::ALL as $key => $definition) {
            if (! $definition['locked'] && array_key_exists($key, $input)) {
                $states[$key] = (bool) $input[$key];
            }
        }

        Setting::current()->update(['sms_features' => $states]);

        activity()->causedBy($user)->performedOn(Setting::current())->log('settings.sms_features_updated');

        return $this->success($this->smsPayload(), __('sms.features_saved'));
    }

    public function updateSmsTemplate(Request $request, string $template): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        abort_unless(SmsTemplates::exists($template), 404);

        SmsTemplates::save($template, $request->validate([
            'en' => ['nullable', 'string', 'max:2000'],
            'bn' => ['nullable', 'string', 'max:2000'],
        ]));

        activity()->causedBy($user)->performedOn(Setting::current())->log('settings.sms_template_updated');

        return $this->success($this->smsPayload(), __('sms.template_saved'));
    }

    public function testSms(Request $request, SmsService $sms): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');

        $mobile = $request->validate(['mobile' => ['required', 'string', 'max:20']])['mobile'];

        $sent = $sms->send($mobile, __('settings.sms_test_message', ['app' => config('app.name')]), SmsFeatures::TEST, $user->id);

        if (! $sent) {
            $reason = SmsLog::query()->where('feature', SmsFeatures::TEST)->where('mobile', $mobile)->latest('id')->value('response');

            throw ValidationException::withMessages(['mobile' => __('settings.sms_test_failed', ['reason' => (string) $reason])]);
        }

        return $this->success(['demo' => SmsService::isDemo()], SmsService::isDemo() ? __('settings.sms_test_demo') : __('settings.sms_test_sent', ['mobile' => $mobile]));
    }

    public function smsHistory(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'sms.manage');

        $filters = $request->validate([
            'feature' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in([SmsLog::STATUS_SENT, SmsLog::STATUS_FAILED, SmsLog::STATUS_SKIPPED, SmsLog::STATUS_DEMO])],
            'q' => ['nullable', 'string', 'max:40'],
        ]);

        $page = SmsLog::query()
            ->with('user:id,name')
            ->when($filters['feature'] ?? null, fn ($query, $feature) => $query->where('feature', $feature))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('mobile', 'like', '%'.preg_replace('/[^0-9+]/', '', $term).'%'))
            ->latest('id')
            ->paginate(min(50, (int) $request->input('per_page', 20)));

        return $this->success([
            'history' => $page->getCollection()->map(fn (SmsLog $log): array => [
                'id' => $log->id,
                'feature' => $log->feature,
                'mobile' => $log->mobile,
                'message' => $log->message,
                'status' => $log->status,
                'provider' => $log->provider,
                'response' => $log->response,
                'by' => $log->user?->name,
                'at' => $log->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'features' => SmsFeatures::keys(),
            'promotion_enabled' => SmsFeatures::enabled('promotion'),
            'audiences' => app(SmsPromotionService::class)->counts(),
        ]);
    }

    public function sendPromotion(Request $request, SmsPromotionService $promotions): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'sms.manage');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:320'],
            'audience' => ['required', Rule::in(SmsPromotionService::AUDIENCES)],
        ]);

        if (! SmsFeatures::enabled('promotion')) {
            throw ValidationException::withMessages(['message' => __('sms.promotion_feature_off')]);
        }

        $queued = $promotions->send($data['message'], $data['audience'], $user);

        if ($queued === 0) {
            throw ValidationException::withMessages(['audience' => __('sms.promotion_none')]);
        }

        return $this->success(['queued' => $queued], __('sms.promotion_sent', ['count' => $queued]));
    }

    public function branchSettings(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        return $this->success($this->branchPayload($branchModel));
    }

    public function updateBranchPos(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $request->validate([
            ...PosSettings::rules(),
            'allow_negative_stock' => ['boolean'],
            'vat_percent' => ['required', 'numeric', 'between:0,100'],
            'vat_inclusive' => ['boolean'],
            'service_charge_percent' => ['required', 'numeric', 'between:0,100'],
            'online_ordering_enabled' => ['boolean'],
        ]);

        $branchModel->update([
            'allow_negative_stock' => (bool) ($validated['allow_negative_stock'] ?? false),
            'vat_percent' => $validated['vat_percent'],
            'vat_inclusive' => (bool) ($validated['vat_inclusive'] ?? false),
            'service_charge_percent' => $validated['service_charge_percent'],
            'online_ordering_enabled' => (bool) ($validated['online_ordering_enabled'] ?? false),
            ...(isset($validated['pos']) ? ['pos_settings' => PosSettings::fromInput($validated['pos'])] : []),
        ]);

        activity()->causedBy($user)->performedOn($branchModel)->log('branch.pos_settings_updated');

        return $this->success($this->branchPayload($branchModel->fresh()), __('settings.saved'));
    }

    public function updateInvoicePrint(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $validated = $request->validate([
            'layout' => ['required', Rule::in(PosSettings::LAYOUTS)],
            'paper' => ['required', Rule::in(PosSettings::PAPERS)],
        ]);

        $branchModel->update(['pos_settings' => [
            ...(is_array($branchModel->pos_settings) ? $branchModel->pos_settings : []),
            'invoice_layout' => $validated['layout'],
            'invoice_paper' => $validated['paper'],
        ]]);

        activity()->causedBy($user)->performedOn($branchModel)->log('branch.invoice_print_updated');

        return $this->success($this->branchPayload($branchModel->fresh()), __('settings.saved'));
    }

    public function toggleDelivery(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $branchModel->update(['online_delivery_enabled' => $request->validate(['enabled' => ['required', 'boolean']])['enabled']]);

        activity()->causedBy($user)->performedOn($branchModel)->log('branch.online_delivery_toggled');

        return $this->success($this->branchPayload($branchModel->fresh()), __('settings.saved'));
    }

    public function storeZone(Request $request, string $branch): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        $branchModel = $this->branchOrFail($request, $branch);

        $zone = DeliveryZone::query()->create($this->validatedZone($request) + [
            'branch_id' => $branchModel->id,
            'sort_order' => (int) DeliveryZone::query()->where('branch_id', $branchModel->id)->max('sort_order') + 1,
        ]);

        activity()->causedBy($user)->performedOn($zone)->log('delivery_zone.created');

        return $this->success($this->zonePayload($zone), __('settings.zone_saved'), 201);
    }

    public function updateZone(Request $request, string $branch, DeliveryZone $zone): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($zone->branch_id === $branchModel->id, 404);

        $zone->update($this->validatedZone($request));

        activity()->causedBy($user)->performedOn($zone)->log('delivery_zone.updated');

        return $this->success($this->zonePayload($zone->fresh()), __('settings.zone_saved'));
    }

    public function destroyZone(Request $request, string $branch, DeliveryZone $zone): JsonResponse
    {
        $user = $this->staffUser($request);
        $this->permit($user, 'settings.manage');
        $branchModel = $this->branchOrFail($request, $branch);
        abort_unless($zone->branch_id === $branchModel->id, 404);

        activity()->causedBy($user)->performedOn($zone)->log('delivery_zone.deleted');
        $zone->delete();

        return $this->success(null, __('settings.zone_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedZone(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'fee_type' => ['required', Rule::in([DeliveryZone::FEE_FIXED, DeliveryZone::FEE_FLEXIBLE])],
            'min_order' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'eta_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function zonePayload(DeliveryZone $zone): array
    {
        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'name_bn' => $zone->name_bn,
            'fee' => (string) $zone->fee,
            'fee_type' => $zone->fee_type,
            'min_order' => (string) $zone->min_order,
            'eta_minutes' => $zone->eta_minutes,
            'is_active' => (bool) $zone->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function branchPayload(Branch $branch): array
    {
        return [
            'branch_id' => $branch->id,
            'pos' => PosSettings::for($branch),
            'allow_negative_stock' => (bool) $branch->allow_negative_stock,
            'vat_percent' => (string) $branch->vat_percent,
            'vat_inclusive' => (bool) $branch->vat_inclusive,
            'service_charge_percent' => (string) $branch->service_charge_percent,
            'online_ordering_enabled' => (bool) $branch->online_ordering_enabled,
            'online_delivery_enabled' => (bool) ($branch->online_delivery_enabled ?? true),
            'invoice_layout' => PosSettings::for($branch)['invoice_layout'],
            'invoice_paper' => PosSettings::for($branch)['invoice_paper'],
            'delivery_zones' => DeliveryZone::query()
                ->where('branch_id', $branch->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (DeliveryZone $zone): array => $this->zonePayload($zone))
                ->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function creatableTypes(): array
    {
        return [PaymentMethod::TYPE_WALLET, PaymentMethod::TYPE_CARD, PaymentMethod::TYPE_OTHER];
    }

    /**
     * Provider choice and its fields (secrets reported as saved only), feature switches and wording.
     *
     * @return array<string, mixed>
     */
    private function smsPayload(): array
    {
        return [
            'provider' => SmsProviders::current(),
            'demo' => SmsService::isDemo(),
            'providers' => collect(SmsProviders::all())->map(function (array $definition, string $key): array {
                $values = SmsProviders::credentials($key);

                return [
                    'key' => $key,
                    'name' => $definition['name'],
                    'configured' => SmsProviders::isConfigured($key),
                    'fields' => array_map(fn (array $field): array => [
                        'key' => $field['key'],
                        'secret' => $field['secret'],
                        'required' => $field['required'],
                        'saved' => ($values[$field['key']] ?? '') !== '',
                        'value' => $field['secret'] ? '' : ($values[$field['key']] ?? ''),
                    ], $definition['fields']),
                ];
            })->values()->all(),
            'features' => collect(SmsFeatures::ALL)->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'group' => $definition['group'],
                'locked' => $definition['locked'],
                'enabled' => SmsFeatures::enabled($key),
            ])->values()->all(),
            'templates' => collect(SmsTemplates::ALL)->map(function (array $definition, string $key): array {
                $defaults = [];
                $custom = [];

                foreach (SmsTemplates::LOCALES as $locale) {
                    $defaults[$locale] = SmsTemplates::defaultBody($key, $locale);
                    $custom[$locale] = SmsTemplates::customBody($key, $locale);
                }

                return [
                    'key' => $key,
                    'feature' => $definition['feature'],
                    'enabled' => SmsFeatures::enabled($definition['feature']),
                    'variables' => collect($definition['vars'])->map(fn (string $sample, string $name): array => ['name' => $name, 'sample' => $sample, 'required' => in_array($name, $definition['required'], true)])->values()->all(),
                    'defaults' => $defaults,
                    'custom' => $custom,
                ];
            })->values()->all(),
        ];
    }
}
