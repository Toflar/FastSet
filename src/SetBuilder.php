<?php

declare(strict_types=1);

namespace Toflar\FastSet;

final class SetBuilder
{
    /**
     * @param string $sourcePath the source path must be a file with all entries separated by new lines
     * @param string $outputPath If your output path ends on ".gz", the set will also get compressed on top.
     */
    public static function buildSet(string $sourcePath, string $outputPath): void
    {
        $inputHandle = fopen($sourcePath, 'r');
        if (false === $inputHandle) {
            throw new \RuntimeException('Unable to open file: '.$sourcePath);
        }

        $terms = [];

        while (($line = fgets($inputHandle)) !== false) {
            $line = trim($line);
            if ('' !== $line) {
                $terms[] = $line;
            }
        }
        fclose($inputHandle);

        sort($terms, SORT_STRING);

        $outputHandle = self::openForWritingPossiblyGzip($outputPath);

        $previousTerm = '';

        foreach ($terms as $term) {
            $commonPrefixLength = self::commonPrefixByteLength($previousTerm, $term);
            $suffix = substr($term, $commonPrefixLength);

            // <prefixLen>\t<suffix>\n
            fwrite($outputHandle, (string) $commonPrefixLength);
            fwrite($outputHandle, "\t");
            fwrite($outputHandle, $suffix);
            fwrite($outputHandle, "\n");

            $previousTerm = $term;
        }

        self::closePossiblyGzip($outputHandle, $outputPath);
    }

    public static function readSet(string $setPath, callable $callable): void
    {
        $handle = self::openForReadingPossiblyGzip($setPath);

        $previousTerm = '';

        while (($line = self::readLinePossiblyGzip($handle, $setPath)) !== false) {
            $line = rtrim($line, "\r\n");
            if ('' === $line) {
                continue;
            }

            $tabPosition = strpos($line, "\t");
            if (false === $tabPosition) {
                throw new \UnexpectedValueException('Invalid file format. Ensure you have built it using the SetBuilder class!');
            }

            $prefixLengthText = substr($line, 0, $tabPosition);
            $suffix = substr($line, $tabPosition + 1);

            $prefixLength = (int) $prefixLengthText;

            $term = substr($previousTerm, 0, $prefixLength).$suffix;

            $callable($term);

            $previousTerm = $term;
        }

        self::closePossiblyGzip($handle, $setPath);
    }

    private static function commonPrefixByteLength(string $left, string $right): int
    {
        $limit = min(\strlen($left), \strlen($right));
        $index = 0;

        // Byte-wise common prefix
        while ($index < $limit && $left[$index] === $right[$index]) {
            ++$index;
        }

        return $index;
    }

    /**
     * @return resource
     */
    private static function openForWritingPossiblyGzip(string $path)
    {
        if (str_ends_with($path, '.gz')) {
            if (!\function_exists('gzopen')) {
                throw new \RuntimeException('Cannot open for gzip write (gzopen not available): '.$path);
            }

            $handle = gzopen($path, 'wb9');
            if (false === $handle) {
                throw new \RuntimeException('Cannot open for gzip write: '.$path);
            }

            return $handle;
        }

        $handle = fopen($path, 'w');
        if (false === $handle) {
            throw new \RuntimeException('Cannot open for write: '.$path);
        }

        return $handle;
    }

    /**
     * @return resource
     */
    private static function openForReadingPossiblyGzip(string $path)
    {
        if (str_ends_with($path, '.gz')) {
            if (!\function_exists('gzopen')) {
                throw new \RuntimeException('Cannot open for reading gzip reading (gzopen not available): '.$path);
            }

            $handle = gzopen($path, 'rb');
            if (false === $handle) {
                throw new \RuntimeException('Cannot open for reading gzip reading: '.$path);
            }

            return $handle;
        }

        $handle = fopen($path, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Cannot open for reading: '.$path);
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private static function readLinePossiblyGzip($handle, string $path): string|false
    {
        if (str_ends_with($path, '.gz')) {
            if (!\function_exists('gzgets')) {
                throw new \RuntimeException('Cannot read gzip: '.$path);
            }

            return gzgets($handle);
        }

        return fgets($handle);
    }

    /**
     * @param resource $handle
     */
    private static function closePossiblyGzip($handle, string $path): void
    {
        if (str_ends_with($path, '.gz')) {
            if (!\function_exists('gzclose')) {
                throw new \RuntimeException('Cannot write gzip: '.$path);
            }

            gzclose($handle);

            return;
        }
        fclose($handle);
    }
}
