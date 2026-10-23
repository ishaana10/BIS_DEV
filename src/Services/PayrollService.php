<?php

namespace App\Services;

class PayrollService
{
    /**
     * @var object|null
     */
    protected $settings;

    public function __construct($settings = null)
    {
        $this->settings = $settings;
    }

    public function savePayroll(array $post): string
    {
        $tenant_id = $this->settings ? $this->settings->active_tenant_id() : null;

        $id = $post['id'] ?? null;
        $employee_id = $post['employee_id'] ?? null;
        $amount = $post['amount'] ?? null;

        return "Payroll saved for tenant: " . $tenant_id;
    }
}
