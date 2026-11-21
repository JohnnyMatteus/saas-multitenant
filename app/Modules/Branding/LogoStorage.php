<?php

declare(strict_types=1);

namespace App\Modules\Branding;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adaptador de armazenamento dos logos sobre um disco do Laravel (local ou
 * S3/LocalStack). Todo caminho nasce aqui, prefixado pelo id do tenant, então
 * nenhum chamador consegue montar um caminho de outro estúdio.
 */
final readonly class LogoStorage
{
    public function __construct(private Filesystem $disk) {}

    public function store(Tenant $tenant, UploadedFile $file): string
    {
        $path = $this->directory($tenant).'/logo.'.$file->extension();
        $this->disk->putFileAs($this->directory($tenant), $file, basename($path));

        // Troca de formato (png -> webp) não deixa o logo antigo órfão.
        if ($tenant->logo_path !== null && $tenant->logo_path !== $path) {
            $this->disk->delete($tenant->logo_path);
        }

        return $path;
    }

    public function response(Tenant $tenant): ?StreamedResponse
    {
        $path = $tenant->logo_path;

        if ($path === null || ! str_starts_with($path, $this->directory($tenant).'/') || ! $this->disk->exists($path)) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = $this->disk;

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    private function directory(Tenant $tenant): string
    {
        return "tenants/{$tenant->id}";
    }
}
