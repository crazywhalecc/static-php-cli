<?php

declare(strict_types=1);

namespace StaticPHP\Package;

/**
 * An install stamp: the fingerprint of the inputs that produced a package's installed files, plus
 * the file it is recorded in.
 *
 * The two belong together - a fingerprint that is not written anywhere cannot be compared later -
 * so packages declare them as one value instead of overriding two separate methods.
 */
readonly class InstallStamp
{
    public function __construct(
        public string $file,
        public string $value,
    ) {}
}
