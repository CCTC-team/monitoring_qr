<?php

namespace CCTC\MonitoringQRModule\Classes;

use ExternalModules\AbstractExternalModule;

/**
 * Manages user role detection and permissions for the Monitoring QR module
 */
class RoleManager
{
    private AbstractExternalModule $module;

    public function __construct(AbstractExternalModule $module)
    {
        $this->module = $module;
    }

    // Checks if the user has the requested role as defined in project settings
    public function userHasRole(string $roleSettingKey): bool
    {
        $roleId = $this->module->getProjectSetting($roleSettingKey);
        $user = $this->module->getUser();
        $rights = $user->getRights();

        return $rights['role_id'] == (int)$roleId;
    }

    // Checks if current user has the monitor role
    public function userHasMonitorRole(): bool
    {
        return $this->userHasRole('monitoring-role');
    }

    // Checks if current user has a data entry role (supports multiple roles)
    public function userHasDataEntryRole(): bool
    {
        $deRoles = $this->module->getProjectSetting('data-entry-roles');
        $user = $this->module->getUser();
        $rights = $user->getRights();

        foreach ($deRoles as $role) {
            if ($rights['role_id'] == (int)$role) {
                return true;
            }
        }

        return false;
    }

    // Checks if current user has the data manager role
    public function userHasDataManagerRole(): bool
    {
        return $this->userHasRole('data-manager-role');
    }

    // Returns the current user's role type as a string
    public function getCurrentUserRoleType(): string
    {
        if ($this->userHasMonitorRole()) {
            return 'monitor';
        }
        if ($this->userHasDataEntryRole()) {
            return 'data_entry';
        }
        if ($this->userHasDataManagerRole()) {
            return 'data_manager';
        }
        return 'none';
    }
}
