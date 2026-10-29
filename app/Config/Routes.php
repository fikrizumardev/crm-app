<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Autentikasi (tanpa login)
$routes->get('login', 'AuthController::login', ['as' => 'login']);
$routes->post('login', 'AuthController::attempt');

// Semua rute aplikasi wajib login; user yang wajib ganti password diarahkan ke /profile/password.
$routes->group('', ['filter' => ['auth', 'forcepassword']], static function (RouteCollection $routes): void {
    $routes->post('logout', 'AuthController::logout', ['as' => 'logout']);

    $routes->get('/', 'DashboardController::index', ['as' => 'dashboard']);

    $routes->get('profile', 'ProfileController::edit', ['as' => 'profile']);
    $routes->post('profile', 'ProfileController::update');
    $routes->get('profile/password', 'ProfileController::password', ['as' => 'profile.password']);
    $routes->post('profile/password', 'ProfileController::updatePassword');

    // Lead
    $routes->get('leads/export', 'LeadController::export', ['filter' => 'role:admin,manager']);
    $routes->get('leads/import', 'LeadController::import');
    $routes->get('leads/import/template', 'LeadController::importTemplate');
    $routes->post('leads/import', 'LeadController::importProcess');
    $routes->get('leads/(:num)/convert', 'LeadController::convertForm/$1');
    $routes->post('leads/(:num)/convert', 'LeadController::convert/$1');

    // Perusahaan
    $routes->get('companies/export', 'CompanyController::export', ['filter' => 'role:admin,manager']);
    $routes->get('companies/search', 'CompanyController::search');

    // Kontak
    $routes->get('contacts/export', 'ContactController::export', ['filter' => 'role:admin,manager']);
    $routes->get('contacts/options', 'ContactController::options');

    // Deal & pipeline
    $routes->get('deals/board', 'DealController::board', ['as' => 'deals.board']);
    $routes->post('deals/(:num)/stage', 'DealController::stage/$1');

    // Aktivitas & tugas
    $routes->post('activities', 'ActivityController::create');
    $routes->post('activities/(:num)/delete', 'ActivityController::delete/$1');
    $routes->get('tasks', 'TaskController::index', ['as' => 'tasks']);
    $routes->post('tasks', 'TaskController::create');
    $routes->post('tasks/(:num)/done', 'TaskController::done/$1');
    $routes->post('tasks/(:num)/reopen', 'TaskController::reopen/$1');
    $routes->post('tasks/(:num)/delete', 'TaskController::delete/$1');

    // Notifikasi
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/read-all', 'NotificationController::readAll');
    $routes->get('notifications/(:num)', 'NotificationController::open/$1');

    // Alihkan owner massal (admin & manager)
    $routes->post('(leads|deals|companies|contacts)/reassign', 'ReassignController::reassign/$1', ['filter' => 'role:admin,manager']);

    // Laporan (admin & manager)
    $routes->group('reports', ['filter' => 'role:admin,manager'], static function (RouteCollection $routes): void {
        $routes->get('/', 'ReportController::index', ['as' => 'reports']);
        $routes->get('performance', 'ReportController::performance');
        $routes->get('win-loss', 'ReportController::winLoss');
        $routes->get('sources', 'ReportController::sources');
        $routes->get('forecast', 'ReportController::forecast');
    });

    // Administrasi (khusus admin)
    $routes->group('admin', ['filter' => 'role:admin', 'namespace' => 'App\Controllers\Admin'], static function (RouteCollection $routes): void {
        $routes->get('users', 'UserController::index', ['as' => 'admin.users']);
        $routes->get('users/new', 'UserController::new');
        $routes->post('users', 'UserController::create');
        $routes->get('users/(:num)/edit', 'UserController::edit/$1');
        $routes->post('users/(:num)', 'UserController::update/$1');
        $routes->post('users/(:num)/activate', 'UserController::activate/$1');
        $routes->post('users/(:num)/deactivate', 'UserController::deactivate/$1');
        $routes->post('users/(:num)/reset-password', 'UserController::resetPassword/$1');

        $routes->get('settings', 'SettingController::edit', ['as' => 'admin.settings']);
        $routes->post('settings', 'SettingController::update');

        $routes->get('master', 'MasterDataController::index', ['as' => 'admin.master']);
        $routes->post('master/stages', 'MasterDataController::createStage');
        $routes->post('master/stages/reorder', 'MasterDataController::reorderStages');
        $routes->post('master/stages/(:num)', 'MasterDataController::updateStage/$1');
        $routes->post('master/stages/(:num)/delete', 'MasterDataController::deleteStage/$1');
        $routes->post('master/(sources|industries|lost-reasons)', 'MasterDataController::create/$1');
        $routes->post('master/(sources|industries|lost-reasons)/(:num)', 'MasterDataController::update/$1/$2');
        $routes->post('master/(sources|industries|lost-reasons)/(:num)/delete', 'MasterDataController::delete/$1/$2');

        $routes->get('audit-logs', 'AuditLogController::index', ['as' => 'admin.audit']);
    });

    // CRUD standar: index, new, create, show, edit, update, delete.
    foreach (['leads' => 'LeadController', 'companies' => 'CompanyController', 'contacts' => 'ContactController', 'deals' => 'DealController'] as $path => $controller) {
        $routes->get($path, $controller . '::index', ['as' => $path]);
        $routes->get($path . '/new', $controller . '::new');
        $routes->post($path, $controller . '::create');
        $routes->get($path . '/(:num)', $controller . '::show/$1');
        $routes->get($path . '/(:num)/edit', $controller . '::edit/$1');
        $routes->post($path . '/(:num)', $controller . '::update/$1');
        $routes->post($path . '/(:num)/delete', $controller . '::delete/$1');
    }
});
