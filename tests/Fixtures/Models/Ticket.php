<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A support ticket. Only its class is used, to select TicketPolicy through the Gate.
 */
class Ticket extends Model {}
