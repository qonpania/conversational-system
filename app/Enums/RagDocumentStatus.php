<?php

namespace App\Enums;

enum RagDocumentStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Ready      = 'ready';
    case Failed     = 'failed';
    case Disabled   = 'disabled';
}
