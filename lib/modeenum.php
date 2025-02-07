<?php

namespace Bx\Xhprof\Listener;

enum ModeEnum: string
{
    case ALL = '';
    case COUNT_CALL = 'ct';
    case WAIT_TIME = 'wt';
    case MEMORY_USAGE = 'mu';
}
