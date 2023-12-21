<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\User\RoleController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\admin\CoinController;
use App\Http\Controllers\Setting\FaqController;
use App\Http\Controllers\CoinSettingsController;
use App\Http\Controllers\admin\CurrencyController;
use App\Http\Controllers\Contact\ContactController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Setting\AdminSettingController;

Route::get('/', [HomeController::class,'index'])->name('home');
Route::get('login', [AuthController::class,'login'])->name('login');
Route::post('login-process', [AuthController::class,'loginProcess'])->name('loginProcess');
Route::get('forgot-password', [AuthController::class,'forgotPassword'])->name('forgotPassword');
Route::get('verify-email', 'AuthController@verifyEmailPost')->name('verifyWeb');
Route::get('reset-password', [AuthController::class,'resetPasswordPage'])->name('resetPasswordPage');
Route::post('send-forgot-mail', [AuthController::class,'sendForgotMail'])->name('sendForgotMail');
Route::post('reset-password-save-process', [AuthController::class,'resetPasswordSave'])->name('resetPasswordSave');

Route::post('contact/store',[ContactController::class,'sendEmail'])->name('contactStoreProcess');


Route::group(['prefix' => 'admin', 'middleware' => ['auth','admins']], function () {
    Route::get('logout', [AuthController::class, 'logOut'])->name('logOut');
    Route::get('profile',[ProfileController::class,'index'])->name('profile');
    Route::get('update-profile',[ProfileController::class,'edit'])->name('updateProfile');
    Route::post('profile-update-process',[ProfileController::class,'update'])->name('updateProfileProcess');
    Route::post('change-password-process',[ProfileController::class,'changePassword'])->name('changePasswordProcess');

    Route::view('/dashboard', 'index')->name('adminDashboard');

    Route::group(['middleware' => ['roles']], function () {
    
    Route::get('faq',[FaqController::class,'index'])->name('faqList');
    Route::get('faq/add',[FaqController::class,'create'])->name('faqAdd');
    Route::get('faq/edit-{id}',[FaqController::class,'edit'])->name('faqEdit');
    Route::get('faq/delete-{id}',[FaqController::class,'destroy'])->name('faqDelete');
    Route::get('faq/preview-{id}',[FaqController::class,'preview'])->name('faqPreview');
    Route::post('faq/store',[FaqController::class,'store'])->name('faqStoreProcess');
    
    Route::get('contact-list',[ContactController::class,'index'])->name('contactList');
    Route::get('contact/delete-{id}',[ContactController::class,'destroy'])->name('contactDelete');
    Route::get('contact/preview-{id}',[ContactController::class,'preview'])->name('contactPreview');

    Route::get('role',[RoleController::class,'index'])->name('roleList');
    Route::get('role/add',[RoleController::class,'create'])->name('roleAdd');
    Route::get('role/edit-{id}',[RoleController::class,'edit'])->name('roleEdit');
    Route::get('role/delete-{id}',[RoleController::class,'destroy'])->name('roleDelete');
    Route::get('role/preview-{id}',[RoleController::class,'preview'])->name('rolePreview');
    Route::post('role/store',[RoleController::class,'store'])->name('roleStoreProcess');

    Route::get('user',[UserController::class,'index'])->name('adminList');
    Route::get('user/add',[UserController::class,'create'])->name('adminAdd');
    Route::get('user/edit-{id}',[UserController::class,'edit'])->name('adminEdit');
    Route::get('user/delete-{id}',[UserController::class,'destroy'])->name('adminDelete');
    Route::get('user/preview-{id}',[UserController::class,'preview'])->name('adminPreview');
    Route::post('user/store',[UserController::class,'store'])->name('adminStoreProcess');

    Route::get('settings',[AdminSettingController::class,'adminSetting'])->name('adminSetting');
    Route::post('update-generel-settings',[AdminSettingController::class,'updateGeneralSetting'])->name('updateGeneralSetting');

    Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index'])->name('adminLogs');

    Route::get('coin-list',[CoinController::class,'adminCoinList'])->name('adminCoinList');
    Route::get('add-new-coin', [CoinController::class,'adminAddCoin'])->name('adminAddCoin');
    Route::get('coin-edit/{id}', [CoinController::class,'adminCoinEdit'])->name('adminCoinEdit');
    Route::get('coin-delete/{id?}', [CoinController::class,'adminCoinDelete'])->name('adminCoinDelete');
    Route::get('coin-settings/{id}', [CoinController::class,'adminCoinSettings'])->name('adminCoinSettings');
    Route::post('coin-save-process', [CoinController::class,'adminCoinSaveProcess'])->name('adminCoinSaveProcess');
    Route::post('save-new-coin', [CoinController::class,'adminSaveCoin'])->name('adminSaveCoin');

    Route::get('currency-list', [CurrencyController::class,'adminCurrencyList'])->name('adminCurrencyList');
    Route::get('currency-add', [CurrencyController::class,'adminCurrencyAdd'])->name('adminCurrencyAdd');
    Route::get('currency-edit-{id}', [CurrencyController::class,'adminCurrencyEdit'])->name('adminCurrencyEdit');
    Route::get('currency-rate-change', [CurrencyController::class,'adminCurrencyRate'])->name('adminCurrencyRate');
    Route::post('currency-all', [CurrencyController::class,'adminAllCurrency'])->name('adminAllCurrency');
    Route::post('currency-status-change', [CurrencyController::class,'adminCurrencyStatus'])->name('adminCurrencyStatus');
    Route::post('currency-save-process', [CurrencyController::class,'adminCurrencyAddEdit'])->name('adminCurrencyStore');

    Route::get('api-settings', [CoinSettingsController::class, "adminCoinApiSettings"])->name('adminCoinApiSettings');
});
});





