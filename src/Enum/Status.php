<?php

namespace App\Enum;

enum Status: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case TO_BE_REVIEWED = 'to_be_reviewed';


}
