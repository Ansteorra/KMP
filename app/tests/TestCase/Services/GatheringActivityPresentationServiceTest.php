<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\GatheringActivityPresentationService;
use App\Services\PublicAssetService;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\Configure;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\UnableToWriteFile;

class GatheringActivityPresentationServiceTest extends BaseTestCase
{
    private function image(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesetpixel($image, 0, 0, imagecolorallocatealpha($image, 255, 0, 0, 0));
        $stream = fopen('php://temp', 'w+b');
        imagepng($image, $stream);
        rewind($stream);

        return new UploadedFile($stream, null, UPLOAD_ERR_OK, 'icon.png', 'image/png');
    }

    public function testUploadPublishesImmutableAlphaPngAndMasksOnlyCompactRendering(): void
    {
        $path = TMP . 'tests/public-assets-' . bin2hex(random_bytes(5));
        Configure::write('PublicAssets.adapter', 'local');
        Configure::write('PublicAssets.localPath', $path);
        Configure::write('PublicAssets.baseUrl', 'https://assets.example.test');
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->newEntity(['name' => 'Uploaded activity']);
        $service = new GatheringActivityPresentationService();

        $result = $service->save($activity, $this->image(128, 128), false);
        $this->assertTrue($result->success);
        $this->assertSame('upload', $activity->icon_source);
        $this->assertNotEmpty($activity->icon_asset_key);
        $file = $path . '/' . $activity->icon_asset_key;
        $this->assertFileExists($file);
        $decoded = imagecreatefrompng($file);
        $this->assertSame(127, (imagecolorat($decoded, 1, 1) >> 24) & 127);
        $this->assertSame(0, (imagecolorat($decoded, 0, 0) >> 24) & 127);
        $this->assertStringStartsWith('https://assets.example.test/images/', PublicAssetService::url($activity->icon_asset_key));
        $key = $activity->icon_asset_key;
        $this->assertTrue($service->save($activity, $this->image(128, 128), false)->success);
        $this->assertSame($key, $activity->icon_asset_key);
        $this->assertTrue($service->save($activity, null, true)->success);
        $this->assertNull($activity->icon_asset_key);
        $this->assertFileExists($file, 'Cached pages retain access to immutable assets after replacement/removal.');
        unlink($file);
    }

    public function testOversizedAndNonRasterUploadsAreRejectedBeforeSaving(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->newEntity(['name' => 'Rejected upload']);
        $service = new GatheringActivityPresentationService();
        $this->assertFalse($service->save($activity, $this->image(129, 1), false)->success);
        $this->assertFalse($activities->exists(['name' => 'Rejected upload']));
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, '<svg onload="alert(1)"></svg>');
        rewind($stream);
        $other = $activities->newEntity(['name' => 'Not an image']);
        $this->assertFalse($service->save(
            $other,
            new UploadedFile($stream, null, UPLOAD_ERR_OK, 'icon.png', 'image/png'),
            false,
        )->success);
    }

    public function testColorsAndIconNamesAreValidated(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $bad = $activities->newEntity([
            'name' => 'Bad colors', 'foreground_color' => '#ffffff', 'background_color' => '#ffffff',
            'icon_name' => 'not-a-real-icon',
        ]);
        $this->assertArrayHasKey('background_color', $bad->getErrors());
        $this->assertArrayHasKey('icon_name', $bad->getErrors());
        $good = $activities->newEntity([
            'name' => 'Readable colors', 'foreground_color' => '#ffffff', 'background_color' => '#000000',
            'icon_name' => 'shield',
        ]);
        $this->assertEmpty($good->getErrors());
        $this->assertEqualsWithDelta(21.0, GatheringActivityPresentationService::contrastRatio('#ffffff', '#000000'), .001);

        $saved = $activities->saveOrFail($good);
        $activities->patchEntity($saved, ['foreground_color' => '#000000']);
        $this->assertFalse($activities->save($saved), 'Partial edits must validate the final pair of colors.');
    }

    public function testEveryOrmWriterSavesCanonicalChoicesAndPreservesExistingUnknownValues(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity([
            'name' => 'Canonical catalog choice', 'icon_name' => 'bi bi-bullseye text-muted',
            'icon_source' => 'bootstrap',
        ]));
        $this->assertSame('target', $activities->get($activity->id)->icon_name);
        $this->assertSame('catalog', $activity->icon_source);
        $default = $activities->saveOrFail($activities->newEntity(['name' => 'Canonical default']));
        $this->assertSame('activity', $activities->get($default->id)->icon_name);
        $this->assertSame('catalog', $default->icon_source);

        $activities->updateAll(['icon_name' => 'future-unknown-icon'], ['id' => $activity->id]);
        $activity = $activities->get($activity->id);
        $activities->patchEntity($activity, ['description' => 'An unrelated edit', 'icon_name' => 'future-unknown-icon']);
        $activities->saveOrFail($activity);
        $this->assertSame('future-unknown-icon', $activities->get($activity->id)->icon_name);
        $activities->patchEntity($activity, ['icon_name' => 'another-unknown-icon']);
        $this->assertNotEmpty($activity->getError('icon_name'));
    }

    public function testStorageFailureDoesNotSaveActivityOrExposeStorageDetails(): void
    {
        $assets = $this->createMock(PublicAssetService::class);
        $assets->method('publishPng')->willThrowException(UnableToWriteFile::atLocation('synthetic-icon.png'));
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->newEntity(['name' => 'Publication failed']);
        $result = (new GatheringActivityPresentationService($assets))->save($activity, $this->image(32, 32), false);
        $this->assertFalse($result->success);
        $this->assertArrayHasKey('icon_file', $activity->getErrors());
        $this->assertStringNotContainsString('synthetic-icon.png', $result->reason);
        $this->assertFalse($activities->exists(['name' => 'Publication failed']));
    }
}
