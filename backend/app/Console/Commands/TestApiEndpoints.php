<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestApiEndpoints extends Command
{
    protected $signature = 'api:test {--endpoint=all}';
    protected $description = 'Prueba los endpoints principales de la API AgriFlor';

    private $baseUrl;
    private $token;

    public function handle()
    {
        $this->baseUrl = config('app.url');
        $this->info("🧪 Iniciando pruebas de API AgriFlor");
        $this->info("URL Base: {$this->baseUrl}");
        $this->newLine();

        // Test 1: Login
        $this->info("1️⃣  Probando Login...");
        if (!$this->testLogin()) {
            $this->error("❌ Login falló. Deteniendo pruebas.");
            return 1;
        }

        // Test 2: Get authenticated user
        $this->info("2️⃣  Probando /api/auth/me...");
        $this->testAuthMe();

        // Test 3: List users
        $this->info("3️⃣  Probando /api/users...");
        $this->testUsers();

        // Test 4: List brands
        $this->info("4️⃣  Probando /api/brands...");
        $this->testBrands();

        // Test 5: List products
        $this->info("5️⃣  Probando /api/products...");
        $this->testProducts();

        // Test 6: List locations
        $this->info("6️⃣  Probando /api/locations...");
        $this->testLocations();

        // Test 7: List suppliers
        $this->info("7️⃣  Probando /api/suppliers...");
        $this->testSuppliers();

        // Test 8: List packaging units
        $this->info("8️⃣  Probando /api/packaging-units...");
        $this->testPackagingUnits();

        // Test 9: List inventory
        $this->info("9️⃣  Probando /api/inventory...");
        $this->testInventory();

        // Test 10: List alerts
        $this->info("🔟 Probando /api/alerts...");
        $this->testAlerts();

        $this->newLine();
        $this->info("✅ Pruebas completadas exitosamente!");

        return 0;
    }

    private function testLogin(): bool
    {
        try {
            $response = Http::post("{$this->baseUrl}/api/auth/login", [
                'email' => 'admin@agriflor.com',
                'password' => 'Admin123!'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->token = $data['data']['token'] ?? null;

                if ($this->token) {
                    $this->info("   ✓ Login exitoso");
                    $this->info("   Token: " . substr($this->token, 0, 20) . "...");
                    $user = $data['data']['user'] ?? [];
                    $this->info("   Usuario: {$user['name']} ({$user['email']})");
                    $this->info("   Rol: {$user['role']}");
                    return true;
                }
            }

            $this->error("   ✗ Login falló: " . $response->body());
            return false;
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
            return false;
        }
    }

    private function testAuthMe()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/auth/me");

            if ($response->successful()) {
                $user = $response->json()['data'] ?? [];
                $this->info("   ✓ Usuario autenticado: {$user['name']}");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function testUsers()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/users");

            if ($response->successful()) {
                $data = $response->json();
                $count = count($data['data'] ?? []);
                $this->info("   ✓ {$count} usuarios encontrados");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function testBrands()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/brands");

            if ($response->successful()) {
                $data = $response->json();
                $count = count($data['data'] ?? []);
                $this->info("   ✓ {$count} marcas encontradas");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function testProducts()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/products");

            if ($response->successful()) {
                $data = $response->json();
                $count = count($data['data'] ?? []);
                $this->info("   ✓ {$count} productos encontrados");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function testLocations()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/locations");

            if ($response->successful()) {
                $data = $response->json();
                $count = count($data['data'] ?? []);
                $this->info("   ✓ {$count} ubicaciones encontradas");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function testSuppliers()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/suppliers");

            if ($response->successful()) {
                $data = $response->json();
                $count = count($data['data'] ?? []);
                $this->info("   ✓ {$count} proveedores encontrados");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function testPackagingUnits()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/packaging-units");

            if ($response->successful()) {
                $data = $response->json();
                $count = count($data['data'] ?? []);
                $this->info("   ✓ {$count} unidades de empaque encontradas");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function testInventory()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/inventory");

            if ($response->successful()) {
                $data = $response->json();
                $count = count($data['data'] ?? []);
                $this->info("   ✓ {$count} registros de inventario encontrados");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }

    private function testAlerts()
    {
        try {
            $response = Http::withToken($this->token)->get("{$this->baseUrl}/api/alerts");

            if ($response->successful()) {
                $data = $response->json();
                $count = count($data['data'] ?? []);
                $this->info("   ✓ {$count} alertas encontradas");
            } else {
                $this->warn("   ✗ Falló: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }
}
