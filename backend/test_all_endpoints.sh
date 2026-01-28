#!/bin/bash

# Script to test all API endpoints
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYXBpL2F1dGgvbG9naW4iLCJpYXQiOjE3NjM0Mzk5MTgsImV4cCI6MTc2MzQ0MzUxOCwibmJmIjoxNzYzNDM5OTE4LCJqdGkiOiJpQjZNYVJ5M2FiRVhmdTdBIiwic3ViIjoiYTA2MTQwNDgtMzhjYy00ZDNlLWIzMzktOTk1OWYxZDQyYjQzIiwicHJ2IjoiMjNiZDVjODk0OWY2MDBhZGIzOWU3MDFjNDAwODcyZGI3YTU5NzZmNyIsInJvbGUiOiJhZG1pbiIsInN0YXR1cyI6ImFjdGl2ZSJ9.lzMgFa1bF4adlkkTOE4cTQtN7gl4SAgMxXmZ67rauF8"

echo "=== Testing All AgriFlor API Endpoints ==="
echo ""

test_endpoint() {
    local name=$1
    local endpoint=$2
    echo -n "Testing $name... "
    response=$(curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "http://localhost:8000/api/$endpoint")

    if echo "$response" | grep -q '"data"'; then
        count=$(echo "$response" | grep -o '"data":\[' | wc -l)
        if [ $count -gt 0 ]; then
            echo "✅ OK - Returns data array"
        else
            echo "✅ OK - Returns data object"
        fi
    elif echo "$response" | grep -q '"success":true'; then
        echo "✅ OK - Success response"
    else
        echo "❌ FAILED"
        echo "Response: $response" | head -c 200
        echo ""
    fi
}

# Master Data
echo "--- Master Data Endpoints ---"
test_endpoint "Products" "products"
test_endpoint "Brands" "brands"
test_endpoint "Suppliers" "suppliers"
test_endpoint "Locations" "locations"

# Technical Processes
echo ""
echo "--- Technical Processes ---"
test_endpoint "Technical Recipes" "technical-recipes"
test_endpoint "Technical Orders" "technical-orders"

# Warehouse Management
echo ""
echo "--- Warehouse Management ---"
test_endpoint "Purchases" "purchases"
test_endpoint "Product Outputs" "product-outputs"
test_endpoint "Receptions" "receptions"

# Inventory
echo ""
echo "--- Inventory ---"
test_endpoint "Inventory" "inventory"
test_endpoint "Inventory Movements" "inventory/movements"

# Alerts & Reports
echo ""
echo "--- Alerts & Dashboard ---"
test_endpoint "Alerts" "alerts"
test_endpoint "Dashboard Stats" "dashboard/statistics"
test_endpoint "Dashboard Inventory" "dashboard/inventory-by-category"
test_endpoint "Dashboard Activity" "dashboard/recent-activity"

# Users
echo ""
echo "--- Administration ---"
test_endpoint "Users" "users"

echo ""
echo "=== Test Complete ==="
