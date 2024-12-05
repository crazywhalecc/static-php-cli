<?php

/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * lwmbs is licensed under Mulan PSL v2. You can use this
 * software according to the terms and conditions of the
 * Mulan PSL v2. You may obtain a copy of Mulan PSL v2 at:
 *
 * http://license.coscl.org.cn/MulanPSL2
 *
 * THIS SOFTWARE IS PROVIDED ON AN "AS IS" BASIS,
 * WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO NON-INFRINGEMENT,
 * MERCHANTABILITY OR FIT FOR A PARTICULAR PURPOSE.
 *
 * See the Mulan PSL v2 for more details.
 */

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

class curl extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\curl;

    public const NAME = 'curl';

    /**
     * @throws FileSystemException
     */
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/curl/CMakeLists.txt',
            '/NOT COREFOUNDATION_FRAMEWORK/m',
            'FALSE'
        );
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/curl/CMakeLists.txt',
            '/NOT SYSTEMCONFIGURATION_FRAMEWORK/m',
            'FALSE'
        );
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/curl/CMakeLists.txt',
            '/NOT CORESERVICES_FRAMEWORK/m',
            'FALSE'
        );
        return true;
    }
}
