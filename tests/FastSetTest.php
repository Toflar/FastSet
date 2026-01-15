<?php

declare(strict_types=1);

namespace Toflar\FastSet\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Toflar\FastSet\FastSet;

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

    public function testFastSet(): void
    {
        $fastSet = new FastSet($this->testDirectory);
        $fastSet->build(__DIR__.'/Fixtures/terms_de.txt');

        $this->assertTrue($fastSet->has('mailadresse'));
        $this->assertTrue($fastSet->has('stolperfalle'));
        $this->assertTrue($fastSet->has('zytozym'));
        $this->assertTrue($fastSet->has('aab'));
        $this->assertFalse($fastSet->has('foobar'));
    }
}
