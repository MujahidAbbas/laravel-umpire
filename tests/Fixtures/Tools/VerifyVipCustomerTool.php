<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use MujahidAbbas\Umpire\Attributes\Redact;

#[Redact('account_pin')]
class VerifyVipCustomerTool extends VerifyCustomerTool
{
    protected string $name = 'verify-vip-customer';

    protected string $description = 'Verify a VIP customer with their account PIN.';
}
