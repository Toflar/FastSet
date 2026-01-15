<?php

declare(strict_types=1);

namespace Toflar\FastSet;

class FastSet
{
    private readonly string $hashesPath;

    private readonly string $indexPath;

    private bool $isInitialized = false;

    private string $blob = '';

    /**
     * @var array<int>
     */
    private array $starts = [];

    public function __construct(private readonly string $directory)
    {
        $this->hashesPath = $this->directory.'/hashes.bin';
        $this->indexPath = $this->directory.'/index.bin';

        if (!is_dir($this->directory)) {
            throw new \InvalidArgumentException('Directory does not exist.');
        }

        if (!\in_array('xxh128', hash_algos(), true)) {
            throw new \LogicException('Requires xxh128 hash algorithm.');
        }
    }

    public function has(string $entry): bool
    {
        if ('' === $entry) {
            return false;
        }

        $this->initialize();

        $fingerprint = $this->getFingerPrintForEntry($entry);

        $prefixKey = $this->getPrefixKey($fingerprint);

        // Restrict search to the bucket range [startIndex, endIndex]
        $startIndex = $this->starts[$prefixKey];
        $endIndex = $this->starts[$prefixKey + 1];

        // Empty bucket -> definitely not present
        if ($startIndex >= $endIndex) {
            return false;
        }

        // Binary search within that bucket
        $low = $startIndex;
        $high = $endIndex - 1;

        while ($low <= $high) {
            $mid = $low + $high >> 1;
            $midFingerprint = substr($this->blob, $mid * 16, 16);

            $cmp = strcmp($midFingerprint, $fingerprint);
            if (0 === $cmp) {
                return true;
            }

            if ($cmp < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return false;
    }

    /**
     * The source path must be a file with all entries separated by new lines.
     */
    public function build(string $sourcePath): void
    {
        if (!is_file($sourcePath)) {
            throw new \InvalidArgumentException('Source file does not exist.');
        }

        $handle = fopen($sourcePath, 'r');
        if (false === $handle) {
            throw new \InvalidArgumentException('Source file is not readable.');
        }

        $fingerPrints = [];

        while (($line = fgets($handle)) !== false) {
            $entry = trim($line);

            if ('' === $entry) {
                continue;
            }

            $fingerPrints[] = $this->getFingerPrintForEntry($entry);
        }

        fclose($handle);

        // Sort all fingerprints so we can binary-search them later
        sort($fingerPrints, SORT_STRING);

        // For each possible 2-byte prefix (0..65535), count how many fingerprints start with that prefix.
        // This lets us build a prefix -> [start, end] lookup table.
        $prefixCounts = array_fill(0, 65536, 0);

        $hashFile = $this->openFileHandleForWriting($this->hashesPath);

        foreach ($fingerPrints as $fingerprint) {
            $prefixKey = $this->getPrefixKey($fingerprint);
            ++$prefixCounts[$prefixKey];

            // Each fingerprint is exactly 16 bytes
            fwrite($hashFile, $fingerprint);
        }

        fclose($hashFile);

        // Build the prefix index
        $indexFile = $this->openFileHandleForWriting($this->indexPath);
        $currentOffset = 0;

        for ($prefix = 0; $prefix < 65536; ++$prefix) {
            // Write starting index for this prefix
            fwrite($indexFile, pack('V', $currentOffset));
            // Advance by the number of fingerprints in this bucket
            $currentOffset += $prefixCounts[$prefix];
        }

        // Final placeholder entry: start offset after the last prefix
        fwrite($indexFile, pack('V', $currentOffset));
        fclose($indexFile);
    }

    public function initialize(): void
    {
        if ($this->isInitialized) {
            return;
        }

        $blob = @file_get_contents($this->hashesPath);
        $indexBytes = @file_get_contents($this->indexPath);

        if (false === $blob || false === $indexBytes) {
            throw new \RuntimeException('Hashes or index files do not exist.');
        }

        $this->blob = $blob;
        $this->starts = array_values(unpack('V*', $indexBytes));
        $this->isInitialized = true;
    }

    /**
     * Use the first 2 bytes of the fingerprint as prefix bucket key.
     */
    public function getPrefixKey(string $fingerprint): int
    {
        return (\ord($fingerprint[0]) << 8) | \ord($fingerprint[1]);
    }

    private function getFingerPrintForEntry(string $entry): string
    {
        return hash('xxh128', $entry, true);
    }

    /**
     * @return resource
     */
    private function openFileHandleForWriting(string $filePath)
    {
        $handle = fopen($filePath, 'w');
        if (false === $handle) {
            throw new \RuntimeException(\sprintf('Cannot open "%s" for writing.', $filePath));
        }

        return $handle;
    }
}
