<?php

namespace EduLazaro\Laraterms\Exceptions;

use RuntimeException;

/**
 * Thrown when a parent is set on a flat taxonomy, or a term is made its own parent.
 */
class RequiresHierarchyException extends RuntimeException {}
