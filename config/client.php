<?php

use App\Client\Modules\BooksModule;
use App\Client\Modules\ClaimsModule;
use App\Client\Modules\CollectionsModule;
use App\Client\Modules\CustomersModule;
use App\Client\Modules\OpsModule;
use App\Client\Modules\OrdersModule;
use App\Client\Modules\PortalModule;
use App\Client\Modules\PriceListModule;
use App\Client\Modules\SiteModule;
use App\Client\Modules\WarehouseModule;
use App\Client\Screens\CentralScreen;

/*
|--------------------------------------------------------------------------
| Central's own layer
|--------------------------------------------------------------------------
|
| Central runs on the August ERP base. Everything that is Central's own —
| its modules, screens, models, migrations, strings — lives under app/Client
| and is registered here, so the base stays readable as the base
| (see CLAUDE.md, "Architecture").
|
*/

return [

    // Central's modules, each a class implementing App\Modules\Module.
    // They register after the standard modules of config/modules.php.
    'modules' => [
        OrdersModule::class,
        PriceListModule::class,
        CustomersModule::class,
        ClaimsModule::class,
        CollectionsModule::class,
        WarehouseModule::class,
        PortalModule::class,
        SiteModule::class,
        OpsModule::class,
        BooksModule::class,
    ],

    // Central's own screen keys: string-backed enums implementing
    // App\Domain\Access\ScreenKey, values starting "client__". Rights, menus and
    // the standard's pages pick them up with the base's MenuKey.
    'screens' => [
        CentralScreen::class,
    ],

    // Optional modules Central starts with, by module key, when erp:install
    // is not told --enable or --disable. Sales extras (check-ins, commissions,
    // targets) are part of the credit-sales operation; payroll, departments
    // and projects are not.
    'features' => [
        'sales-extras' => true,
        'payroll' => false,
        'departments' => false,
        'projects' => false,
    ],

    // The names people see. Staff work in "Central": the panel, its tabs and the mail it sends
    // them. Customers see the trading name everywhere they look: the public site, the buyer
    // portal and the mail sent to them. The customer name is the public site's short name, so
    // the Owner can change it on the Website screen; this is its default.
    'names' => [
        'staff' => env('STAFF_NAME', 'Central'),
        'customer' => env('CUSTOMER_NAME', 'Javaindo'),
    ],

    // The panel's colours; any key left out keeps the base's (DESIGN.md).
    'theme' => [
        'colors' => [
            // 'primary' => '#0f766e',
        ],
        // The company's mark, files under public/, shown beside the name in the panel's header
        // and on the public site; the dark twin is the same paths in the light blue of a dark ground.
        'logo' => 'images/logo.svg',
        'logo_dark' => 'images/logo-dark.svg',
    ],

];
