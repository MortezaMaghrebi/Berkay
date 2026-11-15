<?php

namespace Webkul\Berkay\Services;

use Webkul\Berkay\Payment\IranKish;
use Webkul\Berkay\Payment\Mellat;
use Webkul\Berkay\Payment\Zarinpal;

class BankGateways
{
    /** Checkout payment classes indexed by their provider. */
    public const METHODS = ['zarinpal' => Zarinpal::class, 'mellat' => Mellat::class, 'irankish' => IranKish::class];

    /** Credentials managed through the encrypted integration settings. */
    public const CREDENTIALS = [
        'mellat' => ['terminal_id', 'username', 'password'],
        'irankish' => ['terminal_id', 'acceptor_id', 'password', 'public_key'],
    ];

    /** Resolve the handler for a supported payment provider. */
    public function gateway(string $provider): ZarinpalGateway|MellatGateway|IranKishGateway
    {
        return match ($provider) {
            'zarinpal' => app(ZarinpalGateway::class),
            'mellat' => app(MellatGateway::class),
            'irankish' => app(IranKishGateway::class),
        };
    }
}
