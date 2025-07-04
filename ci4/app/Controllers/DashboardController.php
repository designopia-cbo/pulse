<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\EmployeeModel;
use App\Models\LeaveModel;

class DashboardController extends BaseController
{
    protected $userModel;
    protected $employeeModel;
    protected $leaveModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->employeeModel = new EmployeeModel();
        $this->leaveModel = new LeaveModel();
    }

    /**
     * Display dashboard
     */
    public function index()
    {
        // Require login
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        // Validate session security
        if (!$this->validateSessionSecurity()) {
            return redirect()->to(base_url('login'))->with('security', 'agent_ip_mismatch');
        }

        // Check session timeout
        if (!$this->checkSessionTimeout()) {
            return redirect()->to(base_url('login'))->with('timeout', 'true');
        }

        // Check if user is administrator
        if (!$this->hasLevel('ADMINISTRATOR')) {
            return redirect()->to(base_url('profile'));
        }

        $userId = $this->session->get('userid');
        $category = $this->session->get('category');

        // Get user details
        $employee = $this->employeeModel->getEmployeeByUserId($userId);
        if ($employee) {
            $this->data['fullName'] = ucwords(strtolower($employee['fullname']));
            $this->data['initial'] = strtoupper(substr($employee['fullname'], 0, 1));
        } else {
            $this->data['fullName'] = 'Unknown User';
            $this->data['initial'] = 'U';
        }

        // Pagination settings
        $rowsPerPage = 5;
        $currentPage = $this->request->getGet('page') ? max(1, intval($this->request->getGet('page'))) : 1;
        $offset = ($currentPage - 1) * $rowsPerPage;

        // Get pending leave applications
        $this->data['leaveApplications'] = $this->leaveModel->getPendingLeaves($userId, $rowsPerPage, $offset);

        // Count total pending leaves for pagination
        $totalLeaves = $this->leaveModel->db->table('emp_leave l')
            ->join('employee e', 'l.userid = e.id')
            ->where('(l.hr = ' . $userId . ' AND l.leave_status = 1)')
            ->orWhere('(l.supervisor = ' . $userId . ' AND l.leave_status = 2)')
            ->orWhere('(l.manager = ' . $userId . ' AND l.leave_status = 3)')
            ->countAllResults();

        $this->data['totalPages'] = ceil($totalLeaves / $rowsPerPage);
        $this->data['currentPage'] = $currentPage;
        $this->data['rowsPerPage'] = $rowsPerPage;

        // Get dashboard statistics
        $this->data['stats'] = $this->getDashboardStats();

        return view('dashboard/index', $this->data);
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats()
    {
        $userId = $this->session->get('userid');
        $category = $this->session->get('category');

        $stats = [
            'total_employees' => 0,
            'pending_leaves' => 0,
            'approved_leaves' => 0,
            'total_positions' => 0
        ];

        // Total employees
        $stats['total_employees'] = $this->employeeModel->countAll();

        // Pending leaves for current user
        $stats['pending_leaves'] = $this->leaveModel->db->table('emp_leave l')
            ->where('(l.hr = ' . $userId . ' AND l.leave_status = 1)')
            ->orWhere('(l.supervisor = ' . $userId . ' AND l.leave_status = 2)')
            ->orWhere('(l.manager = ' . $userId . ' AND l.leave_status = 3)')
            ->countAllResults();

        // Approved leaves this month
        $stats['approved_leaves'] = $this->leaveModel->db->table('emp_leave')
            ->where('leave_status', 4)
            ->where('MONTH(appdate)', date('m'))
            ->where('YEAR(appdate)', date('Y'))
            ->countAllResults();

        // Total plantilla positions (if user has access)
        if (in_array($category, ['HR', 'MINISTER', 'SUPERADMIN'])) {
            $stats['total_positions'] = $this->leaveModel->db->table('plantilla_position')
                ->countAllResults();
        }

        return $stats;
    }
}