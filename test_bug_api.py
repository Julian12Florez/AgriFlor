#!/usr/bin/env python3
"""
Script para probar el bug de pérdida de stock en transferencias parciales
Usa la API HTTP real para simular el flujo completo
"""

import requests
import json
import sys

BASE_URL = "http://localhost:8000/api"

def print_section(title):
    print(f"\n{'='*60}")
    print(f"  {title}")
    print(f"{'='*60}\n")

def print_success(msg):
    print(f"✅ {msg}")

def print_error(msg):
    print(f"❌ {msg}")

def print_info(msg):
    print(f"📊 {msg}")

# PASO 1: LOGIN
print_section("PASO 1: AUTENTICACIÓN")

login_data = {
    "email": "admin@agriflor.com",
    "password": "admin123"
}

response = requests.post(f"{BASE_URL}/auth/login", json=login_data)
if response.status_code != 200:
    print_error(f"Error en login: {response.text}")
    sys.exit(1)

token = response.json()["data"]["token"]
user_id = response.json()["data"]["user"]["id"]
print_success("Autenticación exitosa")
print(f"   Usuario ID: {user_id}")

headers = {"Authorization": f"Bearer {token}"}

# PASO 2: CONSULTAR STOCK INICIAL
print_section("PASO 2: STOCK INICIAL")

# IDs de la prueba (obtenidos previamente)
product_id = "a095ca83-34b8-4cb6-8c94-7d506fa73508"  # Glifosato
brand_id = "a095ca82-ee0c-4c87-8806-4cbb88ea73a1"    # Yara
origin_location_id = "a095d5a5-23f8-4f03-b5a0-252d8b51929c"  # BODEGA PRINCIPAL
destination_location_id = "a095ca83-0f19-47c0-bce5-095aa1407bb0"  # Bodega Central

# Consultar inventario inicial
response = requests.get(
    f"{BASE_URL}/inventory",
    headers=headers,
    params={
        "location_id": origin_location_id,
        "product_id": product_id
    }
)

if response.status_code != 200:
    print_error(f"Error consultando inventario: {response.text}")
    sys.exit(1)

inventory_data = response.json()["data"]
stock_inicial = sum([item["quantity"] for item in inventory_data])

print_info(f"Ubicación: BODEGA PRINCIPAL")
print_info(f"Producto: Glifosato")
print_info(f"Stock inicial: {stock_inicial} L")
print(f"\nLotes:")
for item in inventory_data:
    print(f"  - Lote {item['batch_number']}: {item['quantity']} L")

if stock_inicial < 60:
    print_error(f"No hay suficiente stock para la prueba (necesitamos al menos 60 L)")
    sys.exit(1)

# PASO 3: CREAR SALIDA DE TRANSFERENCIA (60 L)
print_section("PASO 3: CREAR SALIDA DE TRANSFERENCIA (60 L)")

# Usar ID directo del tipo de salida "transfer" (obtenido previamente)
transfer_type_id = "a624ac75-d9c7-4770-817d-630f4a04b407"

create_output_data = {
    "output_number": f"TEST-BUG-API",
    "output_date": "2026-01-19",
    "output_type_id": transfer_type_id,
    "origin_location_id": origin_location_id,
    "destination_location_id": destination_location_id,
    "responsible_user": user_id,
    "products": [
        {
            "product_id": product_id,
            "brand_id": brand_id,
            "quantity_requested": 60,
            "quantity_delivered": 60,
            "unit": "L"
        }
    ]
}

response = requests.post(f"{BASE_URL}/product-outputs", headers=headers, json=create_output_data)
if response.status_code != 201:
    print_error(f"Error creando salida ({response.status_code}): {response.text}")
    sys.exit(1)

response_data = response.json()
print(f"DEBUG - Respuesta completa: {json.dumps(response_data, indent=2)}")

if "data" not in response_data:
    print_error("La respuesta no contiene 'data'")
    sys.exit(1)

output = response_data["data"]
output_id = output["id"]
print_success(f"Salida creada: {output.get('output_number', 'N/A')}")
print(f"   ID: {output_id}")
print(f"   Cantidad: 60 L")
print(f"   Estado: {output['status']}")

# Verificar stock después de crear (no debe cambiar)
response = requests.get(
    f"{BASE_URL}/inventory",
    headers=headers,
    params={"location_id": origin_location_id, "product_id": product_id}
)
stock_despues_crear = sum([item["quantity"] for item in response.json()["data"]])

print_info(f"Stock después de crear salida: {stock_despues_crear} L")
print(f"   Esperado: {stock_inicial} L (sin cambios)")
if stock_despues_crear == stock_inicial:
    print_success("CORRECTO: Stock no cambió")
else:
    print_error(f"INCORRECTO: Stock cambió de {stock_inicial} L a {stock_despues_crear} L")

# PASO 4: APROBAR SALIDA (comentado porque el enum no tiene 'approved')
# print_section("PASO 4: APROBAR SALIDA")
# Por ahora saltamos la aprobación y vamos directo a recepción con status 'pending'

# PASO 5: RECEPCIÓN PARCIAL (30 L de 60 L)
print_section("PASO 4: RECEPCIÓN PARCIAL (30 L de 60 L)")

print(f"⚠️  CRÍTICO: Recepcionando solo 30 L, NO los 60 L completos")
print(f"   Esto simula una recepción parcial\n")

create_reception_data = {
    "source_id": output_id,
    "source_type": "output",
    "reception_date": "2026-01-19",
    "received_by": user_id,
    "items": [
        {
            "product_id": product_id,
            "quantity_received": 30,  # ⚠️ SOLO 30 L (PARCIAL)
            "condition": "good",
            "expiration_date": "2027-01-19"
        }
    ]
}

response = requests.post(
    f"{BASE_URL}/receptions/create-with-batch",
    headers=headers,
    json=create_reception_data
)

if response.status_code != 201:
    print_error(f"Error en recepción: {response.status_code}")
    print(f"Respuesta: {response.text}")
    sys.exit(1)

reception = response.json()["data"]
print_success(f"Recepción creada: {reception['reception_number']}")
print(f"   Cantidad esperada: 60 L")
print(f"   Cantidad recibida: 30 L ⚠️ PARCIAL")
print(f"   Cantidad pendiente: 30 L")

# PASO 6: VERIFICAR STOCK FINAL
print_section("PASO 5: VERIFICAR STOCK FINAL")

# Stock en origen
response_origin = requests.get(
    f"{BASE_URL}/inventory",
    headers=headers,
    params={"location_id": origin_location_id, "product_id": product_id}
)

if response_origin.status_code == 200:
    origin_data = response_origin.json()["data"]
    stock_final_origen = sum([item["quantity"] for item in origin_data])
else:
    stock_final_origen = 0
    origin_data = []

esperado_origen = stock_inicial - 30  # Debería ser inicial - 30

print(f"🏢 BODEGA PRINCIPAL (Origen):")
print_info(f"Stock final: {stock_final_origen} L")
print_info(f"Stock esperado: {esperado_origen} L")
print(f"   Lotes:")
for item in origin_data:
    print(f"     - Lote {item['batch_number']}: {item['quantity']} L")

if stock_final_origen == esperado_origen:
    print_success("CORRECTO: Se redujeron solo los 30 L recepcionados")
else:
    print_error(f"BUG CONFIRMADO: Stock incorrecto!")
    print(f"      Se esperaban {esperado_origen} L pero hay {stock_final_origen} L")
    diferencia = esperado_origen - stock_final_origen
    print(f"      Se perdieron {diferencia} L")

# Stock en destino
response_dest = requests.get(
    f"{BASE_URL}/inventory",
    headers=headers,
    params={"location_id": destination_location_id, "product_id": product_id}
)

if response_dest.status_code == 200:
    dest_data = response_dest.json()["data"]
    stock_final_destino = sum([item["quantity"] for item in dest_data])
else:
    stock_final_destino = 0

print(f"\n🏬 Bodega Central (Destino):")
print_info(f"Stock final: {stock_final_destino} L")
print_info(f"Stock esperado: 30 L")
if stock_final_destino == 30:
    print_success("CORRECTO")
else:
    print_error(f"INCORRECTO: Se esperaban 30 L pero hay {stock_final_destino} L")

# Total general
print(f"\n🔢 TOTAL GENERAL:")
total_general = stock_final_origen + stock_final_destino
print_info(f"Total en sistema: {total_general} L")
print_info(f"Total inicial: {stock_inicial} L")
print_info(f"Diferencia: {stock_inicial - total_general} L")

if total_general == stock_inicial:
    print_success("CONSERVACIÓN DE STOCK: Correcto")
else:
    print_error(f"PÉRDIDA DE STOCK: Se perdieron {stock_inicial - total_general} L")

print(f"\n{'='*60}")
print("  FIN DE LA PRUEBA")
print(f"{'='*60}\n")

# Resumen
if stock_final_origen == esperado_origen and stock_final_destino == 30 and total_general == stock_inicial:
    print("✅ RESULTADO: El sistema funciona correctamente")
    sys.exit(0)
else:
    print("❌ RESULTADO: BUG CONFIRMADO - El sistema pierde stock en recepciones parciales")
    sys.exit(1)
