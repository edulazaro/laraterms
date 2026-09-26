<?php

namespace EduLazaro\Laraterms\Exceptions;

use RuntimeException;

/**
 * Thrown when a model would exceed its taxonomy's max_terms_per_model.
 */
class TooManyTermsException extends RuntimeException {}
