<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employee';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'fullname', 'email', 'phone', 'address', 'department', 'position', 'supervisor', 'manager'
    ];

    // Dates
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'fullname' => 'required|max_length[255]',
        'email' => 'permit_empty|valid_email|max_length[255]',
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Get employee by user ID
     */
    public function getEmployeeByUserId(int $userId)
    {
        return $this->where('id', $userId)->first();
    }

    /**
     * Get employees by supervisor or manager
     */
    public function getEmployeesBySupervision(int $userId, string $type = 'supervisor')
    {
        $field = $type === 'manager' ? 'manager' : 'supervisor';
        return $this->where($field, $userId)->findAll();
    }

    /**
     * Get employee with plantilla position
     */
    public function getEmployeeWithPosition(int $userId)
    {
        return $this->db->table('employee e')
            ->select('e.*, ed.*, pp.position_title, pp.salary_grade, pp.office, pp.org_unit')
            ->join('employment_details ed', 'e.id = ed.userid AND ed.edstatus = 1', 'left')
            ->join('plantilla_position pp', 'ed.position_id = pp.id', 'left')
            ->where('e.id', $userId)
            ->get()
            ->getRowArray();
    }
}