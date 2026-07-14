<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyManagerController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceImageController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkerProfileController;
use App\Http\Controllers\WorkgroupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes Configuration Matrix
|--------------------------------------------------------------------------
*/

// Apply global locale detection to all incoming mobile/client app traffic

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/fcm-token', [AuthController::class, 'updateFcmToken']);
        Route::post('/update', [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function () {

    // Regions & Region Managers Management Scope
    Route::prefix('regions')->group(function () {
        // Region Management Core
        Route::post('/', [RegionController::class, 'addRegion']);
        Route::get('/', [RegionController::class, 'getRegions']);
        Route::get('/names', [RegionController::class, 'getRegionsNames']);
        Route::get('/{region}', [RegionController::class, 'showRegion']);
        Route::put('/{region}', [RegionController::class, 'updateRegion']);
        Route::delete('/{region}', [RegionController::class, 'deleteRegion']);

        // Specialized Territory Managers Mapping Roles
        Route::post('/managers', [RegionController::class, 'addManager']);

        Route::delete('/managers/{manager}', [RegionController::class, 'deleteManager']);
    });
    Route::get('/managers', [RegionController::class, 'getManagers']);
    //  Companies & Company Managers Management Scope
    Route::prefix('companies')->group(function () {
        // Corporate Profiles Core
        Route::post('/', [CompanyController::class, 'addCompany']);
        Route::get('/', [CompanyController::class, 'getCompanies']);
        Route::get('/{company}', [CompanyController::class, 'showCompany']);
        Route::put('/{company}', [CompanyController::class, 'updateCompany']);
        Route::delete('/{company}', [CompanyController::class, 'deleteCompany']);

        // Specialized Corporate Leaders Mapping Roles
        Route::post('/managers', [CompanyController::class, 'addManager']);

        Route::delete('/managers/{manager}', [CompanyController::class, 'deleteManager']);
    });
    Route::get('/company/managers', [CompanyController::class, 'getManagers']);

    //  Field Workers Management Scope (Company Manager Operations)
    Route::prefix('workers')->group(function () {
        Route::post('/', [CompanyManagerController::class, 'addWorker']);
        Route::get('/{company}', [CompanyManagerController::class, 'getWorkers']);
        Route::put('/{worker}', [CompanyManagerController::class, 'updateWorker']);
        Route::delete('/{worker}', [CompanyManagerController::class, 'deleteWorker']);
    });

    Route::apiResource('categories', CategoryController::class);


    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::post('/', [ProfileController::class, 'update']);
    });

    Route::prefix('worker-profiles')->group(function () {
        Route::get('/me', [WorkerProfileController::class, 'showOwn']);
        Route::get('/{worker}', [WorkerProfileController::class, 'show']);
        Route::put('/', [WorkerProfileController::class, 'update']);
    });

    Route::prefix('attributes')->group(function () {
        Route::get('/', [AttributeController::class, 'index']);      // Accessible to Admin & Company Managers
        Route::post('/', [AttributeController::class, 'store']);     // Admin only
        Route::delete('/{attribute}', [AttributeController::class, 'destroy']); // Admin only
    });


    Route::apiResource('services', ServiceController::class);
    Route::put('/services/{service}/attributes', [ServiceController::class, 'updateAttributes']);

    Route::apiResource('packages', PackageController::class);
    Route::post('reviews', [ReviewController::class, 'store']);

    Route::get('/home-page', [HomeController::class, 'index']);
    Route::get('/search', [HomeController::class, 'search']);
    Route::get('/offers', [HomeController::class, 'getoffers']);

    Route::get('skills', [SkillController::class, 'index']);
    Route::post('skills', [SkillController::class, 'store']);

    // ==========================================
    // 🎓 Dynamic Skill Assignment Mapping Routes
    // ==========================================
    Route::post('services/{service}/skills', [ServiceController::class, 'attachSkills']);
    Route::post('workers/skills', [CompanyManagerController::class, 'attachSkills']);
    // ==========================================
    // ❤️ (Favorites Engine)
    // ==========================================
    Route::prefix('favorites')->group(function () {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::post('/toggle', [FavoriteController::class, 'toggleFavorite']);
    });

    Route::post('/service-images', [ServiceImageController::class, 'store']);
    Route::post('/work-times', [CompanyManagerController::class, 'insertOrUpdate']);

    // ==========================================
    // 👥 Operational Crews & Workgroups Management
    // ==========================================
    Route::apiResource('workgroups', WorkgroupController::class);


    Route::get('packages/{package}/available-slots', [OrderController::class, 'getAvailableSlots']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    // ==========================================
    // 🛒 مسارات الطلبات المتطورة (Orders Matrix)
    // ==========================================
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/assign', [OrderController::class, 'assignToWorkgroup']); // الإسناد لورشة

    // ==========================================
    // 👷 مسارات مهام العمال والورشات (Tasks Workflows)
    // ==========================================
    Route::get('tasks', [TaskController::class, 'index']); // جلب المهام (متاح لكل العمال في الورشة)
    Route::get('tasks/{task}', [TaskController::class, 'show']); // جلب تفاصيل المهمة
    Route::post('tasks/{task}/update-status', [TaskController::class, 'updateStatus']); // تحديث ورفع الصور (للقائد فقط)
    // ==========================================
    // 🧠 Smart Dashboard Dispatch Indicators
    // ==========================================
    Route::get('orders/{order}/qualified-groups', [OrderController::class, 'getQualifiedGroups']);

    // ==========================================
// 🔔 صندوق وارد الإشعارات (In-App Notifications Inbox)
// ==========================================
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/mark-as-read', [NotificationController::class, 'markAsRead']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
});
