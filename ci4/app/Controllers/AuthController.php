<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\EmployeeModel;

class AuthController extends BaseController
{
    protected $userModel;
    protected $employeeModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->employeeModel = new EmployeeModel();
    }

    /**
     * Display login page
     */
    public function login()
    {
        // If already logged in, redirect to dashboard
        if ($this->isLoggedIn()) {
            return redirect()->to(base_url('dashboard'));
        }

        // Generate CSRF token
        $this->data['csrf_token'] = csrf_token();
        $this->data['csrf_hash'] = csrf_hash();
        
        // Get error messages
        $this->data['error_message'] = $this->session->getFlashdata('error_message');
        $this->data['timeout'] = $this->request->getGet('timeout');
        $this->data['security'] = $this->request->getGet('security');

        return view('auth/login', $this->data);
    }

    /**
     * Process login
     */
    public function loginProcess()
    {
        // Validate CSRF token
        if (!$this->validate([
            'csrf_token' => 'required',
            'username' => 'required',
            'password' => 'required'
        ])) {
            $this->session->setFlashdata('error_message', 'Invalid form data. Please try again.');
            return redirect()->to(base_url('login'));
        }

        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        // Verify credentials
        $user = $this->userModel->verifyCredentials($username, $password);

        if ($user) {
            // Regenerate session ID for security
            $this->session->regenerate();

            // Set session data
            $this->session->set([
                'userid' => $user['userid'],
                'username' => $user['username'],
                'completename' => $user['completename'],
                'level' => $user['level'],
                'category' => $user['category'],
                'last_activity' => time()
            ]);

            // Initialize session security
            $this->initializeSessionSecurity();

            // Redirect based on user level
            if ($user['level'] === 'ADMINISTRATOR') {
                return redirect()->to(base_url('dashboard'));
            } else {
                return redirect()->to(base_url('profile'));
            }
        } else {
            $this->session->setFlashdata('error_message', 'Invalid login credentials. Please try again.');
            return redirect()->to(base_url('login'));
        }
    }

    /**
     * Logout
     */
    public function logout()
    {
        $this->session->destroy();
        return redirect()->to(base_url('login'));
    }

    /**
     * Change password page
     */
    public function changePassword()
    {
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

        $this->data['csrf_token'] = csrf_token();
        $this->data['csrf_hash'] = csrf_hash();
        
        return view('auth/change_password', $this->data);
    }

    /**
     * Process password change
     */
    public function changePasswordProcess()
    {
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        // Validate input
        if (!$this->validate([
            'csrf_token' => 'required',
            'current_password' => 'required',
            'new_password' => 'required|min_length[8]',
            'confirm_password' => 'required|matches[new_password]'
        ])) {
            $this->session->setFlashdata('error_message', 'Please correct the errors and try again.');
            return redirect()->to(base_url('change-password'));
        }

        $userId = $this->session->get('userid');
        $currentPassword = $this->request->getPost('current_password');
        $newPassword = $this->request->getPost('new_password');

        // Get current user
        $user = $this->userModel->find($userId);
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $this->session->setFlashdata('error_message', 'Current password is incorrect.');
            return redirect()->to(base_url('change-password'));
        }

        // Update password
        if ($this->userModel->updatePassword($userId, $newPassword)) {
            $this->session->setFlashdata('success_message', 'Password changed successfully.');
            return redirect()->to(base_url('profile'));
        } else {
            $this->session->setFlashdata('error_message', 'Failed to update password. Please try again.');
            return redirect()->to(base_url('change-password'));
        }
    }
}