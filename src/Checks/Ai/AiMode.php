<?php

namespace Goodport\AutoModerator\Checks\Ai;

/**
 * How the AI check enters the AI moderator package.
 */
enum AiMode: string
{
    case Check = 'check';
    case Policy = 'policy';
}
