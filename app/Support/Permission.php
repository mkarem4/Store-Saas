<?php

namespace App\Support;

final class Permission
{
    public const ManageBusinesses = 'businesses.manage';

    public const ManageBusinessTypes = 'business_types.manage';

    public const ManagePlatformSettings = 'platform_settings.manage';

    public const ManageUsers = 'users.manage';

    public const ManageCatalog = 'catalog.manage';

    public const ManageInventory = 'inventory.manage';

    public const ManageSuppliers = 'suppliers.manage';

    public const UsePos = 'pos.use';

    public const ViewSales = 'sales.view';

    public const RefundSales = 'sales.refund';

    public const ViewCosts = 'costs.view';

    public const ManageOrders = 'orders.manage';

    public const ManageEmployees = 'employees.manage';

    public const ManageAttendance = 'attendance.manage';

    public const ClockAttendance = 'attendance.clock';

    public const ManagePayroll = 'payroll.manage';

    public const ManageExpenses = 'expenses.manage';

    public const ManageVehicles = 'vehicles.manage';

    public const SellVehicles = 'vehicles.sell';

    public const ManageRentals = 'rentals.manage';

    public const ManageClients = 'clients.manage';

    public const ViewReports = 'reports.view';

    public const ManageSettings = 'settings.manage';

    public const ManageGoldPrices = 'gold_prices.manage';

    public const ManageModifiers = 'modifiers.manage';

    public const ViewCommissions = 'commissions.view';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ManageBusinesses,
            self::ManageBusinessTypes,
            self::ManagePlatformSettings,
            self::ManageUsers,
            self::ManageCatalog,
            self::ManageInventory,
            self::ManageSuppliers,
            self::UsePos,
            self::ViewSales,
            self::RefundSales,
            self::ViewCosts,
            self::ManageOrders,
            self::ManageEmployees,
            self::ManageAttendance,
            self::ClockAttendance,
            self::ManagePayroll,
            self::ManageExpenses,
            self::ManageVehicles,
            self::SellVehicles,
            self::ManageRentals,
            self::ManageClients,
            self::ViewReports,
            self::ManageSettings,
            self::ManageGoldPrices,
            self::ManageModifiers,
            self::ViewCommissions,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function businessOwner(): array
    {
        return self::all();
    }

    /**
     * @return array<int, string>
     */
    public static function staff(): array
    {
        return [
            self::UsePos,
            self::ViewSales,
            self::ClockAttendance,
            self::SellVehicles,
            self::ViewCommissions,
        ];
    }
}
