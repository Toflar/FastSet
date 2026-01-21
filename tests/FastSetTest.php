<?php

declare(strict_types=1);

namespace Toflar\FastSet\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Toflar\FastSet\FastSet;
use Toflar\FastSet\SetBuilder;

class FastSetTest extends TestCase
{
    private string $testDirectory;

    protected function setUp(): void
    {
        $testDir = __DIR__.'/../var';
        $fs = new Filesystem();
        $fs->remove($testDir);
        $fs->mkdir($testDir);
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
        $this->assertTrue(filesize($this->testDirectory.'/terms_encoded.txt') < filesize(__DIR__.'/Fixtures/terms_de.txt'));

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
        $this->assertTrue(filesize($this->testDirectory.'/terms_encoded.txt') < filesize(__DIR__.'/Fixtures/terms_de.txt'));
        // File size of the gzipped file must be even smaller
        $this->assertTrue(filesize($this->testDirectory.'/terms_gzipped.gz') < filesize($this->testDirectory.'/terms_encoded.txt'));

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
}
