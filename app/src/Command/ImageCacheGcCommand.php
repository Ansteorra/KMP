<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ImageCacheGcCommand extends Command
{
    /**
     * Get the default command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'image_cache_gc';
    }

    /**
     * Configure options.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Delete stale Glide image cache files.')
            ->addOption('days', [
                'help' => 'Delete cache files older than this many days.',
                'default' => '7',
            ])
            ->addOption('path', [
                'help' => 'Cache directory path.',
                'default' => WWW_ROOT . '../images/cache',
            ])
            ->addOption('dry-run', [
                'help' => 'List files that would be deleted without deleting them.',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * Execute cache garbage collection.
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $path = (string)$args->getOption('path');
        $days = max(1, (int)$args->getOption('days'));
        $dryRun = (bool)$args->getOption('dry-run');

        if (!is_dir($path)) {
            $io->out(sprintf('Image cache path does not exist: %s', $path));

            return self::CODE_SUCCESS;
        }

        $cutoff = time() - ($days * 86400);
        $deleted = 0;
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getMTime() >= $cutoff) {
                continue;
            }

            $bytes += $file->getSize();
            $deleted++;
            if ($dryRun) {
                $io->out($file->getPathname());
                continue;
            }

            unlink($file->getPathname());
        }

        $io->success(sprintf(
            '%s %d stale image cache file(s), %d byte(s).',
            $dryRun ? 'Found' : 'Deleted',
            $deleted,
            $bytes,
        ));

        return self::CODE_SUCCESS;
    }
}
