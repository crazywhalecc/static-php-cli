<?php

declare(strict_types=1);

// static-php-cli version string
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\TextPrompt;
use StaticPHP\ConsoleApplication;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\LinuxUtil;
use StaticPHP\Util\System\MacOSUtil;
use StaticPHP\Util\System\WindowsUtil;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

const SPC_VERSION = ConsoleApplication::VERSION;
// output path for everything, other paths are defined relative to this by default
define('BUILD_ROOT_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_ROOT_PATH')) ? $a : (WORKING_DIR . '/buildroot')));
// output path for header files for development
define('BUILD_INCLUDE_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_INCLUDE_PATH')) ? $a : (BUILD_ROOT_PATH . '/include')));
// output path for libraries and for libphp.so, if building shared embed
define('BUILD_LIB_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_LIB_PATH')) ? $a : (BUILD_ROOT_PATH . '/lib')));
// output path for binaries
define('BUILD_BIN_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_BIN_PATH')) ? $a : (BUILD_ROOT_PATH . '/bin')));
// output path for shared extensions
define('BUILD_MODULES_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_MODULES_PATH')) ? $a : (BUILD_ROOT_PATH . '/modules')));

// pkg arch name
$_pkg_arch_name = getenv('EMULATE_PLATFORM') ?: (arch2gnu(php_uname('m')) . '-' . strtolower(PHP_OS_FAMILY));
define('PKG_ROOT_PATH', FileSystem::convertPath(is_string($a = getenv('PKG_ROOT_PATH')) ? $a : (WORKING_DIR . "/pkgroot/{$_pkg_arch_name}")));

define('SOURCE_PATH', FileSystem::convertPath(is_string($a = getenv('SOURCE_PATH')) ? $a : (WORKING_DIR . '/source')));
define('DOWNLOAD_PATH', FileSystem::convertPath(is_string($a = getenv('DOWNLOAD_PATH')) ? $a : (WORKING_DIR . '/downloads')));
define('CPU_COUNT', match (PHP_OS_FAMILY) {
    'Windows' => (string) WindowsUtil::getCpuCount(),
    'Darwin' => (string) MacOSUtil::getCpuCount(),
    'Linux' => (string) LinuxUtil::getCpuCount(),
    default => 1,
});
define('GNU_ARCH', arch2gnu(php_uname('m')));
define('MAC_ARCH', match ($_im8a = arch2gnu(php_uname('m'))) {
    'aarch64' => 'arm64',
    default => $_im8a
});

// logs dir
define('SPC_LOGS_DIR', FileSystem::convertPath(is_string($a = getenv('SPC_LOGS_DIR')) ? $a : (WORKING_DIR . '/log')));
const SPC_OUTPUT_LOG = SPC_LOGS_DIR . DIRECTORY_SEPARATOR . 'spc.output.log';
const SPC_SHELL_LOG = SPC_LOGS_DIR . DIRECTORY_SEPARATOR . 'spc.shell.log';

// deprecated variables
define('SEPARATED_PATH', [
    '/' . pathinfo(BUILD_LIB_PATH)['basename'], // lib
    '/' . pathinfo(BUILD_INCLUDE_PATH)['basename'], // include
    BUILD_ROOT_PATH,
]);

// add these to env vars with same name
putenv('SPC_VERSION=' . SPC_VERSION);
putenv('BUILD_ROOT_PATH=' . BUILD_ROOT_PATH);
putenv('BUILD_INCLUDE_PATH=' . BUILD_INCLUDE_PATH);
putenv('BUILD_LIB_PATH=' . BUILD_LIB_PATH);
putenv('BUILD_BIN_PATH=' . BUILD_BIN_PATH);
putenv('PKG_ROOT_PATH=' . PKG_ROOT_PATH);
putenv('SOURCE_PATH=' . SOURCE_PATH);
putenv('DOWNLOAD_PATH=' . DOWNLOAD_PATH);
putenv('CPU_COUNT=' . CPU_COUNT);
putenv('SPC_ARCH=' . php_uname('m'));
putenv('GNU_ARCH=' . GNU_ARCH);
putenv('MAC_ARCH=' . MAC_ARCH);

// initialize windows prompt fallback for laravel-prompts
Prompt::fallbackWhen(PHP_OS_FAMILY === 'Windows');
ConfirmPrompt::fallbackUsing(function (ConfirmPrompt $prompt) {
    $helper = new QuestionHelper();
    $case = $prompt->default ? ' [Y/n] ' : ' [y/N] ';
    $question = new ConfirmationQuestion($prompt->label . $case, $prompt->default);
    if (ApplicationContext::has(InputInterface::class) && ApplicationContext::has(OutputInterface::class)) {
        $input = ApplicationContext::get(InputInterface::class);
        $output = ApplicationContext::get(OutputInterface::class);
    } else {
        $input = new ArrayInput([]);
        $output = new ConsoleOutput();
    }
    return $helper->ask($input, $output, $question);
});
TextPrompt::fallbackUsing(function (TextPrompt $prompt) {
    $helper = new QuestionHelper();
    $question = new Question($prompt->label . ' ', $prompt->default);
    if (ApplicationContext::has(InputInterface::class) && ApplicationContext::has(OutputInterface::class)) {
        $input = ApplicationContext::get(InputInterface::class);
        $output = ApplicationContext::get(OutputInterface::class);
    } else {
        $input = new ArrayInput([]);
        $output = new ConsoleOutput();
    }
    return $helper->ask($input, $output, $question);
});
