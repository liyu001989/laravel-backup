<?php

namespace Spatie\Backup\Test\Integration;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\Events\CleanupWasSuccessful;

class CleanupCommandTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2016, 1, 1, 22, 00, 00));

        $this->testHelper->initializeTempDirectory();

        $app = $this->app;

        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => $this->testHelper->getTempDirectory(),
        ]);
    }

    /** @test */
    public function it_can_remove_old_backups_until_using_less_than_maximum_storage()
    {
        $this->app['config']->set('backup.cleanup.defaultStrategy.deleteOldestBackupsWhenUsingMoreMegabytesThan', 2);

        $this->testHelper->createTempFile1Mb('mysite/test1.zip', Carbon::now()->subDays(1));
        $this->testHelper->createTempFile1Mb('mysite/test2.zip', Carbon::now()->subDays(2));
        $this->testHelper->createTempFile1Mb('mysite/test3.zip', Carbon::now()->subDays(3));
        $this->testHelper->createTempFile1Mb('mysite/test4.zip', Carbon::now()->subDays(4));

        Artisan::call('backup:clean');

        $this->assertTempFilesExist([
            'mysite/test1.zip',
            'mysite/test2.zip',
        ]);

        $this->assertTempFilesNotExist([
            'mysite/test3.zip',
            'mysite/test4.zip',
        ]);
    }

    /** @test */
    public function it_can_remove_old_backups_from_the_backup_directory()
    {
        $allBackups = collect();

        collect(range(0, 1000))->each(function (int $numberOfDays) use ($allBackups) {
            $date = Carbon::now()->subDays($numberOfDays);

            $allBackups->push($this->testHelper->createTempFileWithAge("mysite/test_{$date->format('Ymd')}_first.zip", $date));
            $allBackups->push($this->testHelper->createTempFileWithAge("mysite/test_{$date->format('Ymd')}_second.zip", $date->addHour(2)));
        });

        $remainingBackups = collect([
            'mysite/test_20131231_first.zip',
            'mysite/test_20141231_first.zip',
            'mysite/test_20150630_first.zip',
            'mysite/test_20150731_first.zip',
            'mysite/test_20150831_first.zip',
            'mysite/test_20150930_first.zip',
            'mysite/test_20151018_first.zip',
            'mysite/test_20151025_first.zip',
            'mysite/test_20151101_first.zip',
            'mysite/test_20151108_first.zip',
            'mysite/test_20151115_first.zip',
            'mysite/test_20151122_first.zip',
            'mysite/test_20151129_first.zip',
            'mysite/test_20151206_first.zip',
            'mysite/test_20151209_first.zip',
            'mysite/test_20151210_first.zip',
            'mysite/test_20151211_first.zip',
            'mysite/test_20151212_first.zip',
            'mysite/test_20151213_first.zip',
            'mysite/test_20151214_first.zip',
            'mysite/test_20151215_first.zip',
            'mysite/test_20151216_first.zip',
            'mysite/test_20151217_first.zip',
            'mysite/test_20151218_first.zip',
            'mysite/test_20151219_first.zip',
            'mysite/test_20151220_first.zip',
            'mysite/test_20151221_first.zip',
            'mysite/test_20151222_first.zip',
            'mysite/test_20151223_first.zip',
            'mysite/test_20151224_first.zip',
            'mysite/test_20151225_second.zip',
            'mysite/test_20151225_first.zip',
            'mysite/test_20151226_second.zip',
            'mysite/test_20151226_first.zip',
            'mysite/test_20151226_first.zip',
            'mysite/test_20151227_second.zip',
            'mysite/test_20151227_first.zip',
            'mysite/test_20151228_second.zip',
            'mysite/test_20151228_first.zip',
            'mysite/test_20151229_second.zip',
            'mysite/test_20151229_first.zip',
            'mysite/test_20151230_second.zip',
            'mysite/test_20151230_first.zip',
            'mysite/test_20151231_second.zip',
            'mysite/test_20151231_first.zip',
            'mysite/test_20160101_second.zip',
            'mysite/test_20160101_first.zip',
        ]);

        Artisan::call('backup:clean');

        $this->assertTempFilesExist($remainingBackups->toArray());

        $deletedBackups = $allBackups
            ->map(function ($fullPath) {
                $tempPath = str_replace($this->testHelper->getTempDirectory().'/', '', $fullPath);

                return $tempPath;
            })
        ->reject(function (string $deletedPath) use ($remainingBackups) {
            return $remainingBackups->contains($deletedPath);
        });

        $this->assertTempFilesNotExist($deletedBackups->toArray());
    }

    /** @test */
    public function it_will_leave_non_zip_files_alone()
    {
        $this->testHelper->createTempFileWithAge('mysite/test1.txt', Carbon::now()->subDays(1));
        $this->testHelper->createTempFileWithAge('mysite/test2.txt', Carbon::now()->subDays(2));
        $this->testHelper->createTempFileWithAge('mysite/test1000.txt', Carbon::now()->subDays(1000));
        $this->testHelper->createTempFileWithAge('mysite/test2000.txt', Carbon::now()->subDays(2000));

        Artisan::call('backup:clean');

        $this->assertTempFilesExist([
            'mysite/test1.txt',
            'mysite/test2.txt',
            'mysite/test1000.txt',
            'mysite/test2000.txt',
        ]);
    }

    /** @test */
    public function it_will_never_delete_the_newest_backup()
    {
        foreach (range(5, 10) as $numberOfYears) {
            $date = Carbon::now()->subYears($numberOfYears);

            $this->testHelper->createTempFileWithAge("mysite/test_{$date->format('Ymd')}.zip", $date);
        }

        Artisan::call('backup:clean');

        $this->assertTempFilesExist(['mysite/test_20110101.zip']);

        $this->assertTempFilesNotExist([
            'mysite/test_20060101.zip',
            'mysite/test_20070101.zip',
            'mysite/test_20080101.zip',
            'mysite/test_20090101.zip',
            'mysite/test_200100101.zip',
        ]);
    }

    /** @test */
    public function it_should_trigger_the_cleanup_successful_event()
    {
        $this->expectsEvents(CleanupWasSuccessful::class);

        $this->testHelper->createTempFileWithAge('mysite/test1.txt', Carbon::now()->subDays(1));
        $this->testHelper->createTempFileWithAge('mysite/test2.txt', Carbon::now()->subDays(2));
        $this->testHelper->createTempFileWithAge('mysite/test1000.txt', Carbon::now()->subDays(1000));
        $this->testHelper->createTempFileWithAge('mysite/test2000.txt', Carbon::now()->subDays(2000));

        Artisan::call('backup:clean');

        $this->assertTempFilesExist([
            'mysite/test1.txt',
            'mysite/test2.txt',
            'mysite/test1000.txt',
            'mysite/test2000.txt',
        ]);
    }

    /** @test */
    public function it_should_omit_the_cleanup_successful_event()
    {
        $this->doesntExpectEvents(CleanupWasSuccessful::class);

        $this->testHelper->createTempFileWithAge('mysite/test1.txt', Carbon::now()->subDays(1));
        $this->testHelper->createTempFileWithAge('mysite/test2.txt', Carbon::now()->subDays(2));
        $this->testHelper->createTempFileWithAge('mysite/test1000.txt', Carbon::now()->subDays(1000));
        $this->testHelper->createTempFileWithAge('mysite/test2000.txt', Carbon::now()->subDays(2000));

        Artisan::call('backup:clean', ['--disable-notifications' => true]);

        $this->assertTempFilesExist([
            'mysite/test1.txt',
            'mysite/test2.txt',
            'mysite/test1000.txt',
            'mysite/test2000.txt',
        ]);
    }
}
