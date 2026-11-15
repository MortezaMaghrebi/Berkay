<?php

namespace Webkul\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Webkul\Berkay\Services\KavenegarSms;
use Webkul\Checkout\Facades\Cart;
use Webkul\Core\Repositories\CountryStateRepository;
use Webkul\Core\Rules\PhoneNumber;
use Webkul\Core\Rules\PostCode;
use Webkul\Core\Rules\StateBelongsToCountry;
use Webkul\Customer\Rules\VatIdRule;

class CartAddressRequest extends FormRequest
{
    /**
     * Rules.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $this->mergeWithRules(['billing' => ['present', 'array']]);
        $this->mergeAddressRules('billing');

        if (
            ! $this->input('billing.use_for_shipping')
            && Cart::getCart()?->haveStockableItems()
        ) {
            $this->mergeAddressRules('shipping');
        }

        return $this->rules;
    }

    /**
     * Normalize saved and newly entered address values before validation.
     */
    protected function prepareForValidation(): void
    {
        foreach (['billing', 'shipping'] as $type) {
            $address = $this->input($type);

            if (! is_array($address)) {
                continue;
            }

            foreach (['phone', 'postcode'] as $field) {
                if (is_string($address[$field] ?? null)) {
                    $address[$field] = KavenegarSms::westernDigits(trim($address[$field]));
                }
            }

            if (is_string($address['address'] ?? null)) {
                $address['address'] = preg_split('/\R/u', $address['address']);
            }

            if (is_array($address['address'] ?? null)) {
                $address['address'] = array_values(array_filter(
                    $address['address'],
                    fn ($line) => ! is_string($line) || trim($line) !== '',
                ));
            }

            if (is_string($address['country'] ?? null)) {
                $address['country'] = strtoupper(trim($address['country']));
            }

            if (
                ($address['country'] ?? null) === 'IR'
                && is_string($address['state'] ?? null)
            ) {
                $address['state'] = $this->iranianStateCode($address['state']);
            }

            $this->merge([$type => $address]);
        }
    }

    /**
     * Merge new address rules.
     */
    private function mergeAddressRules(string $addressType): void
    {
        $this->mergeWithRules([
            "{$addressType}.company_name" => ['nullable'],
            "{$addressType}.first_name" => ['required', 'string', 'max:255'],
            "{$addressType}.last_name" => ['required', 'string', 'max:255'],
            "{$addressType}.email" => ['nullable', 'email', 'max:255'],
            "{$addressType}.address" => ['bail', 'required', 'array', 'min:1', function ($attribute, $value, $fail) {
                if (mb_strlen(implode(PHP_EOL, array_filter($value, 'is_string'))) > 255) {
                    $fail('validation.max.string')->translate(['max' => 255]);
                }
            }],
            "{$addressType}.address.0" => ['required_with:'.$addressType.'.address', 'string', 'max:255'],
            "{$addressType}.address.*" => ['nullable', 'string', 'max:255'],
            "{$addressType}.city" => ['required', 'string', 'max:255'],
            "{$addressType}.country" => core()->isCountryRequired() ? ['required'] : ['nullable'],
            "{$addressType}.state" => [
                core()->isStateRequired() ? 'required' : 'nullable',
                new StateBelongsToCountry($this->input("{$addressType}.country")),
            ],
            "{$addressType}.postcode" => [core()->isPostCodeRequired() ? 'required' : 'nullable', 'string', 'max:255', new PostCode],
            "{$addressType}.phone" => ['required', 'string', 'max:255', new PhoneNumber],
        ]);

        if ($addressType == 'billing') {
            $this->mergeWithRules([
                "{$addressType}.vat_id" => [(new VatIdRule)->setCountry($this->input('billing.country'))],
            ]);
        }
    }

    /**
     * Resolve the province code for saved addresses that contain a translated name.
     */
    private function iranianStateCode(string $value): string
    {
        $states = app(CountryStateRepository::class)->findByField('country_code', 'IR');
        $name = mb_strtolower(trim($value));

        $matchingCode = $states->first(fn ($state) => mb_strtolower($state->code) === $name);

        if ($matchingCode) {
            return $matchingCode->code;
        }

        foreach ($states->load('translations') as $state) {
            if ($state->translations->contains(fn ($translation) => mb_strtolower(trim($translation->default_name)) === $name)) {
                return $state->code;
            }
        }

        return trim($value);
    }

    /**
     * Merge additional rules.
     */
    private function mergeWithRules(array $rules): void
    {
        $this->rules = array_merge($this->rules, $rules);
    }
}
