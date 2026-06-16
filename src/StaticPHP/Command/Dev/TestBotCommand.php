<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Artifact\Downloader\Type\GitHubTokenSetupTrait;
use StaticPHP\Command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('dev:test-bot', 'Analyze PR changes and labels, output test-bot metadata JSON', [], true)]
class TestBotCommand extends BaseCommand
{
    use GitHubTokenSetupTrait;

    private const string API_BASE = 'https://api.github.com';

    /** Platform labels → os_key used by dev:gen-ext-test-matrix --os= */
    private const array PLATFORM_LABELS = [
        'test/linux' => 'Linux',
        'test/windows' => 'Windows',
        'test/macos' => 'Darwin',
    ];

    private const string TIER2_LABEL = 'test/tier2';

    /** PHP version labels → version string (8.5 is always included as default) */
    private const array PHP_VERSION_LABELS = [
        'test/php-83' => '8.3',
        'test/php-84' => '8.4',
    ];

    private const string DEFAULT_PHP_VERSION = '8.5';

    protected bool $no_motd = true;

    public function configure(): void
    {
        $this->addOption('pr', null, InputOption::VALUE_REQUIRED, 'Pull request number')
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Repository in owner/repo format (e.g. owner/repo)')
            ->addOption('mock-files', null, InputOption::VALUE_REQUIRED, 'Comma-separated file paths to simulate PR changed files (skips GitHub API, for local testing)', '')
            ->addOption('mock-labels', null, InputOption::VALUE_REQUIRED, 'Comma-separated labels to simulate PR labels (skips GitHub API, for local testing)', '');
    }

    public function handle(): int
    {
        $mock_files_raw = (string) $this->input->getOption('mock-files');
        $mock_labels_raw = (string) $this->input->getOption('mock-labels');
        $is_mock = $mock_files_raw !== '' || $mock_labels_raw !== '';

        if ($is_mock) {
            // Local testing mode: skip all GitHub API calls
            $changed_files = array_map(
                fn ($f) => ['filename' => trim($f)],
                array_filter(explode(',', $mock_files_raw))
            );
            $label_names = array_map('trim', array_filter(explode(',', $mock_labels_raw)));
        } else {
            $pr = (int) $this->input->getOption('pr');
            $repo = (string) $this->input->getOption('repo');

            if ($pr <= 0 || $repo === '') {
                $this->output->writeln('<error>Either --mock-files/--mock-labels (local test) or --pr and --repo (live) are required.</error>');
                return static::USER_ERROR;
            }

            $headers = array_merge(
                $this->getGitHubTokenHeaders(),
                ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'],
            );

            // Fetch changed files (paginated, up to 300)
            $changed_files = $this->fetchPaginatedFiles($repo, $pr, $headers);

            // Fetch current labels on the PR/issue
            $labels_raw = $this->apiGet(
                sprintf('%s/repos/%s/issues/%d/labels', self::API_BASE, $repo, $pr),
                $headers
            );
            $label_names = array_column($labels_raw ?? [], 'name');
        }

        // Analyze changed files → extensions, libs, targets
        [$extensions, $libs, $targets] = $this->analyzeChangedFiles($changed_files);

        // Resolve active platform OS keys (used as filters, not as trigger)
        $os_keys = [];
        foreach (self::PLATFORM_LABELS as $label => $os_key) {
            if (in_array($label, $label_names, true)) {
                $os_keys[] = $os_key;
            }
        }
        $tier2 = in_array(self::TIER2_LABEL, $label_names, true);
        $need_test = in_array('need-test', $label_names, true);

        // Resolve PHP versions (default always included)
        $php_versions = [self::DEFAULT_PHP_VERSION];
        foreach (self::PHP_VERSION_LABELS as $label => $version) {
            if (in_array($label, $label_names, true)) {
                $php_versions[] = $version;
            }
        }
        $php_versions = array_unique($php_versions);
        sort($php_versions);

        // Build gen_matrix_args whenever need-test is set.
        // Platform labels narrow the OS scope; absent = no --os filter (all platforms).
        $gen_matrix_args = '';
        $gen_matrix_args_tier2 = '';
        if ($need_test) {
            $flag_parts = [];
            if (!empty($extensions)) {
                $flag_parts[] = '--for-extensions=' . implode(',', $extensions);
            }
            if (!empty($libs)) {
                $flag_parts[] = '--for-libs=' . implode(',', $libs);
            }
            if (!empty($os_keys)) {
                $flag_parts[] = '--os=' . implode(',', $os_keys);
            }
            $gen_matrix_args = implode(' ', $flag_parts);

            if ($tier2) {
                // Tier2 covers Linux + macOS only (never Windows)
                $tier2_os = array_values(array_filter(
                    !empty($os_keys) ? $os_keys : ['Linux', 'Darwin'],
                    fn ($k) => $k !== 'Windows'
                ));
                if (!empty($tier2_os)) {
                    $tier2_parts = array_values(array_filter($flag_parts, fn ($f) => !str_starts_with($f, '--os=')));
                    $tier2_parts[] = '--os=' . implode(',', $tier2_os);
                    $tier2_parts[] = '--tier2';
                    $gen_matrix_args_tier2 = implode(' ', $tier2_parts);
                }
            }
        }

        $comment_body = $this->buildCommentBody(
            $extensions,
            $libs,
            $targets,
            $label_names,
            $os_keys,
            $tier2,
            $php_versions,
            $need_test,
        );

        $result = [
            'need_test' => $need_test,
            'extensions' => array_values($extensions),
            'libs' => array_values($libs),
            'targets' => array_values($targets),
            'gen_matrix_args' => $gen_matrix_args,
            'gen_matrix_args_tier2' => $gen_matrix_args_tier2,
            'php_versions' => $php_versions,
            'tier2' => $tier2,
            'comment_body' => $comment_body,
        ];

        $this->output->write(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return static::SUCCESS;
    }

    /**
     * Fetch all changed files for a PR across up to 3 pages (max 300 files).
     */
    private function fetchPaginatedFiles(string $repo, int $pr, array $headers): array
    {
        $files = [];
        for ($page = 1; $page <= 3; ++$page) {
            $url = sprintf('%s/repos/%s/pulls/%d/files?per_page=100&page=%d', self::API_BASE, $repo, $pr, $page);
            $batch = $this->apiGet($url, $headers);
            if (empty($batch)) {
                break;
            }
            $files = array_merge($files, $batch);
            if (count($batch) < 100) {
                break;
            }
        }
        return $files;
    }

    /**
     * Perform a GET request and return decoded JSON array, or null on failure.
     */
    private function apiGet(string $url, array $headers): ?array
    {
        $data = default_shell()->executeCurl($url, headers: $headers);
        $decoded = json_decode($data ?: '', true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Analyze changed file paths and classify them into extensions, libs, and targets.
     *
     * @return array{string[], string[], string[]}
     */
    private function analyzeChangedFiles(array $files): array
    {
        $extensions = [];
        $libs = [];
        $targets = [];

        foreach ($files as $file) {
            $path = $file['filename'] ?? '';

            if (preg_match('#^src/Package/Extension/([^/]+)\.php$#', $path, $m)) {
                $name = strtolower($m[1]);
                $extensions[$name] = $name;
            } elseif (preg_match('#^config/pkg/ext/ext-([^/]+)\.yml$#', $path, $m)) {
                $extensions[$m[1]] = $m[1];
            } elseif (preg_match('#^src/Package/Library/([^/]+)\.php$#', $path, $m)) {
                $name = strtolower($m[1]);
                $libs[$name] = $name;
            } elseif (preg_match('#^config/pkg/lib/([^/]+)\.yml$#', $path, $m)) {
                $libs[$m[1]] = $m[1];
            } elseif (preg_match('#^src/Package/Target/([^/]+)\.php$#', $path, $m)) {
                $name = strtolower($m[1]);
                $targets[$name] = $name;
            } elseif (preg_match('#^config/pkg/target/([^/]+)\.yml$#', $path, $m)) {
                $targets[$m[1]] = $m[1];
            }
        }

        sort($extensions);
        sort($libs);
        sort($targets);

        return [$extensions, $libs, $targets];
    }

    private function buildCommentBody(
        array $extensions,
        array $libs,
        array $targets,
        array $label_names,
        array $os_keys,
        bool $tier2,
        array $php_versions,
        bool $need_test,
    ): string {
        $fmt = static fn (array $items): string => !empty($items)
            ? '`' . implode('`, `', $items) . '`'
            : '_none_';

        $detected = sprintf(
            '**Detected**: Extensions: %s | Libraries: %s | Targets: %s',
            $fmt($extensions),
            $fmt($libs),
            $fmt($targets),
        );

        // Case 1: need-test absent → invite the author to add it
        if (!$need_test) {
            return implode("\n", [
                '<!-- spc-test-bot -->',
                '**StaticPHP Test Bot**',
                '',
                $detected,
                '',
                'To trigger extension build tests on this PR, add the `need-test` label:',
                '',
                '**Gate**: `need-test`',
                '**Platform filter** (optional, default all): `test/linux` `test/windows` `test/macos` · `test/tier2`',
                '**PHP version** (optional, default 8.5): `test/php-83` `test/php-84`',
            ]);
        }

        // Case 2: need-test present → show what will run
        // os_keys empty = no filter = all platforms
        $effective_os = !empty($os_keys)
            ? $os_keys
            : array_values(self::PLATFORM_LABELS);  // all OS keys

        $platform_parts = [];
        foreach (self::PLATFORM_LABELS as $_label => $os_key) {
            if (!in_array($os_key, $effective_os, true)) {
                continue;
            }
            $platform_parts[] = match ($os_key) {
                'Linux' => 'Linux x86_64',
                'Darwin' => 'macOS arm64',
                /* @phpstan-ignore-next-line */
                'Windows' => 'Windows x86_64',
                default => $os_key,
            };
        }
        if ($tier2) {
            if (in_array('Linux', $effective_os, true)) {
                $platform_parts[] = 'Linux aarch64 (Tier2)';
            }
            if (in_array('Darwin', $effective_os, true)) {
                $platform_parts[] = 'macOS x86_64 (Tier2)';
            }
        }

        $php_str = implode(', ', array_map(fn ($v) => "PHP {$v}", $php_versions)) . ' NTS';
        $active_test_labels = array_values(array_filter($label_names, fn ($l) => str_starts_with($l, 'test/')));
        $labels_str = !empty($active_test_labels) ? '`' . implode('`, `', $active_test_labels) . '`' : '_none_';

        return implode("\n", [
            '<!-- spc-test-bot -->',
            '**StaticPHP Test Bot**',
            '',
            $detected,
            '**Active labels**: ' . $labels_str,
            '**Config**: ' . implode(' + ', $platform_parts) . ' | ' . $php_str,
        ]);
    }
}
