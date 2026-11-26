<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Severity of a single finding.
 */
enum FindingSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
