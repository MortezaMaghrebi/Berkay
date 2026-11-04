<?php

namespace Webkul\Berkay\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Webkul\Berkay\Repositories\IntegrationSettingRepository;

class IntegrationSettings
{
    /** Inject the encrypted configuration repository. */
    public function __construct(protected IntegrationSettingRepository $settings) {}

    /** Prefer administrator settings and retain environment configuration as fallback. */
    public function get(string $name): mixed
    {
        $setting = $this->settings->findOneByField('name', $name);
        if (! $setting) {
            return config('berkay.'.$name);
        }
        try {
            return $setting->value['value'] ?? null;
        } catch (DecryptException) {
            return null;
        }
    }

    /** Persist only values selected by the validated administration boundary. */
    public function put(string $name, mixed $value): void
    {
        $this->settings->updateOrCreate(['name' => $name], ['value' => ['value' => $value]]);
    }
}
