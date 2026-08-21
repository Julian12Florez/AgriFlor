<?php

namespace App\Support;

use App\Models\Company;

/**
 * Única fuente del membrete de cualquier PDF del sistema.
 *
 * Las plantillas Blade (orden de compra y las tres remisiones) reciben SOLO el
 * array que devuelve `resolve()`; ninguna toca el modelo `Company` ni
 * `config('app.company_*')` directamente. Así:
 *
 *  - Cambiar de dónde salen los datos se hace en un único archivo.
 *  - Una plantilla no puede filtrar `logo_base64` crudo ni disparar consultas.
 *  - Los documentos históricos (`company_id` NULL, anteriores al módulo de
 *    empresas) siguen imprimiéndose: se cae a la configuración de siempre.
 */
class CompanyInfo
{
    /**
     * @return array{
     *     name: string,
     *     nit: ?string,
     *     address: ?string,
     *     city: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     legalRep: ?string,
     *     taxRegime: ?string,
     *     ciiu: ?string,
     *     template: string,
     *     logoDataUri: ?string
     * }
     */
    public static function resolve(?Company $company): array
    {
        if (!$company) {
            return self::fromConfig();
        }

        return [
            'name' => $company->name,
            'nit' => $company->nit,
            'address' => $company->address,
            'city' => $company->city,
            'phone' => $company->phone,
            'email' => $company->email,
            'legalRep' => $company->legal_rep,
            'taxRegime' => $company->tax_regime,
            'ciiu' => $company->ciiu,
            'template' => $company->template ?: 'clasico',
            'logoDataUri' => self::logoDataUri($company),
        ];
    }

    /**
     * Membrete de respaldo para documentos sin empresa asociada.
     */
    private static function fromConfig(): array
    {
        return [
            'name' => config('app.company_name', 'AGRILOGISTIC URRAO SAS.'),
            'nit' => config('app.company_nit', ''),
            'address' => config('app.company_address', ''),
            'city' => null,
            'phone' => config('app.company_phone', ''),
            'email' => config('app.company_email', ''),
            'legalRep' => null,
            'taxRegime' => null,
            'ciiu' => null,
            'template' => 'clasico',
            'logoDataUri' => null,
        ];
    }

    /**
     * DomPDF sabe renderizar `<img src="data:image/png;base64,...">`, así que el
     * logo puede vivir en la BD sin pasar por disco ni por una URL pública.
     *
     * `logo_base64` está en `$hidden`, no en un cast diferido: sigue estando
     * disponible aquí, solo se excluye de la serialización JSON.
     */
    private static function logoDataUri(Company $company): ?string
    {
        $mime = $company->logo_mime;
        $base64 = $company->logo_base64;

        if (!$mime || !$base64) {
            return null;
        }

        return "data:{$mime};base64,{$base64}";
    }
}
