<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Catálogo de empresas emisoras.
 *
 * `updateOrCreate` por `nit` => idempotente: se puede correr N veces sin
 * duplicar y sin pisar el `id` ya asignado (importante porque
 * `purchases.company_id` / `product_outputs.company_id` apuntan a él).
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name' => 'AGRILOGISTIC URRAO SAS',
                'nit' => '901441688-7',
                'address' => 'PRJ La Dorada, Vda El Chuscal',
                'city' => 'Urrao, Antioquia',
                'phone' => '312 715 0757 / 604 617 4223',
                'email' => 'info@agrilogistic.co',
                'legal_rep' => 'Daniel Leandro Tuberquia Flórez',
                'tax_regime' => 'Responsable de IVA · Facturador electrónico · Obligado aduanero · Exportador',
                'ciiu' => '0161',
                'template' => 'agrilogistic',
                'is_default' => true,
                'status' => 'active',
            ],
            [
                'name' => 'AVOTERRA EXPORTS S.A.S',
                'nit' => '901987948-0',
                'address' => 'Calle 60B Sur # 44-100',
                'city' => 'Sabaneta, Antioquia',
                'phone' => '317 636 9688',
                'email' => 'contabilidad@avoterraexports.com',
                'legal_rep' => 'María de los Ángeles Rodríguez Rueda',
                'tax_regime' => 'Responsable de IVA · Facturador electrónico · Obligado aduanero · Exportador',
                'ciiu' => '4631',
                'template' => 'avoterra',
                'is_default' => false,
                'status' => 'active',
            ],
            [
                'name' => 'AGUACATES FLOREZ S.A.S.',
                'nit' => '901060667-7',
                'address' => 'Carrera 29 # 30-42',
                'city' => 'Urrao, Antioquia',
                'phone' => '604 408 8580',
                'email' => null,
                'legal_rep' => null,
                'tax_regime' => null,
                'ciiu' => null,
                'template' => 'florez',
                'is_default' => false,
                'status' => 'active',
            ],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(['nit' => $company['nit']], $company);
        }
    }
}
