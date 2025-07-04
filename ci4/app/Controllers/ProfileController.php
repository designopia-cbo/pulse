<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\EmployeeModel;
use App\Models\LeaveModel;

class ProfileController extends BaseController
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
     * Display profile page
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

        $userId = $this->session->get('userid');
        $userCategory = $this->session->get('category');

        // Determine which profile is being viewed
        $profileUserId = $this->request->getGet('userid') ? intval($this->request->getGet('userid')) : $userId;

        // Access control
        $accessGranted = $this->checkProfileAccess($userId, $userCategory, $profileUserId);
        if (!$accessGranted) {
            $this->session->setFlashdata('error', 'Access denied. You do not have permission to view this profile.');
            return redirect()->to(base_url('profile'));
        }

        // Get employee details with position information
        $employee = $this->employeeModel->getEmployeeWithPosition($profileUserId);
        if (!$employee) {
            $this->session->setFlashdata('error', 'Employee profile not found.');
            return redirect()->to(base_url('dashboard'));
        }

        $this->data['employee'] = $employee;
        $this->data['profile_userid'] = $profileUserId;
        $this->data['is_own_profile'] = ($profileUserId === $userId);

        // Get recent leave applications
        $this->data['recent_leaves'] = $this->leaveModel->getLeavesByUserId($profileUserId, 5);

        // Additional profile data (employment details, family, etc.)
        $this->data['employment_details'] = $this->getEmploymentDetails($profileUserId);
        $this->data['family_details'] = $this->getFamilyDetails($profileUserId);
        $this->data['education_details'] = $this->getEducationDetails($profileUserId);

        return view('profile/index', $this->data);
    }

    /**
     * Edit profile page
     */
    public function edit()
    {
        // Require login
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        $userId = $this->session->get('userid');
        $profileUserId = $this->request->getGet('userid') ? intval($this->request->getGet('userid')) : $userId;

        // Access control - only allow editing own profile or if authorized
        if ($profileUserId !== $userId && !$this->hasCategory(['HR', 'MINISTER', 'SUPERADMIN'])) {
            $this->session->setFlashdata('error', 'Access denied. You can only edit your own profile.');
            return redirect()->to(base_url('profile'));
        }

        // Get employee details
        $employee = $this->employeeModel->getEmployeeWithPosition($profileUserId);
        if (!$employee) {
            $this->session->setFlashdata('error', 'Employee profile not found.');
            return redirect()->to(base_url('profile'));
        }

        $this->data['employee'] = $employee;
        $this->data['profile_userid'] = $profileUserId;
        $this->data['csrf_token'] = csrf_token();
        $this->data['csrf_hash'] = csrf_hash();

        return view('profile/edit', $this->data);
    }

    /**
     * Check profile access permissions
     */
    private function checkProfileAccess(int $userId, string $userCategory, int $profileUserId): bool
    {
        // Always allow if viewing own profile
        if ($profileUserId === $userId) {
            return true;
        }

        // MINISTER/HR can view any profile
        if (in_array($userCategory, ['MINISTER', 'HR'])) {
            return true;
        }

        // AAO can view only employees in the same office
        if ($userCategory === 'AAO') {
            return $this->checkOfficeAccess($userId, $profileUserId);
        }

        // Check if user supervises or manages the profile user
        return $this->checkSupervisionAccess($userId, $profileUserId);
    }

    /**
     * Check office access for AAO
     */
    private function checkOfficeAccess(int $userId, int $profileUserId): bool
    {
        // Get AAO's office
        $aaoOffice = $this->leaveModel->db->table('plantilla_position')
            ->select('office')
            ->where('userid', $userId)
            ->get()
            ->getRowArray();

        if (!$aaoOffice) {
            return false;
        }

        // Get profile user's office
        $profileOffice = $this->leaveModel->db->table('employment_details ed')
            ->select('pp.office')
            ->join('plantilla_position pp', 'ed.position_id = pp.id')
            ->where('ed.userid', $profileUserId)
            ->where('ed.edstatus', 1)
            ->get()
            ->getRowArray();

        return $profileOffice && $profileOffice['office'] === $aaoOffice['office'];
    }

    /**
     * Check supervision access
     */
    private function checkSupervisionAccess(int $userId, int $profileUserId): bool
    {
        // Check if user is supervisor or manager of the profile user
        $employee = $this->employeeModel->find($profileUserId);
        return $employee && (
            $employee['supervisor'] == $userId || 
            $employee['manager'] == $userId
        );
    }

    /**
     * Get employment details
     */
    private function getEmploymentDetails(int $userId): array
    {
        return $this->leaveModel->db->table('employment_details ed')
            ->select('ed.*, pp.position_title, pp.salary_grade, pp.office')
            ->join('plantilla_position pp', 'ed.position_id = pp.id', 'left')
            ->where('ed.userid', $userId)
            ->where('ed.edstatus', 1)
            ->get()
            ->getRowArray() ?: [];
    }

    /**
     * Get family details
     */
    private function getFamilyDetails(int $userId): array
    {
        return $this->leaveModel->db->table('family_details')
            ->where('userid', $userId)
            ->get()
            ->getResultArray();
    }

    /**
     * Get education details
     */
    private function getEducationDetails(int $userId): array
    {
        return $this->leaveModel->db->table('education_details')
            ->where('userid', $userId)
            ->orderBy('level', 'ASC')
            ->get()
            ->getResultArray();
    }
}