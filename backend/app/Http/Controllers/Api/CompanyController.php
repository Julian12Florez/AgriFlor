<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Administración de las empresas emisoras de documentos.
 *
 * Ninguna respuesta JSON incluye `logo_base64` (ver CompanyResource): la imagen
 * se sube por `POST /companies/{id}/logo` y se consulta por
 * `GET /companies/{id}/logo`.
 */
class CompanyController extends Controller
{
    /**
     * Tamaño máximo del logo, en KB. Vive en la BD (columna longText) y viaja
     * en cada PDF; 512 KB alcanza de sobra para un membrete y evita inflar
     * tanto la tabla como el peso del documento generado.
     */
    private const LOGO_MAX_KB = 512;

    /**
     * Display a listing of companies
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Company::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nit', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('is_default')->orderBy('name');

        // `all=true` para poblar selects (crear compra / crear salida) sin paginar.
        if ($request->boolean('all')) {
            return CompanyResource::collection($query->get());
        }

        return CompanyResource::collection($query->paginate($request->get('per_page', 15)));
    }

    /**
     * Store a newly created company
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';
        $data['template'] = $data['template'] ?? 'clasico';

        $company = DB::transaction(function () use ($data) {
            $company = Company::create($data);
            $this->enforceSingleDefault($company);

            return $company;
        });

        return response()->json([
            'success' => true,
            'message' => 'Empresa creada exitosamente',
            'data' => new CompanyResource($company->refresh()),
        ], 201);
    }

    /**
     * Display the specified company
     */
    public function show(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
        ]);
    }

    /**
     * Update the specified company
     */
    public function update(UpdateCompanyRequest $request, string $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        $company = DB::transaction(function () use ($company, $request) {
            $company->update($request->validated());
            $this->enforceSingleDefault($company);

            return $company;
        });

        return response()->json([
            'success' => true,
            'message' => 'Empresa actualizada exitosamente',
            'data' => new CompanyResource($company->refresh()),
        ]);
    }

    /**
     * Subir/reemplazar el logo de la empresa.
     *
     * Se guarda en la BD (mime + base64) y NO en `storage/`: el backend corre en
     * un contenedor que se recrea en cada despliegue, así que un archivo en
     * disco se perdería y los PDFs saldrían sin membrete.
     */
    public function uploadLogo(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:' . self::LOGO_MAX_KB],
        ], [
            'logo.required' => 'El archivo del logo es requerido',
            'logo.image' => 'El archivo debe ser una imagen',
            'logo.mimes' => 'El logo debe ser png, jpg, jpeg, gif o webp',
            'logo.max' => 'El logo no puede exceder ' . self::LOGO_MAX_KB . ' KB',
        ]);

        $company = Company::findOrFail($id);
        $file = $request->file('logo');

        $company->update([
            'logo_mime' => $file->getMimeType(),
            'logo_base64' => base64_encode(file_get_contents($file->getRealPath())),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logo actualizado exitosamente',
            'data' => new CompanyResource($company->refresh()),
        ]);
    }

    /**
     * Devuelve los bytes del logo. Necesario porque el base64 nunca viaja en el
     * JSON: sin esto la UI de administración no podría previsualizarlo.
     */
    public function showLogo(string $id)
    {
        $company = Company::findOrFail($id);

        if (!$company->logo_base64) {
            return response()->json([
                'success' => false,
                'message' => 'La empresa no tiene logo cargado',
            ], 404);
        }

        return response(base64_decode($company->logo_base64), 200, [
            'Content-Type' => $company->logo_mime ?: 'application/octet-stream',
        ]);
    }

    /**
     * Quitar el logo (vuelve al membrete solo de texto).
     */
    public function deleteLogo(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->update(['logo_mime' => null, 'logo_base64' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Logo eliminado exitosamente',
            'data' => new CompanyResource($company->refresh()),
        ]);
    }

    /**
     * Solo puede haber una empresa por defecto: al marcar una, se desmarcan las
     * demás. Con varias marcadas, el selector de "empresa emisora" preseleccionaría
     * una cualquiera y las compras/salidas saldrían con el membrete equivocado.
     * La migración de backfill también resuelve la empresa histórica por esta
     * bandera, así que debe ser inequívoca.
     */
    private function enforceSingleDefault(Company $company): void
    {
        if (!$company->is_default) {
            return;
        }

        Company::where('id', '!=', $company->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
