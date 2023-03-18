<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

exit(function_exists('cal_info') && is_array(cal_info(0)) ? 0 : 1);
