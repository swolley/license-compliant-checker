<?php

declare(strict_types=1);

/**
 * License compliance matrix for scripts/check-license-compliance.php.
 *
 * Syntax (Composer-like):
 * - Exact: "MIT", "Apache-2.0"
 * - Wildcard: "BSD-*", "CC-BY-*" (prefix match on SPDX id)
 * - Version constraint: "GPL->=3.0", "Apache-<=1.1" (family op version; see license_families)
 * - Group reference: "@groupname" expands to the group's patterns (groups may reference other groups)
 *
 * Keys = project license. Values = list of patterns and/or @group references.
 *
 * @see https://spdx.org/licenses/
 */
return [
    'license_families' => [
        'GPL-2.0-only' => ['GPL', 2.0],
        'GPL-2.0-or-later' => ['GPL', 2.0],
        'GPL-3.0-only' => ['GPL', 3.0],
        'GPL-3.0-or-later' => ['GPL', 3.0],
        'AGPL-3.0-or-later' => ['AGPL', 3.0],
        'Apache-1.0' => ['Apache', 1.0],
        'Apache-1.1' => ['Apache', 1.1],
        'Apache-2.0' => ['Apache', 2.0],
    ],

    'groups' => [
        'permissive' => [
            '0BSD',
            'Apache-*',
            'BSD-*',
            'CC0-*',
            'CC-BY-*',
            'CC-BY-NC-*',
            'CC-BY-NC-SA-*',
            'CC-BY-SA-*',
            'GPL-*',
            'AGPL-*',
            'ISC',
            'LGPL-*',
            'MIT',
            'MPL-2.0',
            'WTFPL',
        ],
        'permissive-no-strong-copyleft' => [
            '0BSD',
            'Apache-*',
            'BSD-*',
            'CC0-*',
            'CC-BY-*',
            'CC-BY-NC-*',
            'CC-BY-NC-SA-*',
            'ISC',
            'LGPL-*',
            'MIT',
            'MPL-2.0',
            'WTFPL',
        ],
        'proprietary-allowed' => [
            '0BSD',
            'Apache-*',
            'BSD-*',
            'CC0-1.0',
            'CC-BY-*',
            'ISC',
            'LGPL-*',
            'MIT',
            'MPL-2.0',
            'WTFPL',
        ],
    ],

    'compatibility' => [
        'proprietary' => ['@proprietary-allowed'],
        'UNLICENSED' => ['@proprietary-allowed'],

        'MIT' => ['@permissive'],
        'Apache-1.0' => ['@permissive'],
        'Apache-1.1' => ['@permissive'],
        'Apache-2.0' => ['@permissive'],
        'BSD-2-Clause' => ['@permissive'],
        'BSD-3-Clause' => ['@permissive'],
        'BSD-4-Clause' => ['@permissive'],

        'GPL-2.0-only' => [
            '0BSD',
            'Apache-*',
            'BSD-*',
            'CC0-*',
            'CC-BY-*',
            'CC-BY-NC-*',
            'CC-BY-NC-SA-*',
            'CC-BY-SA-*',
            'GPL-<=2.0',
            'ISC',
            'LGPL-*',
            'MIT',
            'MPL-2.0',
            'WTFPL',
        ],
        'GPL-2.0-or-later' => ['@permissive'],
        'GPL-3.0-only' => ['@permissive'],
        'GPL-3.0-or-later' => ['@permissive'],
        'AGPL-3.0-or-later' => ['@permissive'],

        'CC0-1.0' => ['@permissive'],
        'CC-BY-3.0' => ['@permissive'],
        'CC-BY-4.0' => ['@permissive'],
        'CC-BY-SA-3.0' => ['@permissive'],
        'CC-BY-SA-4.0' => ['@permissive'],
        'CC-BY-NC-3.0' => ['@permissive-no-strong-copyleft'],
        'CC-BY-NC-4.0' => ['@permissive-no-strong-copyleft'],
        'CC-BY-NC-SA-3.0' => ['@permissive-no-strong-copyleft'],
        'CC-BY-NC-SA-4.0' => ['@permissive-no-strong-copyleft'],
    ],
];
