<?php
namespace Core;

use Exception;

class TenantContextException extends Exception {
    public function __construct(string $message = "No active tenant context available.", int $code = 403, ?Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
