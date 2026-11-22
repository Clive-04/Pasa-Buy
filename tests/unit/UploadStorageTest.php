<?php

use App\Libraries\UploadStorage;
use App\Services\ServiceException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Validation uses the real file bytes (finfo), so these tests write actual
 * files and wrap them in UploadedFile exactly as an HTTP upload would be.
 *
 * @internal
 */
final class UploadStorageTest extends CIUnitTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function upload(string $bytes, string $clientName, ?int $size = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pbup');
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        // Only the "was this really an HTTP upload" check is replaced; every
        // content, extension and size check in UploadStorage still runs.
        return new class ($path, $clientName, 'application/octet-stream', $size ?? strlen($bytes), UPLOAD_ERR_OK) extends UploadedFile {
            public function isValid(): bool
            {
                return $this->getError() === UPLOAD_ERR_OK;
            }
        };
    }

    private function png(int $width = 2, int $height = 2): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public function testAcceptsARealPng(): void
    {
        $result = UploadStorage::validate($this->upload($this->png(), 'photo.png'), UploadStorage::IMAGE_TYPES, UploadStorage::PROFILE_MAX_BYTES);

        $this->assertSame('png', $result['extension']);
        $this->assertSame('image/png', $result['mime']);
        $this->assertTrue($result['isImage']);
    }

    public function testRejectsPhpCodeDisguisedAsAnImage(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('does not match');

        // Harmless PHP payload (a real webshell string gets quarantined by
        // antivirus before the test can read it).
        UploadStorage::validate($this->upload("<?php echo 'not an image'; ?>\n", 'shell.png'), UploadStorage::IMAGE_TYPES, UploadStorage::PROFILE_MAX_BYTES);
    }

    public function testRejectsDisallowedExtensionEvenWithImageContent(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Unsupported file type');

        UploadStorage::validate($this->upload($this->png(), 'photo.php'), UploadStorage::IMAGE_TYPES, UploadStorage::PROFILE_MAX_BYTES);
    }

    public function testRejectsSvgWhichCanCarryScript(): void
    {
        $this->expectException(ServiceException::class);

        UploadStorage::validate(
            $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.svg'),
            UploadStorage::IMAGE_TYPES + UploadStorage::DOCUMENT_TYPES,
            UploadStorage::MESSAGE_MAX_BYTES
        );
    }

    public function testRejectsOversizedFiles(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('must not exceed 2 MB');

        UploadStorage::validate($this->upload($this->png(), 'big.png', UploadStorage::PROFILE_MAX_BYTES + 1), UploadStorage::IMAGE_TYPES, UploadStorage::PROFILE_MAX_BYTES);
    }

    public function testRejectsEmptyFiles(): void
    {
        $this->expectException(ServiceException::class);

        UploadStorage::validate($this->upload('', 'empty.png', 0), UploadStorage::IMAGE_TYPES, UploadStorage::PROFILE_MAX_BYTES);
    }

    public function testAcceptsPdfForMessages(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

        $result = UploadStorage::validate($this->upload($pdf, 'receipt.pdf'), UploadStorage::IMAGE_TYPES + UploadStorage::DOCUMENT_TYPES, UploadStorage::MESSAGE_MAX_BYTES);

        $this->assertSame('application/pdf', $result['mime']);
        $this->assertFalse($result['isImage']);
    }

    public function testPathRejectsTraversalAndUnknownNames(): void
    {
        $this->assertNull(UploadStorage::path(UploadStorage::PROFILE_PHOTOS, '../../.env'));
        $this->assertNull(UploadStorage::path(UploadStorage::PROFILE_PHOTOS, '..\\x.jpg'));
        $this->assertNull(UploadStorage::path(UploadStorage::PROFILE_PHOTOS, 'does-not-exist.jpg'));
        $this->assertNull(UploadStorage::path(UploadStorage::PROFILE_PHOTOS, null));
    }

    public function testStorageIsOutsideThePublicDocumentRoot(): void
    {
        $dir = realpath(WRITEPATH) . DIRECTORY_SEPARATOR;

        $this->assertStringStartsWith(WRITEPATH, UploadStorage::directory(UploadStorage::MESSAGE_FILES));
        $this->assertStringStartsNotWith(realpath(FCPATH), $dir);
    }
}
