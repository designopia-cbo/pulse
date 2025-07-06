<?php

namespace App\Controllers;

use App\Models\EmployeeModel;

class PlantillaController extends BaseController
{
    protected $employeeModel;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
    }

    /**
     * Display plantilla positions
     */
    public function index()
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
        $category = $this->session->get('category');

        // Check category permissions
        if (!in_array($category, ['HR', 'MINISTER', 'SUPERADMIN'])) {
            $this->session->setFlashdata('error', 'Access denied. Insufficient privileges.');
            return redirect()->to(base_url('dashboard'));
        }

        // Get user details
        $employee = $this->employeeModel->find($userId);
        $this->data['fullName'] = $employee ? ucwords(strtolower($employee['fullname'])) : 'Unknown User';
        $this->data['initial'] = $employee ? strtoupper(substr($employee['fullname'], 0, 1)) : 'U';

        // Search and filter parameters
        $search = $this->request->getGet('search') ?? '';
        $statusFilter = $this->request->getGet('status') ?? '';
        $sortBy = $this->request->getGet('sort') ?? 'item_number';
        $sortOrder = $this->request->getGet('order') ?? 'asc';
        $page = $this->request->getGet('page') ?? 1;
        $rowsPerPage = 10;

        // Build query
        $builder = $this->employeeModel->db->table('plantilla_position pp');
        $builder->select('pp.*, e.fullname');
        $builder->join('employee e', 'pp.userid = e.id', 'left');

        // Apply search filter
        if ($search) {
            $builder->groupStart();
            $builder->like('pp.position_title', $search);
            $builder->orLike('pp.item_number', $search);
            $builder->orLike('e.fullname', $search);
            $builder->groupEnd();
        }

        // Apply status filter
        if ($statusFilter) {
            $builder->where('pp.pstatus', $statusFilter);
        }

        // Get total count for pagination
        $totalRows = $builder->countAllResults(false);

        // Apply sorting and pagination
        $builder->orderBy($sortBy, $sortOrder);
        $builder->limit($rowsPerPage, ($page - 1) * $rowsPerPage);

        $positions = $builder->get()->getResultArray();

        // Process positions data
        $plantillaRows = [];
        foreach ($positions as $row) {
            // Classification mapping
            $classMap = [
                'P' => 'PERM.',
                'CT' => 'COTRM',
                'CTI' => 'COTRM W/INC'
            ];
            $classification = isset($classMap[strtoupper($row['classification'])]) ? 
                $classMap[strtoupper($row['classification'])] : $row['classification'];

            // Status mapping
            $pstatus = $row['pstatus'] == 1 ? 'ACTIVE' : 'CLOSED';

            $plantillaRows[] = [
                'id' => $row['id'],
                'userid' => $row['userid'],
                'item_number' => $row['item_number'],
                'position_title' => strtoupper($row['position_title']),
                'salary_grade' => $row['salary_grade'],
                'org_unit' => $row['org_unit'],
                'office' => $row['office'],
                'cost_structure' => $row['cost_structure'],
                'classification' => $classification,
                'pstatus' => $pstatus,
                'fullname' => $row['fullname'] ?? 'VACANT'
            ];
        }

        $this->data['plantilla'] = $plantillaRows;
        $this->data['total_rows'] = $totalRows;
        $this->data['current_page'] = $page;
        $this->data['rows_per_page'] = $rowsPerPage;
        $this->data['total_pages'] = ceil($totalRows / $rowsPerPage);
        $this->data['search'] = $search;
        $this->data['status_filter'] = $statusFilter;
        $this->data['sort_by'] = $sortBy;
        $this->data['sort_order'] = $sortOrder;

        return view('plantilla/index', $this->data);
    }

    /**
     * Add new plantilla position
     */
    public function add()
    {
        // Require login and admin
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        $adminRedirect = $this->requireAdmin();
        if ($adminRedirect) return $adminRedirect;

        // Check category permissions
        $categoryRedirect = $this->requireCategory(['HR', 'MINISTER', 'SUPERADMIN']);
        if ($categoryRedirect) return $categoryRedirect;

        if ($this->request->getMethod() === 'POST') {
            return $this->processAdd();
        }

        $this->data['csrf_token'] = csrf_token();
        $this->data['csrf_hash'] = csrf_hash();

        return view('plantilla/add', $this->data);
    }

    /**
     * Process add plantilla position
     */
    public function processAdd()
    {
        // Validate input
        $rules = [
            'item_number' => 'required|is_unique[plantilla_position.item_number]',
            'position_title' => 'required|max_length[255]',
            'salary_grade' => 'required|integer|greater_than[0]',
            'org_unit' => 'required|max_length[255]',
            'office' => 'required|max_length[255]',
            'cost_structure' => 'required|max_length[255]',
            'classification' => 'required|in_list[P,CT,CTI]',
            'pstatus' => 'required|in_list[0,1]',
            'csrf_token' => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'item_number' => $this->request->getPost('item_number'),
            'position_title' => $this->request->getPost('position_title'),
            'salary_grade' => $this->request->getPost('salary_grade'),
            'org_unit' => $this->request->getPost('org_unit'),
            'office' => $this->request->getPost('office'),
            'cost_structure' => $this->request->getPost('cost_structure'),
            'classification' => $this->request->getPost('classification'),
            'pstatus' => $this->request->getPost('pstatus'),
            'userid' => $this->request->getPost('userid') ?: null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $builder = $this->employeeModel->db->table('plantilla_position');
        if ($builder->insert($data)) {
            $this->session->setFlashdata('success', 'Plantilla position added successfully.');
            return redirect()->to(base_url('plantilla'));
        } else {
            $this->session->setFlashdata('error', 'Failed to add plantilla position.');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Edit plantilla position
     */
    public function edit($id)
    {
        // Require login and admin
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        $adminRedirect = $this->requireAdmin();
        if ($adminRedirect) return $adminRedirect;

        // Check category permissions
        $categoryRedirect = $this->requireCategory(['HR', 'MINISTER', 'SUPERADMIN']);
        if ($categoryRedirect) return $categoryRedirect;

        // Get position details
        $position = $this->employeeModel->db->table('plantilla_position')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if (!$position) {
            $this->session->setFlashdata('error', 'Plantilla position not found.');
            return redirect()->to(base_url('plantilla'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->processEdit($id);
        }

        $this->data['position'] = $position;
        $this->data['csrf_token'] = csrf_token();
        $this->data['csrf_hash'] = csrf_hash();

        return view('plantilla/edit', $this->data);
    }

    /**
     * Process edit plantilla position
     */
    public function processEdit($id)
    {
        // Validate input
        $rules = [
            'item_number' => "required|is_unique[plantilla_position.item_number,id,$id]",
            'position_title' => 'required|max_length[255]',
            'salary_grade' => 'required|integer|greater_than[0]',
            'org_unit' => 'required|max_length[255]',
            'office' => 'required|max_length[255]',
            'cost_structure' => 'required|max_length[255]',
            'classification' => 'required|in_list[P,CT,CTI]',
            'pstatus' => 'required|in_list[0,1]',
            'csrf_token' => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'item_number' => $this->request->getPost('item_number'),
            'position_title' => $this->request->getPost('position_title'),
            'salary_grade' => $this->request->getPost('salary_grade'),
            'org_unit' => $this->request->getPost('org_unit'),
            'office' => $this->request->getPost('office'),
            'cost_structure' => $this->request->getPost('cost_structure'),
            'classification' => $this->request->getPost('classification'),
            'pstatus' => $this->request->getPost('pstatus'),
            'userid' => $this->request->getPost('userid') ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $builder = $this->employeeModel->db->table('plantilla_position');
        if ($builder->where('id', $id)->update($data)) {
            $this->session->setFlashdata('success', 'Plantilla position updated successfully.');
            return redirect()->to(base_url('plantilla'));
        } else {
            $this->session->setFlashdata('error', 'Failed to update plantilla position.');
            return redirect()->back()->withInput();
        }
    }
}