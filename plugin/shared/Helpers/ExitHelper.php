<?php

namespace Transbank\Plugin\Helpers;

final class ExitHelper
{
    /**
     * Terminates script execution with an optional exit code.
     *
     * @param int $code Exit code (default: 0).
     * @return void
     */
    public static function terminate(int $code = 0): void
    {
        exit($code);
    }
}
