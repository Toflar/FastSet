<?php

declare(strict_types=1);

namespace Toflar\FastSet;

final class FastSet
{
    private readonly string $hashesPath;

    private readonly string $indexPath;

    private readonly int $fingerprintByteLength;

    private readonly int $storedTailByteLength;

    private bool $isInitialized = false;

    private string $blob = '';

    /**
     * Prefix → start-offset lookup table for the sorted fingerprint blob.
     *
     * Fingerprints (16 bytes each) are stored sorted (binary order) in `hashes.bin`.
     * We bucket them by their first 2 bytes (a 16-bit prefix key in the range 0..65535).
     *
     * For each prefix key `p`, this array stores the starting index (not the byte offset)
     * of that bucket within the sorted fingerprint list. This defines the low of our
     * bucket. For the high, we need to take `p + 1`.
     *
     * This is why the table has 65537 entries: one extra "placeholder" entry at the end
     * containing the offset after the last fingerprint, so `p + 1` is always defined.
     *
     * Values are indices of 16-byte fingerprints (so byte position = index * 16).
     *
     * @var array<int, int>
     */
    private array $prefixOffsets = [];

    public function __construct(
        private readonly string $directory,
        private readonly string $hashAlgorithm = 'xxh64',
    ) {
        if (!is_dir($this->directory)) {
            throw new \InvalidArgumentException('Directory does not exist.');
        }

        if (!\in_array($this->hashAlgorithm, ['xxh64', 'xxh128'], true)) {
            throw new \LogicException(\sprintf('Unsupported hash algorithm "%s". Use "xxh64" or "xxh128".', $this->hashAlgorithm));
        }

        if (!\in_array($this->hashAlgorithm, hash_algos(), true)) {
            throw new \LogicException('Desired hash algorithm is not available.');
        }

        $this->fingerprintByteLength = 'xxh128' === $this->hashAlgorithm ? 16 : 8;
        $this->storedTailByteLength = $this->fingerprintByteLength - 2;

        $this->indexPath = $this->directory.'/index_'.$this->hashAlgorithm.'.bin';
        $this->hashesPath = $this->directory.'/hashes_'.$this->hashAlgorithm.'.bin';
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
        $startIndex = $this->prefixOffsets[$prefixKey];
        $endIndex = $this->prefixOffsets[$prefixKey + 1];

        // Empty bucket -> definitely not present
        if ($startIndex >= $endIndex) {
            return false;
        }

        $queryFingerprintTailBytes = substr($fingerprint, 2, $this->storedTailByteLength);

        // Binary search within that bucket
        $low = $startIndex;
        $high = $endIndex - 1;

        while ($low <= $high) {
            $mid = $low + $high >> 1;

            $middleTailByteOffset = $mid * $this->storedTailByteLength;
            $middleFingerprintTailBytes = substr($this->blob, $middleTailByteOffset, $this->storedTailByteLength);

            $cmp = strcmp($middleFingerprintTailBytes, $queryFingerprintTailBytes);
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
     * The source path must be a file built using the SetBuilder.
     */
    public function build(string $sourcePath): void
    {
        $fingerPrints = [];

        SetBuilder::readSet(
            $sourcePath,
            function (string $entry) use (&$fingerPrints): void {
                $fingerPrints[] = $this->getFingerPrintForEntry($entry);
            },
        );

        // Sort all fingerprints so we can binary-search them later
        sort($fingerPrints, SORT_STRING);

        // For each possible 2-byte prefix (0..65535), count how many fingerprints start with that prefix.
        // This lets us build a prefix -> [start, end] lookup table.
        $prefixCounts = array_fill(0, 65536, 0);

        $hashFile = $this->openFileHandleForWriting($this->hashesPath);

        foreach ($fingerPrints as $fingerprint) {
            $prefixKey = $this->getPrefixKey($fingerprint);
            ++$prefixCounts[$prefixKey];

            // Skip the first 2 bytes in our hashes.bin - they are already part of the index.bin
            // so we can save 2 bytes per fingerprint to reduce our memory footprint even more
            fwrite($hashFile, substr($fingerprint, 2, $this->storedTailByteLength));
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
        $this->prefixOffsets = array_values(unpack('V*', $indexBytes));
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
        return hash($this->hashAlgorithm, $entry, true);
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
