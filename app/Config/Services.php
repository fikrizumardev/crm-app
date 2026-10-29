<?php

namespace Config;

use App\Models\ActivityModel;
use App\Models\AuditLogModel;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\DealStageHistoryModel;
use App\Models\LeadModel;
use App\Models\LeadSourceModel;
use App\Models\LoginAttemptModel;
use App\Models\LostReasonModel;
use App\Models\NotificationModel;
use App\Models\PipelineStageModel;
use App\Models\TaskModel;
use App\Models\UserModel;
use App\Services\ActivityService;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\CompanyService;
use App\Services\ContactService;
use App\Services\DashboardService;
use App\Services\DealPipelineService;
use App\Services\DealService;
use App\Services\LeadConversionService;
use App\Services\LeadImportService;
use App\Services\LeadService;
use App\Services\MasterDataService;
use App\Services\NotificationService;
use App\Services\ReassignService;
use App\Services\ReportService;
use App\Services\TaskService;
use App\Services\UserService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function auth(bool $getShared = true): AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new AuthService(
            model(UserModel::class, false),
            model(LoginAttemptModel::class, false),
            static::auditLogger(),
            config(Auth::class),
        );
    }

    public static function auditLogger(bool $getShared = true): AuditLogger
    {
        if ($getShared) {
            return static::getSharedInstance('auditLogger');
        }

        return new AuditLogger(model(AuditLogModel::class, false));
    }

    public static function leadService(bool $getShared = true): LeadService
    {
        if ($getShared) {
            return static::getSharedInstance('leadService');
        }

        return new LeadService(model(LeadModel::class), static::auditLogger(), model(UserModel::class));
    }

    public static function companyService(bool $getShared = true): CompanyService
    {
        if ($getShared) {
            return static::getSharedInstance('companyService');
        }

        return new CompanyService(model(CompanyModel::class), static::auditLogger(), model(UserModel::class));
    }

    public static function contactService(bool $getShared = true): ContactService
    {
        if ($getShared) {
            return static::getSharedInstance('contactService');
        }

        return new ContactService(model(ContactModel::class), static::auditLogger(), model(UserModel::class));
    }

    public static function leadConversion(bool $getShared = true): LeadConversionService
    {
        if ($getShared) {
            return static::getSharedInstance('leadConversion');
        }

        return LeadConversionService::create();
    }

    public static function leadImport(bool $getShared = true): LeadImportService
    {
        if ($getShared) {
            return static::getSharedInstance('leadImport');
        }

        return new LeadImportService(
            static::leadService(),
            model(LeadSourceModel::class),
            model(UserModel::class),
            static::auditLogger(),
        );
    }

    public static function notifications(bool $getShared = true): NotificationService
    {
        if ($getShared) {
            return static::getSharedInstance('notifications');
        }

        return new NotificationService(model(NotificationModel::class));
    }

    public static function dealPipeline(bool $getShared = true): DealPipelineService
    {
        if ($getShared) {
            return static::getSharedInstance('dealPipeline');
        }

        return new DealPipelineService(
            model(DealModel::class),
            model(PipelineStageModel::class),
            model(DealStageHistoryModel::class),
            model(LostReasonModel::class),
            static::auditLogger(),
            db_connect(),
        );
    }

    public static function dealService(bool $getShared = true): DealService
    {
        if ($getShared) {
            return static::getSharedInstance('dealService');
        }

        return new DealService(
            model(DealModel::class),
            static::auditLogger(),
            model(UserModel::class),
            static::dealPipeline(),
            model(PipelineStageModel::class),
            model(DealStageHistoryModel::class),
        );
    }

    public static function activityService(bool $getShared = true): ActivityService
    {
        if ($getShared) {
            return static::getSharedInstance('activityService');
        }

        return new ActivityService(model(ActivityModel::class), static::auditLogger());
    }

    public static function taskService(bool $getShared = true): TaskService
    {
        if ($getShared) {
            return static::getSharedInstance('taskService');
        }

        return new TaskService(model(TaskModel::class), model(UserModel::class), static::auditLogger(), static::notifications());
    }

    public static function dashboard(bool $getShared = true): DashboardService
    {
        if ($getShared) {
            return static::getSharedInstance('dashboard');
        }

        return new DashboardService(db_connect(), model(UserModel::class));
    }

    public static function reports(bool $getShared = true): ReportService
    {
        if ($getShared) {
            return static::getSharedInstance('reports');
        }

        return new ReportService(db_connect(), model(UserModel::class));
    }

    public static function userService(bool $getShared = true): UserService
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        return new UserService(model(UserModel::class), static::auditLogger());
    }

    public static function reassign(bool $getShared = true): ReassignService
    {
        if ($getShared) {
            return static::getSharedInstance('reassign');
        }

        return new ReassignService(model(UserModel::class), static::auditLogger(), static::notifications());
    }

    public static function masterData(bool $getShared = true): MasterDataService
    {
        if ($getShared) {
            return static::getSharedInstance('masterData');
        }

        return new MasterDataService(db_connect(), static::auditLogger());
    }
}
