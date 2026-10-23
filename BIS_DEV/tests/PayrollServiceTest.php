<?php
declare(strict_types=1);

namespace Tests;

use App\Services\PayrollService;

require_once dirname(__DIR__, 2) . '/src/Services/PayrollService.php';

class PayrollServiceTest
{
    public static function run(): void
    {
        $settingsMock = new class {
            public function active_tenant_id(): string
            {
                return 'tenant_123';
            }
        };

        $service = new PayrollService($settingsMock);

        // Test standard savePayroll behavior
        $result1 = $service->savePayroll([
            'id' => 1,
            'employee_id' => 100,
            'amount' => 5000,
        ]);

        if ($result1 !== 'Payroll saved for tenant: tenant_123') {
            throw new \RuntimeException('testSavePayrollWithValidData failed! Got: ' . var_export($result1, true));
        }

        // Test against variable extraction overwrite attack
        $maliciousPost = [
            'settings' => 'OVERWRITTEN',
            'this' => 'OVERWRITTEN',
            'id' => 1,
        ];

        $result2 = $service->savePayroll($maliciousPost);

        if ($result2 !== 'Payroll saved for tenant: tenant_123') {
            throw new \RuntimeException('testSavePayrollDoesNotAllowVariableOverwrite failed! Got: ' . var_export($result2, true));
        }

        echo "PayrollServiceTest: All 2 tests passed successfully!\n";
    }
}

PayrollServiceTest::run();
