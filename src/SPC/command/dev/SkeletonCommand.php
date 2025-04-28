<?php

declare(strict_types=1);

namespace SPC\command\dev;

use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use SPC\builder\Extension;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\command\BaseCommand;
use SPC\exception\FileSystemException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\PhpPrinter;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class SkeletonCommand extends BaseCommand
{
    /**
     * @var array{
     *     ext: array<string, array<string, array>>,
     *     lib: array<string, array<string, array{
     *         lib-support: array<string>
     *     }>>,
     *     source: array<string, array<string, array>>
     * }
     */
    protected static array $cache = [
        'ext' => [],
        'lib' => [],
        'source' => [],
    ];

    public function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED);
        $this->addOption('is-middle-step', null, null, 'Middle step does not create final file');
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (!$input->isInteractive() || PHP_OS_FAMILY === 'Windows') {
            throw new LogicException('This command is not supported in non-interactive mode or on Windows.');
        }
    }

    /**
     * @throws FileSystemException
     */
    public function validateExtName(string $name): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            return 'Extension name must be alphanumeric and underscore only';
        }
        if (isset(Config::getExts()[$name]) || isset(self::$cache['ext'][$name])) {
            return "Extension {$name} already exists";
        }
        return null;
    }

    /**
     * @throws FileSystemException
     */
    public function validateLibName(string $name): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            return 'Library name must be alphanumeric and underscore only';
        }
        if (isset(Config::getLibs()[$name]) || isset(self::$cache['lib'][$name])) {
            return "Library {$name} already exists";
        }
        return null;
    }

    public function validateStaticLibs(string $libs): ?string
    {
        $libs = explode("\n", $libs);
        foreach ($libs as $lib) {
            if (!preg_match('/^[a-zA-Z0-9_.\-+]+$/', trim($lib))) {
                return 'Illegal static lib name';
            }
        }
        return null;
    }

    /**
     * Generate extension class
     *
     * @param  string              $ext_name Extension name
     * @throws FileSystemException
     */
    protected function generateExtensionClass(string $ext_name): string
    {
        $class_name = str_replace('-', '_', $ext_name);

        // use php-generator
        $printer = new PhpPrinter();
        $file = new PhpFile();
        $file->setStrictTypes()
            ->addComment('Remove this file if you do not need to patch the extension')
            ->addNamespace('SPC\builder\extension')
            ->addUse(Extension::class)
            ->addUse(CustomExt::class)
            ->addClass($ext_name)
            ->setExtends(Extension::class)
            ->addAttribute(CustomExt::class, [$ext_name]);
        $path = WORKING_DIR . '/src/SPC/builder/extension/' . $class_name . '.php';
        FileSystem::writeFile($path, $printer->printFile($file));
        return $path;
    }

    /**
     * @return array<string>
     * @throws FileSystemException
     */
    protected function generateLibraryClass(string $lib_name): array
    {
        $printer = new PhpPrinter();

        $lib_config = self::$cache['lib'][$lib_name];

        // class name needs to convert - to _
        $class_name = str_replace('-', '_', $lib_name);

        // check lib-support, if includes linux and macOS at the same time, use unix trait

        // generate base class
        if (in_array('Linux', $lib_config['lib-support'])) {
            $linux_file = new PhpFile();
            $linux_namespace = $linux_file->setStrictTypes()
                ->addNamespace('SPC\builder\linux\library')
                ->addUse(LinuxLibraryBase::class);
            $linux_class = $linux_namespace->addClass($class_name)
                ->setExtends(LinuxLibraryBase::class);
        }
        if (in_array('Darwin', $lib_config['lib-support'])) {
            $macos_file = new PhpFile();
            $macos_namespace = $macos_file->setStrictTypes()
                ->addNamespace('SPC\builder\macos\library')
                ->addUse(MacOSLibraryBase::class);
            $macos_class = $macos_namespace->addClass($class_name)
                ->setExtends(MacOSLibraryBase::class);
        }
        // generate build function
        if (isset($linux_class) || isset($macos_class)) {
            $unix_build_method = new Method('build');
            $unix_build_method->setProtected()->setReturnType('void');

            switch ($lib_config['build-tool-unix']) {
                case 'cmake':
                    $unix_build_method->addBody(<<<'FILE'
\SPC\Store\FileSystem::resetDir($this->source_dir . '/build-dir');
shell()->cd($this->source_dir . '/build-dir')
    ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
    ->execWithEnv(
        'cmake ' .
        '-DCMAKE_BUILD_TYPE=Release ' .
        '-DCMAKE_TOOLCHAIN_FILE=' . $this->builder->cmake_toolchain_file . ' ' .
        '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
        '-DCMAKE_INSTALL_LIBDIR=lib ' .
        '-DSHARE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
        '-DBUILD_SHARED_LIBS=OFF ' .
        '..'
    )
    ->execWithEnv('cmake --build . -j ' . $this->builder->concurrency)
    ->execWithEnv('make install');
FILE);
                    break;
                case 'autoconf':
                    $unix_build_method->addBody(<<<'FILE'
shell()->cd($this->source_dir)
    ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
    ->execWithEnv(
        './configure --disable-shared --enable-static ' .
        '--prefix=' . BUILD_ROOT_PATH . ' '
    )
    ->execWithEnv("make -j{$this->builder->concurrency}")
    ->execWithEnv('make install');
FILE);
                    break;
                case 'other':
                    $unix_build_method->addBody(<<<'FILE'
shell()->cd($this->source_dir)
    ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
    ->execWithEnv('echo "your build command here, e.g. make xxx"');
FILE);
                    break;
            }

            // if lib-support is linux only, add build method to linux class
            if (isset($linux_class) && !isset($macos_class)) {
                $linux_class->setMethods([$unix_build_method]);
                $linux_class->addConstant('NAME', $lib_name)->setPublic();
            } elseif (!isset($linux_class) && isset($macos_class)) {
                $macos_class->setMethods([$unix_build_method]);
                $macos_class->addConstant('NAME', $lib_name)->setPublic();
            } elseif (isset($linux_class, $macos_class)) {
                // we need to add unix trait
                $unix_trait_file = new PhpFile();
                $unix_trait = $unix_trait_file->setStrictTypes()
                    ->addNamespace('SPC\builder\unix\library')
                    ->addTrait($class_name);
                $unix_trait->setMethods([$unix_build_method]);
                // add trait to linux and macos class
                $linux_class->addTrait('SPC\builder\unix\library\\' . $class_name);
                $macos_class->addTrait('SPC\builder\unix\library\\' . $class_name);
                $linux_class->addConstant('NAME', $lib_name)->setPublic();
                $macos_class->addConstant('NAME', $lib_name)->setPublic();
            }
        }

        // generate trait file
        $wrote = [];
        if (isset($macos_file)) {
            $path = WORKING_DIR . '/src/SPC/builder/macos/library/' . $class_name . '.php';
            FileSystem::writeFile($path, $printer->printFile($macos_file));
            $wrote[] = $path;
        }
        if (isset($linux_file)) {
            $path = WORKING_DIR . '/src/SPC/builder/linux/library/' . $class_name . '.php';
            FileSystem::writeFile($path, $printer->printFile($linux_file));
            $wrote[] = $path;
        }
        if (isset($unix_trait_file)) {
            $path = WORKING_DIR . '/src/SPC/builder/unix/library/' . $class_name . '.php';
            FileSystem::writeFile($path, $printer->printFile($unix_trait_file));
            $wrote[] = $path;
        }
        return $wrote;
    }

    protected function generateAll(): void
    {
        // gen exts
        foreach (self::$cache['ext'] as $ext_name => $ext_content) {
            $wrote = $this->generateExtensionClass($ext_name);
            $this->output->writeln(sprintf('<info>Generated extension [%s]:</info>', $ext_name));
            $this->output->writeln("<info>\t{$wrote}</info>");
        }

        // gen libs
        foreach (self::$cache['lib'] as $lib_name => $lib_content) {
            $wrote = $this->generateLibraryClass($lib_name);
            $this->output->writeln('<info>Generated library [' . $lib_name . '] skeleton:</info>');
            foreach ($wrote as $line) {
                $this->output->writeln("<info>\t{$line}</info>");
            }
        }
    }
}
