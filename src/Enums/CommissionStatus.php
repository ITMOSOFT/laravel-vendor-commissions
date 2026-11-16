<?php

namespace ItmoSoft\VendorCommissions\Enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Reversed = 'reversed';
}
