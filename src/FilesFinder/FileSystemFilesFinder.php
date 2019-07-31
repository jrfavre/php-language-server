<?php
declare(strict_types = 1);

namespace LanguageServer\FilesFinder;

use Webmozart\Glob\Iterator\GlobIterator;
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;
use function LanguageServer\{pathToUri, timeout};

class FileSystemFilesFinder implements FilesFinder
{
    /**
     * Returns all files in the workspace that match a glob.
     * If the client does not support workspace/xfiles, it falls back to searching the file system directly.
     *
     * @param string $glob
     * @return Promise <string[]>
     */
    public function find(string $glob): Promise
    {
        return coroutine(function () use ($glob) {
            $uris = [];
            foreach (new GlobIterator($glob) as $path) {
                // Exclude any directories that also match the glob pattern
                if (!is_dir($path)) {
                    $uris[] = pathToUri($path);
                }

                yield timeout();
            }
            return $uris;
        });
    }

    public function findNew(string $rootPath, string $regex): Promise
    {
        return coroutine(function () use ($rootPath, $regex) {
            $uris = [];

            $directory = new \RecursiveDirectoryIterator($rootPath, \RecursiveDirectoryIterator::CURRENT_AS_PATHNAME | \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
            $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);
            $regexIterator = new \RegexIterator($iterator, $regex, \RegexIterator::MATCH);

            foreach($regexIterator as $path) {
                // Exclude any directories that also match the glob pattern
                if (!is_dir($path)) {
                    $uris[] = pathToUri($path);
                }

                yield timeout();
            }
            return $uris;
        });
    }
}
