import type { RouteObject } from 'react-router';
import { Navigate, createBrowserRouter } from 'react-router';

import { AccountsPayablePage } from './accounting/pages/accounts-payable-page';
import { AccountsReceivablePage } from './accounting/pages/accounts-receivable-page';
import { CreateAdvancePage } from './advances/pages/create-advance-page';
import { CrossAdvancePage } from './advances/pages/cross-advance-page';
import { ChartOfAccountsPage } from './accounting/pages/chart-of-accounts-page';
import { CostCentersPage } from './accounting/pages/cost-centers-page';
import { CreateAccountPage } from './accounting/pages/create-account-page';
import { CreateCostCenterPage } from './accounting/pages/create-cost-center-page';
import { CreateVoucherPage } from './accounting/pages/create-voucher-page';
import { CreateVoucherTypePage } from './accounting/pages/create-voucher-type-page';
import { FinancialStatementsPage } from './accounting/pages/financial-statements-page';
import { ImportsPage } from './imports/pages/imports-page';
import { NewImportPage } from './imports/pages/new-import-page';
import { ImportDetailPage } from './imports/pages/import-detail-page';
import { JournalPage } from './accounting/pages/journal-page';
import { LedgerPage } from './accounting/pages/ledger-page';
import { ReceiptsListPage } from './receipts/pages/receipts-list-page';
import { BankAccountsPage } from './reconciliation/pages/bank-accounts-page';
import { BankStatementsPage } from './reconciliation/pages/bank-statements-page';
import { CreateBankAccountPage } from './reconciliation/pages/create-bank-account-page';
import { ReconciliationMatchingPage } from './reconciliation/pages/reconciliation-matching-page';
import { PeriodsPage } from './accounting/pages/periods-page';
import { PostingRulesPage } from './accounting/pages/posting-rules-page';
import { TrialBalancePage } from './accounting/pages/trial-balance-page';
import { AuxiliariesPage } from './accounting/pages/auxiliaries-page';
import { OpeningReconciliationPage } from './accounting/pages/opening-reconciliation-page';
import { VoucherDetailPage } from './accounting/pages/voucher-detail-page';
import { VoucherTypesPage } from './accounting/pages/voucher-types-page';
import { VouchersListPage } from './accounting/pages/vouchers-list-page';
import { AuditLogPage } from './audit/pages/audit-log-page';
import { AuditRecoveryPage } from './audit/pages/audit-recovery-page';
import { BackupsPage } from './backups/pages/backups-page';
import { CalendarPage } from './calendar/pages/calendar-page';
import { CreateEconomicActivityPage } from './expenses/pages/create-economic-activity-page';
import { CreateExpensePage } from './expenses/pages/create-expense-page';
import { CreateExpenseTypePage } from './expenses/pages/create-expense-type-page';
import { CreateMunicipalityPage } from './geography/pages/create-municipality-page';
import { CreateParametrizationPage } from './expenses/pages/create-parametrization-page';
import { CreateReteicaTariffPage } from './expenses/pages/create-reteica-tariff-page';
import { CreateUvtValuePage } from './expenses/pages/create-uvt-value-page';
import { EditUvtValuePage } from './expenses/pages/edit-uvt-value-page';
import { ExpenseCostCenterReportPage } from './expenses/pages/expense-cost-center-report-page';
import { EconomicActivitiesPage } from './expenses/pages/economic-activities-page';
import { ExpenseDetailPage } from './expenses/pages/expense-detail-page';
import { ExpenseTypesPage } from './expenses/pages/expense-types-page';
import { ExpensesListPage } from './expenses/pages/expenses-list-page';
import { CreateInvoicePage } from './invoicing/pages/create-invoice-page';
import { CreateMultiplePaymentPage } from './invoicing/pages/create-multiple-payment-page';
import { InvoiceDetailPage } from './invoicing/pages/invoice-detail-page';
import { InvoiceSequencesPage } from './invoicing/pages/invoice-sequences-page';
import { FacturasListPage } from './invoicing/pages/facturas-list-page';
import { NotasCreditoListPage } from './invoicing/pages/notas-credito-list-page';
import { NotasDebitoListPage } from './invoicing/pages/notas-debito-list-page';
import { MunicipalitiesAdminPage } from './geography/pages/municipalities-admin-page';
import { ParametrizationsPage } from './expenses/pages/parametrizations-page';
import { ReteicaTariffsPage } from './expenses/pages/reteica-tariffs-page';
import { UvtValuesPage } from './expenses/pages/uvt-values-page';
import { CreateRolePage } from './auth/pages/create-role-page';
import { CreateUserPage } from './auth/pages/create-user-page';
import { EditRolePage } from './auth/pages/edit-role-page';
import { EditUserPage } from './auth/pages/edit-user-page';
import { LoginPage } from './auth/pages/login-page';
import { RolesListPage } from './auth/pages/roles-list-page';
import { UsersListPage } from './auth/pages/users-list-page';
import { RequireAdmin } from './auth/components/require-admin';
import { RequireAuditAccess } from './auth/components/require-audit-access';
import { RequireAuth } from './auth/components/require-auth';
import { RequireModulePermission } from './auth/components/require-module-permission';
import { RequireSuperAdmin } from './auth/components/require-super-admin';
import { ClientDetailPage } from './clients/pages/client-detail-page';
import { ClientsListPage } from './clients/pages/clients-list-page';
import { CreateClientPage } from './clients/pages/create-client-page';
import { EditClientPage } from './clients/pages/edit-client-page';
import { RequireCompany } from './company/components/require-company';
import { CreateEmployerPage } from './employers/pages/create-employer-page';
import { EditEmployerPage } from './employers/pages/edit-employer-page';
import { EmployersListPage } from './employers/pages/employers-list-page';
import { AccountStatementPage } from './credits/pages/account-statement-page';
import { EditCreditPage } from './credits/pages/edit-credit-page';
import { CreateCreditPage } from './credits/pages/create-credit-page';
import { CreditDetailPage } from './credits/pages/credit-detail-page';
import { CreditsListPage } from './credits/pages/credits-list-page';
import { CompaniesListPage } from './company/pages/companies-list-page';
import { CreateCompanyPage } from './company/pages/create-company-page';
import { EditCompanyPage } from './company/pages/edit-company-page';
import { EmailSettingsPage } from './email/pages/email-settings-page';
import { EmailTemplatesPage } from './email/pages/email-templates-page';
import { CreateEmailTemplatePage } from './email/pages/create-email-template-page';
import { EditEmailTemplatePage } from './email/pages/edit-email-template-page';
import { AppLayout } from './layout/components/app-layout';
import { ErrorPage } from './layout/pages/error-page';
import { HomePage } from './layout/pages/home-page';
import { CreditPaymentsPage } from './payments/pages/credit-payments-page';
import { PaymentsListPage } from './payments/pages/payments-list-page';
import { RegisterPaymentPage } from './payments/pages/register-payment-page';
import { SearchCreditsPage } from './payments/pages/search-credits-page';
import { SettleCreditPage } from './payments/pages/settle-credit-page';
import { EmployerReportPage } from './portfolio/pages/employer-report-page';
import { PortfolioPage } from './portfolio/pages/portfolio-page';
import { GoalsPage } from './goals/pages/goals-page';
import { GoalSettingsPage } from './goals/pages/goal-settings-page';
import { CreateThirdPartyPage } from './third-parties/pages/create-third-party-page';
import { EditThirdPartyPage } from './third-parties/pages/edit-third-party-page';
import { ThirdPartiesListPage } from './third-parties/pages/third-parties-list-page';

const routes: RouteObject[] = [
    {
        errorElement: <ErrorPage />,
        children: [
            { path: '/login', element: <LoginPage /> },
            {
                element: (
                    <RequireAuth>
                        <RequireCompany>
                            <AppLayout />
                        </RequireCompany>
                    </RequireAuth>
                ),
                children: [
                    { index: true, element: <HomePage /> },
                    {
                        path: 'metas',
                        element: (
                            <RequireModulePermission modulo="metas" accion="listar">
                                <GoalsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'metas/configuracion',
                        element: (
                            <RequireModulePermission modulo="metas" accion="configurar">
                                <GoalSettingsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'calendario',
                        element: (
                            <RequireModulePermission modulo="calendario" accion="listar">
                                <CalendarPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'clientes',
                        element: (
                            <RequireModulePermission modulo="clientes" accion="listar">
                                <ClientsListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'clientes/crear',
                        element: (
                            <RequireModulePermission modulo="clientes" accion="crear">
                                <CreateClientPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'clientes/:id/editar',
                        element: (
                            <RequireModulePermission modulo="clientes" accion="editar">
                                <EditClientPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'clientes/:id',
                        element: (
                            <RequireModulePermission modulo="clientes" accion="ver">
                                <ClientDetailPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'empleadores',
                        element: (
                            <RequireModulePermission modulo="clientes" accion="listar">
                                <EmployersListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'empleadores/crear',
                        element: (
                            <RequireModulePermission modulo="clientes" accion="crear">
                                <CreateEmployerPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'empleadores/:id/editar',
                        element: (
                            <RequireModulePermission modulo="clientes" accion="editar">
                                <EditEmployerPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'creditos',
                        element: (
                            <RequireModulePermission modulo="creditos" accion="listar">
                                <CreditsListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'creditos/crear',
                        element: (
                            <RequireModulePermission modulo="creditos" accion="crear">
                                <CreateCreditPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'creditos/:id',
                        element: (
                            <RequireModulePermission modulo="creditos" accion="listar">
                                <CreditDetailPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'creditos/:id/editar',
                        element: (
                            <RequireModulePermission modulo="creditos" accion="editar">
                                <EditCreditPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'creditos/:id/estado-cuenta',
                        element: (
                            <RequireModulePermission modulo="creditos" accion="listar">
                                <AccountStatementPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'pagos',
                        element: (
                            <RequireModulePermission modulo="pagos" accion="listar">
                                <PaymentsListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'recibos',
                        element: (
                            <RequireModulePermission modulo="recibos" accion="listar">
                                <ReceiptsListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'pagos/buscar',
                        element: (
                            <RequireModulePermission modulo="pagos" accion="crear">
                                <SearchCreditsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'pagos/creditos/:id',
                        element: (
                            <RequireModulePermission modulo="pagos" accion="listar">
                                <CreditPaymentsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'pagos/creditos/:id/nuevo',
                        element: (
                            <RequireModulePermission modulo="pagos" accion="crear">
                                <RegisterPaymentPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'pagos/creditos/:id/liquidar',
                        element: (
                            <RequireModulePermission modulo="pagos" accion="crear">
                                <SettleCreditPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'cartera',
                        element: (
                            <RequireModulePermission modulo="cartera" accion="listar">
                                <PortfolioPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'cartera/reporte-empleador',
                        element: (
                            <RequireModulePermission modulo="cartera" accion="listar">
                                <EmployerReportPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'empresa',
                        element: (
                            <RequireAdmin>
                                <EditCompanyPage />
                            </RequireAdmin>
                        ),
                    },
                    {
                        path: 'correo',
                        element: (
                            <RequireAdmin>
                                <EmailSettingsPage />
                            </RequireAdmin>
                        ),
                    },
                    {
                        path: 'plantillas-correo',
                        element: (
                            <RequireAdmin>
                                <EmailTemplatesPage />
                            </RequireAdmin>
                        ),
                    },
                    {
                        path: 'plantillas-correo/:tipo/crear',
                        element: (
                            <RequireAdmin>
                                <CreateEmailTemplatePage />
                            </RequireAdmin>
                        ),
                    },
                    {
                        path: 'plantillas-correo/:tipo/:id/editar',
                        element: (
                            <RequireAdmin>
                                <EditEmailTemplatePage />
                            </RequireAdmin>
                        ),
                    },
                    {
                        path: 'empresas',
                        element: (
                            <RequireSuperAdmin>
                                <CompaniesListPage />
                            </RequireSuperAdmin>
                        ),
                    },
                    {
                        path: 'empresas/crear',
                        element: (
                            <RequireSuperAdmin>
                                <CreateCompanyPage />
                            </RequireSuperAdmin>
                        ),
                    },
                    {
                        path: 'geografia/municipios',
                        element: (
                            <RequireSuperAdmin>
                                <MunicipalitiesAdminPage />
                            </RequireSuperAdmin>
                        ),
                    },
                    {
                        path: 'geografia/municipios/crear',
                        element: (
                            <RequireSuperAdmin>
                                <CreateMunicipalityPage />
                            </RequireSuperAdmin>
                        ),
                    },
                    {
                        path: 'auditoria',
                        element: (
                            <RequireAuditAccess>
                                <AuditLogPage />
                            </RequireAuditAccess>
                        ),
                    },
                    {
                        path: 'recuperacion-auditoria',
                        element: (
                            <RequireSuperAdmin>
                                <AuditRecoveryPage />
                            </RequireSuperAdmin>
                        ),
                    },
                    {
                        path: 'respaldos',
                        element: <BackupsPage />,
                    },
                    {
                        path: 'roles',
                        element: (
                            <RequireAdmin>
                                <RolesListPage />
                            </RequireAdmin>
                        ),
                    },
                    {
                        path: 'roles/crear',
                        element: (
                            <RequireAdmin>
                                <CreateRolePage />
                            </RequireAdmin>
                        ),
                    },
                    {
                        path: 'roles/:id/editar',
                        element: (
                            <RequireAdmin>
                                <EditRolePage />
                            </RequireAdmin>
                        ),
                    },
                    {
                        path: 'usuarios',
                        element: (
                            <RequireModulePermission modulo="usuarios" accion="listar">
                                <UsersListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'usuarios/crear',
                        element: (
                            <RequireModulePermission modulo="usuarios" accion="crear">
                                <CreateUserPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'usuarios/:id/editar',
                        element: (
                            <RequireModulePermission modulo="usuarios" accion="editar">
                                <EditUserPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'terceros',
                        element: (
                            <RequireModulePermission modulo="terceros" accion="listar">
                                <ThirdPartiesListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'terceros/crear',
                        element: (
                            <RequireModulePermission modulo="terceros" accion="crear">
                                <CreateThirdPartyPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'terceros/:id/editar',
                        element: (
                            <RequireModulePermission modulo="terceros" accion="editar">
                                <EditThirdPartyPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/plan-cuentas',
                        element: (
                            <RequireModulePermission modulo="configuracion_contable" accion="listar">
                                <ChartOfAccountsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/plan-cuentas/crear',
                        element: (
                            <RequireModulePermission modulo="configuracion_contable" accion="crear">
                                <CreateAccountPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/periodos',
                        element: (
                            <RequireModulePermission modulo="configuracion_contable" accion="listar">
                                <PeriodsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/tipos-comprobante',
                        element: (
                            <RequireModulePermission modulo="configuracion_contable" accion="listar">
                                <VoucherTypesPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/tipos-comprobante/crear',
                        element: (
                            <RequireModulePermission modulo="configuracion_contable" accion="crear">
                                <CreateVoucherTypePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/centros-costo',
                        element: (
                            <RequireModulePermission modulo="configuracion_contable" accion="listar">
                                <CostCentersPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/centros-costo/crear',
                        element: (
                            <RequireModulePermission modulo="configuracion_contable" accion="crear">
                                <CreateCostCenterPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/reglas',
                        element: (
                            <RequireModulePermission modulo="configuracion_contable" accion="configurar">
                                <PostingRulesPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/comprobantes',
                        element: (
                            <RequireModulePermission modulo="contabilidad" accion="listar">
                                <VouchersListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/comprobantes/crear',
                        element: (
                            <RequireModulePermission modulo="contabilidad" accion="crear_manual">
                                <CreateVoucherPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/comprobantes/:id',
                        element: (
                            <RequireModulePermission modulo="contabilidad" accion="listar">
                                <VoucherDetailPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/libro-diario',
                        element: (
                            <RequireModulePermission modulo="informes_contables" accion="listar">
                                <JournalPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/libro-mayor',
                        element: (
                            <RequireModulePermission modulo="informes_contables" accion="listar">
                                <LedgerPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/caja-bancos',
                        element: (
                            <RequireModulePermission modulo="informes_contables" accion="listar">
                                <LedgerPage modo="cajaBancos" />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/cuentas-por-pagar',
                        element: (
                            <RequireModulePermission modulo="contabilidad" accion="listar">
                                <AccountsPayablePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/cuentas-por-cobrar',
                        element: (
                            <RequireModulePermission modulo="contabilidad" accion="listar">
                                <AccountsReceivablePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/anticipos/crear',
                        element: (
                            <RequireModulePermission modulo="anticipos" accion="crear">
                                <CreateAdvancePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/anticipos/cruzar',
                        element: (
                            <RequireModulePermission modulo="anticipos" accion="crear">
                                <CrossAdvancePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/balance-prueba',
                        element: (
                            <RequireModulePermission modulo="informes_contables" accion="listar">
                                <TrialBalancePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/estados-financieros',
                        element: (
                            <RequireModulePermission modulo="informes_contables" accion="listar">
                                <FinancialStatementsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/auxiliares',
                        element: (
                            <RequireModulePermission modulo="informes_contables" accion="listar">
                                <AuxiliariesPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'contabilidad/conciliacion-apertura',
                        element: (
                            <RequireModulePermission modulo="informes_contables" accion="listar">
                                <OpeningReconciliationPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'importaciones',
                        element: (
                            <RequireModulePermission modulo="importaciones" accion="listar">
                                <ImportsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'importaciones/nueva',
                        element: (
                            <RequireModulePermission modulo="importaciones" accion="ejecutar">
                                <NewImportPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'importaciones/:id',
                        element: (
                            <RequireModulePermission modulo="importaciones" accion="listar">
                                <ImportDetailPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'facturacion',
                        element: (
                            <RequireModulePermission modulo="facturacion" accion="listar">
                                <FacturasListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'facturacion/notas-credito',
                        element: (
                            <RequireModulePermission modulo="facturacion" accion="listar">
                                <NotasCreditoListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'facturacion/notas-debito',
                        element: (
                            <RequireModulePermission modulo="facturacion" accion="listar">
                                <NotasDebitoListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'facturacion/consecutivos',
                        element: (
                            <RequireModulePermission modulo="facturacion" accion="configurar">
                                <InvoiceSequencesPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'facturacion/crear',
                        element: (
                            <RequireModulePermission modulo="facturacion" accion="crear">
                                <CreateInvoicePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'facturacion/cobros/crear',
                        element: (
                            <RequireModulePermission modulo="facturacion" accion="crear">
                                <CreateMultiplePaymentPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'facturacion/:id',
                        element: (
                            <RequireModulePermission modulo="facturacion" accion="listar">
                                <InvoiceDetailPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos',
                        element: (
                            <RequireModulePermission modulo="gastos" accion="listar">
                                <ExpensesListPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/crear',
                        element: (
                            <RequireModulePermission modulo="gastos" accion="crear">
                                <CreateExpensePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/:id',
                        element: (
                            <RequireModulePermission modulo="gastos" accion="listar">
                                <ExpenseDetailPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/por-centro-costo',
                        element: (
                            <RequireModulePermission modulo="gastos" accion="listar">
                                <ExpenseCostCenterReportPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/tipos-gasto',
                        element: (
                            <RequireModulePermission modulo="gastos" accion="listar">
                                <ExpenseTypesPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/tipos-gasto/crear',
                        element: (
                            <RequireModulePermission modulo="gastos" accion="parametrizar">
                                <CreateExpenseTypePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/actividades-economicas',
                        element: (
                            <RequireModulePermission modulo="tributario" accion="listar">
                                <EconomicActivitiesPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/actividades-economicas/crear',
                        element: (
                            <RequireModulePermission modulo="tributario" accion="parametrizar">
                                <CreateEconomicActivityPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/valores-uvt',
                        element: (
                            <RequireModulePermission modulo="tributario" accion="listar">
                                <UvtValuesPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/valores-uvt/crear',
                        element: (
                            <RequireModulePermission modulo="tributario" accion="parametrizar">
                                <CreateUvtValuePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/valores-uvt/:id/editar',
                        element: (
                            <RequireModulePermission modulo="tributario" accion="parametrizar">
                                <EditUvtValuePage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/parametrizaciones',
                        element: (
                            <RequireModulePermission modulo="gastos" accion="listar">
                                <ParametrizationsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/parametrizaciones/crear',
                        element: (
                            <RequireModulePermission modulo="gastos" accion="parametrizar">
                                <CreateParametrizationPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'conciliacion/cuentas-bancarias',
                        element: (
                            <RequireModulePermission modulo="conciliacion" accion="listar">
                                <BankAccountsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'conciliacion/cuentas-bancarias/crear',
                        element: (
                            <RequireModulePermission modulo="conciliacion" accion="configurar">
                                <CreateBankAccountPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'conciliacion/cuentas-bancarias/:id/extractos',
                        element: (
                            <RequireModulePermission modulo="conciliacion" accion="listar">
                                <BankStatementsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'conciliacion/extractos/:id/conciliar',
                        element: (
                            <RequireModulePermission modulo="conciliacion" accion="listar">
                                <ReconciliationMatchingPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/tarifas-reteica',
                        element: (
                            <RequireModulePermission modulo="tributario" accion="listar">
                                <ReteicaTariffsPage />
                            </RequireModulePermission>
                        ),
                    },
                    {
                        path: 'gastos/tarifas-reteica/crear',
                        element: (
                            <RequireModulePermission modulo="tributario" accion="parametrizar">
                                <CreateReteicaTariffPage />
                            </RequireModulePermission>
                        ),
                    },
                ],
            },
            { path: '*', element: <Navigate to="/" replace /> },
        ],
    },
];

export const router = createBrowserRouter(routes);
