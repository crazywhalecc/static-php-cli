<?php

declare(strict_types=1);

exit(class_exists('\\Redis') ? 0 : 1);
