<?php

namespace Goodport\AutoModerator\Exceptions;

use LogicException;

/**
 * A check returned a result that contradicts the report semantics.
 */
final class InvalidCheckResultException extends LogicException {}
