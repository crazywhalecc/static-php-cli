<?php

declare(strict_types=1);

namespace SPC\exception;

use SPC\builder\Extension;

/**
 * Exception thrown for validation errors in SPC.
 *
 * This exception is used to indicate that a validation error has occurred,
 * typically when input data does not meet the required criteria.
 */
class ValidationException extends SPCException
{
    private null|array|string $validation_module = null;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, null|array|string $validation_module = null)
    {
        parent::__construct($message, $code, $previous);

        // init validation module
        if ($validation_module === null) {
            foreach ($this->getTrace() as $trace) {
                // Extension validate() => "Extension validator"
                if (is_a($trace['class'] ?? null, Extension::class, true) && $trace['function'] === 'validate') {
                    $this->validation_module = 'Extension validator';
                    break;
                }

                // Other => "ClassName::functionName"
                $this->validation_module = [
                    'class' => $trace['class'] ?? null,
                    'function' => $trace['function'],
                ];
                break;
            }
        } else {
            $this->validation_module = $validation_module;
        }
    }

    /**
     * Returns the validation module string.
     */
    public function getValidationModuleString(): string
    {
        if ($this->validation_module === null) {
            return 'Unknown';
        }
        if (is_string($this->validation_module)) {
            return $this->validation_module;
        }
        $str = $this->validation_module['class'] ?? null;
        if ($str !== null) {
            $str .= '::';
        }
        return ($str ?? '') . $this->validation_module['function'];
    }
}
