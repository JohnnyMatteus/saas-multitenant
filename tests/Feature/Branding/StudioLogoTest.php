<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Modules\Identity\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * O disco real (S3/LocalStack) é trocado por um disco falso: mesmo contrato,
 * nenhuma rede.
 */
final class StudioLogoTest extends TestCase
{
    use RefreshDatabase;

    private FilesystemAdapter $disk;

    private Tenant $lotus;

    private Tenant $equilibrio;

    protected function setUp(): void
    {
        parent::setUp();

        config(['studio_media.disk' => 'studio-media-test']);
        $this->disk = Storage::fake('studio-media-test');
        $this->lotus = $this->createTenant('estudio-lotus');
        $this->equilibrio = $this->createTenant('pilates-equilibrio');
    }

    /**
     * @return TestResponse<Response>
     */
    private function upload(Tenant $tenant, Role $role, UploadedFile $file): TestResponse
    {
        return $this->actingAsJwt($this->createUser($tenant, $role))
            ->post('/api/v1/studio/logo', ['logo' => $file], ['Accept' => 'application/json']);
    }

    #[Test]
    public function an_admin_uploads_the_logo_under_the_tenant_prefix(): void
    {
        $this->upload($this->lotus, Role::StudioAdmin, UploadedFile::fake()->image('logo.png', 200, 200))
            ->assertOk()
            ->assertJsonPath('data.logo_path', "tenants/{$this->lotus->id}/logo.png");

        $this->disk->assertExists("tenants/{$this->lotus->id}/logo.png");
    }

    #[Test]
    public function replacing_the_logo_with_another_format_removes_the_old_file(): void
    {
        $this->upload($this->lotus, Role::StudioAdmin, UploadedFile::fake()->image('logo.png'));
        $this->upload($this->lotus, Role::StudioAdmin, UploadedFile::fake()->image('logo.webp'))->assertOk();

        $this->disk->assertMissing("tenants/{$this->lotus->id}/logo.png");
        $this->disk->assertExists("tenants/{$this->lotus->id}/logo.webp");
    }

    #[Test]
    public function each_studio_only_gets_its_own_logo(): void
    {
        $this->upload($this->lotus, Role::StudioAdmin, UploadedFile::fake()->image('logo.png'));

        $this->actingAsJwt($this->createUser($this->lotus, Role::Student))
            ->get('/api/v1/studio/logo')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAsJwt($this->createUser($this->equilibrio, Role::Student))
            ->getJson('/api/v1/studio/logo')
            ->assertNotFound();
    }

    #[Test]
    public function only_the_studio_admin_changes_the_logo(): void
    {
        foreach ([Role::Instructor, Role::Student] as $role) {
            $this->upload($this->lotus, $role, UploadedFile::fake()->image('logo.png'))->assertForbidden();
        }

        $this->assertSame([], $this->disk->allFiles());
    }

    #[Test]
    public function svg_and_oversized_files_are_rejected(): void
    {
        $this->upload($this->lotus, Role::StudioAdmin, UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('logo');

        $this->upload($this->lotus, Role::StudioAdmin, UploadedFile::fake()->image('logo.png')->size(600))
            ->assertUnprocessable();
    }
}
