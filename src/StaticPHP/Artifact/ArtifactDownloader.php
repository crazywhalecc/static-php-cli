<?php

declare(strict_types=1);

namespace StaticPHP\Artifact;

use Psr\Log\LogLevel;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Artifact\Downloader\Type\BitBucketTag;
use StaticPHP\Artifact\Downloader\Type\DownloadTypeInterface;
use StaticPHP\Artifact\Downloader\Type\FileList;
use StaticPHP\Artifact\Downloader\Type\Git;
use StaticPHP\Artifact\Downloader\Type\GitHubRelease;
use StaticPHP\Artifact\Downloader\Type\GitHubTarball;
use StaticPHP\Artifact\Downloader\Type\HostedPackageBin;
use StaticPHP\Artifact\Downloader\Type\LocalDir;
use StaticPHP\Artifact\Downloader\Type\PhpRelease;
use StaticPHP\Artifact\Downloader\Type\PIE;
use StaticPHP\Artifact\Downloader\Type\Url;
use StaticPHP\Artifact\Downloader\Type\ValidatorInterface;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Exception\ExecutionException;
use StaticPHP\Exception\SPCException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\Shell\Shell;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Logger\ConsoleColor;

/**
 * Artifact Downloader class
 */
class ArtifactDownloader
{
    /** @var array<string, class-string<DownloadTypeInterface>> */
    public const array DOWNLOADERS = [
        'bitbuckettag' => BitBucketTag::class,
        'filelist' => FileList::class,
        'git' => Git::class,
        'ghrel' => GitHubRelease::class,
        'ghtar' => GitHubTarball::class,
        'ghtagtar' => GitHubTarball::class,
        'local' => LocalDir::class,
        'pie' => PIE::class,
        'url' => Url::class,
        'php-release' => PhpRelease::class,
        'hosted' => HostedPackageBin::class,
    ];

    /** @var array<string, Artifact> Artifact objects */
    protected array $artifacts = [];

    /** @var int Parallel process number (1 and 0 as single-threaded mode) */
    protected int $parallel = 1;

    protected int $retry = 0;

    /** @var array<string, string> Override custom download urls from options */
    protected array $custom_urls = [];

    /** @var array<string, array{0: string, 1: string}> Override custom git options from options ([branch, git url]) */
    protected array $custom_gits = [];

    /** @var array<string, string> Override custom local paths from options */
    protected array $custom_locals = [];

    /** @var int Fetch type preference */
    protected int $default_fetch_pref = Artifact::FETCH_PREFER_SOURCE;

    /** @var array<string, int> Specific fetch preference */
    protected array $fetch_prefs = [];

    /** @var array<string>|bool Whether to ignore cache for specific artifacts or all */
    protected array|bool $ignore_cache = false;

    /** @var bool Whether to enable alternative mirror downloads */
    protected bool $alt = true;

    private array $_before_files;

    /**
     * @param array{
     *     parallel?: int,
     *     retry?: int,
     *     custom-url?: array<string>,
     *     custom-git?: array<string>,
     *     custom-local?: array<string>,
     *     prefer-source?: null|bool|string,
     *     prefer-pre-built?: null|bool|string,
     *     prefer-binary?: null|bool|string,
     *     source-only?: null|bool|string,
     *     binary-only?: null|bool|string,
     *     ignore-cache?: null|bool|string,
     *     ignore-cache-sources?: null|bool|string,
     *     no-alt?: bool,
     *     no-shallow-clone?: bool
     * } $options Downloader options
     */
    public function __construct(protected array $options = [])
    {
        // Allow setting concurrency via options
        $this->parallel = max(1, (int) ($options['parallel'] ?? 1));
        // Allow setting retry via options
        $this->retry = max(0, (int) ($options['retry'] ?? 0));
        // Prefer source (default)
        if (array_key_exists('prefer-source', $options)) {
            if (is_string($options['prefer-source'])) {
                $ls = parse_comma_list($options['prefer-source']);
                foreach ($ls as $name) {
                    $this->fetch_prefs[$name] = Artifact::FETCH_PREFER_SOURCE;
                }
            } elseif ($options['prefer-source'] === null || $options['prefer-source'] === true) {
                $this->default_fetch_pref = Artifact::FETCH_PREFER_SOURCE;
            }
        }
        // Prefer binary (originally prefer-pre-built)
        if (array_key_exists('prefer-binary', $options)) {
            if (is_string($options['prefer-binary'])) {
                $ls = parse_comma_list($options['prefer-binary']);
                foreach ($ls as $name) {
                    $this->fetch_prefs[$name] = Artifact::FETCH_PREFER_BINARY;
                }
            } elseif ($options['prefer-binary'] === null || $options['prefer-binary'] === true) {
                $this->default_fetch_pref = Artifact::FETCH_PREFER_BINARY;
            }
        }
        if (array_key_exists('prefer-pre-built', $options)) {
            if (is_string($options['prefer-pre-built'])) {
                $ls = parse_comma_list($options['prefer-pre-built']);
                foreach ($ls as $name) {
                    $this->fetch_prefs[$name] = Artifact::FETCH_PREFER_BINARY;
                }
            } elseif ($options['prefer-pre-built'] === null || $options['prefer-pre-built'] === true) {
                $this->default_fetch_pref = Artifact::FETCH_PREFER_BINARY;
            }
        }
        // Source only
        if (array_key_exists('source-only', $options)) {
            if (is_string($options['source-only'])) {
                $ls = parse_comma_list($options['source-only']);
                foreach ($ls as $name) {
                    $this->fetch_prefs[$name] = Artifact::FETCH_ONLY_SOURCE;
                }
            } elseif ($options['source-only'] === null || $options['source-only'] === true) {
                $this->default_fetch_pref = Artifact::FETCH_ONLY_SOURCE;
            }
        }
        // Binary only
        if (array_key_exists('binary-only', $options)) {
            if (is_string($options['binary-only'])) {
                $ls = parse_comma_list($options['binary-only']);
                foreach ($ls as $name) {
                    $this->fetch_prefs[$name] = Artifact::FETCH_ONLY_BINARY;
                }
            } elseif ($options['binary-only'] === null || $options['binary-only'] === true) {
                $this->default_fetch_pref = Artifact::FETCH_ONLY_BINARY;
            }
        }
        // Ignore cache
        if (array_key_exists('ignore-cache', $options)) {
            if (is_string($options['ignore-cache'])) {
                $this->ignore_cache = parse_comma_list($options['ignore-cache']);
            } elseif ($options['ignore-cache'] === null || $options['ignore-cache'] === true) {
                $this->ignore_cache = true;
            }
        }
        // backward compatibility for ignore-cache-sources
        if (array_key_exists('ignore-cache-sources', $options)) {
            if (is_string($options['ignore-cache-sources'])) {
                $this->ignore_cache = parse_comma_list($options['ignore-cache-sources']);
            } elseif ($options['ignore-cache-sources'] === null || $options['ignore-cache-sources'] === true) {
                $this->ignore_cache = true;
            }
        }
        // Allow setting custom urls via options
        foreach (($options['custom-url'] ?? []) as $value) {
            [$artifact_name, $url] = explode(':', $value, 2);
            $this->custom_urls[$artifact_name] = $url;
            $this->ignore_cache = match ($this->ignore_cache) {
                true => true,
                false => [$artifact_name],
                default => array_merge($this->ignore_cache, [$artifact_name]),
            };
        }
        // Allow setting custom git options via options
        foreach (($options['custom-git'] ?? []) as $value) {
            [$artifact_name, $branch, $git_url] = explode(':', $value, 3) + [null, null, null];
            $this->custom_gits[$artifact_name] = [$branch ?? 'main', $git_url];
            $this->ignore_cache = match ($this->ignore_cache) {
                true => true,
                false => [$artifact_name],
                default => array_merge($this->ignore_cache, [$artifact_name]),
            };
        }
        // Allow setting custom local paths via options
        foreach (($options['custom-local'] ?? []) as $value) {
            [$artifact_name, $local_path] = explode(':', $value, 2);
            $this->custom_locals[$artifact_name] = $local_path;
            $this->ignore_cache = match ($this->ignore_cache) {
                true => true,
                false => [$artifact_name],
                default => array_merge($this->ignore_cache, [$artifact_name]),
            };
        }
        // no alt
        if (array_key_exists('no-alt', $options) && $options['no-alt'] === true) {
            $this->alt = false;
        }

        // read downloads dir
        $this->_before_files = FileSystem::scanDirFiles(DOWNLOAD_PATH, false, true, true) ?: [];
    }

    /**
     * Add an artifact to the download list.
     *
     * @param Artifact|string $artifact Artifact instance or artifact name
     */
    public function add(Artifact|string $artifact): static
    {
        if (is_string($artifact)) {
            $artifact_instance = ArtifactLoader::getArtifactInstance($artifact);
        } else {
            $artifact_instance = $artifact;
        }
        if ($artifact_instance === null) {
            $name = $artifact;
            throw new WrongUsageException("Artifact '{$name}' not found, please check the name.");
        }
        // only add if not already added
        if (!isset($this->artifacts[$artifact_instance->getName()])) {
            $this->artifacts[$artifact_instance->getName()] = $artifact_instance;
        }
        return $this;
    }

    /**
     * Add multiple artifacts to the download list.
     *
     * @param array<Artifact|string> $artifacts Multiple artifacts to add
     */
    public function addArtifacts(array $artifacts): static
    {
        foreach ($artifacts as $artifact) {
            $this->add($artifact);
        }
        return $this;
    }

    /**
     * Set the concurrency limit for parallel downloads.
     *
     * @param int $parallel Number of concurrent downloads (default: 3)
     */
    public function setParallel(int $parallel): static
    {
        $this->parallel = max(1, $parallel);
        return $this;
    }

    /**
     * Download all artifacts, with optional parallel processing.
     *
     * @param bool $interactive Enable interactive mode with Ctrl+C handling
     */
    public function download(bool $interactive = true): void
    {
        if ($interactive) {
            Shell::passthruCallback(function () {
                InteractiveTerm::advance();
            });
            keyboard_interrupt_register(function () {
                echo PHP_EOL;
                InteractiveTerm::error('Download cancelled by user.');
                // scan changed files
                $after_files = FileSystem::scanDirFiles(DOWNLOAD_PATH, false, true, true) ?: [];
                $new_files = array_diff($after_files, $this->_before_files);

                // remove new files
                foreach ($new_files as $file) {
                    if ($file === '.cache.json') {
                        continue;
                    }
                    logger()->debug("Removing corrupted artifact: {$file}");
                    $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($path)) {
                        FileSystem::removeDir($path);
                    } elseif (is_file($path)) {
                        FileSystem::removeFileIfExists($path);
                    }
                }
                exit(2);
            });
        }

        $this->applyCustomDownloads();

        $count = count($this->artifacts);
        $artifacts_str = implode(',', array_map(fn ($x) => '' . ConsoleColor::yellow($x->getName()), $this->artifacts));
        // mute the first line if not interactive
        if ($interactive) {
            InteractiveTerm::notice("Downloading {$count} artifacts: {$artifacts_str} ...");
        }
        try {
            // Create dir
            if (!is_dir(DOWNLOAD_PATH)) {
                FileSystem::createDir(DOWNLOAD_PATH);
            }
            logger()->info('Downloading' . implode(', ', array_map(fn ($x) => " '{$x->getName()}'", $this->artifacts)) . " with concurrency {$this->parallel} ...");
            // Download artifacts parallely
            if ($this->parallel > 1) {
                $this->downloadWithConcurrency();
            } else {
                // normal sequential download
                $current = 0;
                $skipped = [];
                foreach ($this->artifacts as $artifact) {
                    ++$current;
                    if ($this->downloadWithType($artifact, $current, $count, interactive: $interactive) === SPC_DOWNLOAD_STATUS_SKIPPED) {
                        $skipped[] = $artifact->getName();
                        continue;
                    }
                    $this->_before_files = FileSystem::scanDirFiles(DOWNLOAD_PATH, false, true, true) ?: [];
                }
                if ($interactive) {
                    $skip_msg = !empty($skipped) ? ' (Skipped ' . count($skipped) . ' artifacts for being already downloaded)' : '';
                    InteractiveTerm::success("Downloaded all {$count} artifacts.{$skip_msg}", true);
                    echo PHP_EOL;
                }
            }
        } catch (SPCException $e) {
            array_map(fn ($x) => InteractiveTerm::error($x), explode("\n", $e->getMessage()));
            throw new WrongUsageException();
        } finally {
            if ($interactive) {
                Shell::passthruCallback(null);
                keyboard_interrupt_unregister();
            }
        }
    }

    public function getRetry(): int
    {
        return $this->retry;
    }

    public function getArtifacts(): array
    {
        return $this->artifacts;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    private function downloadWithType(Artifact $artifact, int $current, int $total, bool $parallel = false, bool $interactive = true): int
    {
        $queue = $this->generateQueue($artifact);
        // already downloaded
        if ($queue === []) {
            logger()->debug("Artifact '{$artifact->getName()}' is already downloaded, skipping.");
            return SPC_DOWNLOAD_STATUS_SKIPPED;
        }

        $try = false;
        foreach ($queue as $item) {
            try {
                $instance = null;
                $call = self::DOWNLOADERS[$item['config']['type']] ?? null;
                $type_display_name = match (true) {
                    $item['lock'] === 'source' && ($callback = $artifact->getCustomSourceCallback()) !== null => 'user defined source downloader',
                    $item['lock'] === 'binary' && ($callback = $artifact->getCustomBinaryCallback()) !== null => 'user defined binary downloader',
                    default => SPC_DOWNLOAD_TYPE_DISPLAY_NAME[$item['config']['type']] ?? $item['config']['type'],
                };
                $try_h = $try ? 'Try downloading' : 'Downloading';
                logger()->info("{$try_h} artifact '{$artifact->getName()}' {$item['display']} ...");
                if ($parallel === false && $interactive) {
                    InteractiveTerm::indicateProgress("[{$current}/{$total}] Downloading artifact " . ConsoleColor::green($artifact->getName()) . " {$item['display']} from {$type_display_name} ...");
                }
                // is valid download type
                if ($item['lock'] === 'source' && ($callback = $artifact->getCustomSourceCallback()) !== null) {
                    $lock = ApplicationContext::invoke($callback, [
                        Artifact::class => $artifact,
                        ArtifactDownloader::class => $this,
                    ]);
                } elseif ($item['lock'] === 'binary' && ($callback = $artifact->getCustomBinaryCallback()) !== null) {
                    $lock = ApplicationContext::invoke($callback, [
                        Artifact::class => $artifact,
                        ArtifactDownloader::class => $this,
                    ]);
                } elseif (is_a($call, DownloadTypeInterface::class, true)) {
                    $instance = new $call();
                    $lock = $instance->download($artifact->getName(), $item['config'], $this);
                } else {
                    if ($item['config']['type'] === 'custom') {
                        $msg = "Artifact [{$artifact->getName()}] has no valid custom " . SystemTarget::getCurrentPlatformString() . ' download callback defined.';
                    } else {
                        $msg = "Artifact has invalid download type '{$item['config']['type']}' for {$item['display']}.";
                    }
                    throw new ValidationException($msg);
                }
                if (!$lock instanceof DownloadResult) {
                    throw new ValidationException("Artifact {$artifact->getName()} has invalid custom return value. Must be instance of DownloadResult.");
                }
                // verifying hash if possible
                $hash_validator = $instance ?? null;
                $verified = $lock->verified;
                if ($hash_validator instanceof ValidatorInterface) {
                    if (!$hash_validator->validate($artifact->getName(), $item['config'], $this, $lock)) {
                        throw new ValidationException("Hash validation failed for artifact '{$artifact->getName()}' {$item['display']}.");
                    }
                    $verified = true;
                }
                // process lock
                ApplicationContext::get(ArtifactCache::class)->lock($artifact, $item['lock'], $lock, SystemTarget::getCurrentPlatformString());
                if ($parallel === false && $interactive) {
                    $ver = $lock->hasVersion() ? (' (' . ConsoleColor::yellow($lock->version) . ')') : '';
                    InteractiveTerm::finish('Downloaded ' . ($verified ? 'and verified ' : '') . 'artifact ' . ConsoleColor::green($artifact->getName()) . $ver . " {$item['display']} .");
                }
                return SPC_DOWNLOAD_STATUS_SUCCESS;
            } catch (DownloaderException|ExecutionException $e) {
                if ($parallel === false && $interactive) {
                    InteractiveTerm::finish("Download artifact {$artifact->getName()} {$item['display']} failed !", false);
                    InteractiveTerm::error("Failed message: {$e->getMessage()}", true);
                }
                $try = true;
                continue;
            } catch (ValidationException $e) {
                if ($parallel === false) {
                    InteractiveTerm::finish("Download artifact {$artifact->getName()} {$item['display']} failed !", false);
                    InteractiveTerm::error("Validation failed: {$e->getMessage()}");
                }
                break;
            }
        }
        $vvv = ApplicationContext::isDebug() ? "\nIf the problem persists, consider using `-vvv` to enable verbose mode, and disable parallel downloading for more details." : '';
        throw new DownloaderException("Download artifact '{$artifact->getName()}' failed. Please check your internet connection and try again.{$vvv}");
    }

    private function downloadWithConcurrency(): void
    {
        $skipped = [];
        $fiber_pool = [];
        $old_verbosity = null;
        $old_debug = null;
        try {
            $count = count($this->artifacts);
            // must mute
            $output = ApplicationContext::get(OutputInterface::class);
            if ($output->isVerbose()) {
                $old_verbosity = $output->getVerbosity();
                $old_debug = ApplicationContext::isDebug();
                logger()->warning('Parallel download is not supported in verbose mode, I will mute the output temporarily.');
                $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
                ApplicationContext::setDebug(false);
                logger()->setLevel(LogLevel::ERROR);
            }
            $pool_count = $this->parallel;
            $downloaded = 0;
            $total = count($this->artifacts);

            Shell::passthruCallback(function () {
                InteractiveTerm::advance();
                \Fiber::suspend();
            });

            InteractiveTerm::indicateProgress("[{$downloaded}/{$total}] Downloading artifacts with concurrency {$this->parallel} ...");
            $failed_downloads = [];
            while (true) {
                // fill pool
                while (count($fiber_pool) < $pool_count && ($artifact = array_shift($this->artifacts)) !== null) {
                    $current = $count - count($this->artifacts);
                    $fiber = new \Fiber(function () use ($artifact, $current, $count) {
                        return [$artifact, $this->downloadWithType($artifact, $current, $count, true)];
                    });
                    $fiber->start();
                    $fiber_pool[] = $fiber;
                }
                // check pool
                foreach ($fiber_pool as $index => $fiber) {
                    if ($fiber->isTerminated()) {
                        try {
                            [$artifact, $int] = $fiber->getReturn();
                            if ($int === SPC_DOWNLOAD_STATUS_SKIPPED) {
                                $skipped[] = $artifact->getName();
                            }
                        } catch (\Throwable $e) {
                            $artifact_name = 'unknown';
                            if (isset($artifact)) {
                                $artifact_name = $artifact->getName();
                            }
                            $failed_downloads[] = ['artifact' => $artifact_name, 'error' => $e];
                            InteractiveTerm::setMessage("[{$downloaded}/{$total}] Download failed: {$artifact_name}");
                            InteractiveTerm::advance();
                        }
                        // remove from pool
                        unset($fiber_pool[$index]);
                        ++$downloaded;
                        InteractiveTerm::setMessage("[{$downloaded}/{$total}] Downloading artifacts with concurrency {$this->parallel} ...");
                        InteractiveTerm::advance();
                    } else {
                        $fiber->resume();
                    }
                }
                // all done
                if (count($this->artifacts) === 0 && count($fiber_pool) === 0) {
                    if (!empty($failed_downloads)) {
                        InteractiveTerm::finish('Download completed with ' . count($failed_downloads) . ' failure(s).', false);
                        foreach ($failed_downloads as $failure) {
                            InteractiveTerm::error("Failed to download '{$failure['artifact']}': {$failure['error']->getMessage()}");
                        }
                        throw new DownloaderException('Failed to download ' . count($failed_downloads) . ' artifact(s). Please check your internet connection and try again.');
                    }
                    $skip_msg = !empty($skipped) ? ' (Skipped ' . count($skipped) . ' artifacts for being already downloaded)' : '';
                    InteractiveTerm::finish("Downloaded all {$total} artifacts.{$skip_msg}");
                    break;
                }
            }
        } catch (\Throwable $e) {
            // throw to all fibers to make them stop
            foreach ($fiber_pool as $fiber) {
                if (!$fiber->isTerminated()) {
                    try {
                        $fiber->throw($e);
                    } catch (\Throwable) {
                        // ignore errors when stopping fibers
                    }
                }
            }
            InteractiveTerm::finish('Parallel download failed !', false);
            throw $e;
        } finally {
            if ($old_verbosity !== null) {
                ApplicationContext::get(OutputInterface::class)->setVerbosity($old_verbosity);
                logger()->setLevel(match ($old_verbosity) {
                    OutputInterface::VERBOSITY_VERBOSE => LogLevel::INFO,
                    OutputInterface::VERBOSITY_VERY_VERBOSE, OutputInterface::VERBOSITY_DEBUG => LogLevel::DEBUG,
                    default => LogLevel::WARNING,
                });
            }
            if ($old_debug !== null) {
                ApplicationContext::setDebug($old_debug);
            }
            Shell::passthruCallback(null);
        }
    }

    /**
     * Generate download queue based on type preference.
     */
    private function generateQueue(Artifact $artifact): array
    {
        /** @var array<array{display: string, lock: string, config: array}> $queue */
        $queue = [];
        $binary_downloaded = $artifact->isBinaryDownloaded(compare_hash: true);
        $source_downloaded = $artifact->isSourceDownloaded(compare_hash: true);

        $item_source = ['display' => 'source', 'lock' => 'source', 'config' => $artifact->getDownloadConfig('source')];
        $item_source_mirror = ['display' => 'source (mirror)', 'lock' => 'source', 'config' => $artifact->getDownloadConfig('source-mirror')];

        // For binary config, handle both array configs and custom callbacks
        $binary_config = $artifact->getDownloadConfig('binary');
        $has_custom_binary = $artifact->getCustomBinaryCallback() !== null;
        $item_binary_config = null;
        if (is_array($binary_config)) {
            $item_binary_config = $binary_config[SystemTarget::getCurrentPlatformString()] ?? null;
        } elseif ($has_custom_binary) {
            // For custom binaries, create a dummy config to allow queue generation
            $item_binary_config = ['type' => 'custom'];
        }
        $item_binary = ['display' => 'binary', 'lock' => 'binary', 'config' => $item_binary_config];

        $binary_mirror_config = $artifact->getDownloadConfig('binary-mirror');
        $item_binary_mirror_config = null;
        if (is_array($binary_mirror_config)) {
            $item_binary_mirror_config = $binary_mirror_config[SystemTarget::getCurrentPlatformString()] ?? null;
        }
        $item_binary_mirror = ['display' => 'binary (mirror)', 'lock' => 'binary', 'config' => $item_binary_mirror_config];

        $pref = $this->fetch_prefs[$artifact->getName()] ?? $this->default_fetch_pref;
        if ($pref === Artifact::FETCH_PREFER_SOURCE) {
            $queue[] = $item_source['config'] !== null ? $item_source : null;
            $queue[] = $item_source_mirror['config'] !== null && $this->alt ? $item_source_mirror : null;
            $queue[] = $item_binary['config'] !== null ? $item_binary : null;
            $queue[] = $item_binary_mirror['config'] !== null && $this->alt ? $item_binary_mirror : null;
        } elseif ($pref === Artifact::FETCH_PREFER_BINARY) {
            $queue[] = $item_binary['config'] !== null ? $item_binary : null;
            $queue[] = $item_binary_mirror['config'] !== null && $this->alt ? $item_binary_mirror : null;
            $queue[] = $item_source['config'] !== null ? $item_source : null;
            $queue[] = $item_source_mirror['config'] !== null && $this->alt ? $item_source_mirror : null;
        } elseif ($pref === Artifact::FETCH_ONLY_SOURCE) {
            $queue[] = $item_source['config'] !== null ? $item_source : null;
            $queue[] = $item_source_mirror['config'] !== null && $this->alt ? $item_source_mirror : null;
        } elseif ($pref === Artifact::FETCH_ONLY_BINARY) {
            $queue[] = $item_binary['config'] !== null ? $item_binary : null;
            $queue[] = $item_binary_mirror['config'] !== null && $this->alt ? $item_binary_mirror : null;
        }
        // filter nulls
        $queue = array_values(array_filter($queue));

        // always download
        if ($this->ignore_cache === true || is_array($this->ignore_cache) && in_array($artifact->getName(), $this->ignore_cache)) {
            // validate: ensure at least one download source is available
            if (empty($queue)) {
                throw new ValidationException("Artifact '{$artifact->getName()}' does not provide any download source for current platform (" . SystemTarget::getCurrentPlatformString() . ').');
            }
            return $queue;
        }

        // check if already downloaded
        $has_usable_download = false;
        if ($pref === Artifact::FETCH_PREFER_SOURCE) {
            // prefer source: check source first, if not available check binary
            $has_usable_download = $source_downloaded || $binary_downloaded;
        } elseif ($pref === Artifact::FETCH_PREFER_BINARY) {
            // prefer binary: check binary first, if not available check source
            $has_usable_download = $binary_downloaded || $source_downloaded;
        } elseif ($pref === Artifact::FETCH_ONLY_SOURCE) {
            // source-only: only check if source is downloaded
            $has_usable_download = $source_downloaded;
        } elseif ($pref === Artifact::FETCH_ONLY_BINARY) {
            // binary-only: only check if binary for current platform is downloaded
            $has_usable_download = $binary_downloaded;
        }

        // if already downloaded, skip
        if ($has_usable_download) {
            return [];
        }

        // validate: ensure at least one download source is available
        if (empty($queue)) {
            if ($pref === Artifact::FETCH_ONLY_SOURCE) {
                throw new ValidationException("Artifact '{$artifact->getName()}' does not provide source download, cannot use --source-only mode.");
            }
            if ($pref === Artifact::FETCH_ONLY_BINARY) {
                throw new ValidationException("Artifact '{$artifact->getName()}' does not provide binary download for current platform (" . SystemTarget::getCurrentPlatformString() . '), cannot use --binary-only mode.');
            }
            // prefer modes should also throw error if no download source available
            throw new ValidationException("Validation failed: Artifact '{$artifact->getName()}' does not provide any download source for current platform (" . SystemTarget::getCurrentPlatformString() . ').');
        }

        return $queue;
    }

    private function applyCustomDownloads(): void
    {
        foreach ($this->custom_urls as $artifact_name => $custom_url) {
            if (isset($this->artifacts[$artifact_name])) {
                $this->artifacts[$artifact_name]->setCustomSourceCallback(function (ArtifactDownloader $downloader) use ($artifact_name, $custom_url) {
                    return (new Url())->download($artifact_name, ['url' => $custom_url], $downloader);
                });
            }
        }
        foreach ($this->custom_gits as $artifact_name => [$branch, $git_url]) {
            if (isset($this->artifacts[$artifact_name])) {
                $this->artifacts[$artifact_name]->setCustomSourceCallback(function (ArtifactDownloader $downloader) use ($artifact_name, $branch, $git_url) {
                    return (new Git())->download($artifact_name, ['rev' => $branch, 'url' => $git_url], $downloader);
                });
            }
        }
        foreach ($this->custom_locals as $artifact_name => $local_path) {
            if (isset($this->artifacts[$artifact_name])) {
                $this->artifacts[$artifact_name]->setCustomSourceCallback(function (ArtifactDownloader $downloader) use ($artifact_name, $local_path) {
                    return (new LocalDir())->download($artifact_name, ['dirname' => $local_path], $downloader);
                });
            }
        }
    }
}
