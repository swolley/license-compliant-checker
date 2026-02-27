<?php

declare(strict_types=1);

/**
 * License compliance check – main logic.
 *
 * Expects $GLOBALS['LICENSE_CHECK_PROJECT_ROOT'] and $GLOBALS['LICENSE_CHECK_CONFIG_PATH'].
 * Returns exit code; caller should exit(require 'run-license-check.php').
 */
require_once __DIR__ . '/RunLicenseCheck.php';

return run_license_check_from_globals();
