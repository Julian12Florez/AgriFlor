import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ConfigProvider } from 'antd';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { antdTheme } from './config/theme';

// Components
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import MainLayout from './components/layout/MainLayout';

// Pages
import Dashboard from './pages/Dashboard';
import Products from './pages/master/Products';
import PackagingUnits from './pages/master/PackagingUnits';
import BaseUnits from './pages/master/BaseUnits';
import Brands from './pages/master/Brands';
import Suppliers from './pages/master/Suppliers';
import Locations from './pages/master/Locations';
import OutputTypes from './pages/master/OutputTypes';
import Recipes from './pages/technical/Recipes';
import Orders from './pages/technical/Orders';
import Purchases from './pages/purchases/Purchases';
import Outputs from './pages/outputs/Outputs';
import Reception from './pages/reception/Reception';
import Inventory from './pages/inventory/Inventory';
import Reports from './pages/reports/Reports';
import Alerts from './pages/reports/Alerts';
import InventoryMovementsReport from './pages/reports/InventoryMovementsReport';
import ConsolidatedMovementsReport from './pages/reports/ConsolidatedMovementsReport';
import InventoryKardex from './pages/reports/InventoryKardex';
import InventoryAudit from './pages/reports/InventoryAudit';
import StockReport from './pages/reports/StockReport';
import ConsumptionReport from './pages/reports/ConsumptionReport';
import Users from './pages/admin/Users';
import Workers from './pages/liquidation/Workers';
import Tasks from './pages/liquidation/Tasks';
import DailyAssignments from './pages/liquidation/DailyAssignments';
import LiquidationReport from './pages/liquidation/LiquidationReport';
import LaborCostsReport from './pages/reports/LaborCostsReport';
import WorkerProductivityReport from './pages/reports/WorkerProductivityReport';
import TaskAnalysisReport from './pages/reports/TaskAnalysisReport';
import DeductionsBreakdownReport from './pages/reports/DeductionsBreakdownReport';
import PeriodComparisonReport from './pages/reports/PeriodComparisonReport';
import Login from './pages/auth/Login';
import ResetPassword from './pages/auth/ResetPassword';

// Create a client for React Query
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: 1,
    },
  },
});

// Main App component
const App: React.FC = () => {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ConfigProvider theme={antdTheme}>
          <Router>
            <Routes>
              {/* Public routes */}
              <Route path="/login" element={<Login />} />
              <Route path="/reset-password" element={<ResetPassword />} />

              {/* Protected routes - Dashboard (accessible to all authenticated users) */}
              <Route path="/dashboard" element={
                <ProtectedRoute authOnly>
                  <MainLayout>
                    <Dashboard />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Master Data Routes - module: 'master' */}
              <Route path="/master/products" element={
                <ProtectedRoute module="master" showAccessDenied>
                  <MainLayout>
                    <Products />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/master/packaging-units" element={
                <ProtectedRoute module="master" showAccessDenied>
                  <MainLayout>
                    <PackagingUnits />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/master/base-units" element={
                <ProtectedRoute module="master" showAccessDenied>
                  <MainLayout>
                    <BaseUnits />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/master/brands" element={
                <ProtectedRoute module="master" showAccessDenied>
                  <MainLayout>
                    <Brands />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/master/suppliers" element={
                <ProtectedRoute module="master" showAccessDenied>
                  <MainLayout>
                    <Suppliers />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/master/locations" element={
                <ProtectedRoute module="master" showAccessDenied>
                  <MainLayout>
                    <Locations />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/master/output-types" element={
                <ProtectedRoute module="master" showAccessDenied>
                  <MainLayout>
                    <OutputTypes />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Technical Routes - module: 'technical' */}
              <Route path="/technical/recipes" element={
                <ProtectedRoute module="technical" showAccessDenied>
                  <MainLayout>
                    <Recipes />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/technical/orders" element={
                <ProtectedRoute module="technical" showAccessDenied>
                  <MainLayout>
                    <Orders />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Purchases Routes - module: 'purchases' */}
              <Route path="/purchases" element={
                <ProtectedRoute module="purchases" showAccessDenied>
                  <MainLayout>
                    <Purchases />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Outputs Routes - module: 'outputs' */}
              <Route path="/outputs" element={
                <ProtectedRoute module="outputs" showAccessDenied>
                  <MainLayout>
                    <Outputs />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Reception Routes - module: 'reception' */}
              <Route path="/reception" element={
                <ProtectedRoute module="reception" showAccessDenied>
                  <MainLayout>
                    <Reception />
                  </MainLayout>
                </ProtectedRoute>
              } />


              {/* Inventory Routes - module: 'inventory' */}
              <Route path="/inventory" element={
                <ProtectedRoute module="inventory" showAccessDenied>
                  <MainLayout>
                    <Inventory />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Reports Routes - module: 'reports' */}
              <Route path="/alerts" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <Alerts />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <Reports />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/stock" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <StockReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/consumption" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <ConsumptionReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/movements" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <InventoryMovementsReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/consolidated" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <ConsolidatedMovementsReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/kardex" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <InventoryKardex />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/audit" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <InventoryAudit />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Admin Routes - module: 'admin' */}
              <Route path="/admin/users" element={
                <ProtectedRoute module="admin" showAccessDenied>
                  <MainLayout>
                    <Users />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Liquidation Routes - module: 'liquidation' */}
              <Route path="/liquidation/workers" element={
                <ProtectedRoute module="liquidation" showAccessDenied>
                  <MainLayout>
                    <Workers />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/liquidation/tasks" element={
                <ProtectedRoute module="liquidation" showAccessDenied>
                  <MainLayout>
                    <Tasks />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/liquidation/assignments" element={
                <ProtectedRoute module="liquidation" showAccessDenied>
                  <MainLayout>
                    <DailyAssignments />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/liquidation/report" element={
                <ProtectedRoute module="liquidation" showAccessDenied>
                  <MainLayout>
                    <LiquidationReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Liquidation Analytics Reports - accessible via reports module */}
              <Route path="/reports/labor-costs" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <LaborCostsReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/worker-productivity" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <WorkerProductivityReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/task-analysis" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <TaskAnalysisReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/deductions-breakdown" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <DeductionsBreakdownReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              <Route path="/reports/period-comparison" element={
                <ProtectedRoute module="reports" showAccessDenied>
                  <MainLayout>
                    <PeriodComparisonReport />
                  </MainLayout>
                </ProtectedRoute>
              } />

              {/* Redirects */}
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
          </Router>
        </ConfigProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
};

export default App;
