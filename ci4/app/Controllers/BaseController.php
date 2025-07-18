<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = ['url', 'form', 'security'];

    /**
     * Session instance
     *
     * @var \CodeIgniter\Session\Session
     */
    protected $session;

    /**
     * Common data for all views
     *
     * @var array
     */
    protected $data = [];

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        $this->session = \Config\Services::session();
        
        // Set common data for views
        $this->data['session'] = $this->session;
        $this->data['user'] = $this->getCurrentUser();
        
        // Set security headers
        $this->setSecurityHeaders();
    }

    /**
     * Set security headers
     */
    protected function setSecurityHeaders()
    {
        $this->response->setHeader('X-Frame-Options', 'DENY');
        $this->response->setHeader('X-XSS-Protection', '1; mode=block');
        $this->response->setHeader('X-Content-Type-Options', 'nosniff');
        $this->response->setHeader('Referrer-Policy', 'no-referrer');
        
        if ($this->request->isSecure()) {
            $this->response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Get current user information
     */
    protected function getCurrentUser()
    {
        $userId = $this->session->get('userid');
        if (!$userId) {
            return null;
        }

        return [
            'userid' => $this->session->get('userid'),
            'username' => $this->session->get('username'),
            'completename' => $this->session->get('completename'),
            'level' => $this->session->get('level'),
            'category' => $this->session->get('category'),
        ];
    }

    /**
     * Check if user is logged in
     */
    protected function isLoggedIn(): bool
    {
        return $this->session->get('userid') !== null;
    }

    /**
     * Check if user has required level
     */
    protected function hasLevel(string $requiredLevel): bool
    {
        $userLevel = $this->session->get('level');
        return $userLevel === $requiredLevel;
    }

    /**
     * Check if user has required category
     */
    protected function hasCategory(array $requiredCategories): bool
    {
        $userCategory = $this->session->get('category');
        return in_array($userCategory, $requiredCategories);
    }

    /**
     * Require login - redirect to login if not logged in
     */
    protected function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            return redirect()->to(base_url('login'));
        }
    }

    /**
     * Require admin level
     */
    protected function requireAdmin()
    {
        if (!$this->hasLevel('ADMINISTRATOR')) {
            $this->session->setFlashdata('error', 'Access denied. Administrator level required.');
            return redirect()->to(base_url('dashboard'));
        }
    }

    /**
     * Require specific category
     */
    protected function requireCategory(array $categories)
    {
        if (!$this->hasCategory($categories)) {
            $this->session->setFlashdata('error', 'Access denied. Insufficient privileges.');
            return redirect()->to(base_url('dashboard'));
        }
    }

    /**
     * Validate session security
     */
    protected function validateSessionSecurity(): bool
    {
        $userAgent = $this->session->get('user_agent');
        $ipAddress = $this->session->get('ip_address');
        
        $currentUserAgent = $this->request->getUserAgent()->getAgentString();
        $currentIpAddress = $this->request->getIPAddress();
        
        if ($userAgent !== $currentUserAgent || $ipAddress !== $currentIpAddress) {
            $this->session->destroy();
            return false;
        }
        
        return true;
    }

    /**
     * Check session timeout
     */
    protected function checkSessionTimeout(): bool
    {
        $lastActivity = $this->session->get('last_activity');
        $timeoutDuration = 15 * 60; // 15 minutes
        
        if ($lastActivity && (time() - $lastActivity) > $timeoutDuration) {
            $this->session->destroy();
            return false;
        }
        
        $this->session->set('last_activity', time());
        return true;
    }

    /**
     * Initialize session security
     */
    protected function initializeSessionSecurity()
    {
        if (!$this->session->get('user_agent')) {
            $this->session->set('user_agent', $this->request->getUserAgent()->getAgentString());
            $this->session->set('ip_address', $this->request->getIPAddress());
        }
    }
}
