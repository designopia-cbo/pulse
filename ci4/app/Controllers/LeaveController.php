<?php

namespace App\Controllers;

use App\Models\LeaveModel;
use App\Models\EmployeeModel;

class LeaveController extends BaseController
{
    protected $leaveModel;
    protected $employeeModel;

    public function __construct()
    {
        $this->leaveModel = new LeaveModel();
        $this->employeeModel = new EmployeeModel();
    }

    /**
     * Display user's leave applications
     */
    public function myApplications()
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

        $userId = $this->session->get('userid');

        // Pagination
        $page = $this->request->getGet('page') ?? 1;
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        // Get user's leave applications
        $this->data['leaves'] = $this->leaveModel->getLeavesByUserId($userId, $perPage, $offset);
        $this->data['total_leaves'] = $this->leaveModel->where('userid', $userId)->countAllResults();
        $this->data['current_page'] = $page;
        $this->data['per_page'] = $perPage;
        $this->data['total_pages'] = ceil($this->data['total_leaves'] / $perPage);

        // Get user details
        $employee = $this->employeeModel->find($userId);
        $this->data['employee'] = $employee;

        return view('leave/my_applications', $this->data);
    }

    /**
     * Display all leave applications (for administrators)
     */
    public function allLeaves()
    {
        // Require login and admin
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        $adminRedirect = $this->requireAdmin();
        if ($adminRedirect) return $adminRedirect;

        // Validate session security
        if (!$this->validateSessionSecurity()) {
            return redirect()->to(base_url('login'))->with('security', 'agent_ip_mismatch');
        }

        // Check session timeout
        if (!$this->checkSessionTimeout()) {
            return redirect()->to(base_url('login'))->with('timeout', 'true');
        }

        $userId = $this->session->get('userid');

        // Pagination
        $page = $this->request->getGet('page') ?? 1;
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        // Get all leave applications that user can approve
        $this->data['leaves'] = $this->leaveModel->getPendingLeaves($userId, $perPage, $offset);
        $this->data['current_page'] = $page;
        $this->data['per_page'] = $perPage;

        // Get statistics
        $this->data['stats'] = [
            'pending' => $this->leaveModel->where('leave_status', 1)->countAllResults(),
            'approved' => $this->leaveModel->where('leave_status', 4)->countAllResults(),
            'rejected' => $this->leaveModel->where('leave_status', 5)->countAllResults(),
        ];

        return view('leave/all_leaves', $this->data);
    }

    /**
     * View specific leave application
     */
    public function view($leaveId)
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

        $userId = $this->session->get('userid');
        $userLevel = $this->session->get('level');

        // Get leave details
        $leave = $this->leaveModel->getLeaveDetails($leaveId);
        
        if (!$leave) {
            $this->session->setFlashdata('error', 'Leave application not found.');
            return redirect()->to(base_url('leave/applications'));
        }

        // Check access permissions
        $hasAccess = false;
        
        // User can view their own leave
        if ($leave['userid'] == $userId) {
            $hasAccess = true;
        }
        
        // Administrators can view any leave
        if ($userLevel === 'ADMINISTRATOR') {
            $hasAccess = true;
        }
        
        // Supervisors/Managers can view leaves they need to approve
        if ($leave['supervisor'] == $userId || $leave['manager'] == $userId || $leave['hr'] == $userId) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            $this->session->setFlashdata('error', 'You do not have permission to view this leave application.');
            return redirect()->to(base_url('leave/applications'));
        }

        $this->data['leave'] = $leave;
        $this->data['can_approve'] = $this->canApproveLeave($leave, $userId);
        $this->data['csrf_token'] = csrf_token();
        $this->data['csrf_hash'] = csrf_hash();

        return view('leave/view', $this->data);
    }

    /**
     * Apply for leave
     */
    public function apply()
    {
        // Require login
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        if ($this->request->getMethod() === 'POST') {
            return $this->processApplication();
        }

        $this->data['csrf_token'] = csrf_token();
        $this->data['csrf_hash'] = csrf_hash();

        return view('leave/apply', $this->data);
    }

    /**
     * Process leave application
     */
    public function processApplication()
    {
        // Validate input
        $rules = [
            'leave_type' => 'required|max_length[100]',
            'leave_details' => 'required|max_length[500]',
            'leave_reason' => 'required|max_length[500]',
            'date_from' => 'required|valid_date',
            'date_to' => 'required|valid_date',
            'csrf_token' => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = $this->session->get('userid');
        
        // Get user's supervisor and manager
        $employee = $this->employeeModel->find($userId);
        
        $data = [
            'userid' => $userId,
            'leave_type' => $this->request->getPost('leave_type'),
            'leave_details' => $this->request->getPost('leave_details'),
            'leave_reason' => $this->request->getPost('leave_reason'),
            'date_from' => $this->request->getPost('date_from'),
            'date_to' => $this->request->getPost('date_to'),
            'appdate' => date('Y-m-d H:i:s'),
            'leave_status' => 1, // Pending
            'supervisor' => $employee['supervisor'] ?? null,
            'manager' => $employee['manager'] ?? null,
            'hr' => null // Will be set by HR
        ];

        if ($this->leaveModel->save($data)) {
            $this->session->setFlashdata('success', 'Leave application submitted successfully.');
            return redirect()->to(base_url('leave/applications'));
        } else {
            $this->session->setFlashdata('error', 'Failed to submit leave application.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Approve leave application
     */
    public function approve($leaveId)
    {
        // Require login and admin
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        $userId = $this->session->get('userid');
        
        // Get leave details
        $leave = $this->leaveModel->find($leaveId);
        if (!$leave) {
            return $this->response->setJSON(['success' => false, 'message' => 'Leave application not found.']);
        }

        // Check if user can approve this leave
        if (!$this->canApproveLeave($leave, $userId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'You do not have permission to approve this leave.']);
        }

        // Update leave status
        $notes = $this->request->getPost('notes') ?? '';
        $newStatus = $this->getNextApprovalStatus($leave, $userId);
        
        if ($this->leaveModel->updateLeaveStatus($leaveId, $newStatus, $notes)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Leave application approved successfully.']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to approve leave application.']);
        }
    }

    /**
     * Reject leave application
     */
    public function reject($leaveId)
    {
        // Require login and admin
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        $userId = $this->session->get('userid');
        
        // Get leave details
        $leave = $this->leaveModel->find($leaveId);
        if (!$leave) {
            return $this->response->setJSON(['success' => false, 'message' => 'Leave application not found.']);
        }

        // Check if user can reject this leave
        if (!$this->canApproveLeave($leave, $userId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'You do not have permission to reject this leave.']);
        }

        // Update leave status
        $notes = $this->request->getPost('notes') ?? '';
        
        if ($this->leaveModel->updateLeaveStatus($leaveId, 5, $notes)) { // 5 = Rejected
            return $this->response->setJSON(['success' => true, 'message' => 'Leave application rejected.']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to reject leave application.']);
        }
    }

    /**
     * Check if user can approve leave
     */
    private function canApproveLeave($leave, $userId): bool
    {
        // Check if user is in the approval chain
        return in_array($userId, [$leave['supervisor'], $leave['manager'], $leave['hr']]);
    }

    /**
     * Get next approval status
     */
    private function getNextApprovalStatus($leave, $userId): int
    {
        // Status flow: 1 (Pending) -> 2 (Supervisor) -> 3 (Manager) -> 4 (HR/Approved)
        
        if ($leave['supervisor'] == $userId && $leave['leave_status'] == 1) {
            return 2; // Supervisor approved
        }
        
        if ($leave['manager'] == $userId && $leave['leave_status'] == 2) {
            return 3; // Manager approved
        }
        
        if ($leave['hr'] == $userId && $leave['leave_status'] == 3) {
            return 4; // HR approved (final)
        }
        
        // Default to approved if no specific workflow
        return 4;
    }
}