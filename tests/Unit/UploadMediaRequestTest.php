<?php

namespace Tests\Unit;

use App\Http\Requests\Gallery\UploadMediaRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UploadMediaRequestTest extends TestCase
{
    public function test_it_accepts_supported_gallery_images(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'wedding-photo.jpg',
            base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==',
                true,
            ),
        );

        $validator = Validator::make(
            ['file' => $file],
            ['file' => (new UploadMediaRequest)->rules()['file']],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_it_rejects_executable_uploads(): void
    {
        $file = UploadedFile::fake()->create(
            'malware.exe',
            100,
            'application/x-msdownload',
        );

        $validator = Validator::make(
            ['file' => $file],
            ['file' => (new UploadMediaRequest)->rules()['file']],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_it_rejects_svg_uploads_because_they_can_contain_active_content(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'unsafe.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $validator = Validator::make(
            ['file' => $file],
            ['file' => (new UploadMediaRequest)->rules()['file']],
        );

        $this->assertTrue($validator->fails());
    }
}
