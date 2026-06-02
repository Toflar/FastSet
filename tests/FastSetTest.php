<?php

declare(strict_types=1);

namespace Toflar\FastSet\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Toflar\FastSet\FastSet;
use Toflar\FastSet\SetBuilder;

final class FastSetTest extends TestCase
{
    private string $testDirectory;

    protected function setUp(): void
    {
        $testDir = __DIR__.'/../var';
        $this->removeDirectory($testDir);
        mkdir($testDir, 0777, true);
        $this->testDirectory = $testDir;
    }

    public function testFastSetFailsWithWrongFileFormat(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid file format. Ensure you have built it using the SetBuilder class!');

        $fastSet = new FastSet($this->testDirectory);
        $fastSet->build(__DIR__.'/Fixtures/terms_de.txt');
    }

    #[TestWith(['xxh3'])]
    #[TestWith(['xxh128'])]
    public function testWorkingWithSetBuilderWithoutGzipCompression(string $hashAlgorithm): void
    {
        // Build a set without gzip but with our prefix algorithm
        SetBuilder::buildSet(__DIR__.'/Fixtures/terms_de.txt', $this->testDirectory.'/terms_encoded.txt');

        // File size of the encoded file must be definitely smaller
        $this->assertLessThan(filesize(__DIR__.'/Fixtures/terms_de.txt'), filesize($this->testDirectory.'/terms_encoded.txt'));

        $fastSet = new FastSet($this->testDirectory, $hashAlgorithm);
        $fastSet->build($this->testDirectory.'/terms_encoded.txt');

        $this->assertFastSetContents($fastSet);
    }

    #[TestWith(['xxh3'])]
    #[TestWith(['xxh128'])]
    public function testWorkingWithSetBuilderWithGzipCompression(string $hashAlgorithm): void
    {
        // Build a set without gzip but with our prefix algorithm
        SetBuilder::buildSet(__DIR__.'/Fixtures/terms_de.txt', $this->testDirectory.'/terms_encoded.txt');
        // Also build the gzipped one
        SetBuilder::buildSet(__DIR__.'/Fixtures/terms_de.txt', $this->testDirectory.'/terms_gzipped.gz');

        // File size of the encoded file must be definitely smaller
        $this->assertLessThan(filesize(__DIR__.'/Fixtures/terms_de.txt'), filesize($this->testDirectory.'/terms_encoded.txt'));
        // File size of the gzipped file must be even smaller
        $this->assertLessThan(filesize($this->testDirectory.'/terms_encoded.txt'), filesize($this->testDirectory.'/terms_gzipped.gz'));

        $fastSet = new FastSet($this->testDirectory, $hashAlgorithm);
        $fastSet->build($this->testDirectory.'/terms_gzipped.gz');

        $this->assertFastSetContents($fastSet);
    }

    private function assertFastSetContents(FastSet $fastSet): void
    {
        $this->assertTrue($fastSet->has('mailadresse'));
        $this->assertTrue($fastSet->has('stolperfalle'));
        $this->assertTrue($fastSet->has('zytozym'));
        $this->assertTrue($fastSet->has('aab'));
        $this->assertFalse($fastSet->has('foobar'));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
