<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        
        // Check if user is logged in
        if (!$session->get('userid')) {
            return redirect()->to(base_url('login'));
        }
        
        // Validate session security
        $userAgent = $session->get('user_agent');
        $ipAddress = $session->get('ip_address');
        
        $currentUserAgent = $request->getUserAgent()->getAgentString();
        $currentIpAddress = $request->getIPAddress();
        
        if ($userAgent !== $currentUserAgent || $ipAddress !== $currentIpAddress) {
            $session->destroy();
            return redirect()->to(base_url('login'))->with('security', 'agent_ip_mismatch');
        }
        
        // Check session timeout
        $lastActivity = $session->get('last_activity');
        $timeoutDuration = 15 * 60; // 15 minutes
        
        if ($lastActivity && (time() - $lastActivity) > $timeoutDuration) {
            $session->destroy();
            return redirect()->to(base_url('login'))->with('timeout', 'true');
        }
        
        // Update last activity
        $session->set('last_activity', time());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after request
    }
}